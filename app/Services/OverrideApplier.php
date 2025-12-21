<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OverrideRuleType;
use App\Models\OverrideRule;
use App\Models\Release;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class OverrideApplier
{
    /**
     * Apply overrides to files in $sourceDir, output to $preparedDir.
     */
    public function apply(Release $release, string $sourceDir, string $preparedDir, ?string $remoteDir = null): void
    {
        $this->copyDirectory($sourceDir, $preparedDir);

        $rules = OverrideRule::query()
            ->where(function ($q) use ($release) {
                $q->where(function ($q2) use ($release) {
                    $q2->where('scope', 'global')
                        ->where(function ($q) use ($release) {
                            $q->whereNull('minecraft_version')
                                ->orWhere('minecraft_version', $release->server->minecraft_version);
                        });
                })
                    ->orWhere(function ($q2) use ($release) {
                        $q2->where('scope', 'server')
                            ->whereHas('servers', function ($q3) use ($release) {
                                $q3->where('servers.id', $release->server_id);
                            });
                    });
            })
            ->where('enabled', true)
            ->orderByDesc('priority')
            ->get();

        $skipPatterns = OverrideRule::getSkipPatternsForServer($release->server);

        // If we have a remote snapshot, we might want to keep and modify files that aren't in the modpack
        if ($remoteDir) {
            $this->copyMatchingRemoteFiles($rules, $remoteDir, $preparedDir, $skipPatterns);
        }

        foreach ($rules as $rule) {
            $this->applyRule($rule, $preparedDir);
        }

        // Remove skipped files from prepared directory so they aren't even considered for upload
        if (! empty($skipPatterns)) {
            $disk = Storage::disk('local');
            foreach ($disk->allFiles($preparedDir) as $file) {
                $relative = ltrim(str_replace($preparedDir.'/', '', $file), '/');
                foreach ($skipPatterns as $pattern) {
                    if (fnmatch($pattern, $relative)) {
                        $disk->delete($file);
                        break;
                    }
                }
            }
        }
    }

    protected function applyRule(OverrideRule $rule, string $root): void
    {
        if ($rule->type === OverrideRuleType::FileAdd) {
            $payload = $rule->payload ?? [];
            $overwrite = (bool) ($payload['overwrite'] ?? true);
            $files = $payload['files'] ?? [];

            // Support legacy single-file rules
            if (empty($files) && (isset($payload['from_upload']) || isset($payload['to']))) {
                $files = [
                    [
                        'from_upload' => $payload['from_upload'] ?? '',
                        'to' => $payload['to'] ?? '',
                        'from_url' => $payload['from_url'] ?? '',
                    ],
                ];
            }

            $disk = Storage::disk('local');

            foreach ($files as $fileData) {
                $to = (string) ($fileData['to'] ?? '');
                $fromUpload = (string) ($fileData['from_upload'] ?? '');
                $fromUrl = (string) ($fileData['from_url'] ?? '');

                if ($to === '') {
                    continue;
                }

                $target = trim($root.'/'.$to, '/');
                $targetParent = dirname($target);
                if ($targetParent !== '.' && ! $disk->exists($targetParent)) {
                    $disk->makeDirectory($targetParent);
                }

                if (! $overwrite && $disk->exists($target)) {
                    continue;
                }

                if ($fromUpload !== '') {
                    if (! $disk->copy($fromUpload, $target)) {
                        $disk->put($target, $disk->get($fromUpload));
                    }

                    continue;
                }

                if ($fromUrl !== '') {
                    try {
                        /** @var Response $resp */
                        $resp = Http::timeout(30)->get($fromUrl);
                        if ($resp->successful()) {
                            $bin = $resp->body();
                            $disk->put($target, $bin);
                        }
                    } catch (\Throwable $e) {
                        // ignore fetch errors
                    }

                    continue;
                }
            }

            return;
        }

        $disk = Storage::disk('local');
        $iterator = $disk->allFiles($root);
        $patterns = (array) ($rule->path_patterns ?? []);

        foreach ($iterator as $file) {
            $relative = ltrim(str_replace($root.'/', '', $file), '/');
            $matched = false;
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $relative)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                continue;
            }

            if ($rule->type === OverrideRuleType::TextReplace) {
                $this->applyTextReplace($file, $rule->payload);
            } elseif ($rule->type === OverrideRuleType::JsonPatch) {
                $this->applyJsonPatch($file, $rule->payload);
            } elseif ($rule->type === OverrideRuleType::YamlPatch) {
                $this->applyYamlPatch($file, $rule->payload);
            } elseif ($rule->type === OverrideRuleType::FileRemove) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Copy files from remote snapshot to prepared directory if they match a content-modifying rule
     * and aren't already in the prepared directory (from the modpack source).
     */
    protected function copyMatchingRemoteFiles($rules, string $remoteDir, string $preparedDir, array $skipPatterns): void
    {
        $disk = Storage::disk('local');
        $remoteFiles = $disk->allFiles($remoteDir);

        $contentModifyingTypes = [
            OverrideRuleType::TextReplace,
            OverrideRuleType::JsonPatch,
            OverrideRuleType::YamlPatch,
        ];

        foreach ($remoteFiles as $remoteFile) {
            $relative = ltrim(str_replace($remoteDir.'/', '', $remoteFile), '/');

            // If already in prepared dir, no need to copy
            if ($disk->exists($preparedDir.'/'.$relative)) {
                continue;
            }

            // If skipped, don't touch
            $isSkipped = false;
            foreach ($skipPatterns as $pattern) {
                if (fnmatch($pattern, $relative)) {
                    $isSkipped = true;
                    break;
                }
            }
            if ($isSkipped) {
                continue;
            }

            // Check if any content-modifying rule matches
            foreach ($rules as $rule) {
                if (! in_array($rule->type, $contentModifyingTypes)) {
                    continue;
                }

                $patterns = (array) ($rule->path_patterns ?? []);
                foreach ($patterns as $pattern) {
                    if (fnmatch($pattern, $relative)) {
                        // Match found! Copy to prepared dir so it can be modified and kept.
                        $target = $preparedDir.'/'.$relative;
                        $targetParent = dirname($target);
                        if ($targetParent !== '.' && ! $disk->exists($targetParent)) {
                            $disk->makeDirectory($targetParent);
                        }
                        $disk->put($target, $disk->get($remoteFile));
                        break 2;
                    }
                }
            }
        }
    }

    private function copyDirectory(string $sourceDir, string $targetDir): void
    {
        $disk = Storage::disk('local');

        $disk->makeDirectory($targetDir);

        foreach ($disk->allFiles($sourceDir) as $sourceFile) {
            $relative = ltrim(str_replace($sourceDir.'/', '', $sourceFile), '/');
            $targetFile = $targetDir.'/'.$relative;
            $targetParent = dirname($targetFile);
            if ($targetParent !== '.' && ! $disk->exists($targetParent)) {
                $disk->makeDirectory($targetParent);
            }

            $disk->put($targetFile, $disk->get($sourceFile));
        }
    }

    protected function applyTextReplace(string $path, array $payload): void
    {
        $search = $payload['search'] ?? '';
        $replace = $payload['replace'] ?? '';
        $regex = (bool) ($payload['regex'] ?? false);
        $content = Storage::disk('local')->get($path);
        if ($content === false || $content === null) {
            return;
        }
        if ($regex) {
            $content = preg_replace($search, $replace, $content) ?? $content;
        } else {
            $content = str_replace($search, $replace, $content);
        }
        Storage::disk('local')->put($path, $content);
    }

    protected function applyJsonPatch(string $path, array $payload): void
    {
        $content = Storage::disk('local')->get($path);
        if ($content === false || $content === null) {
            return;
        }
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return;
        }
        $merge = $payload['merge'] ?? [];
        $data = $this->recursiveMerge($data, $merge);
        $out = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Storage::disk('local')->put($path, $out);
    }

    protected function applyYamlPatch(string $path, array $payload): void
    {
        $content = Storage::disk('local')->get($path);
        if ($content === false || $content === null) {
            return;
        }
        $data = Yaml::parse($content);
        if (! is_array($data)) {
            return;
        }
        $merge = $payload['merge'] ?? [];
        $data = $this->recursiveMerge($data, $merge);
        $out = Yaml::dump($data, 4);
        Storage::disk('local')->put($path, $out);
    }

    protected function recursiveMerge(array $base, array $merge): array
    {
        foreach ($merge as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->recursiveMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}

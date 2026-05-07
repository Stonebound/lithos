<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\NormalizesStringValues;
use App\Enums\OverrideRuleType;
use App\Models\OverrideRule;
use App\Models\Release;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class OverrideApplier
{
    use NormalizesStringValues;

    /**
     * Apply overrides to files in $sourceDir, output to $preparedDir.
     */
    public function apply(Release $release, string $sourceDir, string $preparedDir, ?string $remoteDir = null, ?callable $onProgress = null): void
    {
        $this->copyDirectory($sourceDir, $preparedDir);

        $rules = OverrideRule::query()
            ->where(function ($q) use ($release) {
                $q->where(function ($q2) {
                    $q2->where('scope', 'global');
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
            ->get()
            ->filter(function (OverrideRule $rule) use ($release) {
                $versionPattern = $rule->minecraft_version;
                if ($versionPattern === null || $versionPattern === '') {
                    return true;
                }

                if ($release->server->minecraft_version === null) {
                    return false;
                }

                return preg_match('/^'.$versionPattern.'$/', $release->server->minecraft_version) === 1;
            });

        $skipPatterns = array_values(OverrideRule::getSkipPatternsForServer($release->server));

        // If we have a remote snapshot, we might want to keep and modify files that aren't in the modpack
        if ($remoteDir) {
            $this->copyMatchingRemoteFiles($rules, $remoteDir, $preparedDir, $skipPatterns);
        }

        foreach ($rules as $rule) {
            if ($onProgress) {
                $onProgress('rule', $rule->name);
            }
            $this->applyRule($rule, $preparedDir);
        }

        // Remove skipped files from prepared directory so they aren't even considered for upload
        if (! empty($skipPatterns)) {
            $disk = Storage::disk('local');
            foreach ($this->localFiles($preparedDir) as $file) {
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
            $payload = $this->payloadArray($rule->payload);
            $overwrite = $this->boolValue($payload['overwrite'] ?? true);

            $disk = Storage::disk('local');

            foreach ($this->fileAddEntries($payload) as $fileData) {
                $to = $fileData['to'];
                $fromUpload = $fileData['from_upload'];

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

                if ($fromUpload !== null) {
                    if (! $disk->copy($fromUpload, $target)) {
                        copy($disk->path($fromUpload), $disk->path($target));
                    }

                    continue;
                }
            }

            return;
        }

        $disk = Storage::disk('local');
        $patterns = $this->pathPatterns($rule);

        foreach ($this->localFiles($root) as $file) {
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

            if (in_array($rule->type, [OverrideRuleType::TextReplace, OverrideRuleType::JsonPatch, OverrideRuleType::YamlPatch])) {
                if (FileUtility::isBinary($file) || ! $this->wouldModify($rule, $file)) {
                    continue;
                }
            }

            if ($rule->type === OverrideRuleType::TextReplace) {
                $this->applyTextReplace($file, $this->payloadArray($rule->payload));
            } elseif ($rule->type === OverrideRuleType::JsonPatch) {
                $this->applyJsonPatch($file, $this->payloadArray($rule->payload));
            } elseif ($rule->type === OverrideRuleType::YamlPatch) {
                $this->applyYamlPatch($file, $this->payloadArray($rule->payload));
            } elseif ($rule->type === OverrideRuleType::FileRemove) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Copy files from remote snapshot to prepared directory if they match a content-modifying rule
     * and aren't already in the prepared directory (from the modpack source).
     *
     * @param  Collection<int, OverrideRule>  $rules
     * @param  array<int, string>  $skipPatterns
     */
    protected function copyMatchingRemoteFiles(Collection $rules, string $remoteDir, string $preparedDir, array $skipPatterns): void
    {
        $disk = Storage::disk('local');

        $contentModifyingTypes = [
            OverrideRuleType::TextReplace,
            OverrideRuleType::JsonPatch,
            OverrideRuleType::YamlPatch,
        ];

        foreach ($this->localFiles($remoteDir) as $remoteFile) {
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
            if (FileUtility::isBinary($remoteFile)) {
                continue;
            }

            foreach ($rules as $rule) {
                if (! in_array($rule->type, $contentModifyingTypes)) {
                    continue;
                }

                $patterns = $this->pathPatterns($rule);
                foreach ($patterns as $pattern) {
                    if (fnmatch($pattern, $relative)) {
                        if (! $this->wouldModify($rule, $remoteFile)) {
                            continue;
                        }

                        // Match found! Copy to prepared dir so it can be modified and kept.
                        $target = $preparedDir.'/'.$relative;
                        $targetParent = dirname($target);
                        if ($targetParent !== '.' && ! $disk->exists($targetParent)) {
                            $disk->makeDirectory($targetParent);
                        }
                        copy($disk->path($remoteFile), $disk->path($target));
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

        foreach ($this->localFiles($sourceDir) as $sourceFile) {
            $relative = ltrim(str_replace($sourceDir.'/', '', $sourceFile), '/');
            $targetFile = $targetDir.'/'.$relative;
            $targetParent = dirname($targetFile);
            if ($targetParent !== '.' && ! $disk->exists($targetParent)) {
                $disk->makeDirectory($targetParent);
            }

            copy($disk->path($sourceFile), $disk->path($targetFile));
        }
    }

    protected function applyTextReplace(string $path, array $payload): void
    {
        $payloads = $this->normalizeTextReplacePayloads($payload);
        $content = Storage::disk('local')->get($path);

        if (! is_string($content) || $content === '') {
            return;
        }

        foreach ($payloads as $singlePayload) {
            $search = $singlePayload['search'];
            $replace = $singlePayload['replace'];
            $regex = $singlePayload['regex'];

            if ($regex) {
                $content = preg_replace($search, $replace, $content) ?? $content;
            } else {
                $content = str_replace($search, $replace, $content);
            }
        }

        Storage::disk('local')->put($path, $content);
    }

    protected function applyJsonPatch(string $path, array $payload): void
    {
        $content = Storage::disk('local')->get($path);
        if (! is_string($content) || $content === '') {
            return;
        }
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            return;
        }
        $merge = $this->mergePayload($payload);
        $data = $this->recursiveMerge($data, $merge);
        $out = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        Storage::disk('local')->put($path, $out);
    }

    protected function applyYamlPatch(string $path, array $payload): void
    {
        $content = Storage::disk('local')->get($path);
        if (! is_string($content) || $content === '') {
            return;
        }
        $data = Yaml::parse($content);
        if (! is_array($data)) {
            return;
        }
        $merge = $this->mergePayload($payload);
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

    protected function wouldModify(OverrideRule $rule, string $path): bool
    {
        $disk = Storage::disk('local');
        $content = $disk->get($path);

        if (! is_string($content) || $content === '') {
            return false;
        }

        if ($rule->type === OverrideRuleType::TextReplace) {
            $newContent = $content;

            foreach ($this->normalizeTextReplacePayloads($this->payloadArray($rule->payload)) as $payload) {
                $search = $payload['search'];
                $replace = $payload['replace'];
                $regex = $payload['regex'];

                if ($regex) {
                    $newContent = preg_replace($search, $replace, $newContent) ?? $newContent;

                    continue;
                }

                $newContent = str_replace($search, $replace, $newContent);
            }

            return $newContent !== $content;
        }

        if ($rule->type === OverrideRuleType::JsonPatch) {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                return false;
            }
            $merge = $this->mergePayload($this->payloadArray($rule->payload));
            $newData = $this->recursiveMerge($data, $merge);

            return $data !== $newData;
        }

        if ($rule->type === OverrideRuleType::YamlPatch) {
            try {
                $data = Yaml::parse($content);
            } catch (\Throwable $e) {
                return false;
            }
            if (! is_array($data)) {
                return false;
            }
            $merge = $this->mergePayload($this->payloadArray($rule->payload));
            $newData = $this->recursiveMerge($data, $merge);

            return $data !== $newData;
        }

        return false;
    }

    /**
     * @return list<array{search: string, replace: string, regex: bool}>
     */
    private function normalizeTextReplacePayloads(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $firstKey = array_key_first($payload);

        $payloads = is_int($firstKey) ? $payload : [$payload];

        $normalized = [];

        foreach ($payloads as $singlePayload) {
            if (! is_array($singlePayload)) {
                continue;
            }

            $normalized[] = [
                'search' => self::normalizeStringValue($singlePayload['search'] ?? ''),
                'replace' => self::normalizeStringValue($singlePayload['replace'] ?? ''),
                'regex' => $this->boolValue($singlePayload['regex'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function localFiles(string $directory): array
    {
        $files = Storage::disk('local')->allFiles($directory);

        $normalized = [];

        foreach ($files as $file) {
            if (is_string($file)) {
                $normalized[] = $file;
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function pathPatterns(OverrideRule $rule): array
    {
        $patterns = $rule->path_patterns;

        $normalized = [];

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '') {
                $normalized[] = $pattern;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function payloadArray(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{to: string, from_upload: ?string}>
     */
    private function fileAddEntries(array $payload): array
    {
        $files = $payload['files'] ?? [];

        if (! is_array($files)) {
            return [];
        }

        $entries = [];

        foreach ($files as $fileData) {
            if (! is_array($fileData)) {
                continue;
            }

            $fromUploads = self::normalizeStringList($fileData['from_upload'] ?? []);
            $entries[] = [
                'to' => self::normalizeStringValue($fileData['to'] ?? ''),
                'from_upload' => $fromUploads[0] ?? null,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function mergePayload(array $payload): array
    {
        $merge = $payload['merge'] ?? [];

        return is_array($merge) ? $merge : [];
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return (bool) $value;
    }
}

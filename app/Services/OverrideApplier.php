<?php

declare(strict_types=1);

namespace App\Services;

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
    public function apply(Release $release, string $sourceDir, string $preparedDir): void
    {
        $this->copyDirectory($sourceDir, $preparedDir);
        $rules = OverrideRule::query()
            ->where(function ($q) use ($release) {
                $q->where('scope', 'global')
                    ->orWhere(function ($q2) use ($release) {
                        $q2->where('scope', 'server')
                            ->where('server_id', $release->server_id);
                    });
            })
            ->where('enabled', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            $this->applyRule($rule, $preparedDir);
        }
    }

    protected function applyRule(OverrideRule $rule, string $root): void
    {
        if ($rule->type === 'file_add') {
            $payload = $rule->payload ?? [];
            $to = (string) ($payload['to'] ?? '');
            $fromUpload = (string) ($payload['from_upload'] ?? '');
            $fromUrl = (string) ($payload['from_url'] ?? '');
            $overwrite = (bool) ($payload['overwrite'] ?? true);
            if ($to === '') {
                return;
            }
            $disk = Storage::disk('local');
            $target = trim($root.'/'.$to, '/');
            $targetParent = dirname($target);
            if ($targetParent !== '.' && ! $disk->exists($targetParent)) {
                $disk->makeDirectory($targetParent);
            }

            if (! $overwrite && $disk->exists($target)) {
                return;
            }
            if ($fromUpload !== '') {
                if (! $disk->copy($fromUpload, $target)) {
                    $disk->put($target, $disk->get($fromUpload));
                }

                return;
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

                return;
            }

            return;
        }

        $disk = Storage::disk('local');
        $iterator = $disk->allFiles($root);
        foreach ($iterator as $file) {
            $relative = ltrim(str_replace($root.'/', '', $file), '/');
            if (! fnmatch($rule->path_pattern, $relative)) {
                continue;
            }
            if ($rule->type === 'text_replace') {
                $this->applyTextReplace($file, $rule->payload);
            } elseif ($rule->type === 'json_patch') {
                $this->applyJsonPatch($file, $rule->payload);
            } elseif ($rule->type === 'yaml_patch') {
                $this->applyYamlPatch($file, $rule->payload);
            } elseif ($rule->type === 'file_remove') {
                $disk->delete($file);
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

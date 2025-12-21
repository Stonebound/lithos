<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OverrideRule;
use App\Models\Release;
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
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = str_replace($root.'/', '', $file->getPathname());
            if (! fnmatch($rule->path_pattern, $relative)) {
                continue;
            }
            if ($rule->type === 'text_replace') {
                $this->applyTextReplace($file->getPathname(), $rule->payload);
            } elseif ($rule->type === 'json_patch') {
                $this->applyJsonPatch($file->getPathname(), $rule->payload);
            } elseif ($rule->type === 'yaml_patch') {
                $this->applyYamlPatch($file->getPathname(), $rule->payload);
            }
        }
    }

    protected function applyTextReplace(string $path, array $payload): void
    {
        $search = $payload['search'] ?? '';
        $replace = $payload['replace'] ?? '';
        $regex = (bool) ($payload['regex'] ?? false);
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }
        if ($regex) {
            $content = preg_replace($search, $replace, $content) ?? $content;
        } else {
            $content = str_replace($search, $replace, $content);
        }
        file_put_contents($path, $content);
    }

    protected function applyJsonPatch(string $path, array $payload): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return;
        }
        $merge = $payload['merge'] ?? [];
        $data = $this->recursiveMerge($data, $merge);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function applyYamlPatch(string $path, array $payload): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }
        $data = Yaml::parse($content);
        if (! is_array($data)) {
            return;
        }
        $merge = $payload['merge'] ?? [];
        $data = $this->recursiveMerge($data, $merge);
        file_put_contents($path, Yaml::dump($data, 4));
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

    protected function copyDirectory(string $source, string $dest): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dest.'/'.str_replace($source.'/', '', $item->getPathname());
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } else {
                if (! is_dir(dirname($target))) {
                    mkdir(dirname($target), 0777, true);
                }
                copy($item->getPathname(), $target);
            }
        }
    }
}

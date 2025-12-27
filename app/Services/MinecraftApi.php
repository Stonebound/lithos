<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MinecraftApi
{
    public function uuidForName(string $name): ?string
    {
        $endpoint = config('services.minecraft.endpoints.minecraft_profile_by_name');
        $endpoint = rtrim($endpoint, '/').'/'.$name;

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::acceptJson()->get($endpoint);
            if ($response->successful()) {
                $json = $response->json();

                $id = $json['id'] ?? null;
                if (is_string($id)) {
                    return self::formatUuid($id);
                }

                return null;
            }
        } catch (\Throwable $e) {
            // swallow and return null - caller will handle
        }

        return null;
    }

    public static function minifyUuid(string $uuid): ?string
    {
        $minified = str_replace('-', '', $uuid);
        if (strlen($minified) === 32) {
            return $minified;
        }

        return null;
    }

    public static function formatUuid(string $uuid): ?string
    {
        $uuid = self::minifyUuid($uuid);
        if ($uuid !== null) {
            return substr($uuid, 0, 8).'-'.substr($uuid, 8, 4).'-'.substr($uuid, 12, 4).'-'.substr($uuid, 16, 4).'-'.substr($uuid, 20, 12);
        }

        return null;
    }
}

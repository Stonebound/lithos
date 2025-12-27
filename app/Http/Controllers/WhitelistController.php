<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WhitelistUser;
use App\Services\MinecraftApi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class WhitelistController extends Controller
{
    public function json(): JsonResponse
    {
        $users = WhitelistUser::query()->orderBy('id')->get(['uuid', 'username'])->pluck('username', 'uuid');

        return response()->json($users->map(fn ($username, $uuid) => [
            'uuid' => $uuid,
            'name' => $username,
        ])->values());
    }

    public function txt(): Response
    {
        $users = WhitelistUser::query()->orderBy('id')->get(['uuid'])->pluck('uuid');
        $content = $users->implode("\n");

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }

    public function apiAdd(Request $request, MinecraftApi $minecraftApi): JsonResponse
    {
        $headers = $request->headers;

        $apiUser = config('services.minecraft.api_user_prefix');
        if ($headers->has('api-user')) {
            $apiUser .= ': '.$headers->get('api-user');
        }

        $username = trim((string) $request->input('name'));
        if ($username === '') {
            return response()->json(['status' => 'error', 'message' => 'No username provided!'], 422);
        }

        $exists = WhitelistUser::where('username', $username)->exists();
        if ($exists) {
            return response()->json(['status' => 'error', 'message' => 'Already whitelisted'], 409);
        }

        $uuid = $minecraftApi->uuidForName($username);
        if ($uuid === null) {
            return response()->json(['status' => 'error', 'message' => 'Could not resolve UUID for that username.'], 422);
        }

        $user = WhitelistUser::create([
            'username' => $username,
            'uuid' => $uuid,
            'source' => 'api',
        ]);

        // Log to AuditLog
        AuditLog::create([
            'user_id' => null,
            'model_type' => WhitelistUser::class,
            'model_id' => $user->id,
            'action' => 'create',
            'old_values' => null,
            'new_values' => ['username' => $username, 'source' => 'api'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()
            ->json([
                'status' => 'success',
                'message' => "{$username} has been whitelisted!",
                'uuid' => $uuid,
            ], 201);
    }
}

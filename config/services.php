<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'curseforge' => [
        'key' => env('CURSEFORGE_API_KEY'),
    ],

    'pterodactyl' => [
        'api_key' => env('PTERODACTYL_API_KEY'),
        'base_url' => env('PTERODACTYL_BASE_URL'),
    ],

    'bunnynet' => [
        'api_key' => env('BUNNYNET_API_KEY'),
    ],

    'hetzner' => [
        'api_token' => env('HETZNER_API_TOKEN'),
        'base_url' => env('HETZNER_BASE_URL', 'https://api.hetzner.cloud/v1/'),
    ],

    'dns' => [
        'provider' => env('DNS_PROVIDER', 'bunny'),
        'base_domain' => env('DNS_BASE_DOMAIN', env('BUNNYNET_BASE_DOMAIN', 'stonebound.net')),
        'additional_subdomains' => ['la'],
        'ttl' => (int) env('DNS_TTL', env('BUNNYNET_TTL', 300)),
        'base_target' => env('DNS_BASE_TARGET', env('BUNNYNET_BASE_TARGET', 'mc.stonebound.net')),
        'additional_targets' => [
            'la' => env('DNS_LA_TARGET', env('BUNNYNET_LA_TARGET', 'la.stonebound.net')),
        ],
    ],

    'minecraft' => [
        'api_user_prefix' => env('API_USER_PREFIX', 'DiscordBot'),
        'endpoints' => [
            'minecraft_profile_by_name' => env('MINECRAFT_PROFILE_BY_NAME', 'https://api.minecraftservices.com/minecraft/profile/lookup/name/'),
        ],
    ],

    'sftp' => [
        'parallel_upload_connections' => (int) env('SFTP_PARALLEL_UPLOAD_CONNECTIONS', 10),
        'parallel_upload_driver' => env('SFTP_PARALLEL_UPLOAD_DRIVER', 'process'),
        'progress_every_files' => (int) env('SFTP_PROGRESS_EVERY_FILES', 100),
        'progress_every_seconds' => (float) env('SFTP_PROGRESS_EVERY_SECONDS', 3),
    ],

];

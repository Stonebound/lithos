<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int|null $user_id
 * @property string $model_type
 * @property int $model_id
 * @property string $action
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $model
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAuditLog {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $release_id
 * @property string $relative_path
 * @property \App\Enums\FileChangeType $change_type
 * @property bool $is_binary
 * @property string|null $diff_summary
 * @property string|null $checksum_old
 * @property string|null $checksum_new
 * @property int|null $size_old
 * @property int|null $size_new
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Release $release
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereChangeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereChecksumNew($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereChecksumOld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereDiffSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereIsBinary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereRelativePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereReleaseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereSizeNew($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereSizeOld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperFileChange {}
}

namespace App\Models{
/**
 * @property string $id
 * @property \Illuminate\Support\Carbon $release_time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion whereReleaseTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperMinecraftVersion {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $scope
 * @property array<array-key, mixed> $path_patterns
 * @property \App\Enums\OverrideRuleType $type
 * @property array<array-key, mixed>|null $payload
 * @property int $enabled
 * @property int $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $minecraft_version
 * @property-read \App\Models\MinecraftVersion|null $minecraftVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Server> $servers
 * @property-read int|null $servers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereMinecraftVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule wherePathPatterns($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOverrideRule {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $server_id
 * @property string|null $version_label
 * @property string $source_type
 * @property string $source_path
 * @property string|null $extracted_path
 * @property string|null $prepared_path
 * @property \App\Enums\ReleaseStatus $status
 * @property array<array-key, mixed>|null $summary_json
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $provider_version_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FileChange> $fileChanges
 * @property-read int|null $file_changes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReleaseLog> $logs
 * @property-read int|null $logs_count
 * @property-read \App\Models\Server $server
 * @method static \Database\Factories\ReleaseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereExtractedPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release wherePreparedPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereProviderVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereSourcePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereSummaryJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereVersionLabel($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperRelease {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $release_id
 * @property string $level
 * @property string $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Release $release
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereReleaseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperReleaseLog {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string $auth_type
 * @property string|null $password
 * @property string|null $private_key_path
 * @property string $remote_root_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $provider
 * @property string|null $provider_pack_id
 * @property string|null $provider_current_version
 * @property array<array-key, mixed>|null $include_paths
 * @property string|null $minecraft_version
 * @property-read \App\Models\MinecraftVersion|null $minecraftVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverrideRule> $overrideRules
 * @property-read int|null $override_rules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Release> $releases
 * @property-read int|null $releases_count
 * @method static \Database\Factories\ServerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereAuthType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereIncludePaths($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereMinecraftVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server wherePrivateKeyPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereProviderCurrentVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereProviderPackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereRemoteRootPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereUsername($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperServer {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $subdomain
 * @property int $port
 * @property array<array-key, mixed>|null $record_ids
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\SrvRecordFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereRecordIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereSubdomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSrvRecord {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \App\Enums\UserRole $role
 * @property \Illuminate\Support\Carbon|null $last_logged_in_at
 * @property string|null $app_authentication_secret
 * @property array<array-key, mixed>|null $app_authentication_recovery_codes
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAppAuthenticationRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAppAuthenticationSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoggedInAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $uuid
 * @property string|null $username
 * @property string|null $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereUuid($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperWhitelistUser {}
}


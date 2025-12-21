<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->enum('provider', ['curseforge', 'ftb'])->nullable()->after('exclude_paths');
            $table->string('provider_pack_id')->nullable()->after('provider');
            $table->string('provider_current_version')->nullable()->after('provider_pack_id');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn(['provider', 'provider_pack_id', 'provider_current_version']);
        });
    }
};

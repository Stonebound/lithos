<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'exclude_paths')) {
                $table->dropColumn('exclude_paths');
            }
            if (! Schema::hasColumn('servers', 'include_paths')) {
                $table->json('include_paths')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'include_paths')) {
                $table->dropColumn('include_paths');
            }
            if (! Schema::hasColumn('servers', 'exclude_paths')) {
                $table->json('exclude_paths')->nullable();
            }
        });
    }
};

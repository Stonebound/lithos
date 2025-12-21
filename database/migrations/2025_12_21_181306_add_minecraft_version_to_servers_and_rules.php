<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('minecraft_version')->nullable()->index();
            $table->foreign('minecraft_version')->references('id')->on('minecraft_versions')->nullOnDelete();
        });

        Schema::table('override_rules', function (Blueprint $table) {
            $table->string('minecraft_version')->nullable()->index();
            $table->foreign('minecraft_version')->references('id')->on('minecraft_versions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('override_rules', function (Blueprint $table) {
            $table->dropForeign(['minecraft_version']);
            $table->dropColumn('minecraft_version');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['minecraft_version']);
            $table->dropColumn('minecraft_version');
        });
    }
};

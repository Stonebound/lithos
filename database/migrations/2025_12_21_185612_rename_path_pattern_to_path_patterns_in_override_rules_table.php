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
        Schema::table('override_rules', function (Blueprint $table) {
            $table->renameColumn('path_pattern', 'path_patterns');
        });

        Schema::table('override_rules', function (Blueprint $table) {
            $table->json('path_patterns')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('override_rules', function (Blueprint $table) {
            $table->renameColumn('path_patterns', 'path_pattern');
        });

        Schema::table('override_rules', function (Blueprint $table) {
            $table->string('path_pattern')->change();
        });
    }
};

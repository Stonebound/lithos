<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('override_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('scope', ['global', 'server'])->default('global');
            $table->foreignId('server_id')->nullable()->constrained('servers')->cascadeOnDelete();
            $table->string('path_pattern');
            $table->enum('type', ['text_replace', 'json_patch', 'yaml_patch', 'file_add', 'file_remove']);
            $table->json('payload');
            $table->boolean('enabled')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('override_rules');
    }
};

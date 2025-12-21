<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained('releases')->cascadeOnDelete();
            $table->string('relative_path');
            $table->enum('change_type', ['added', 'removed', 'modified']);
            $table->boolean('is_binary')->default(false);
            $table->longText('diff_summary')->nullable();
            $table->string('checksum_old')->nullable();
            $table->string('checksum_new')->nullable();
            $table->unsignedBigInteger('size_old')->nullable();
            $table->unsignedBigInteger('size_new')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_changes');
    }
};

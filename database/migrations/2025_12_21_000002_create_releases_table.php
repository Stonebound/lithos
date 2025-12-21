<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('version_label')->nullable();
            $table->enum('source_type', ['zip', 'dir']);
            $table->string('source_path');
            $table->string('extracted_path')->nullable();
            $table->string('remote_snapshot_path')->nullable();
            $table->string('prepared_path')->nullable();
            $table->enum('status', ['draft', 'prepared', 'deployed'])->default('draft');
            $table->longText('summary_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};

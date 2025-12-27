<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whitelist_users', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->index();
            $table->string('username')->nullable()->index();
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whitelist_users');
    }
};

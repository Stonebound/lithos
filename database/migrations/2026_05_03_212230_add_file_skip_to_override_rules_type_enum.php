<?php

declare(strict_types=1);

use App\Enums\OverrideRuleType;
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
            $table->enum('type', array_map(
                static fn (OverrideRuleType $type): string => $type->value,
                OverrideRuleType::cases(),
            ))->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('override_rules', function (Blueprint $table) {
            $table->enum('type', [
                OverrideRuleType::TextReplace->value,
                OverrideRuleType::JsonPatch->value,
                OverrideRuleType::YamlPatch->value,
                OverrideRuleType::FileAdd->value,
                OverrideRuleType::FileRemove->value,
            ])->change();
        });
    }
};

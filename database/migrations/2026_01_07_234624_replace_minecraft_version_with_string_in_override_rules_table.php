<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('override_rules', function (Blueprint $table) {
            $table->dropForeign(['minecraft_version']);
            $table->string('minecraft_version')->nullable()->after('scope')->change();
        });

        // since the column is now treated as regex we need to escape the dots in existing entries
        $overrideRules = DB::table('override_rules')
            ->whereNotNull('minecraft_version')
            ->get(['id', 'minecraft_version']);
        foreach ($overrideRules as $rule) {
            $escapedVersion = str_replace('.', '\\.', $rule->minecraft_version);
            DB::table('override_rules')
                ->where('id', $rule->id)
                ->update(['minecraft_version' => $escapedVersion]);
        }
    }
};

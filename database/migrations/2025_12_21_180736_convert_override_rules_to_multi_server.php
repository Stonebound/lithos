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
        Schema::create('override_rule_server', function (Blueprint $table) {
            $table->id();
            $table->foreignId('override_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Migrate existing data
        $rules = DB::table('override_rules')->whereNotNull('server_id')->get();
        foreach ($rules as $rule) {
            DB::table('override_rule_server')->insert([
                'override_rule_id' => $rule->id,
                'server_id' => $rule->server_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('override_rules', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->dropColumn('server_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('override_rules', function (Blueprint $table) {
            $table->foreignId('server_id')->nullable()->constrained()->cascadeOnDelete();
        });

        // Migrate data back (this might be lossy if multiple servers were assigned)
        $pivotData = DB::table('override_rule_server')->get();
        foreach ($pivotData as $data) {
            DB::table('override_rules')
                ->where('id', $data->override_rule_id)
                ->update(['server_id' => $data->server_id]);
        }

        Schema::dropIfExists('override_rule_server');
    }
};

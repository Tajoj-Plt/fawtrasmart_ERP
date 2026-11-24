<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Convert existing single-value target_action to JSON array
        $challenges = DB::table('challenges')->get();
        foreach ($challenges as $challenge) {
            $value = $challenge->target_action;
            if ($value && !str_starts_with($value, '[')) {
                DB::table('challenges')
                    ->where('id', $challenge->id)
                    ->update(['target_action' => json_encode([$value])]);
            }
        }

        // 2. Change column type to JSON
        Schema::table('challenges', function (Blueprint $table) {
            $table->json('target_action')->comment('الإجراءات المطلوبة (يمكن تحديد عدة إجراءات)')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $challenges = DB::table('challenges')->get();
        foreach ($challenges as $challenge) {
            $value = $challenge->target_action;
            if ($value) {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    DB::table('challenges')
                        ->where('id', $challenge->id)
                        ->update(['target_action' => $decoded[0]]);
                }
            }
        }

        Schema::table('challenges', function (Blueprint $table) {
            $table->string('target_action')->comment('العملية المطلوبة')->change();
        });
    }
};

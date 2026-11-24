<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenge_teams', function (Blueprint $table) {
            if (! Schema::hasColumn('challenge_teams', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('is_completed')
                    ->comment('تاريخ اكتمال الفريق للهدف - يُستخدم لفك التعادل');
            }
        });
    }

    public function down(): void
    {
        Schema::table('challenge_teams', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};

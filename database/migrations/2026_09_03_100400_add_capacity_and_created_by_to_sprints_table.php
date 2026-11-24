<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sprints')) {
            return;
        }

        Schema::table('sprints', function (Blueprint $table) {
            if (! Schema::hasColumn('sprints', 'capacity')) {
                $table->unsignedSmallInteger('capacity')->nullable()->after('goal')
                    ->comment('سعة الفريق بنقاط القصة لهذا السبرنت — تُستخدم في تخطيط السبرنت');
            }

            if (! Schema::hasColumn('sprints', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sprints')) {
            return;
        }

        Schema::table('sprints', function (Blueprint $table) {
            if (Schema::hasColumn('sprints', 'created_by')) {
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('sprints', 'capacity')) {
                $table->dropColumn('capacity');
            }
        });
    }
};

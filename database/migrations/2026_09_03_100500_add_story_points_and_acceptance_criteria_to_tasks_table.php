<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول خاصة بقصص المستخدم في المشاريع السكرم فقط — تبقى null دوماً على
 * مهام المشاريع اليدوية (Normal)، ولا تُعرض هناك في الواجهة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'story_points')) {
                $table->unsignedTinyInteger('story_points')->nullable()->after('type')
                    ->comment('نقاط القصة (1,2,3,5,8,13,21) — لقصص المستخدم في المشاريع السكرم فقط');
            }

            if (! Schema::hasColumn('tasks', 'acceptance_criteria')) {
                $table->longText('acceptance_criteria')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'acceptance_criteria')) {
                $table->dropColumn('acceptance_criteria');
            }

            if (Schema::hasColumn('tasks', 'story_points')) {
                $table->dropColumn('story_points');
            }
        });
    }
};

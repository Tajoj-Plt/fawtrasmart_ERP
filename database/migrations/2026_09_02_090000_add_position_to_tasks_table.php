<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ترتيب المهام داخل عمود الحالة الواحد في لوحة كانبان (السحب لأعلى/لأسفل
 * ضمن نفس الحالة، لا فقط السحب بين الأعمدة).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks') || Schema::hasColumn('tasks', 'position')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'position')) {
                $table->integer('position')->default(0)->after('completion_percentage');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasColumn('tasks', 'position')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة عمود popup_shown لتتبع إذا تم عرض الإشعار كـ popup للموظف
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'popup_shown')) {
                $table->tinyInteger('popup_shown')->default(0)->after('read')
                    ->comment('1 = تم عرضه كـ popup للموظف');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'popup_shown')) {
                $table->dropColumn('popup_shown');
            }
        });
    }
};

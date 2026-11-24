<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خزينة افتراضية للمبنى — تُقترح تلقائياً عند تسجيل دفعةٍ لأي وحدةٍ فيه،
 * وتبقى قابلةً للتغيير يدوياً. العمود يحمل accounts.id (كما treasury_account_id
 * في re_payments) لا treasuries.id، فكلّ خزينة تُغلَّف بحسابٍ في دليل الحسابات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('re_buildings', function (Blueprint $table) {
            if (! Schema::hasColumn('re_buildings', 'default_treasury_account_id')) {
                $table->unsignedBigInteger('default_treasury_account_id')->nullable()->after('store_house_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('re_buildings', function (Blueprint $table) {
            $table->dropIndex(['default_treasury_account_id']);
            $table->dropColumn('default_treasury_account_id');
        });
    }
};

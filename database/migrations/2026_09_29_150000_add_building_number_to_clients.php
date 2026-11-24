<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رقم مبنى العميل — من العنوان الوطني.
 *
 * الهيئة تطلب في الفاتورة الضريبية (لمنشأةٍ برقم ضريبي) عنوان المشتري
 * مفصَّلاً: شارعٌ ورقم مبنى وحيٌّ ومدينة ورمز بريدي. وسجلّ العميل يحمل
 * أربعةً منها — الحيّ في `neighborhoods` — وينقصه رقم المبنى، فكانت كلّ
 * فاتورةٍ ضريبية تُعتمَد بملاحظةٍ من الهيئة (BR-KSA-63).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'building_number')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('building_number', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        // لا يُسقَط: فيه ما أدخله المستخدم من عناوين عملائه
    }
};

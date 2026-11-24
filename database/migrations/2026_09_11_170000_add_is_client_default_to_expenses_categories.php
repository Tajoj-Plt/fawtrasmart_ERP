<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «التصنيف الافتراضي لسند صرف العميل».
 *
 * سند الصرف لعميل لا يُلزَم بتصنيف (`required_unless:expense_type,client`)،
 * فكان يُحفظ «غير محدد» — ولكلّ منشأةٍ تصنيفُها لهذه السندات باسمٍ تختاره
 * هي («صرف عملاء»، «سند صرف للعميل»…)، فلا يصحّ تثبيته بالاسم في الكود.
 * علامةٌ على تصنيفٍ واحد في شاشة «تصنيفات المصروفات»، ويقرؤها
 * `ExpensesController` فيختاره سلفاً لنوع «لعميل» ويملؤه عند الحفظ متى تُرك.
 *
 * `down()` لا يحذف العمود — راجع قاعدة «التقاعد لا الحذف» في الترحيلات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses_categories', 'is_client_default')) {
                $table->boolean('is_client_default')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        // لا حذف: عمودٌ بعلامةٍ اختارها المستخدم يُترك ولا يُمحى بالتراجع
    }
};

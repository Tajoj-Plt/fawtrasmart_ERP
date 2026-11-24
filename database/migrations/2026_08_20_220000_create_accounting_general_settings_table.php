<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ضوابط القيد العامة — صفٌّ واحد للمنشأة كلّها.
 *
 * كانت شاشة «الإعدادات العامة» في وحدة الحسابات أربعة مفاتيح تبديل داخل
 * `<form action="#">`، وزرُّ الحفظ خارج النموذج أصلاً. أي أنّ الشاشة لا
 * تحفظ شيئاً ولا تحاول: تُفتَح فتظهر القيم الافتراضية دائماً، ويُبدَّل
 * المفتاح فيعود إلى حاله عند أول تحديث.
 *
 * لا مخزنَ عامّاً للإعدادات في هذا النظام يصلح لها: `account_settings`
 * مربوط بمستخدم (`user_id`) وهذه ضوابط منشأة لا تفضيلات شخص، و
 * `application_settings` مفاتيحُ تفعيل وحدات لا قيمٌ محاسبية.
 *
 * فجدولٌ خاصّ بصفٍّ واحد — كما `accounting_period_settings`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_general_settings')) {
            return;
        }

        Schema::create('accounting_general_settings', function (Blueprint $table) {
            $table->id();

            // مركز التكلفة والضريبة يظهران في سطر القيد لمن يستعملهما،
            // ويُخفيان عمن لا يستعملهما فلا يزدحم النموذج بحقلٍ لا يُملأ
            $table->boolean('show_cost_center')->default(true);
            $table->boolean('show_tax')->default(true);

            // سعر الصرف يُحدَّث من الإعدادات عند فتح نموذج القيد
            $table->boolean('update_exchange_rate')->default(false);

            // وسوم حرّة على حركات القيد للتصنيف والبحث
            $table->boolean('assign_tags')->default(false);

            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_general_settings');
    }
};

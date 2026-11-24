<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طريقة الدفع على سندَي الصرف والقبض.
 *
 * **أين تلزم فعلاً؟**
 *
 * شاشة «طرق الدفع» في الإعدادات تعرّف كاشاً وشيكاً وتحويلاً بنكياً ودفعاً
 * إلكترونياً، وتستعملها المبيعاتُ والمشترياتُ ونقطةُ البيع — ولا تستعملها
 * وحدةُ الحسابات في موضع واحد. والسندان هما الموضعان اللذان يحتاجانها
 * حقّاً: مصروفٌ دُفع، وقبضٌ استُلم — ولكلٍّ منهما طريقةٌ يسأل عنها المراجع.
 *
 * **وأين لا تلزم؟**
 *
 * الشيكُ طريقةُ دفعٍ بذاته، فلا معنى لسؤاله عن طريقته. والقيدُ اليدوي تسويةٌ
 * محاسبية لا حركةَ نقدٍ فيها. والتحويلُ بين خزينتين حركةٌ داخلية بين حسابَين
 * معلومَين. فلم تُضَف إليها — «في المواضع التي تحتاجها فعلياً فقط».
 *
 * العمود اختياري: سندٌ قديم لم تُسجَّل طريقته يبقى صالحاً.
 */
return new class extends Migration
{
    private const TABLES = ['expenses', 'receipts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'payment_method_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $column = $t->unsignedBigInteger('payment_method_id')->nullable();

                if (Schema::hasColumn($table, 'treasury_id')) {
                    $column->after('treasury_id');
                }

                // بلا مفتاح أجنبي: `payment_methods` تستعمل الحذف الناعم،
                // وحذفُ طريقةٍ لا يجوز أن يُسقط سنداً محفوظاً أو يمنع حذفها
                $t->index('payment_method_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'payment_method_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['payment_method_id']);
                    $t->dropColumn('payment_method_id');
                });
            }
        }
    }
};

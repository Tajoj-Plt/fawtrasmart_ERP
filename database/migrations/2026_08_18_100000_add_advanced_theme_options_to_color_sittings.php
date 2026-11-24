<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خيارات المظهر المتقدّمة — تكملة لوحة الألوان وضبط الشكل وصفحة الدخول.
 *
 * كلها اختيارية وفارغة افتراضياً: القيمة الفارغة تعني «اشتقّ من الأساسي» أو
 * «اترك ما في القالب الأصلي»، فتفعيل الترحيل وحده لا يغيّر مظهر أي شاشة.
 */
return new class extends Migration
{
    /** العمود => [النوع, الطول, التعليق] */
    private const COLUMNS = [
        'info_color' => ['string', 20, 'لون التنبيهات المعلوماتية'],
        'warning_color' => ['string', 20, 'لون التحذيرات'],
        'link_color' => ['string', 20, 'لون الروابط النصية — فارغ يعني اللون الأساسي'],
        'sidebar_width' => ['string', 10, 'عرض القائمة الجانبية مثل 260px'],
        'card_shadow' => ['string', 20, 'none|soft|medium|strong'],
        'login_bg_color' => ['string', 20, 'لون خلفية صفحة الدخول — فارغ يعني اللون الأساسي'],
        'custom_css' => ['text', null, 'CSS إضافي يُحقن آخر الترويسة — لمن يعرف ما يفعل'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('color_sittings')) {
            return;
        }

        Schema::table('color_sittings', function (Blueprint $table) {
            foreach (self::COLUMNS as $name => [$type, $length, $comment]) {
                if (Schema::hasColumn('color_sittings', $name)) {
                    continue;
                }

                $column = $type === 'text'
                    ? $table->text($name)->nullable()
                    : $table->string($name, $length)->nullable();

                $column->comment($comment);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('color_sittings')) {
            return;
        }

        Schema::table('color_sittings', function (Blueprint $table) {
            $existing = array_filter(
                array_keys(self::COLUMNS),
                fn ($name) => Schema::hasColumn('color_sittings', $name)
            );

            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};

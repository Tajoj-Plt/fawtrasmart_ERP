<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات مظهر الجداول والبطاقات وعناصر التحكّم.
 *
 * كلها فارغة/مطفأة افتراضياً: الفارغ يعني «اترك ما في القالب الأصلي»، فتشغيل
 * الترحيل وحده لا يغيّر شكل أي شاشة قبل أن يضبط المسؤول شيئاً فعلاً.
 */
return new class extends Migration
{
    /** العمود => [النوع, الطول, التعليق] */
    private const COLUMNS = [
        // ==================== الجداول ====================
        'table_header_bg' => ['string', 20, 'خلفية رأس الجدول'],
        'table_header_text' => ['string', 20, 'لون نص رأس الجدول'],
        'table_striped' => ['boolean', null, 'فرض تلوين الصفوف بالتناوب'],
        'table_hover' => ['boolean', null, 'إبراز الصف عند مرور المؤشر'],
        'table_border_style' => ['string', 20, 'none|horizontal|full — فارغ يترك حدود القالب'],

        // ==================== البطاقات ====================
        'card_border' => ['boolean', null, 'إظهار حد حول البطاقة'],
        'card_header_bg' => ['string', 20, 'خلفية رأس البطاقة'],
        'card_padding' => ['string', 10, 'حشو داخل البطاقة مثل 20px'],

        // ==================== الأزرار والحقول ====================
        'button_size' => ['string', 10, 'sm|md|lg — فارغ يترك مقاس القالب'],
        'button_radius' => ['string', 10, 'استدارة الأزرار وحدها — فارغ يتبع الاستدارة العامة'],
        'input_bg' => ['string', 20, 'خلفية حقول الإدخال'],
        'input_height' => ['string', 10, 'ارتفاع حقول الإدخال مثل 38px'],

        // ==================== التخطيط ====================
        'topbar_height' => ['string', 10, 'ارتفاع الشريط العلوي مثل 62px'],
        'content_width' => ['string', 20, 'full|boxed — عرض منطقة المحتوى'],
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

                $column = $type === 'boolean'
                    ? $table->boolean($name)->default(false)
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
            $existing = array_values(array_filter(
                array_keys(self::COLUMNS),
                fn ($name) => Schema::hasColumn('color_sittings', $name)
            ));

            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};

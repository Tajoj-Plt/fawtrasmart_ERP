<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * العميلُ والحساب على أمر التصنيع اختياريان — كما تقول الشاشة.
 *
 * ── التناقض ──────────────────────────────────────────────────────────
 *
 * الجدولُ يفرضهما والتحقّقُ يقبلهما فارغين:
 *
 *     manufactur_orders.client_id    NOT NULL
 *     manufactur_orders.account_id   NOT NULL
 *
 *     ManufacturOrderRequest:  'client_id'  => 'nullable|exists:clients,id'
 *                              'account_id' => 'nullable|exists:accounts,id'
 *
 * فالطلبُ يمرّ من بوّابة التحقّق سالماً، ثم يسقط عند الإدراج. والمستخدم
 * يرى نصّ استثناء SQL خاماً — اسمَ الجدول والعمود ورقمَ الخطأ — بدل
 * رسالةٍ مفهومة. رُصد هذا أثناء اختبار المرحلة ١-ب.
 *
 * ── ولماذا الصواب أن يكونا اختياريين ─────────────────────────────────
 *
 * **أمرُ التصنيع للمخزون هو الحال الغالب في المصانع**: يُنتَج المنتج
 * ليُخزَّن ثم يُباع لاحقاً لعملاء لا يُعرفون لحظةَ الإنتاج. وربطُ كل
 * أمرٍ بعميلٍ إجباراً يفرض بياناتٍ كاذبة — يختار المستخدم عميلاً
 * عشوائياً ليمرّ الحفظ، فتفسد تقاريرُ العملاء كلُّها.
 *
 * والحساب كذلك: حساباتُ التصنيع تُقرأ من `accounting_settings` لا من
 * حقلٍ على الأمر، فالعمود هنا تخصيصٌ اختياري لا ركنٌ لازم.
 *
 * وبقيّةُ الأعمدة الإلزامية — `employee_id` و`production_material_id`
 * و`production_path_id` — يطلبها التحقّق `required` فعلاً، فلا تناقض
 * فيها وتُترك على حالها.
 */
return new class extends Migration
{
    private const TABLE = 'manufactur_orders';

    private array $columns = ['client_id', 'account_id'];

    public function up(): void
    {
        $this->changeNullability(true);
    }

    /**
     * العكسُ مشروط.
     *
     * إعادةُ `NOT NULL` تفشل إن وُجد صفٌّ واحد بقيمةٍ فارغة — وهو ما
     * ستُنشئه الأوامرُ الجديدة فوراً. فالعكسُ عمليًّا متاحٌ ما دامت
     * القاعدة خاليةً منها، ومقصودٌ أن يفشل بصخبٍ متى لم تكن.
     */
    public function down(): void
    {
        $this->changeNullability(false);
    }

    private function changeNullability(bool $nullable): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $present = array_filter(
            $this->columns,
            fn (string $column) => Schema::hasColumn(self::TABLE, $column)
        );

        if ($present === []) {
            return;
        }

        Schema::table(self::TABLE, function ($blueprint) use ($present, $nullable) {
            foreach ($present as $column) {
                $blueprint->unsignedBigInteger($column)->nullable($nullable)->change();
            }
        });
    }
};

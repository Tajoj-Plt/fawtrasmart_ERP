<?php

namespace App\Enums;

/**
 * حالةُ الموظف الوظيفية.
 *
 * **لماذا أعدادٌ لا نصوص؟** لأن العمود `employees.status` قائمٌ منذ أوّل
 * مهاجرة بقيمتين (`1 نشط` / `2 غير نشط`)، وخمسةَ عشرَ موضعاً في النظام
 * تقارنه بـ`1` — الرواتبُ والأهدافُ والسلفُ والتحدّياتُ وتقييمُ الأداء
 * والشاشات. فتغييرُ النوع إلى نصٍّ يعني لمسَ تلك المواضع كلِّها دفعةً
 * واحدة، وأخطرُها مسارُ الصرف.
 *
 * ─────────────────── «على رأس العمل» ليست حالةً واحدة ───────────────────
 *
 * `ACTIVE` و`PROBATION` كلتاهما عملٌ يُصرف له، و`PROBATION` قيمتُها `3` —
 * أي **ليست `1`**. فكلُّ فحصٍ كان يقارن الرقمَ بـ`1` كان سيمنع راتبَ من
 * هو تحت التجربة.
 *
 * ولهذا نُقلت تلك الفحوصُ كلُّها — تسعةُ حرّاسٍ في مسار الصرف والأهداف،
 * وخمسةَ عشرَ استعلاماً يبني قوائم الموظفين — إلى `isActive()` و
 * `Employee::active()`. **فالجوابُ عن «أعلى رأس العمل؟» صار في موضعٍ
 * واحد**، وإضافةُ حالةٍ عاملةٍ ثالثةً غداً لا تقتضي المرور عليها ثانية.
 *
 * أمّا `SUSPENDED` و`RESIGNED` و`TERMINATED` فليست عاملةً، ويقرؤها القديمُ
 * والجديدُ سواءً.
 */
enum EmploymentStatus: int
{
    /** على رأس العمل — يُصرف له ويدخل الأهداف والتقارير. */
    case ACTIVE = 1;

    /** موقوفٌ عن العمل مؤقّتاً بقرارٍ إداري أو بطلبه. */
    case INACTIVE = 2;

    /**
     * تحت التجربة — **يعمل ويُصرف له**.
     *
     * فترةٌ من العمل لا انقطاعٌ عنه، ولذلك تردّ `isActive()` صدقاً لها.
     * وتمييزُها مطلوبٌ لأن قواعد الإنهاء فيها غيرُ قواعده بعدها.
     */
    case PROBATION = 3;

    /** موقوفٌ تأديبياً — لا يُصرف له حتى يُرفع الإيقاف. */
    case SUSPENDED = 4;

    /** استقال بطلبه، وانتهت علاقتُه بالمنشأة. */
    case RESIGNED = 5;

    /** أُنهيت خدمتُه بقرارٍ من المنشأة. */
    case TERMINATED = 6;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::PROBATION => 'تحت التجربة',
            self::SUSPENDED => 'موقوف',
            self::RESIGNED => 'مستقيل',
            self::TERMINATED => 'منتهي الخدمة',
        };
    }

    /** لونُ الشارة في الشاشات — بمفردات ثيم النظام. */
    public function badge(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::PROBATION => 'info',
            self::SUSPENDED => 'warning',
            self::RESIGNED => 'dark',
            self::TERMINATED => 'danger',
        };
    }

    /**
     * أعلى رأس العمل؟ — الجوابُ الوحيد الذي يجب أن تعتمد عليه الشيفرة
     * الجديدة بدل مقارنة الرقم.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::ACTIVE, self::PROBATION], true);
    }

    /** أفي فترة التجربة؟ — نشطٌ، لكن بقواعد إنهاءٍ مختلفة. */
    public function isOnProbation(): bool
    {
        return $this === self::PROBATION;
    }

    /**
     * قيمُ الحالات التي يُعَدّ صاحبُها على رأس العمل — لاستعلامات القاعدة.
     *
     * @return array<int, int>
     */
    public static function activeValues(): array
    {
        return [self::ACTIVE->value, self::PROBATION->value];
    }

    /**
     * أانتهت علاقتُه بالمنشأة نهائياً؟
     *
     * تفرّق بين الإيقاف المؤقّت — يعود منه الموظف — وبين الانتهاء الذي
     * يلزمه تاريخٌ وسبب.
     */
    public function isEnded(): bool
    {
        return in_array($this, [self::RESIGNED, self::TERMINATED], true);
    }

    /**
     * الحالاتُ التي تُنهي الخدمة — تُشترط معها بيانات الإنهاء.
     *
     * @return array<int, self>
     */
    public static function ending(): array
    {
        return [self::RESIGNED, self::TERMINATED];
    }

    /**
     * خياراتُ القوائم المنسدلة: القيمة ← التسمية.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * الحالةُ من قيمةٍ مخزَّنة — وما لا يُعرف يُقرأ «غير نشط».
     *
     * القيمةُ `0` موجودةٌ فعلاً في قواعد قائمة: كان
     * `EmployeeController::updateStatus()` يكتب `! $status` — نفياً
     * منطقياً على عمودٍ مجاله `1`/`2` — فيحفظ `0` في كل تعطيل. وهي قيمةٌ
     * خارج المجال يقرؤها كلُّ فحصٍ «غير نشط»، فتُقرأ هنا كذلك.
     */
    public static function fromValue($value): self
    {
        return self::tryFrom((int) $value) ?? self::INACTIVE;
    }
}

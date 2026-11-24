<?php

namespace App\Enums;

/**
 * نوعُ وثيقة الموظف.
 *
 * **لماذا نوعٌ لا جدولُ إعدادات؟**
 *
 * لأن الأنواعَ هنا ثابتةٌ لا تختلف من منشأةٍ إلى أخرى — الهويةُ هويةٌ
 * والإقامةُ إقامة — وما يختلف هو **أيُّها مطلوبٌ عندي**، وذاك لا يحتاج
 * جدولاً بل تركَ الحقل فارغاً. وجدولُ إعداداتٍ لسبعة صفوفٍ ثابتة يُضيف
 * شاشةً تُدار ولا يُضيف قدرة.
 *
 * وإن لزم لاحقاً نوعٌ خاصٌّ بمنشأة، فـ`OTHER` بابُه مع `title` الحرّ.
 *
 * ─────────────────── ما ينتهي وما لا ينتهي ───────────────────
 *
 * `expires()` هي ما يبني عليه تنبيهُ الانتهاء: شهادةُ الميلاد لا تنتهي،
 * والإقامةُ تنتهي فتُوقف العملَ إن فاتت. فالتفريقُ هنا يمنع تنبيهاتٍ لا
 * معنى لها — وهي أوّلُ ما يجعل التنبيهات تُهمَل كلُّها.
 */
enum EmployeeDocumentType: string
{
    /** الهوية الوطنية — للمواطن. */
    case NATIONAL_ID = 'national_id';

    /** الإقامة — للمقيم، وانتهاؤها يوقف العمل. */
    case RESIDENCY = 'residency';

    case PASSPORT = 'passport';

    case DRIVING_LICENSE = 'driving_license';

    /** رخصةٌ مهنية أو تصريحُ عملٍ يخصّ الوظيفة. */
    case WORK_PERMIT = 'work_permit';

    /** شهادةٌ علمية أو دورة — لا تنتهي. */
    case CERTIFICATE = 'certificate';

    /** شهادةٌ صحّية أو تأمينٌ طبّي. */
    case HEALTH = 'health';

    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NATIONAL_ID => 'الهوية الوطنية',
            self::RESIDENCY => 'الإقامة',
            self::PASSPORT => 'جواز السفر',
            self::DRIVING_LICENSE => 'رخصة القيادة',
            self::WORK_PERMIT => 'رخصة العمل',
            self::CERTIFICATE => 'شهادة',
            self::HEALTH => 'وثيقة صحّية',
            self::OTHER => 'أخرى',
        };
    }

    /**
     * أَلِهذا النوعِ تاريخُ انتهاءٍ يُنبَّه عليه؟
     *
     * الشهادةُ العلمية لا تنتهي، فتنبيهٌ عليها ضجيجٌ يُفقد بقيّةَ التنبيهات
     * قيمتَها.
     */
    public function expires(): bool
    {
        return $this !== self::CERTIFICATE;
    }

    /** @return array<string, string> القيمة ← التسمية، لقوائم الاختيار */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** قيمُ الأنواع التي لها انتهاء — لاستعلامات التنبيه. */
    public static function expiringValues(): array
    {
        return array_values(array_map(
            fn (self $case) => $case->value,
            array_filter(self::cases(), fn (self $case) => $case->expires())
        ));
    }
}

<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRule;
use App\Models\Employee;
use Illuminate\Support\Facades\Schema;

/**
 * يقرأ قواعد الحضور النشطة ويحوّل الشرط/الصيغة إلى مفاتيح إعداد معروفة.
 *
 * الصيغة الحرة بلا مُفسِّر آمن كانت تُحفظ ولا تُقرأ. هنا نقبل أزواجاً
 * `مفتاح=قيمة` مفصولة بـ `;` أو سطر جديد، من الحقلين `condition` و`formula`.
 *
 * المفاتيح المدعومة (نفس أسماء إعدادات V2):
 * late_grace_minutes, absent_after_minutes, early_leave_threshold_minutes,
 * checkout_window_minutes, work_hours_per_day
 */
class AttendanceRuleOverrides
{
    private const ALLOWED = [
        'late_grace_minutes',
        'absent_after_minutes',
        'early_leave_threshold_minutes',
        'checkout_window_minutes',
        'work_hours_per_day',
    ];

    /**
     * المفاتيحُ المدعومةُ بأسمائها ووحداتها وحدودها — **مصدرٌ واحدٌ للشاشة
     * والمحرّك معاً**.
     *
     * كان النموذجُ حقلَي نصٍّ حرَّين («الصيغة الحسابية» و«الشرط»)، والمحرّكُ
     * لا يقبل إلّا `مفتاح=قيمة` من هذه الخمسة. فمن كتب قاعدةً بالعربية حُفظت
     * بنجاحٍ **وأُهملت بصمت** — ثبت ذلك بتجربة. فصارت الشاشةُ تُولَّد من هنا:
     * لا يُكتب إلّا ما يُقرأ.
     *
     * @return array<string, array{label: string, unit: string, hint: string, min: int|float, max: int|float, step: int|float}>
     */
    public static function fields(): array
    {
        return [
            'late_grace_minutes' => [
                'label' => 'مهلة التسامح قبل احتساب التأخير',
                'unit' => 'دقيقة',
                'hint' => 'من حضر داخلها لا يُعدّ متأخّراً ولا تُحتسب عليه دقائق.',
                'min' => 0, 'max' => 240, 'step' => 1,
            ],
            'absent_after_minutes' => [
                'label' => 'يُقلب اليوم غياباً بعد تأخير',
                'unit' => 'دقيقة',
                'hint' => 'تجاوزُها يحوّل اليوم من «متأخّر» إلى «غائب» — والفرقُ حسمُ يومٍ كامل.',
                'min' => 0, 'max' => 720, 'step' => 1,
            ],
            'early_leave_threshold_minutes' => [
                'label' => 'حدّ الانصراف المبكر',
                'unit' => 'دقيقة',
                'hint' => 'من انصرف قبل نهاية ورديته بأكثر منها يُسجَّل انصرافاً مبكراً.',
                'min' => 0, 'max' => 480, 'step' => 1,
            ],
            'checkout_window_minutes' => [
                'label' => 'مهلة تسجيل الانصراف بعد نهاية الوردية',
                'unit' => 'دقيقة',
                'hint' => 'بعدها لا يُقبل ختمُ الانصراف ويُقَصّ إلى نهاية الوردية.',
                'min' => 0, 'max' => 720, 'step' => 1,
            ],
            'work_hours_per_day' => [
                'label' => 'ساعات العمل اليومية',
                'unit' => 'ساعة',
                'hint' => 'الأساسُ الذي يُقاس عليه صافي العمل ونصفُ اليوم والوقتُ الإضافي.',
                'min' => 0, 'max' => 24, 'step' => 0.25,
            ],
        ];
    }

    /**
     * قراءةُ قيم قاعدةٍ محفوظة — من `formula` و`condition` معاً.
     *
     * الحقلان يُقرآن منذ البداية بالمنطق نفسه، فالتفريقُ بينهما في الشاشة
     * كان وهماً. والصفوفُ القديمةُ المكتوبةُ بأيّ منهما تُفتح صحيحةً.
     *
     * @return array<string, int|float>
     */
    public function valuesOf(?string $formula, ?string $condition = null): array
    {
        return $this->parse(trim((string) $condition).' ; '.trim((string) $formula));
    }

    /**
     * نصُّ `formula` كما يُحفظ من قيم الشاشة.
     *
     * @param  array<string, mixed>  $values
     */
    public static function compose(array $values): string
    {
        $pairs = [];

        foreach (self::fields() as $key => $meta) {
            $value = $values[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $pairs[] = $key.'='.(is_float($value + 0) && fmod((float) $value, 1.0) !== 0.0
                ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.')
                : (int) $value);
        }

        return implode('; ', $pairs);
    }

    /**
     * وصفٌ عربيٌّ لما تضبطه قاعدةٌ — للقوائم وصفحة العرض.
     *
     * @return array<int, string>
     */
    public function describe(?string $formula, ?string $condition = null): array
    {
        $fields = self::fields();
        $out = [];

        foreach ($this->valuesOf($formula, $condition) as $key => $value) {
            $out[] = $fields[$key]['label'].': '.$this->amount($value, $fields[$key]['unit']);
        }

        return $out;
    }

    /**
     * تمييزُ العدد بالعربية: «دقيقةٌ واحدة»، «دقيقتان»، «٥ دقائق»، «٣٠ دقيقة».
     *
     * أربعُ صيغٍ لا صيغتان، و`trans_choice` لا تُحسنها — فتُكتب صراحةً.
     */
    private function amount(int|float $value, string $unit): string
    {
        $forms = [
            'دقيقة' => ['دقيقةٌ واحدة', 'دقيقتان', 'دقائق', 'دقيقة'],
            'ساعة' => ['ساعةٌ واحدة', 'ساعتان', 'ساعات', 'ساعة'],
        ][$unit] ?? null;

        $number = fmod((float) $value, 1.0) === 0.0 ? (string) (int) $value : (string) $value;

        if (! $forms || fmod((float) $value, 1.0) !== 0.0) {
            return $number.' '.$unit;
        }

        $n = (int) $value;

        return match (true) {
            $n === 1 => $forms[0],
            $n === 2 => $forms[1],
            $n >= 3 && $n <= 10 => $number.' '.$forms[2],
            default => $number.' '.$forms[3],
        };
    }

    /**
     * نصٌّ قديمٌ لا يفهمه المحرّك — يُعرض للمستخدم بدل أن يُبتلَع.
     *
     * فالقواعدُ المكتوبةُ بالعربية قبل اليوم محفوظةٌ ولا أثرَ لها، ومن حقّ
     * من يفتحها أن يعرف أنّها لم تكن تعمل.
     */
    public function leftover(?string $formula, ?string $condition = null): string
    {
        $raw = trim(trim((string) $condition).' ; '.trim((string) $formula), " ;\n\r\t");
        $kept = [];

        foreach (preg_split('/[;\n\r]+/', $raw) ?: [] as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            if ($this->parse($chunk) === []) {
                $kept[] = $chunk;
            }
        }

        return implode(' · ', $kept);
    }

    /**
     * @return array<string, int|float>
     */
    public function forEmployee(Employee $employee, ?int $shiftId = null): array
    {
        if (! Schema::hasTable('attendance_rules')) {
            return [];
        }

        $rules = AttendanceRule::query()
            ->where('status', 'active')
            ->orderByDesc('id')
            ->get();

        $merged = [];

        foreach ($rules as $rule) {
            if ($rule->shift_id && (int) $rule->shift_id !== (int) $shiftId) {
                continue;
            }

            foreach ($this->parse($rule->condition.' ; '.$rule->formula) as $key => $value) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @return array<string, int|float>
     */
    private function parse(string $raw): array
    {
        $out = [];

        foreach (preg_split('/[;\n\r]+/', $raw) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || ! str_contains($chunk, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $chunk, 2));
            $key = strtolower(str_replace([' ', '-'], '_', $key));

            if (! in_array($key, self::ALLOWED, true)) {
                continue;
            }

            if (! is_numeric($value)) {
                continue;
            }

            $out[$key] = str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $out;
    }
}

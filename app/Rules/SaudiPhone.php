<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

/**
 * رقمُ هاتفٍ أو جوالٍ سعودي بصيغته المحلية: يبدأ بصفر وطولُه عشرُ خانات
 * (05xxxxxxxx للجوال، 01xxxxxxxx للثابت).
 *
 * كانت الحقولُ تقبل أيَّ نصٍّ حتى عشرين خانة، فيُحفظ الرقمُ ناقصاً أو بلا
 * صفره أو بمفتاح الدولة مرّةً وبدونه مرّة — والرقمُ نفسه يتكرّر بثلاث صيغ
 * لا يطابق بعضُها بعضاً في البحث ولا في منع التكرار.
 *
 * طبقتان:
 *
 *   ١) **التطبيع** ({@see normalize()}) قبل التحقّق: الأرقام الهندية تُحوَّل،
 *      والفراغاتُ والشرطاتُ والأقواس تُحذف، ومفتاحُ الدولة (+966 / 00966 /
 *      966) يُردّ صفراً. فمن ألصق «+966 50 123 4567» يُحفظ له 0501234567
 *      ولا يُردّ.
 *   ٢) **الصيغة**: صفرٌ ثم تسعُ خانات.
 *
 * **الأرقامُ القديمة لا تحبس السجلّ** (`$legacy`): سجلٌّ حُفظ قبل هذه القاعدة
 * برقمٍ مخالف يُقبل ما دام الرقمُ لم يتغيّر — وإلّا رُدّ كلُّ تعديلٍ عليه
 * (اسمٌ، صورة، تصنيف) بخطأٍ في حقلٍ لم يُمسّ. متى غُيّر الرقمُ لزمته الصيغة.
 */
class SaudiPhone implements ValidationRule
{
    public const LENGTH = 10;

    public const PATTERN = '/^0[0-9]{9}$/';

    public const MESSAGE = 'الرقم يجب أن يبدأ بـ 0 ويتكوّن من 10 أرقام — مثال: 0512345678';

    private const EASTERN_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const WESTERN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /** @var array<int, string> */
    private array $legacy;

    /**
     * @param  iterable<int, mixed>|string|null  $legacy  الأرقام المحفوظة حالياً في السجلّ — تُقبل كما هي إن لم تتغيّر
     */
    public function __construct(iterable|string|null $legacy = null)
    {
        $values = is_iterable($legacy) ? $legacy : [$legacy];
        $this->legacy = [];

        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $this->legacy[] = trim((string) $value);
                $this->legacy[] = self::normalize((string) $value);
            }
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return; // الإلزامُ شأنُ `required`، لا شأنُ الصيغة
        }

        $raw = trim((string) $value);

        if (in_array($raw, $this->legacy, true) || in_array(self::normalize($raw), $this->legacy, true)) {
            return;
        }

        if (! self::isValid($raw)) {
            $fail(self::MESSAGE);
        }
    }

    public static function isValid(?string $value): bool
    {
        // الحروفُ تُردّ ولا تُحذف: «05a12345678» ليس رقماً صحيحاً كُتب بفواصل
        if (preg_match('/[^\d\s+\-()]/u', str_replace(self::EASTERN_DIGITS, self::WESTERN_DIGITS, (string) $value))) {
            return false;
        }

        return preg_match(self::PATTERN, self::normalize($value)) === 1;
    }

    /**
     * الرقمُ كما يُحفظ: أرقامٌ لاتينية بلا فواصل، ومفتاحُ الدولة مردودٌ صفراً.
     * ما لا يُفهم يُعاد أرقامَه كما هي لتردّه القاعدةُ برسالتها.
     */
    public static function normalize(?string $value): string
    {
        $value = str_replace(self::EASTERN_DIGITS, self::WESTERN_DIGITS, trim((string) $value));
        $international = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/u', '', $value) ?? '';

        if (str_starts_with($digits, '00966')) {
            $digits = substr($digits, 2);
            $international = true;
        }

        // 9665xxxxxxxx ← اثنتا عشرة خانة لا تكون رقماً محلياً، فهي مفتاحُ الدولة
        if (str_starts_with($digits, '966') && ($international || strlen($digits) === 12)) {
            $digits = '0'.ltrim(substr($digits, 3), '0');
        }

        return $digits;
    }

    /**
     * يطبّع حقول الأرقام في الطلب قبل التحقّق، فيُحفظ ما يُتحقَّق منه.
     *
     * الرقمُ القديمُ المخالف يبقى كما أُرسل: تطبيعُه يغيّره فلا يطابق
     * المحفوظَ، فيُردّ وهو لم يُمسّ.
     *
     * @param  array<int, string>  $fields  حقولٌ مفردة
     * @param  array<string, array<int, string>>  $rows  حقولٌ داخل صفوفٍ متكرّرة: ['contacts' => ['phone', 'mobile']]
     */
    public static function normalizeRequest(Request $request, array $fields, array $rows = []): void
    {
        $clean = fn ($value) => is_scalar($value) && trim((string) $value) !== '' && self::isValid((string) $value)
            ? self::normalize((string) $value)
            : $value;

        $merge = [];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $merge[$field] = $clean($request->input($field));
            }
        }

        foreach ($rows as $key => $columns) {
            $list = $request->input($key);
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $index => $row) {
                foreach ($columns as $column) {
                    if (is_array($row) && array_key_exists($column, $row)) {
                        $list[$index][$column] = $clean($row[$column]);
                    }
                }
            }
            $merge[$key] = $list;
        }

        if ($merge) {
            $request->merge($merge);
        }
    }
}

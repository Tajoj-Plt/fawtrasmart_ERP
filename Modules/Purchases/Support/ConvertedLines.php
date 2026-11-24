<?php

namespace Modules\Purchases\Support;

/**
 * بنود التحويل — ما يَرِثه المستند عن سابقه في دورة المشتريات.
 *
 * طلب الشراء يُحوَّل إلى طلب عرض أسعار، وطلبُ العرض يُحوَّل إلى عرض سعر.
 * البنود تُنسخ في كل خطوة، وما يُنسَخ ليس ملكاً للشاشة التي استقبلته: هو
 * ما اعتُمد في المستند السابق. فلو عُدِّل هنا لخرج طلبُ التسعير يطلب غير
 * ما وافقت عليه الإدارة، وخرج عرضُ السعر يسعّر غير ما طُلب — والفارق لا
 * يظهر إلا في الفاتورة، بعد فوات وقت المراجعة.
 *
 * ولذلك القفل في موضعين لا موضع واحد:
 *
 *   1. **الشاشة** — `mark()` تعلّم البنود الموروثة، فيُغلق النموذج حقولها
 *      (الصنف والوحدة والكمية والوصف) ويمنع حذفها. أمّا السعر والخصم
 *      والضريبة فتبقى مفتوحةً: هي ما يضيفه المستند الجديد لا ما ورثه.
 *
 *   2. **الخادم** — `guard()` تتحقّق عند الحفظ أن البنود الموروثة وصلت كما
 *      خرجت. سمة `readonly` تُنزَع من أدوات المتصفّح في ثوانٍ، فالقفل
 *      الذي لا يُسنده فحصٌ عند الحفظ ليس قفلاً بل رجاء.
 *
 * البنود المضافة يدوياً فوق الموروثة مسموحة — الغرض حفظُ ما وُرِث لا
 * تجميدُ المستند كلّه.
 */
class ConvertedLines
{
    /** فرقٌ في الكمية دون هذا يُعدّ صفراً — الكمية تمرّ بالنصّ ذهاباً وإياباً */
    private const TOLERANCE = 0.0001;

    /**
     * معرّفات أصناف المستند المصدر.
     *
     * @param  iterable  $source  بنود المستند المصدر
     * @return array<int, int>
     */
    public static function lockedProducts($source): array
    {
        return collect($source)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * تعليم البنود الموروثة في صفوف النموذج.
     *
     * العلامة تُشتقّ من المستند المصدر لا من الطلب الوارد: `old()` لا
     * يحملها بعد فشل التحقّق فتضيع، ولو حُملت في حقل مخفيّ لصار القفل
     * رهن ما يرسله المتصفّح — وهو ما يُراد منعه أصلاً.
     *
     * @param  array  $rows  صفوف النموذج
     * @param  iterable  $source  بنود المستند المصدر — فارغة إن لم يكن تحويلاً
     */
    public static function mark(array $rows, $source): array
    {
        $locked = self::lockedProducts($source);

        if (! $locked) {
            return $rows;
        }

        return array_map(function ($row) use ($locked) {
            $row['locked'] = in_array((int) ($row['product_id'] ?? 0), $locked, true);

            return $row;
        }, $rows);
    }

    /**
     * فحصُ الحفظ: هل وصلت البنود الموروثة كما خرجت؟
     *
     * المطابقة صنفاً وكميةً، وبنداً ببند لا بالمجموع: بندان لنفس الصنف
     * في المصدر يحتاجان بندين مثلهما في المرسَل، فلا يُغطّي أحدهما الآخر.
     * وما زاد عن ذلك من بنود مضافة يدوياً لا يُمسّ.
     *
     * @param  iterable  $source  بنود المستند المصدر
     * @param  array  $submitted  بنود النموذج المرسلة
     * @return string|null رسالة الرفض، أو null إن سلمت
     */
    public static function guard($source, array $submitted): ?string
    {
        $missing = self::missing($source, $submitted);

        if (! $missing) {
            return null;
        }

        return 'لا يمكن تعديل أو حذف بنود جاءت بالتحويل من المستند الأصلي: '
            .implode('، ', $missing)
            .'. أعِد فتح الشاشة من زرّ التحويل، أو عدّل المستند الأصلي أوّلاً.';
    }

    /**
     * فحصُ الحفظ في شاشة التعديل.
     *
     * كالسابق، مع استثناءٍ واحد: مستندٌ حُفظت بنوده مخالفةً لأصلها **قبل**
     * وجود هذا الفحص لا يُحبَس صاحبه اليوم عن حفظ أي تعديل — الفحص جاء
     * ليمنع مخالفةً جديدة لا ليُقفل ما مضى. فإن كانت البنود المحفوظة
     * أصلاً غير مطابقة، مُرَّ الحفظ كما كان.
     *
     * @param  iterable  $source  بنود المستند المصدر
     * @param  iterable  $existing  بنود المستند المحفوظة الآن
     * @param  array  $submitted  بنود النموذج المرسلة
     */
    public static function guardUpdate($source, $existing, array $submitted): ?string
    {
        if (self::missing($source, self::rowsOf($existing))) {
            return null;
        }

        return self::guard($source, $submitted);
    }

    /**
     * بنود محفوظة إلى الشكل الذي تقرأه `missing()`.
     *
     * @param  iterable  $items
     * @return array<int, array{product_id: int, quantity: float}>
     */
    private static function rowsOf($items): array
    {
        return collect($items)->map(fn ($item) => [
            'product_id' => (int) (is_array($item) ? ($item['product_id'] ?? 0) : ($item->product_id ?? 0)),
            'quantity' => (float) (is_array($item) ? ($item['quantity'] ?? 0) : ($item->quantity ?? 0)),
        ])->values()->all();
    }

    /**
     * أسماء البنود الموروثة التي لم تصل كما خرجت.
     *
     * @param  iterable  $source
     * @return array<int, string>
     */
    public static function missing($source, array $submitted): array
    {
        $pool = [];

        foreach ($submitted as $item) {
            $pool[] = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'used' => false,
            ];
        }

        $missing = [];

        foreach ($source as $line) {
            $productId = (int) (is_array($line) ? ($line['product_id'] ?? 0) : ($line->product_id ?? 0));
            $quantity = (float) (is_array($line) ? ($line['quantity'] ?? 0) : ($line->quantity ?? 0));

            $matched = false;

            foreach ($pool as $key => $candidate) {
                if ($candidate['used'] || $candidate['product_id'] !== $productId) {
                    continue;
                }

                if (abs($candidate['quantity'] - $quantity) > self::TOLERANCE) {
                    continue;
                }

                $pool[$key]['used'] = true;
                $matched = true;
                break;
            }

            if (! $matched) {
                $missing[] = self::labelFor($line, $productId);
            }
        }

        return $missing;
    }

    /** اسمُ البند كما يعرفه المستخدم — لا معرّفه الرقمي إن أمكن */
    private static function labelFor($line, int $productId): string
    {
        $name = is_array($line)
            ? ($line['item'] ?? null)
            : ($line->item ?? optional($line->product ?? null)->name);

        $name = trim((string) $name);

        return $name !== '' ? $name : '#'.$productId;
    }
}

<?php

namespace Modules\Pos\Services\Inventory;

use App\Models\PermissionSource;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;

/**
 * إذنُ نقطة البيع — يُصدَر معتمَداً، بنوعه، ومربوطاً بفاتورته.
 *
 * ═════════════════════ ثلاثةُ أعطالٍ في إصدار الأذون ═════════════════════
 *
 * كانت الأذونُ تُنشأ في ستّة مواضعَ من `SalesStartController`، كلٌّ منها
 * بهذه الصورة:
 *
 *     WarehousePermits::create([
 *         'permission_type' => $source->id,
 *         'permission_date' => $invoice->created_at,
 *         'number'          => $invoice->id,
 *         'grand_total'     => $invoice->grand_total,
 *         'store_houses_id' => $storeHouse->id,
 *         'created_by'      => auth()->id(),
 *     ]);
 *
 * **١. بلا حالة.** والعمودُ افتراضُه `'pending'`. فكلُّ إذنٍ يصدر من نقطة
 * البيع ينتظر موافقةً لا تأتي — والبيعُ قد تمّ، والمخزونُ نقص، والقيدُ
 * كُتب. فيبقى في شاشة الأذون طابورٌ من أذونٍ معلَّقةٍ لأحداثٍ منتهية،
 * ومن يوافق عليها لاحقاً قد يُحدث أثراً مزدوجاً.
 *
 * وبيعُ نقطة البيع ليس طلباً يُراجَع: هو **إثباتُ واقع**. البضاعةُ خرجت
 * والزبونُ مضى. فالإذنُ يوثّق ما جرى لا يستأذن فيه.
 *
 * **٢. بلا ربطٍ بالمستند.** `reference_type` يبقى على افتراضه `'normal'`
 * و`reference_id` فارغاً. و`number` يحمل **معرّف الفاتورة** لا رقمَها —
 * فلا يُعرف من الإذن أيَّ فاتورةٍ يخصّ إلّا بتخمين.
 *
 * **٣. بلا كمّياتٍ موحَّدة.** كلُّ موضعٍ يكتب سطرَ المنتج بيده، فيسهو أحدُها
 * عن `stock_before` أو يقلبه.
 *
 * ═════════════════════ ولماذا مُصدِرٌ واحد ═════════════════════
 *
 * الإذنُ وسطرُه يُكتبان معاً أو لا يُكتبان: إذنٌ بلا سطورٍ حركةٌ بلا محتوى،
 * وسطرٌ بلا إذنٍ يتيم. وجمعُهما في نداءٍ واحد يمنع أن يُنسى أحدُهما.
 */
class PosPermitIssuer
{
    /**
     * حالةُ الاعتماد كما يقرؤها النظام.
     *
     * ⚠️ الخطأُ الإملائي متوارَث: `'apporod'` هي القيمةُ المكتوبة في
     * قواعد البيانات القائمة وفي شاشات المخزون والتصنيع والمتجر
     * (`Modules/Manufacturing/Constants/ManufacturingSources.php`).
     * وتصحيحُها هنا وحدَها يجعل أذونَ نقطة البيع غيرَ مرئيّةٍ لتلك الشاشات.
     */
    public const APPROVED = 'apporod';

    /* ═══════════ مصادرُ الإذن — بأسمائها في `permission_sources` ═══════════ */

    public const SOURCE_SALE = 'فاتورة مبيعات';

    public const SOURCE_RETURN = 'مرتجع مبيعات';

    public const SOURCE_COMPILE_ADD = 'إضافة منتج مجمع فوري';

    /*
        ═══════════ رقمُ الإذن من إعدادات الترقيم ═══════════

        الإذنُ مستندٌ يُقرأ ويُبحث به ويُطبع، فله رقمُه كسائر المستندات.

        وكان `number` يحمل **معرّف الفاتورة** — رقمَ صفٍّ في قاعدة بيانات
        لا رقمَ مستند. ثم صار يحمل رقمَ الفاتورة، وهو أقربُ لكنّه خطأ
        أيضاً: فاتورةٌ واحدةٌ قد تُصدر أذوناً عدّة — منتجٌ مجمَّعٌ فوريّ
        يُصدر ثلاثة — فتحمل كلُّها الرقمَ نفسَه، ويُنقض تفرّدُ العمود.

        والاتجاهُ يحسم القسم: ما يخرج `warehouse-dispose`، وما يدخل
        `warehouse-add`. وكلاهما معرَّفٌ في `DocumentSections` ومربوطٌ
        بـ`warehouse_permits.number`، فيُقرأ منه آخرُ رقمٍ محجوزٍ ولا
        يتصادم.

        وربطُ الإذن بفاتورته يبقى في `reference_type`/`reference_id` — وهو
        موضعُه الصحيح.
    */
    private const SECTION_OF = [
        self::SOURCE_SALE => 'warehouse-dispose',
        self::SOURCE_RETURN => 'warehouse-add',
        self::SOURCE_COMPILE_ADD => 'warehouse-add',
    ];

    /** ذاكرةُ المصادر — تُقرأ مرّةً لكل طلب لا لكل بند. */
    private static array $sources = [];

    /**
     * إصدارُ إذنٍ معتمَدٍ بسطوره.
     *
     * @param  string  $source  اسمُ المصدر من الثوابت أعلاه
     * @param  array<int, array{
     *     product_id: int, quantity: float, unit_price: float,
     *     total: float, stock_before: float, stock_after: float
     * }>  $lines
     * @param  array{
     *     reference_type?: string, reference_id?: int|null,
     *     number?: mixed, currency?: string|null,
     *     accounting_period_id?: int|null, client_id?: int|null,
     *     details?: string|null
     * }  $context
     */
    public static function issue(
        string $source,
        int $storeHouseId,
        float $grandTotal,
        array $lines,
        array $context = [],
        $date = null
    ): WarehousePermits {
        $permit = WarehousePermits::create(array_filter([
            'permission_type' => static::sourceId($source),
            'permission_date' => $date ?: now(),
            'store_houses_id' => $storeHouseId,
            'grand_total' => $grandTotal,
            'created_by' => auth()->id(),

            /*
                معتمَدٌ عند الإصدار.

                البيعُ إثباتُ واقعٍ لا طلبُ إذن — والمخزونُ نقص والقيدُ
                كُتب قبل أن يصل التنفيذُ إلى هنا.
            */
            'status' => static::APPROVED,

            // الربطُ بالمستند: يُعرف الإذنُ من فاتورته لا بتخمين
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,

            // رقمُه هو، من إعدادات الترقيم — لا رقمُ الفاتورة
            'number' => $context['number'] ?? static::nextNumber($source),
            'currency' => $context['currency'] ?? null,
            'accounting_period_id' => $context['accounting_period_id'] ?? null,
            'client_id' => $context['client_id'] ?? null,
            'details' => $context['details'] ?? null,
        ], fn ($v) => $v !== null));

        foreach ($lines as $line) {
            WarehousePermitsProducts::create([
                'warehouse_permits_id' => $permit->id,
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'] ?? 0,
                'total' => $line['total'] ?? 0,
                'stock_before' => $line['stock_before'] ?? 0,
                'stock_after' => $line['stock_after'] ?? 0,
            ]);
        }

        return $permit;
    }

    /** إصدارُ إذنٍ بسطرٍ واحد — أشيعُ الحالات. */
    public static function issueLine(
        string $source,
        int $storeHouseId,
        array $line,
        array $context = [],
        $date = null
    ): WarehousePermits {
        return static::issue(
            $source,
            $storeHouseId,
            (float) ($line['total'] ?? 0),
            [$line],
            $context,
            $date
        );
    }

    /**
     * رقمُ الإذن التالي من إعدادات «الترقيم المتسلسل».
     *
     * `DocumentNumberService::next()` تعمل تحت قفلٍ داخل معاملة وتتخطّى
     * المحجوزَ فعلاً — فجهازا كاشيرٍ يبيعان معاً لا يأخذان رقماً واحداً.
     *
     * وإن تعذّر التوليدُ — قسمٌ غيرُ مضبوطٍ في شاشة الإعدادات — يبقى
     * `null` ولا يُخترع رقم: عمودٌ فارغٌ يُصلَح، ورقمٌ مخترعٌ يتصادم.
     */
    public static function nextNumber(string $source): ?string
    {
        $section = static::SECTION_OF[$source] ?? null;

        if (! $section) {
            return null;
        }

        try {
            return next_document_number($section);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** معرّفُ المصدر — يُنشأ إن لم يوجد، ويُحفظ لمدّة الطلب. */
    public static function sourceId(string $name): int
    {
        if (! array_key_exists($name, static::$sources)) {
            static::$sources[$name] = (int) PermissionSource::firstOrCreate(['name' => $name])->id;
        }

        return static::$sources[$name];
    }

    /** تُنسى الذاكرة — للاختبار. */
    public static function forget(): void
    {
        static::$sources = [];
    }
}

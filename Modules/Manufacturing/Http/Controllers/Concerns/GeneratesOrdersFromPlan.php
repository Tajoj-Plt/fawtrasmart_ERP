<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use App\Constants\planFeatures\StockPlanFeatures;
use App\Enums\BomLineType;
use App\Enums\ManufacturOrderStatus;
use App\Models\ManufacturOrders;
use App\Models\ManufacturOrdersItem;
use App\Models\ProductionMaterialsItem;
use App\Models\ProductionPlan;
use App\Models\ProductionPlanItem;
use App\Services\Fawtra\SubscriptionGate;
use App\Support\OperationLog;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Services\ReservationService;

/**
 * توليدُ أوامر التصنيع من بنود خطّة الإنتاج.
 *
 * ── المسألة ──────────────────────────────────────────────────────────
 *
 * شاشةُ إنشاء أمر التصنيع تطلب قائمةَ المواد ثمّ **لا تنسخ بنودَها**:
 * يختار المستخدم الوصفة ثمّ يُدخل موادّها صفّاً صفّاً بيده. فالوصفةُ
 * مرجعٌ لا مصدر، وتوليدُ عشرين أمراً من خطّةٍ كان يعني إعادةَ إدخال
 * عشرين وصفةٍ موجودةٍ أصلاً في القاعدة.
 *
 * هنا تُنسَخ الوصفةُ في الخادم: بندُ خطّةٍ واحد ⇦ أمرٌ كاملُ البنود.
 *
 * ── القياس ───────────────────────────────────────────────────────────
 *
 * الوصفةُ معرَّفةٌ لكميّةٍ أساسية (`production_materials.quantity`) —
 * «هذه مكوّناتُ ١٠٠ كرتونة». فكلُّ سطرٍ يُضرب في:
 *
 *     المعامل  =  الكميّة المخطّطة  ÷  الكميّة الأساسية للوصفة
 *
 * ويُضرب فيه **كلُّ ما يتبع حجمَ الدفعة** لا الموادُّ وحدها: وقتُ
 * التشغيل والهالك والمصروفُ «بناءً على الكمية» — فقياسُ الموادّ وحدها
 * يُنتج أمراً بموادّ دفعتين ووقتِ تشغيل دفعةٍ واحدة.
 *
 * **والمصروفُ «مبلغ ثابت» لا يُضرب**: ثابتٌ بنصّ نوعه، يُنسخ كما هو
 * أيّاً كانت الكميّة — انظر `copyExpenseLine`.
 *
 * والكميّةُ الأساسية الصفرية (وصفاتٌ قديمة قبل ضبط الحقل) تُبقي المعاملَ
 * ١: القسمةُ على صفر خطأٌ قاتل، ورفضُ التوليد يمنع العملَ لأجل حقلٍ لم
 * يكن مطلوباً يوم أُنشئت الوصفة.
 *
 * ── حدُّ الباقة ──────────────────────────────────────────────────────
 *
 * `manufacturing_orders` عدّادٌ شهري، وحارسُه الوحيد `plan.limit` على
 * مسار `orders/store` — بكميّة **واحد**. و`ManufacturOrders` ليس في
 * خريطة [\App\Observers\PlanLimitObserver]، فلا حارسَ على الصفّ نفسه.
 * فتوليدُ عشرين أمراً من مسارٍ آخر يمرّ فوق السقف بلا سؤال. ولذلك يُسأل
 * الحدُّ هنا **بعدد البنود المعلّقة كلّها** قبل إنشاء أوّل أمر: إمّا أن
 * تتّسع الباقةُ للدفعة كلّها وإمّا لا يُنشأ شيء — لا نصفُ خطّةٍ مولَّد
 * ونصفٌ عالق.
 *
 * ── عدمُ الازدواج ────────────────────────────────────────────────────
 *
 * البندُ الذي يحمل `manufactur_order_id` يُتخطّى. فالضغطُ مرّتين، أو
 * إضافةُ بندٍ إلى خطّةٍ وُلِّدت، لا يُنشئ نسخاً مكرّرة. والاستثناءُ
 * بندٌ **أُلغي أمرُه**: يُولَّد له أمرٌ جديد يحلّ محلّ الملغى في الربط.
 *
 * ── الحجز ────────────────────────────────────────────────────────────
 *
 * إن حملت الخطّةُ «مستودع المواد» وكان إعدادُ «حجز المواد» مُفعَّلاً،
 * حُجزت موادُّ كلِّ أمرٍ مولَّد فيه عبر [ReservationService] — كما يفعل
 * حفظُ الأمر من شاشته. والنقصُ لا يُسقط التوليد: يُبلَّغ به.
 *
 * ── ما تحتاجه من الصنف المضيف ────────────────────────────────────────
 *
 *   GeneratesDocumentNumbers   issueDocumentNumber()
 *   RecalculatesTotals         money() · lineTotal() · unitPrice() · safeDivide()
 *   UsesDocumentCurrency       newDocumentCurrencyAttributes()
 *   RespectsAccountingPeriods  accountingPeriodBlock()
 */
trait GeneratesOrdersFromPlan
{
    /** نوعُ تكلفة المصروف «بناءً على الكمية» — وحدَه يُقاس مع حجم الدفعة. */
    private const EXPENSE_COST_PER_QUANTITY = 2;

    /**
     * يُولّد أمرَ تصنيعٍ لكلّ بندٍ لم يُولَّد أمرُه بعد.
     *
     * يُستدعى **داخل معاملة مفتوحة**: إمّا أن تُنشأ الأوامرُ كلُّها وتُربط
     * ببنودها، وإمّا ألّا يُنشأ شيء.
     *
     * @return array{created:int, skipped:int, codes:array<string>}
     *
     * @throws \DomainException إذا كانت الخطّة لا تقبل التوليد
     */
    protected function generateOrdersForPlan(ProductionPlan $plan): array
    {
        if (! $plan->status->canGenerateOrders()) {
            throw new \DomainException(sprintf(
                'لا يمكن توليد أوامر من خطّة «%s» — اعتمد الخطّة أولاً.',
                $plan->status->label()
            ));
        }

        /*
            المسؤولُ شرطٌ لا تجميل: `manufactur_orders.employee_id`
            إلزاميّ. والخانةُ تُفرَّغ وحدَها حين يُحذف سجلّ الموظّف
            (`nullOnDelete`)، فتُكتشَف هنا لا عند الإدراج.
        */
        if (! $plan->employee_id) {
            throw new \DomainException(
                'الخطّة بلا مسؤول — عيِّن مسؤولاً قبل توليد الأوامر '
                .'(الأرجح أنّ سجلّ الموظّف السابق حُذف).'
            );
        }

        /*
            الفترةُ المحاسبية — كما يفحصها `ManufacturingOrdersController::store`.

            الأمرُ يُرحّل قيوده بتاريخ `from_date`، والأوامرُ المولَّدة
            تأخذ فترةَ الخطّة. فخطّةٌ في شهرٍ أقفله المحاسب تُنشئ أوامرَ
            يمنعها إنشاؤها اليدويّ.
        */
        if ($block = $this->accountingPeriodBlock($plan->from_date)) {
            throw new \DomainException($block);
        }

        $items = $plan->items()->with(['productionMaterial', 'product', 'manufacturOrder'])->get();

        // ما لم يُولَّد قطّ، وما أُلغي أمرُه — انظر `ProductionPlanItem::isGenerable`
        $pending = $items->filter(fn (ProductionPlanItem $item) => $item->isGenerable());

        /*
            الوصفةُ وصفةُ المنتج نفسِه.

            الشاشةُ تُصفّي القائمة والحفظُ يتحقّق، وهذا الحارسُ لما حُفظ
            قبلهما: أمرٌ يُنتج «خبزاً» بوصفة «كعك» يصرف موادَّ الكعك
            ويُدخل خبزاً بتكلفته.
        */
        foreach ($pending as $item) {
            $bomProductId = $item->productionMaterial->product_id ?? null;

            if ((int) $bomProductId !== (int) $item->product_id) {
                throw new \DomainException(sprintf(
                    'بند «%s»: قائمة المواد «%s» تخصّ منتجاً آخر — صحِّح البند من شاشة التعديل قبل توليد الأوامر.',
                    $item->product->name ?? '#'.$item->product_id,
                    $item->productionMaterial->name ?? '—'
                ));
            }
        }

        if ($pending->isNotEmpty()) {
            $quota = app(SubscriptionGate::class)
                ->checkLive(StockPlanFeatures::LIMIT_MANUFACTURING_ORDERS, $pending->count());

            if (! $quota['allowed']) {
                throw new \DomainException((string) $quota['reason']);
            }
        }

        $codes = [];
        $reserved = [];
        $shortages = [];

        foreach ($pending as $item) {
            $order = $this->createOrderFromPlanItem($plan, $item);
            $codes[] = $order->code;

            // `null` = لم يُحاوَل الحجز (بلا مستودع، أو الإعداد مُطفأ، أو بلا مواد)
            $orderShortages = $this->reservePlanOrderMaterials($order);

            if ($orderShortages === []) {
                $reserved[] = $order->code;
            } elseif ($orderShortages !== null) {
                $shortages[$order->code] = $orderShortages;
            }
        }

        return [
            'created' => count($codes),
            'skipped' => $items->count() - $pending->count(),
            'codes' => $codes,
            'reserved' => $reserved,
            'shortages' => $shortages,
        ];
    }

    /**
     * حجزُ مواد الأمر المولَّد في مستودع الخطّة.
     *
     * الأمرُ المُنشأ من شاشته يحجز موادَّه عند الحفظ
     * ([ReservesOrderMaterials])، والمولَّدُ من الخطّة كان يُنشأ بمستودعه
     * ولا يحجز — فيَعِد أمران بالكميّة نفسها. هنا نفسُ الحجز ونفسُ
     * القاعدة: النقصُ **لا يُسقط التوليد**، يُبلَّغ به المستخدم ويُحجز
     * لاحقاً من شاشة الأمر.
     *
     * الحجزُ بالمنتج لا بالسطر: وصفةٌ تذكر المادّةَ نفسَها في مرحلتين
     * تُقاس مجموعةً، وإلّا مرّ كلُّ سطرٍ وحدَه والمجموعُ فوق المتاح.
     *
     * @return array<int, array<string, mixed>>|null النواقص، `[]` إن حُجز، `null` إن لم يُحاوَل
     */
    private function reservePlanOrderMaterials(ManufacturOrders $order): ?array
    {
        $warehouseId = (int) $order->storehouse_id;
        $reservations = app(ReservationService::class);

        if ($warehouseId <= 0 || ! $reservations->enabled()) {
            return null;
        }

        $lines = $order->manufacturOrdersItem()
            ->whereNotNull('raw_product_id')
            ->where('raw_quantity', '>', 0)
            ->get()
            ->groupBy('raw_product_id')
            ->map(fn ($group, $productId) => [
                'product_id' => (int) $productId,
                'quantity' => round((float) $group->sum('raw_quantity'), 4),
            ])
            ->values()
            ->all();

        if ($lines === []) {
            return null;
        }

        return $reservations->reserve($order, $warehouseId, $lines);
    }

    /**
     * رسالةُ ما بعد التوليد عن الحجز — `null` إن لم يكن ما يُقال.
     *
     * @param  array{reserved:array<string>, shortages:array<string, array>}  $result
     */
    protected function planReservationWarning(ProductionPlan $plan, array $result): ?string
    {
        if (! app(ReservationService::class)->enabled()) {
            return null;
        }

        if (! $plan->storehouse_id) {
            return 'لم تُحجز مواد الأوامر — الخطّة بلا «مستودع المواد». '
                .'اختر المستودع من شاشة كل أمر لتُحجز موادُّه.';
        }

        if ($result['shortages'] === []) {
            return null;
        }

        $message = "وُلِّدت الأوامر، ولم تُحجز مواد بعضها — المتاح لا يكفي:\n";

        foreach ($result['shortages'] as $code => $shortages) {
            $message .= "\nأمر #{$code}:\n";

            foreach ($shortages as $shortage) {
                $message .= sprintf(
                    "• %s: المطلوب %s، المتاح %s، النقص %s\n",
                    $shortage['name'],
                    $this->planQuantityText((float) $shortage['required']),
                    $this->planQuantityText((float) $shortage['available']),
                    $this->planQuantityText((float) $shortage['shortage'])
                );
            }
        }

        return $message."\nتُحجز المواد بعد توفّرها بإعادة حفظ الأمر من شاشته.";
    }

    private function planQuantityText(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    /** أمرُ تصنيعٍ واحد عن بندٍ واحد، ببنودٍ منسوخةٍ من وصفته. */
    private function createOrderFromPlanItem(ProductionPlan $plan, ProductionPlanItem $item): ManufacturOrders
    {
        $order = ManufacturOrders::create([
            // اسمٌ يقود إلى مصدره: «خطة مارس — خبز»
            'name' => $plan->name.' — '.($item->product->name ?? 'بند #'.$item->id),

            /*
                الرقمُ من `issueDocumentNumber` — نفسُ ما يفعله
                `ManufacturingOrdersController::store`: قفلٌ داخل معاملة
                وتخطٍّ للمحجوز، فتوليدُ عشرين أمراً لا يصطدم بأمرٍ يُنشئه
                مستخدمٌ آخر في اللحظة نفسها.
            */
            'code' => $this->issueDocumentNumber(ManufacturingSources::SECTION_ORDERS),

            /*
                نصّاً بصيغة التاريخ لا كائنَ Carbon: العمودان `DATE`، و
                `$plan->from_date` مُحوَّلٌ بـ`$casts`؛ فتمريرُه كما هو
                يُدرج 'Y-m-d H:i:s' — تقتطعه MySQL صامتةً وتحفظه SQLite
                كما هو، فيختلف ما في القاعدة باختلاف المحرّك.
            */
            'from_date' => optional($plan->from_date)->toDateString(),
            'to_date' => optional($plan->to_date)->toDateString(),

            'employee_id' => $plan->employee_id,
            'client_id' => $plan->client_id,
            'account_id' => $plan->account_id,
            'storehouse_id' => $plan->storehouse_id ?: null,

            'product_id' => $item->product_id,
            'quantity' => $item->planned_quantity,
            'production_material_id' => $item->production_material_id,
            'production_path_id' => $item->production_path_id,

            /*
                فرعُ الخطّة لا الفرعُ النشط.

                الخطّةُ خُتمت بفرعها يوم أُنشئت، والأمرُ المولَّد منها
                ينتمي إليه — لا إلى الفرع الذي صادف أن وقف عليه من ضغط
                الزرّ. وإلّا تفرّقت أوامرُ خطّةٍ واحدة على فروعٍ عدّة
                بحسب من ولّدها ومتى.
            */
            'branch_id' => $plan->branch_id,

            'status' => ManufacturOrderStatus::Pending,

            // يُشتقّ من البنود بعد نسخها
            'last_total_cost' => 0,

            'created_by' => auth()->id(),
        ] + $this->newDocumentCurrencyAttributes());

        $order->update([
            'last_total_cost' => $this->copyBomLinesToOrder($order, $item),
        ]);

        $item->update(['manufactur_order_id' => $order->id]);

        // في خطّ الأمر الزمنيّ: من أين جاء
        OperationLog::record(
            'created',
            ManufacturingSources::LOG_ORDER,
            $order->id,
            'تم إنشاء أمر تصنيع **'.$order->name.'** برقم '.$order->code
                .' من خطة الإنتاج '.$plan->code,
        );

        return $order;
    }

    /**
     * ينسخ بنودَ الوصفة إلى الأمر مقيسةً، ويُرجع إجماليَّ التكلفة.
     *
     * قاعدةُ الإجماليّ — واحدةٌ في الوحدة كلِّها:
     *
     *     الإجمالي = المواد + المصروفات + التشغيل − قيمة الهالك
     *
     * قيمةُ الهالك تُطرح: ما يخرج من العملية بقيمةٍ يُستردّ من تكلفتها،
     * فلا يُحمَّل المنتجُ التامّ به. ولا ينزل الإجماليّ تحت الصفر.
     */
    private function copyBomLinesToOrder(ManufacturOrders $order, ProductionPlanItem $item): float
    {
        $factor = $this->batchFactor($item);
        $lines = ProductionMaterialsItem::where('production_material_id', $item->production_material_id)->get();

        $cost = 0.0;
        $waste = 0.0;

        foreach ($lines as $line) {
            $type = $this->lineTypeOf($line);

            if ($type === null) {
                continue;
            }

            if ($type === BomLineType::Waste) {
                $waste += $this->copyWasteLine($order, $line, $factor);

                continue;
            }

            $cost += match ($type) {
                BomLineType::Raw => $this->copyRawLine($order, $line, $factor),
                BomLineType::Expense => $this->copyExpenseLine($order, $line, $factor),
                BomLineType::Operation => $this->copyOperationLine($order, $line, $factor),
            };
        }

        return $this->money(max(0.0, $cost - $waste));
    }

    /** الكميّة المخطّطة ÷ الكميّة الأساسية للوصفة — و١ إن غابت. */
    private function batchFactor(ProductionPlanItem $item): float
    {
        $base = (float) ($item->productionMaterial->quantity ?? 0);

        return $base > 0
            ? $this->safeDivide((float) $item->planned_quantity, $base, 1.0)
            : 1.0;
    }

    /**
     * نوعُ السطر — من `line_type` إن كُتب، وإلّا من العمود المرجعي.
     *
     * الاستنتاجُ احتياطٌ لا أصل: هجرةُ `2026_09_02_000003` ملأت العمودَ
     * بأثرٍ رجعي وأبلغت عن صفوفٍ «يتيمة» لم يُعرف نوعُها. تلك تسقط من
     * النسخ (`null`) بدل أن تُنسخ نوعاً خاطئاً.
     */
    private function lineTypeOf(ProductionMaterialsItem $line): ?BomLineType
    {
        if ($line->line_type instanceof BomLineType) {
            return $line->line_type;
        }

        foreach (BomLineType::cases() as $case) {
            if (! empty($line->{$case->discriminatorColumn()})) {
                return $case;
            }
        }

        return null;
    }

    /** مادّة خام — الكميّة تُقاس، والسعر لا. */
    private function copyRawLine(ManufacturOrders $order, ProductionMaterialsItem $line, float $factor): float
    {
        $quantity = (float) $line->raw_quantity * $factor;
        $unitPrice = (float) $line->raw_unit_price;
        $total = $this->lineTotal($quantity, $unitPrice);

        ManufacturOrdersItem::create([
            'manufactur_order_id' => $order->id,
            'line_type' => BomLineType::Raw,
            'raw_product_id' => $line->raw_product_id,
            'raw_production_stage_id' => $line->raw_production_stage_id ?: null,
            'raw_unit_id' => $line->raw_unit_id ?: null,
            'raw_conversion_factor' => (float) ($line->raw_conversion_factor ?: 1),
            'raw_unit_price' => $this->unitPrice($unitPrice),
            'raw_quantity' => $quantity,
            'raw_total' => $total,
        ]);

        return $total;
    }

    /**
     * مصروف — يُقاس بحسب نوع تكلفته:
     *
     *  · ٢ «بناءً على الكمية»  ⟵ يُضرب في المعامل: مبلغُه لدفعة الوصفة.
     *  · ١ «مبلغ ثابت» (وما لم يُحدَّد نوعُه) ⟵ يُنسخ كما هو: إيجارُ
     *    الفرن لا يتضاعف بمضاعفة العجين، ونصفُ الدفعة لا يُنصِّفه.
     */
    private function copyExpenseLine(ManufacturOrders $order, ProductionMaterialsItem $line, float $factor): float
    {
        $scales = (int) $line->expenses_cost_type === self::EXPENSE_COST_PER_QUANTITY;
        $amount = $this->money((float) $line->expenses_price * ($scales ? $factor : 1.0));

        ManufacturOrdersItem::create([
            'manufactur_order_id' => $order->id,
            'line_type' => BomLineType::Expense,
            'expenses_account_id' => $line->expenses_account_id,
            'expenses_cost_type' => $line->expenses_cost_type ?: null,
            'expenses_production_stage_id' => $line->expenses_production_stage_id ?: null,
            'expenses_price' => $amount,
            'expenses_description' => $line->expenses_description,
            'expenses_total' => $amount,
        ]);

        return $amount;
    }

    /** تشغيلٌ على محطّة — الوقتُ يُقاس، وتكلفةُ الساعة لا. */
    private function copyOperationLine(ManufacturOrders $order, ProductionMaterialsItem $line, float $factor): float
    {
        $time = (float) $line->operating_time * $factor;
        $hourly = (float) $line->manu_total_cost;
        $total = $this->lineTotal($time, $hourly);

        ManufacturOrdersItem::create([
            'manufactur_order_id' => $order->id,
            'line_type' => BomLineType::Operation,
            'workstation_id' => $line->workstation_id,
            'operating_time' => $time,
            'manu_production_stage_id' => $line->manu_production_stage_id ?: null,
            'manu_cost_type' => $line->manu_cost_type ?: null,
            'manu_total_cost' => $this->unitPrice($hourly),
            'manu_description' => $line->manu_description,
            'manu_total' => $total,
        ]);

        return $total;
    }

    /** هالكٌ أو ناتجٌ ثانوي — يُنسخ مقيساً، وتُرجَع قيمتُه لتُطرح من تكلفة الأمر. */
    private function copyWasteLine(ManufacturOrders $order, ProductionMaterialsItem $line, float $factor): float
    {
        $quantity = (float) $line->end_life_quantity * $factor;
        $unitPrice = (float) $line->end_life_unit_price;
        $total = $this->lineTotal($quantity, $unitPrice);

        ManufacturOrdersItem::create([
            'manufactur_order_id' => $order->id,
            'line_type' => BomLineType::Waste,
            'end_life_product_id' => $line->end_life_product_id,
            'end_life_production_stage_id' => $line->end_life_production_stage_id ?: null,
            'end_life_unit_price' => $this->unitPrice($unitPrice),
            'end_life_quantity' => $quantity,
            'end_life_total' => $total,
        ]);

        return $total;
    }

    /**
     * تُعيد ضبطَ حالة الخطّة على ما تقوله أوامرُها فعلاً.
     *
     * الحالةُ بعد الاعتماد **مشتقّةٌ لا مُعلَنة**، ومصدرُها حالاتُ الأوامر
     * لا مجرّدُ وجودها: القاعدةُ كلُّها في
     * [ProductionPlan::syncStatusFromOrders] كي تستدعيها دورةُ حياة الأمر
     * أيضاً. تُستدعى هنا بعد كل توليد وعند عرض الخطّة وسردها.
     */
    protected function syncPlanStatusFromItems(ProductionPlan $plan): void
    {
        $plan->syncStatusFromOrders();
    }
}

<?php

namespace Modules\Manufacturing\Tests\Feature;

use App\Enums\BomLineType;
use App\Enums\ManufacturOrderStatus;
use App\Enums\ProductionPlanStatus;
use App\Models\ManufacturingGeneralSetting;
use App\Models\ManufacturOrders;
use App\Models\ManufacturOrdersItem;
use App\Models\ProductionMaterials;
use App\Models\ProductionMaterialsItem;
use App\Models\ProductionPlan;
use App\Models\StockReservation;
use App\Services\Fawtra\SubscriptionGate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Http\Controllers\Concerns\GeneratesOrdersFromPlan;
use Modules\Manufacturing\Http\Controllers\Concerns\RecalculatesTotals;
use Modules\Manufacturing\Services\ReservationService;
use Modules\Manufacturing\Tests\ManufacturingTestCase;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

/*
    معيارُ قبول خطط الإنتاج.

    الخطّةُ نيّةٌ لا حركة، وكلُّ أثرها عبر الأوامر المولَّدة منها. فما
    يُختبَر هنا أربعة:

      ١) **متى يجوز التوليد** — بعد الاعتماد، وبمسؤول، وفي حدود الباقة.
      ٢) **ماذا يُنسَخ** — بنودُ الوصفة مقيسةً على الكميّة المخطّطة.
      ٣) **ألّا يزدوج** — البندُ الذي وُلِّد أمرُه لا يُولَّد ثانيةً.
      ٤) **أنّ الحالة مشتقّة** — تتبع حالاتِ الأوامر بعد الاعتماد:
         «قيد التنفيذ» بعد التوليد، و«مكتملة» حين يكتمل كلُّ أمرٍ قائم.
      ٥) **الحجز** — تُحجز موادُّ الأمر المولَّد في مستودع الخطّة، والنقصُ
         يُبلَّغ ولا يمنع.

    وما لا يُختبَر هنا: المخزونُ والقيود — تخضع لها الأوامرُ المولَّدة
    في `OrderLifecycleTest`.
*/
class ProductionPlanGenerationTest extends ManufacturingTestCase
{
    /*
        السمةُ تُستدعى من متحكّم. نستعملها في صنفٍ مجهول كي يفشل الاختبارُ
        عند منطق التوليد لا عند صلاحيةٍ ناقصة أو جلسةٍ غير مهيّأة.
    */
    private object $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new class
        {
            use GeneratesDocumentNumbers,
                GeneratesOrdersFromPlan,
                RecalculatesTotals,
                RespectsAccountingPeriods,
                UsesDocumentCurrency;

            public function generate(ProductionPlan $plan): array
            {
                return DB::transaction(fn () => $this->generateOrdersForPlan($plan));
            }

            public function sync(ProductionPlan $plan): void
            {
                $this->syncPlanStatusFromItems($plan);
            }
        };
    }

    // ─────────────────────────────────────────────────────────────────
    // متى يجوز التوليد
    // ─────────────────────────────────────────────────────────────────

    public function test_it_refuses_to_generate_from_a_draft_plan(): void
    {
        $plan = $this->planWithOneItem(['status' => ProductionPlanStatus::Draft]);

        $this->expectException(\DomainException::class);

        $this->generator->generate($plan);
    }

    public function test_it_refuses_when_the_plan_has_no_responsible_employee(): void
    {
        $plan = $this->planWithOneItem();
        Model::unguarded(fn () => $plan->update(['employee_id' => null]));

        $this->expectException(\DomainException::class);

        $this->generator->generate($plan->fresh());
    }

    /*
        `ManufacturOrders` ليس في `PlanLimitObserver`، وحارسُ المسار يسأل
        عن أمرٍ واحد. فخطّةٌ بثلاثة بنود وباقةٌ بقي فيها أمران يجب أن
        تُردّ **كلُّها** — لا أن يُولَّد أمران ويعلق الثالث.
    */
    public function test_it_refuses_a_batch_larger_than_the_remaining_order_quota(): void
    {
        $plan = $this->planWithOneItem();
        foreach (range(1, 2) as $_) {
            $this->fixtures->planItem($plan, $this->fixtures->bom($this->fixtures->product()), 1);
        }

        $this->app->instance(SubscriptionGate::class, new class extends SubscriptionGate
        {
            public function checkLive(string $feature, int $quantity = 1): array
            {
                return $quantity <= 2
                    ? ['allowed' => true, 'reason' => null, 'remaining' => 2]
                    : ['allowed' => false, 'reason' => 'الباقة لا تتّسع', 'remaining' => 2];
            }
        });

        $before = ManufacturOrders::count();

        try {
            $this->generator->generate($plan->fresh());
            $this->fail('وُلِّدت دفعةٌ أكبر من المتبقّي في الباقة');
        } catch (\DomainException $e) {
            $this->assertSame('الباقة لا تتّسع', $e->getMessage());
        }

        $this->assertSame($before, ManufacturOrders::count(), 'أُنشئ بعضُ الأوامر قبل الرفض');
    }

    public function test_it_generates_one_order_per_pending_item(): void
    {
        $plan = $this->planWithOneItem();
        $this->fixtures->planItem($plan, $this->fixtures->bom($this->fixtures->product()), 3);

        $before = ManufacturOrders::count();
        $result = $this->generator->generate($plan->fresh());

        $this->assertSame(2, $result['created']);
        $this->assertSame($before + 2, ManufacturOrders::count());
        $this->assertCount(0, $plan->fresh()->pendingItems()->get());
    }

    // ─────────────────────────────────────────────────────────────────
    // ماذا يُنسَخ
    // ─────────────────────────────────────────────────────────────────

    /*
        الوصفةُ لعشر وحدات وفيها ٥ كجم بسعر ٢٫٥، والخطّةُ تطلب ٢٥:

            المعامل  = ٢٥ ÷ ١٠      = ٢٫٥
            الكمية   = ٥ × ٢٫٥      = ١٢٫٥
            الإجمالي = ١٢٫٥ × ٢٫٥   = ٣١٫٢٥
    */
    public function test_it_scales_bom_lines_by_planned_over_batch_quantity(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product(), ['quantity' => 10]);
        $this->fixtures->bomRawLine($bom, $this->fixtures->product(), 5, 2.5);

        $plan = $this->fixtures->plan();
        $this->fixtures->planItem($plan, $bom, 25);

        $this->generator->generate($plan->fresh());

        $order = ManufacturOrders::latest('id')->first();
        $line = ManufacturOrdersItem::where('manufactur_order_id', $order->id)
            ->where('line_type', BomLineType::Raw)
            ->firstOrFail();

        $this->assertEqualsWithDelta(12.5, (float) $line->raw_quantity, 0.0001);
        $this->assertEqualsWithDelta(2.5, (float) $line->raw_unit_price, 0.0001);
        $this->assertEqualsWithDelta(31.25, (float) $line->raw_total, 0.01);
        $this->assertEqualsWithDelta(31.25, (float) $order->last_total_cost, 0.01);
    }

    /* وصفةٌ بكميّةٍ أساسية صفر — تُنسخ كما هي، لا تُقسَم على صفر. */
    public function test_it_falls_back_to_factor_one_when_the_bom_has_no_batch_quantity(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product(), ['quantity' => 0]);
        $this->fixtures->bomRawLine($bom, $this->fixtures->product(), 4, 3);

        $plan = $this->fixtures->plan();
        $this->fixtures->planItem($plan, $bom, 100);

        $this->generator->generate($plan->fresh());

        $line = ManufacturOrdersItem::where('manufactur_order_id', ManufacturOrders::max('id'))
            ->where('line_type', BomLineType::Raw)
            ->firstOrFail();

        $this->assertEqualsWithDelta(4, (float) $line->raw_quantity, 0.0001);
    }

    /*
        الوصفةُ لعشر وحدات، والخطّةُ تطلب خمساً (المعامل ٠٫٥):

            خام      ٤ × ٠٫٥ × ١٠           = ٢٠
            مصروفٌ ثابت (النوع ١)   ١٠٠     = ١٠٠   ← لا يُنصَّف
            مصروفٌ بالكمية (النوع ٢) ٦٠ × ٠٫٥ = ٣٠
            هالك     ٢ × ٠٫٥ × ٣            = ٣    ← يُطرح

            الإجمالي = ٢٠ + ١٠٠ + ٣٠ − ٣     = ١٤٧
    */
    public function test_fixed_expenses_stay_fixed_and_waste_value_is_deducted(): void
    {
        $bom = $this->fixtures->bom($this->fixtures->product(), ['quantity' => 10]);
        $this->fixtures->bomRawLine($bom, $this->fixtures->product(), 4, 10);
        $this->bomExpenseLine($bom, costType: 1, amount: 100);
        $this->bomExpenseLine($bom, costType: 2, amount: 60);
        $this->bomWasteLine($bom, quantity: 2, unitPrice: 3);

        $plan = $this->fixtures->plan();
        $this->fixtures->planItem($plan, $bom, 5);

        $this->generator->generate($plan->fresh());

        $order = ManufacturOrders::latest('id')->first();
        $lines = ManufacturOrdersItem::where('manufactur_order_id', $order->id)->get();

        // بالحساب لا بالنوع وحده: `expenses_cost_type` افتراضُه ١ على كل سطر
        $expenses = $lines->filter(fn ($line) => $line->expenses_account_id !== null);
        $fixed = $expenses->first(fn ($line) => (int) $line->expenses_cost_type === 1);
        $perQuantity = $expenses->first(fn ($line) => (int) $line->expenses_cost_type === 2);
        $raw = $lines->first(fn ($line) => $line->raw_product_id !== null);
        $waste = $lines->first(fn ($line) => $line->end_life_product_id !== null);

        $this->assertEqualsWithDelta(100, (float) $fixed->expenses_total, 0.01, 'المصروفُ الثابت قيس مع الكميّة');
        $this->assertEqualsWithDelta(30, (float) $perQuantity->expenses_total, 0.01);
        $this->assertEqualsWithDelta(2, (float) $raw->raw_quantity, 0.0001);
        $this->assertEqualsWithDelta(1, (float) $waste->end_life_quantity, 0.0001);
        $this->assertEqualsWithDelta(147, (float) $order->last_total_cost, 0.01);
    }

    /* وصفةُ منتجٍ آخر — يُرفض التوليد ولا يُنشأ أمر. */
    public function test_it_refuses_an_item_whose_bom_belongs_to_another_product(): void
    {
        $plan = $this->fixtures->plan();
        $this->fixtures->planItem($plan, $this->fixtures->bom($this->fixtures->product()), 3, [
            'product_id' => $this->fixtures->product()->id,
        ]);

        $before = ManufacturOrders::count();

        try {
            $this->generator->generate($plan->fresh());
            $this->fail('وُلِّد أمرٌ بوصفة منتجٍ آخر');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('تخصّ منتجاً آخر', $e->getMessage());
        }

        $this->assertSame($before, ManufacturOrders::count());
    }

    // ─────────────────────────────────────────────────────────────────
    // الحجز
    // ─────────────────────────────────────────────────────────────────

    /* خطّةٌ بمستودع ⟵ تُحجز موادُّ الأمر المولَّد فيه، مقيسةً. */
    public function test_it_reserves_the_generated_order_materials_in_the_plan_warehouse(): void
    {
        ManufacturingGeneralSetting::updateOrCreate(['id' => 1], ['reserve_materials_on_order' => true]);

        [$plan, $flour, $warehouse] = $this->planWithWarehouse(onHand: 100);

        $result = $this->generator->generate($plan->fresh());
        $order = ManufacturOrders::latest('id')->first();

        $this->assertSame((int) $warehouse->id, (int) $order->storehouse_id);
        $this->assertSame([$order->code], $result['reserved']);
        $this->assertSame([], $result['shortages']);
        // الوصفة: ٢ لكل وحدة، والخطّة ١٠ ⟵ ٢٠
        $this->assertSame(20.0, app(ReservationService::class)->reserved($flour->id, $warehouse->id));
    }

    /* النقصُ لا يُسقط التوليد: الأمرُ يُنشأ، ولا يُحجز له شيء، ويُبلَّغ بالنقص. */
    public function test_a_shortage_is_reported_without_blocking_generation(): void
    {
        ManufacturingGeneralSetting::updateOrCreate(['id' => 1], ['reserve_materials_on_order' => true]);

        [$plan, $flour, $warehouse] = $this->planWithWarehouse(onHand: 5);

        $result = $this->generator->generate($plan->fresh());
        $order = ManufacturOrders::latest('id')->first();

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['reserved']);
        $this->assertArrayHasKey($order->code, $result['shortages']);
        $this->assertEqualsWithDelta(15, $result['shortages'][$order->code][0]['shortage'], 0.0001);
        $this->assertSame(0.0, app(ReservationService::class)->reserved($flour->id, $warehouse->id));
    }

    public function test_it_reserves_nothing_when_the_setting_is_off_or_the_plan_has_no_warehouse(): void
    {
        ManufacturingGeneralSetting::updateOrCreate(['id' => 1], ['reserve_materials_on_order' => false]);

        [$plan] = $this->planWithWarehouse(onHand: 100);
        $result = $this->generator->generate($plan->fresh());

        $this->assertSame([], $result['reserved']);
        $this->assertSame(0, StockReservation::where('reference_id', ManufacturOrders::max('id'))->count());

        ManufacturingGeneralSetting::updateOrCreate(['id' => 1], ['reserve_materials_on_order' => true]);

        $result = $this->generator->generate($this->planWithOneItem()->fresh());

        $this->assertSame([], $result['reserved']);
        $this->assertSame([], $result['shortages']);
    }

    public function test_the_generated_order_carries_the_plan_header_and_branch(): void
    {
        $plan = $this->planWithOneItem();

        $this->generator->generate($plan->fresh());

        $order = ManufacturOrders::latest('id')->first();

        $this->assertSame(ManufacturOrderStatus::Pending, $order->status);
        $this->assertSame((int) $plan->employee_id, (int) $order->employee_id);
        $this->assertSame($plan->from_date->toDateString(), (string) $order->from_date);
        $this->assertSame($plan->branch_id, $order->branch_id);
        $this->assertNotEmpty($order->code, 'الأمر بلا رقم مستند');
        $this->assertNotEmpty($order->currency, 'الأمر بلا عملة');
    }

    // ─────────────────────────────────────────────────────────────────
    // ألّا يزدوج
    // ─────────────────────────────────────────────────────────────────

    public function test_regenerating_creates_nothing_and_skips_generated_items(): void
    {
        $plan = $this->planWithOneItem();

        $this->generator->generate($plan->fresh());
        $count = ManufacturOrders::count();

        $again = $this->generator->generate($plan->fresh());

        $this->assertSame(0, $again['created']);
        $this->assertSame(1, $again['skipped']);
        $this->assertSame($count, ManufacturOrders::count());
    }

    public function test_deleting_a_generated_order_frees_its_item(): void
    {
        $plan = $this->planWithOneItem();
        $this->generator->generate($plan->fresh());

        $this->deleteLatestOrder();

        $this->assertCount(1, $plan->fresh()->pendingItems()->get());
    }

    // ─────────────────────────────────────────────────────────────────
    // الحالةُ مشتقّة
    // ─────────────────────────────────────────────────────────────────

    /*
        التوليدُ وحدَه لا يُكمل الخطّة: الأوامرُ ما زالت «جديدة». كانت
        الخطّةُ تصير «مكتملة» بمجرّد كتابة أوامرها ولم يُنتَج شيء.
    */
    public function test_the_plan_is_in_progress_after_every_item_is_generated(): void
    {
        $plan = $this->planWithOneItem();

        $this->generator->generate($plan->fresh());
        $this->generator->sync($plan->fresh());

        $this->assertSame(ProductionPlanStatus::InProgress, $plan->fresh()->status);
    }

    /* تكتمل حين يكتمل أو يُغلق كلُّ أمرٍ قائم — لا قبل ذلك. */
    public function test_the_plan_becomes_completed_only_when_every_order_is_finished(): void
    {
        $plan = $this->planWithOneItem();
        $this->fixtures->planItem($plan, $this->fixtures->bom($this->fixtures->product()), 5);

        $this->generator->generate($plan->fresh());
        [$first, $second] = $this->orderIdsOf($plan);

        $this->setOrderStatus($first, ManufacturOrderStatus::Completed);
        $this->assertSame(ProductionPlanStatus::InProgress, $plan->fresh()->syncStatusFromOrders());

        $this->setOrderStatus($second, ManufacturOrderStatus::Closed);
        $this->assertSame(ProductionPlanStatus::Completed, $plan->fresh()->syncStatusFromOrders());
    }

    /* التراجعُ عن إتمام أمرٍ يُعيد الخطّة «قيد التنفيذ» — عبر نداء دورة حياة الأمر. */
    public function test_undoing_an_order_completion_reopens_the_plan(): void
    {
        $plan = $this->planWithOneItem();

        $this->generator->generate($plan->fresh());
        [$orderId] = $this->orderIdsOf($plan);

        $this->setOrderStatus($orderId, ManufacturOrderStatus::Completed);
        ProductionPlan::syncStatusForOrder($orderId);
        $this->assertSame(ProductionPlanStatus::Completed, $plan->fresh()->status);

        $this->setOrderStatus($orderId, ManufacturOrderStatus::MaterialsReceived);
        ProductionPlan::syncStatusForOrder($orderId);
        $this->assertSame(ProductionPlanStatus::InProgress, $plan->fresh()->status);
    }

    /*
        الأمرُ الملغى يُتجاهَل ما دام للخطّة أمرٌ قائم غيرُه: لا يمنع
        اكتمالَها، وبندُه يعود قابلاً للتوليد.
    */
    public function test_a_cancelled_order_is_ignored_and_frees_its_item(): void
    {
        $plan = $this->planWithOneItem();
        $this->fixtures->planItem($plan, $this->fixtures->bom($this->fixtures->product()), 5);

        $this->generator->generate($plan->fresh());
        [$first, $second] = $this->orderIdsOf($plan);

        $this->setOrderStatus($first, ManufacturOrderStatus::Cancelled);
        $this->assertSame(ProductionPlanStatus::InProgress, $plan->fresh()->syncStatusFromOrders());
        $this->assertCount(1, $plan->fresh()->pendingItems()->get(), 'بندُ الأمر الملغى لم يعد قابلاً للتوليد');

        $this->setOrderStatus($second, ManufacturOrderStatus::Completed);
        $this->assertSame(ProductionPlanStatus::Completed, $plan->fresh()->syncStatusFromOrders());
    }

    /* وحين تُلغى الأوامرُ كلُّها لا يبقى أمرٌ قائم — فالخطّةُ «معتمدة» ويُولَّد من جديد. */
    public function test_it_returns_to_approved_and_regenerates_when_all_orders_are_cancelled(): void
    {
        $plan = $this->planWithOneItem();

        $this->generator->generate($plan->fresh());
        [$cancelled] = $this->orderIdsOf($plan);

        $this->setOrderStatus($cancelled, ManufacturOrderStatus::Cancelled);
        $this->assertSame(ProductionPlanStatus::Approved, $plan->fresh()->syncStatusFromOrders());

        $again = $this->generator->generate($plan->fresh());

        $this->assertSame(1, $again['created']);
        $this->assertNotSame($cancelled, $this->orderIdsOf($plan)[0], 'البندُ ما زال مربوطاً بالأمر الملغى');
    }

    public function test_it_returns_to_in_progress_when_one_of_several_orders_is_deleted(): void
    {
        $plan = $this->planWithOneItem();
        $this->fixtures->planItem($plan, $this->fixtures->bom($this->fixtures->product()), 5);

        $this->generator->generate($plan->fresh());
        $this->generator->sync($plan->fresh());

        $this->deleteLatestOrder();
        $this->generator->sync($plan->fresh());

        $this->assertSame(ProductionPlanStatus::InProgress, $plan->fresh()->status);
    }

    /* وحين تُحذف كلُّها لا يبقى مولَّد — فالحالةُ الصادقة «معتمدة». */
    public function test_it_returns_to_approved_when_all_orders_are_deleted(): void
    {
        $plan = $this->planWithOneItem();

        $this->generator->generate($plan->fresh());
        $this->generator->sync($plan->fresh());

        $this->deleteLatestOrder();
        $this->generator->sync($plan->fresh());

        $this->assertSame(ProductionPlanStatus::Approved, $plan->fresh()->status);
    }

    public function test_draft_and_cancelled_plans_keep_their_status(): void
    {
        foreach ([ProductionPlanStatus::Draft, ProductionPlanStatus::Cancelled] as $status) {
            $plan = $this->planWithOneItem(['status' => $status]);

            $this->generator->sync($plan->fresh());

            $this->assertSame($status, $plan->fresh()->status);
        }
    }

    public function test_a_plan_reports_its_generated_orders(): void
    {
        $plan = $this->planWithOneItem();

        $this->assertFalse($plan->hasGeneratedOrders());

        $this->generator->generate($plan->fresh());

        $this->assertTrue($plan->fresh()->hasGeneratedOrders());
    }

    // ─────────────────────────────────────────────────────────────────

    private function planWithOneItem(array $attributes = []): ProductionPlan
    {
        $bom = $this->fixtures->bom($this->fixtures->product());
        $this->fixtures->bomRawLine($bom, $this->fixtures->product(), 2, 5);

        $plan = $this->fixtures->plan($attributes);
        $this->fixtures->planItem($plan, $bom, 10);

        return $plan;
    }

    /** خطّةٌ بمستودع مواد، ووصفةٌ بمادّةٍ واحدة (٢ لكل وحدة) ورصيدٌ منها. */
    private function planWithWarehouse(float $onHand): array
    {
        $warehouse = $this->fixtures->warehouse();
        $flour = $this->fixtures->product();
        $this->fixtures->stock($flour, $warehouse, $onHand);

        $bom = $this->fixtures->bom($this->fixtures->product());
        $this->fixtures->bomRawLine($bom, $flour, 2, 5);

        $plan = $this->fixtures->plan(['storehouse_id' => $warehouse->id]);
        $this->fixtures->planItem($plan, $bom, 10);

        return [$plan, $flour, $warehouse];
    }

    private function bomExpenseLine(ProductionMaterials $bom, int $costType, float $amount): void
    {
        Model::unguarded(fn () => ProductionMaterialsItem::create([
            'production_material_id' => $bom->id,
            'line_type' => BomLineType::Expense,
            'expenses_account_id' => $this->fixtures->expenseAccount()->id,
            'expenses_cost_type' => $costType,
            'expenses_price' => $amount,
            'expenses_total' => $amount,
        ]));
    }

    private function bomWasteLine(ProductionMaterials $bom, float $quantity, float $unitPrice): void
    {
        Model::unguarded(fn () => ProductionMaterialsItem::create([
            'production_material_id' => $bom->id,
            'line_type' => BomLineType::Waste,
            'end_life_product_id' => $this->fixtures->product()->id,
            'end_life_quantity' => $quantity,
            'end_life_unit_price' => $unitPrice,
            'end_life_total' => $quantity * $unitPrice,
        ]));
    }

    /** @return array<int, int> معرّفاتُ أوامر الخطّة بترتيب بنودها */
    private function orderIdsOf(ProductionPlan $plan): array
    {
        return $plan->items()->orderBy('id')->pluck('manufactur_order_id')->map(fn ($id) => (int) $id)->all();
    }

    /* الحالةُ تُكتب مباشرةً: دورةُ حياة الأمر تُختبَر في `OrderLifecycleTest`. */
    private function setOrderStatus(int $orderId, ManufacturOrderStatus $status): void
    {
        ManufacturOrders::whereKey($orderId)->update(['status' => $status->value]);
    }

    private function deleteLatestOrder(): void
    {
        $order = ManufacturOrders::latest('id')->first();
        ManufacturOrdersItem::where('manufactur_order_id', $order->id)->delete();
        $order->delete();
    }
}

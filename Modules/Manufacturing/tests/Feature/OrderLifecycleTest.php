<?php

namespace Modules\Manufacturing\Tests\Feature;

use App\Constants\SystemAccountIds;
use App\Enums\BomLineType;
use App\Enums\ManufacturOrderStatus;
use App\Enums\ReferenceType;
use App\Models\JournalEntry;
use App\Models\ManufacturOrders;
use App\Models\ManufacturOrdersItem;
use App\Models\Product;
use App\Models\StoreHouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\ManufacturingOrdersController;
use Modules\Manufacturing\Services\ManufacturingJournalService;
use Modules\Manufacturing\Tests\ManufacturingTestCase;

/*
    دورةُ أمر التصنيع من طرفها إلى طرفها.

    هذه الاختبارات تغطّي ما وعدت به شروطُ قبول المرحلة ٤ ولم يغطِّه
    `CostAndAccountingTest`: إقفال WIP، ومقدار الانحراف، والإلغاء بعد
    إرجاعٍ جزئي، والعكس الانتقائي عند التراجع.
*/
class OrderLifecycleTest extends ManufacturingTestCase
{
    /* ══════════ إقفال WIP والانحراف ══════════ */

    /*
        الشرطُ الحاكم للمرحلة: رصيدُ «مخزون تحت التشغيل» لأي أمرٍ
        مكتمل يساوي صفراً — وأيُّ فرقٍ حقيقي يذهب إلى حساب الانحراف.
    */
    public function test_wip_is_closed_to_zero_after_completion(): void
    {
        [$order, , $warehouse] = $this->readyOrder(inventoryCost: 8.0, estimated: 12.0, quantity: 10);

        $this->issue($order, $warehouse);
        $this->complete($order, $warehouse);

        $residue = app(ManufacturingJournalService::class)->wipResidueFor($order->id);

        $this->assertEqualsWithDelta(0.0, $residue, 0.01, 'WIP لم يُقفل');
        $this->assertSame(
            ManufacturOrderStatus::Completed,
            $order->fresh()->status
        );
    }

    /*
        صُرفت المواد بتكلفة ٨ (٨٠ إجمالاً) ودخل المنتج التام بالتكلفة
        المشتقّة نفسها، فلا انحراف. الاختبار يثبت أن الاشتقاق يقوم على
        ما حُمِّل فعلاً لا على التقدير — لولا ذلك لظهر انحرافٌ بـ٤٠.
    */
    public function test_no_phantom_variance_when_estimate_differs_from_cost(): void
    {
        [$order, , $warehouse] = $this->readyOrder(inventoryCost: 8.0, estimated: 12.0, quantity: 10);

        $this->issue($order, $warehouse);
        $this->complete($order, $warehouse);

        $variance = (float) DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->where('journal_entries.reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('journal_entries.reference_id', $order->id)
            ->where('journal_entry_details.account_id', SystemAccountIds::PRODUCTION_VARIANCE)
            ->sum(DB::raw('journal_entry_details.debit - journal_entry_details.credit'));

        $this->assertEqualsWithDelta(
            0.0, $variance, 0.01,
            'ظهر انحرافٌ وهميّ — التكلفة اشتُقّت من التقدير لا من المصروف'
        );
    }

    /* ══════════ الإلغاء بعد إرجاعٍ جزئي ══════════ */

    /*
        كان الإلغاء يُرجع **كامل** كمية كل بند بلا نظرٍ إلى ما أُرجع
        قبله: صرفُ ١٠ ثم إرجاعُ ٤ ثم الإلغاء = ١٤ من ١٠.
    */
    public function test_cancelling_returns_only_what_remains(): void
    {
        [$order, $flour, $warehouse] = $this->readyOrder(
            inventoryCost: 8.0, estimated: 8.0, quantity: 10, onHand: 100
        );

        $this->issue($order, $warehouse);
        $this->assertSame(90.0, $this->stockOf($flour->id, $warehouse->id));

        $this->returnSome($order, $flour, 4, $warehouse);
        $this->assertSame(94.0, $this->stockOf($flour->id, $warehouse->id));

        $this->cancel($order);

        $this->assertSame(
            100.0,
            $this->stockOf($flour->id, $warehouse->id),
            'الإلغاء أرجع أكثر مما صُرف — خُلق مخزون من العدم'
        );
    }

    /* الإلغاء بلا إرجاعٍ سابق يُرجع كل شيء — ولا شيء أكثر. */
    public function test_cancelling_without_a_prior_return_restores_exactly(): void
    {
        [$order, $flour, $warehouse] = $this->readyOrder(
            inventoryCost: 8.0, estimated: 8.0, quantity: 10, onHand: 100
        );

        $this->issue($order, $warehouse);
        $this->cancel($order);

        $this->assertSame(100.0, $this->stockOf($flour->id, $warehouse->id));
    }

    /* ══════════ العكس الانتقائي ══════════ */

    /*
        التراجعُ عن الإتمام يعكس قيودَ الإتمام وحدها. وقيدُ صرف المواد
        مُرحَّلٌ في مرحلةٍ سابقة وإذنُه لم يُعكَس، فعكسُه يعيد ثمنَ موادَّ
        ما تزال مستهلَكة.
    */
    public function test_undoing_completion_keeps_the_issue_entry_posted(): void
    {
        [$order, , $warehouse] = $this->readyOrder(inventoryCost: 8.0, estimated: 8.0, quantity: 10);

        $this->issue($order, $warehouse);
        $this->complete($order, $warehouse);

        app(ManufacturingOrdersController::class)->undoCompletion($order->id);

        $issueEntries = DB::table('journal_entries')
            ->where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('reference_id', $order->id)
            ->where('batch_reference', ManufacturingSources::STAGE_ISSUE)
            ->get();

        $this->assertCount(1, $issueEntries, 'قيد الصرف عُكس أو تكرّر');
        $this->assertFalse(
            (bool) $issueEntries->first()->is_reversed,
            'قيد صرف المواد عُكس مع التراجع عن الإتمام'
        );

        $this->assertSame(
            ManufacturOrderStatus::MaterialsReceived,
            $order->fresh()->status
        );
    }

    /* بعد التراجع يبقى في WIP ما صُرف فعلاً — لا صفر ولا سالب. */
    public function test_wip_still_holds_the_issued_cost_after_undo(): void
    {
        [$order, , $warehouse] = $this->readyOrder(inventoryCost: 8.0, estimated: 8.0, quantity: 10);

        $this->issue($order, $warehouse);
        $this->complete($order, $warehouse);

        app(ManufacturingOrdersController::class)->undoCompletion($order->id);

        $residue = app(ManufacturingJournalService::class)->wipResidueFor($order->id);

        $this->assertEqualsWithDelta(
            80.0, $residue, 0.01,
            'رصيد WIP بعد التراجع لا يساوي ما صُرف (١٠ × ٨)'
        );
    }

    /*
        الرفضُ عبر المسار الفعلي لا عبر التعداد وحده.

        فحصُ `canReturnMaterials()` يثبت القاعدة؛ وهذا يثبت أن المتحكّم
        يطبّقها — وأن المخزون لا يتغيّر بمحاولةٍ مرفوضة.
    */
    public function test_the_controller_refuses_a_return_after_completion(): void
    {
        [$order, $flour, $warehouse] = $this->readyOrder(
            inventoryCost: 8.0, estimated: 8.0, quantity: 10, onHand: 100
        );

        $this->issue($order, $warehouse);
        $this->complete($order, $warehouse);

        $stockBefore = $this->stockOf($flour->id, $warehouse->id);

        $response = $this->controller()->returnMaterials(
            Request::create('/', 'POST', [
                'storehouse_id' => $warehouse->id,
                'return_items' => [
                    ['product_id' => $flour->id, 'quantity' => 3],
                ],
            ]),
            $order->id
        );

        $error = $response->getSession()?->get('error');

        $this->assertNotNull($error, 'قُبل الإرجاع بعد الإتمام');
        $this->assertStringContainsString('لا يمكن إرجاع المواد', $error);

        $this->assertSame(
            $stockBefore,
            $this->stockOf($flour->id, $warehouse->id),
            'تغيّر المخزون رغم رفض الإرجاع'
        );
    }

    /* ══════════ الذرّية: المخزون والدفتر يسقطان معاً ══════════ */

    /*
        فشلُ القيد يجب أن يُرجع حركة المخزون.

        كنتُ تركتُ هذا الاختبار بحجّة أن «كلَّ شيء داخل معاملة، فالتغطية
        قائمةٌ ضمناً». وهي حجّةٌ كذّبتها المرحلة ٢ نفسها: قِيس هناك أن
        `RefreshDatabase` يبلّغ عن معاملةٍ مفتوحة (level = 1) بينما
        `pdo->inTransaction()` تقول false ولا أثرَ لها في `INNODB_TRX` —
        فتسرّبت صفوفُ كل اختبارٍ إلى تاليه.

        فالافتراضُ بوجود معاملةٍ ليس دليلاً على وجودها. هنا يُكسر القيدُ
        عمداً بعد تحريك المخزون، ويُقاس الرصيد.
    */
    public function test_a_failed_journal_entry_rolls_the_inventory_back(): void
    {
        [$order, $flour, $warehouse] = $this->readyOrder(
            inventoryCost: 8.0, estimated: 8.0, quantity: 10, onHand: 100
        );

        $before = $this->stockOf($flour->id, $warehouse->id);
        $this->assertSame(100.0, $before);

        // قيدٌ يرفض الترحيل — يُستدعى بعد خصم المخزون وختم الإذن
        $this->app->instance(
            ManufacturingJournalService::class,
            new class extends ManufacturingJournalService
            {
                public function post(
                    array $lines,
                    int $referenceId,
                    string $description,
                    Carbon|string|null $date = null,
                    ?string $currency = null,
                    ?int $clientId = null,
                    ?int $employeeId = null,
                    ReferenceType $referenceType = ReferenceType::MANUFACTURING_ORDER,
                    ?string $stage = null
                ): ?JournalEntry {
                    throw new \RuntimeException('فشلٌ مُصطنع في الترحيل');
                }
            }
        );

        $response = $this->controller()->receiveMaterials(
            Request::create('/', 'POST', ['storehouse_id' => $warehouse->id]),
            $order->id
        );

        $this->assertNotNull($response->getSession()?->get('error'), 'لم يُبلَّغ بأي خطأ');

        $this->assertSame(
            $before,
            $this->stockOf($flour->id, $warehouse->id),
            'خُصم المخزون رغم فشل القيد'
        );

        $this->assertSame(
            0,
            $this->permitCount($order->id, ManufacturingSources::PERMIT_ISSUE_MATERIALS),
            'بقي إذنُ صرفٍ بلا قيد'
        );

        $this->assertSame(
            ManufacturOrderStatus::Pending,
            $order->fresh()->status,
            'تغيّرت حالةُ الأمر رغم فشل العملية'
        );
    }

    // ═════════════════════════════════════════════════════════════════

    private function controller(): ManufacturingOrdersController
    {
        $this->actingAs($this->fixtures->user());

        return app(ManufacturingOrdersController::class);
    }

    private function issue(ManufacturOrders $order, StoreHouse $warehouse): void
    {
        $response = $this->controller()->receiveMaterials(
            Request::create('/', 'POST', ['storehouse_id' => $warehouse->id]),
            $order->id
        );

        $this->assertNull($response->getSession()?->get('error'), 'تعذّر الصرف: '.$response->getSession()?->get('error'));
    }

    private function complete(ManufacturOrders $order, StoreHouse $warehouse): void
    {
        $response = $this->controller()->completeOrder(
            Request::create('/', 'POST', ['storehouse_id' => $warehouse->id]),
            $order->id
        );

        $this->assertNull($response->getSession()?->get('error'), 'تعذّر الإتمام: '.$response->getSession()?->get('error'));
    }

    private function returnSome(ManufacturOrders $order, Product $product, float $quantity, StoreHouse $warehouse): void
    {
        $response = $this->controller()->returnMaterials(
            Request::create('/', 'POST', [
                'storehouse_id' => $warehouse->id,
                'return_items' => [
                    ['product_id' => $product->id, 'quantity' => $quantity],
                ],
            ]),
            $order->id
        );

        $this->assertNull($response->getSession()?->get('error'), 'تعذّر الإرجاع: '.$response->getSession()?->get('error'));
    }

    private function cancel(ManufacturOrders $order): void
    {
        $response = $this->controller()->cancelOrder(
            Request::create('/', 'POST', ['cancellation_reason' => 'اختبار']),
            $order->id
        );

        $this->assertNull($response->getSession()?->get('error'), 'تعذّر الإلغاء: '.$response->getSession()?->get('error'));
    }

    /**
     * @return array{0:ManufacturOrders, 1:Product, 2:StoreHouse}
     */
    private function readyOrder(
        float $inventoryCost,
        float $estimated,
        float $quantity,
        float $onHand = 100
    ): array {
        $warehouse = $this->fixtures->warehouse();
        $flour = $this->fixtures->product(['name' => 'دقيق']);
        $bread = $this->fixtures->product(['name' => 'خبز']);

        $this->fixtures->stock($flour, $warehouse, $onHand, $inventoryCost);

        $bom = $this->fixtures->bom($bread);
        $order = $this->fixtures->order($bom, [
            'quantity' => 1,
            'status' => ManufacturOrderStatus::Pending,
            'storehouse_id' => $warehouse->id,
        ]);

        ManufacturOrdersItem::create([
            'manufactur_order_id' => $order->id,
            'line_type' => BomLineType::Raw,
            'raw_product_id' => $flour->id,
            'raw_quantity' => $quantity,
            'raw_unit_price' => $estimated,
            'raw_total' => $quantity * $estimated,
        ]);

        return [$order->fresh('manufacturOrdersItem'), $flour, $warehouse];
    }
}

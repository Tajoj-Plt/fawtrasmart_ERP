<?php

namespace Modules\Manufacturing\Tests\Feature;

use App\Enums\BomLineType;
use App\Enums\ManufacturOrderStatus;
use App\Models\ManufacturingGeneralSetting;
use App\Models\ManufacturOrders;
use App\Models\ManufacturOrdersItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\StoreHouse;
use Illuminate\Http\Request;
use Modules\Manufacturing\Http\Controllers\Concerns\ReservesOrderMaterials;
use Modules\Manufacturing\Http\Controllers\ManufacturingOrdersController;
use Modules\Manufacturing\Services\ReservationService;
use Modules\Manufacturing\Tests\ManufacturingTestCase;

/*
    المرحلة ٥ — حجز المواد.

        المتاح  =  الرصيد الفعلي  −  المحجوز النشط
*/
class MaterialReservationTest extends ManufacturingTestCase
{
    private ReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservations = app(ReservationService::class);
    }

    /* ══════════ القياس ══════════ */

    public function test_available_equals_on_hand_minus_reserved(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 30, onHand: 100);

        $this->assertSame(100.0, $this->reservations->onHand($flour->id, $warehouse->id));
        $this->assertSame(0.0, $this->reservations->reserved($flour->id, $warehouse->id));

        $this->reserve($order, $warehouse, $flour, 30);

        $breakdown = $this->reservations->breakdown($flour->id, $warehouse->id);

        $this->assertSame(100.0, $breakdown['on_hand']);
        $this->assertSame(30.0, $breakdown['reserved']);
        $this->assertSame(70.0, $breakdown['available']);
    }

    /*
        الأمرُ لا يُحجب عن حجزه.

        لولا الاستثناء لمنع الأمرُ نفسَه من صرف ما حجزه: المتاح صفر
        بينما الثلاثون محجوزةٌ له هو.
    */
    public function test_an_order_is_not_blocked_by_its_own_reservation(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 100, onHand: 100);

        $this->reserve($order, $warehouse, $flour, 100);

        $this->assertSame(0.0, $this->reservations->available($flour->id, $warehouse->id));

        $this->assertSame(
            100.0,
            $this->reservations->available($flour->id, $warehouse->id, $order->id),
            'الأمرُ يمنع نفسَه من أخذ ما حجزه'
        );
    }

    /* ══════════ التعارض بين أمرين ══════════ */

    /*
        البند ٥ من الخطة: منعُ أمرين من الاعتماد على الكمية نفسها.
    */
    public function test_a_second_order_cannot_reserve_what_the_first_already_holds(): void
    {
        [$first, $flour, $warehouse] = $this->orderWithMaterial(required: 80, onHand: 100);

        $this->assertSame([], $this->reserve($first, $warehouse, $flour, 80));

        $second = $this->orderFor($flour, $warehouse, quantity: 50);

        $shortages = $this->reserve($second, $warehouse, $flour, 50);

        $this->assertNotEmpty($shortages, 'حُجزت الكمية مرّتين');
        $this->assertSame(50.0, $shortages[0]['required']);
        $this->assertSame(20.0, $shortages[0]['available']);
        $this->assertSame(30.0, $shortages[0]['shortage']);
    }

    /* والحجزُ الثاني يمرّ ضمن المتبقّي. */
    public function test_a_second_order_may_reserve_what_remains(): void
    {
        [$first, $flour, $warehouse] = $this->orderWithMaterial(required: 80, onHand: 100);
        $this->reserve($first, $warehouse, $flour, 80);

        $second = $this->orderFor($flour, $warehouse, quantity: 20);

        $this->assertSame([], $this->reserve($second, $warehouse, $flour, 20));
        $this->assertSame(0.0, $this->reservations->available($flour->id, $warehouse->id));
    }

    /* ══════════ دورة الحجز ══════════ */

    /*
        البند ٤: تحويلُ الحجز إلى صرف — لا حجز يبقى معلّقاً بعد الصرف.
        لولاه لخُصمت الكميةُ من المتاح مرّتين: بالحركة وبالوعد.
    */
    public function test_issuing_materials_consumes_the_reservation(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 30, onHand: 100);
        $this->reserve($order, $warehouse, $flour, 30);

        $this->actingAs($this->fixtures->user());
        app(ManufacturingOrdersController::class)->receiveMaterials(
            Request::create('/', 'POST', ['storehouse_id' => $warehouse->id]),
            $order->id
        );

        $this->assertSame(70.0, $this->reservations->onHand($flour->id, $warehouse->id));
        $this->assertSame(0.0, $this->reservations->reserved($flour->id, $warehouse->id), 'بقي حجزٌ بعد الصرف');
        $this->assertSame(70.0, $this->reservations->available($flour->id, $warehouse->id));

        $this->assertSame(
            StockReservation::STATUS_CONSUMED,
            StockReservation::where('reference_id', $order->id)->value('status')
        );
    }

    /* البند ٣: فكُّ الحجز عند الإلغاء. */
    public function test_cancelling_releases_the_reservation(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 30, onHand: 100);
        $this->reserve($order, $warehouse, $flour, 30);

        $this->assertSame(70.0, $this->reservations->available($flour->id, $warehouse->id));

        $this->actingAs($this->fixtures->user());
        app(ManufacturingOrdersController::class)->cancelOrder(
            Request::create('/', 'POST', ['cancellation_reason' => 'اختبار']),
            $order->id
        );

        $this->assertSame(0.0, $this->reservations->reserved($flour->id, $warehouse->id));
        $this->assertSame(100.0, $this->reservations->available($flour->id, $warehouse->id));

        $this->assertSame(
            StockReservation::STATUS_RELEASED,
            StockReservation::where('reference_id', $order->id)->value('status')
        );
    }

    /* إعادةُ الحجز تستبدل ولا تراكم. */
    public function test_reserving_twice_replaces_rather_than_accumulates(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 30, onHand: 100);

        $this->reserve($order, $warehouse, $flour, 30);
        $this->reserve($order, $warehouse, $flour, 40);

        $this->assertSame(40.0, $this->reservations->reserved($flour->id, $warehouse->id));
        $this->assertSame(1, StockReservation::active()->where('reference_id', $order->id)->count());
    }

    /* ══════════ المفتاح ══════════ */

    public function test_reservation_can_be_switched_off(): void
    {
        ManufacturingGeneralSetting::updateOrCreate(['id' => 1], ['reserve_materials_on_order' => false]);

        $this->assertFalse($this->reservations->enabled());

        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 30, onHand: 100);

        $this->assertSame([], $this->reserve($order, $warehouse, $flour, 30));
        $this->assertSame(0.0, $this->reservations->reserved($flour->id, $warehouse->id));
        $this->assertSame(100.0, $this->reservations->available($flour->id, $warehouse->id));
    }

    /* والافتراضُ مُفعَّل حتى بلا صفّ إعدادات. */
    public function test_reservation_defaults_to_enabled(): void
    {
        ManufacturingGeneralSetting::query()->delete();

        $this->assertTrue($this->reservations->enabled());
    }

    /* ══════════ لا حجزَ بلا مستودع ══════════ */

    /*
        شاشةُ الأمر لا تسأل عن مستودع إلا اختياراً. وبلا مستودعٍ لا
        يصحّ الحجز: المتاح يُقاس لكل مستودعٍ على حدة.
    */
    public function test_no_reservation_is_made_without_a_warehouse(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 30, onHand: 100);

        $controller = new class
        {
            use ReservesOrderMaterials;

            public function run(ManufacturOrders $order, Request $request): array
            {
                return $this->reserveOrderMaterials($order, $request);
            }
        };

        $controller->run($order, Request::create('/', 'POST', []));

        $this->assertSame(0.0, $this->reservations->reserved($flour->id, $warehouse->id));
    }

    /*
        والمسارُ الموجب: مستودعٌ مختار ⟵ حجزٌ فعليّ.

        اختبارُ «لا حجز بلا مستودع» وحده يمرّ حتى لو كان الحجز معطّلاً
        بالكامل. فهذا يثبت أن الربط بين حفظ الأمر والخدمة قائمٌ فعلاً.
    */
    public function test_saving_an_order_with_a_warehouse_reserves_its_materials(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 25, onHand: 100);

        $this->assertSame(0.0, $this->reservations->reserved($flour->id, $warehouse->id));

        $shortages = $this->orderReserver()->run(
            $order,
            Request::create('/', 'POST', ['storehouse_id' => $warehouse->id])
        );

        $this->assertSame([], $shortages);
        $this->assertSame(25.0, $this->reservations->reserved($flour->id, $warehouse->id));
        $this->assertSame(75.0, $this->reservations->available($flour->id, $warehouse->id));
    }

    /* ونقصُ المتاح يُبلَّغ ولا يُسقط الحفظ. */
    public function test_a_shortage_is_reported_rather_than_thrown(): void
    {
        [$first, $flour, $warehouse] = $this->orderWithMaterial(required: 90, onHand: 100);
        $this->reserve($first, $warehouse, $flour, 90);

        $second = $this->orderFor($flour, $warehouse, quantity: 40);

        $shortages = $this->orderReserver()->run(
            $second,
            Request::create('/', 'POST', ['storehouse_id' => $warehouse->id])
        );

        $this->assertCount(1, $shortages, 'لم يُبلَّغ عن النقص');
        $this->assertSame(10.0, $shortages[0]['available']);
        $this->assertSame(30.0, $shortages[0]['shortage']);

        // والأمرُ باقٍ — النقصُ لم يُسقطه
        $this->assertNotNull($second->fresh());
    }

    /* ══════════ العرض ══════════ */

    /*
        البند ٦: الأرقام الثلاثة تصل إلى الشاشة.

        الحسابُ وحده لا يكفي — كان مبنيّاً ولا يُعرض. وشاشةُ الاستلام
        هي موضع القرار: أيُّ مستودعٍ يكفي؟
    */
    public function test_the_receive_screen_receives_the_three_figures(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 30, onHand: 100);

        // حجزٌ لأمرٍ آخر — هو ما يجعل «الفعلي» و«المتاح» يفترقان
        $other = $this->orderFor($flour, $warehouse, quantity: 60);
        $this->reserve($other, $warehouse, $flour, 60);

        $this->actingAs($this->fixtures->user());

        $view = app(ManufacturingOrdersController::class)->showReceiveMaterialsForm($order->id);
        $figures = $view->getData()['stockByWarehouse'][$warehouse->id][$flour->id] ?? null;

        $this->assertNotNull($figures, 'الأرقام لم تصل إلى الشاشة');
        $this->assertSame(100.0, $figures['on_hand']);
        $this->assertSame(60.0, $figures['reserved']);
        $this->assertSame(40.0, $figures['available'], 'المتاح لا يطرح حجز الأمر الآخر');
    }

    /* وحجزُ الأمر نفسه لا يظهر ناقصاً في شاشته. */
    public function test_the_screen_excludes_the_orders_own_reservation(): void
    {
        [$order, $flour, $warehouse] = $this->orderWithMaterial(required: 30, onHand: 100);
        $this->reserve($order, $warehouse, $flour, 30);

        $this->actingAs($this->fixtures->user());

        $view = app(ManufacturingOrdersController::class)->showReceiveMaterialsForm($order->id);
        $figures = $view->getData()['stockByWarehouse'][$warehouse->id][$flour->id];

        $this->assertSame(0.0, $figures['reserved'], 'حجزُ الأمر يُحسب ضدّه');
        $this->assertSame(100.0, $figures['available']);
    }

    // ═════════════════════════════════════════════════════════════════

    /** كاشفٌ لسمة الحجز — الطريقة محميّة في المتحكّم. */
    private function orderReserver(): object
    {
        return new class
        {
            use ReservesOrderMaterials;

            public function run(ManufacturOrders $order, Request $request): array
            {
                return $this->reserveOrderMaterials($order, $request);
            }
        };
    }

    private function reserve(ManufacturOrders $order, StoreHouse $warehouse, Product $product, float $quantity): array
    {
        return $this->reservations->reserve($order, $warehouse->id, [
            ['product_id' => $product->id, 'quantity' => $quantity],
        ]);
    }

    /**
     * @return array{0:ManufacturOrders, 1:Product, 2:StoreHouse}
     */
    private function orderWithMaterial(float $required, float $onHand): array
    {
        $warehouse = $this->fixtures->warehouse();
        $flour = $this->fixtures->product(['name' => 'دقيق']);

        $this->fixtures->stock($flour, $warehouse, $onHand, 8.0);

        return [$this->orderFor($flour, $warehouse, $required), $flour, $warehouse];
    }

    private function orderFor(Product $material, StoreHouse $warehouse, float $quantity): ManufacturOrders
    {
        $product = $this->fixtures->product();
        $bom = $this->fixtures->bom($product);

        $order = $this->fixtures->order($bom, [
            'quantity' => 1,
            'status' => ManufacturOrderStatus::Pending,
            'storehouse_id' => $warehouse->id,
        ]);

        ManufacturOrdersItem::create([
            'manufactur_order_id' => $order->id,
            'line_type' => BomLineType::Raw,
            'raw_product_id' => $material->id,
            'raw_quantity' => $quantity,
            'raw_unit_price' => 8,
            'raw_total' => $quantity * 8,
        ]);

        return $order->fresh('manufacturOrdersItem');
    }
}

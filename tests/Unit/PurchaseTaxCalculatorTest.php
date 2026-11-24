<?php

namespace Tests\Unit;

use App\Services\Purchases\PurchaseTaxCalculator as Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ضريبة بند المشتريات — متضمَّنة في السعر أم مضافة فوقه.
 *
 * جدول `tax_sittings` وحده هو ما يلزم، فيُبنى هنا على sqlite في الذاكرة
 * بدل تشغيل مئات الهجرات التي لا شأن لها بالحساب.
 */
class PurchaseTaxCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tax_sittings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->double('tax')->default(0);
            $table->string('type')->default('excluded');
            $table->timestamps();
        });

        DB::table('tax_sittings')->insert([
            ['id' => 1, 'name' => 'القيمة المضافة', 'tax' => 15, 'type' => 'included'],
            ['id' => 2, 'name' => 'ضريبة مضافة', 'tax' => 15, 'type' => 'excluded'],
            ['id' => 3, 'name' => 'صفرية', 'tax' => 0, 'type' => 'excluded'],
        ]);

        Calculator::forget();
    }

    protected function tearDown(): void
    {
        Calculator::forget();
        Schema::dropIfExists('tax_sittings');

        parent::tearDown();
    }

    #[Test]
    public function an_included_tax_is_extracted_from_the_price_not_added_to_it(): void
    {
        // 2000 بنسبة 15% متضمَّنة: الإجمالي يبقى 2000
        $line = Calculator::line(1, 2000, 0, [1]);

        $this->assertSame(2000.0, $line['total']);
        $this->assertSame(260.87, $line['tax']);
        $this->assertSame(1739.13, $line['net']);
    }

    #[Test]
    public function an_excluded_tax_is_added_on_top_of_the_price(): void
    {
        $line = Calculator::line(1, 2000, 0, [2]);

        $this->assertSame(2300.0, $line['total']);
        $this->assertSame(300.0, $line['tax']);
        $this->assertSame(2000.0, $line['net']);
    }

    #[Test]
    public function the_tax_base_is_the_amount_after_the_line_discount(): void
    {
        // 10 × 100 − 200 = 800 هو الوعاء لا الألف
        $line = Calculator::line(10, 100, 200, [2]);

        $this->assertSame(800.0, $line['base']);
        $this->assertSame(120.0, $line['tax']);
        $this->assertSame(920.0, $line['total']);
    }

    #[Test]
    public function the_configured_setting_beats_a_rate_posted_by_the_screen(): void
    {
        // نسبة مرسلة مخالفة للمضبوطة لا تُعتمد — الإعداد هو المرجع
        $line = Calculator::line(1, 1000, 0, [3], [99]);

        $this->assertSame(0.0, $line['tax']);
        $this->assertSame(1000.0, $line['total']);
    }

    #[Test]
    public function a_line_without_a_tax_id_keeps_the_old_added_tax_behaviour(): void
    {
        // بنود حُفظت قبل عمود المعرّف: نسبتها تُضاف فوق السعر كما حُسبت يومها
        $line = Calculator::line(1, 1000, 0, [null], [15]);

        $this->assertSame(150.0, $line['tax']);
        $this->assertSame(1150.0, $line['total']);
    }

    #[Test]
    public function two_taxes_on_one_line_are_summed_each_by_its_own_type(): void
    {
        // المتضمَّنة تُستخرَج من الوعاء والمضافة تُضاف فوقه
        $line = Calculator::line(1, 1000, 0, [1, 2]);

        $this->assertSame(130.43, $line['taxes'][0]['amount']);
        $this->assertSame(150.0, $line['taxes'][1]['amount']);
        $this->assertSame(1150.0, $line['total']);
    }

    #[Test]
    public function totals_add_up_the_way_the_screen_shows_them(): void
    {
        // المجموع − الخصم + الضريبة = الإجمالي، مهما كان نوع الضريبة
        $lines = [
            Calculator::line(1, 2000, 0, [1]),   // متضمَّنة
            Calculator::line(2, 500, 100, [2]),  // مضافة مع خصم
        ];

        $totals = Calculator::totals($lines);

        $this->assertSame(
            round($totals['subtotal'] - $totals['discount'] + $totals['tax'], 2),
            $totals['grand_total']
        );
        $this->assertSame(3035.0, $totals['grand_total']);
    }

    #[Test]
    public function a_zero_quantity_or_price_produces_no_tax(): void
    {
        $this->assertSame(0.0, Calculator::line(0, 2000, 0, [1])['tax']);
        $this->assertSame(0.0, Calculator::line(3, 0, 0, [2])['tax']);
    }

    #[Test]
    public function a_discount_larger_than_the_line_does_not_go_negative(): void
    {
        $line = Calculator::line(1, 100, 500, [2]);

        $this->assertSame(0.0, $line['base']);
        $this->assertSame(0.0, $line['tax']);
        $this->assertSame(0.0, $line['total']);
    }

    #[Test]
    public function the_type_of_a_tax_is_readable_from_its_id(): void
    {
        $this->assertTrue(Calculator::isIncluded(1));
        $this->assertFalse(Calculator::isIncluded(2));
        // بلا معرّف: مضافة، كما كان السلوك قبل عمود المعرّف
        $this->assertFalse(Calculator::isIncluded(null));
    }

    #[Test]
    public function an_old_line_rate_is_matched_back_to_its_setting(): void
    {
        // شاشة التعديل تعيد اختيار الضريبة لبند حُفظ بنسبته وحدها
        $this->assertSame(1, Calculator::matchByRate(15));
        $this->assertNull(Calculator::matchByRate(0));
        $this->assertNull(Calculator::matchByRate(7.5));
    }
}

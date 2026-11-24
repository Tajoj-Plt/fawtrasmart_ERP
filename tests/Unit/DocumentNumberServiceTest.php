<?php

namespace Tests\Unit;

use App\Models\SerialSetting;
use App\Services\DocumentNumberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * توليد أرقام المستندات — تخطّي الأرقام المحجوزة تحديداً.
 *
 * لا `RefreshDatabase` هنا: الاختبار يحتاج جدولين اثنين فقط على اتصال
 * sqlite في الذاكرة، فبناؤهما يدوياً أسرع من 467 هجرة لا شأن لها بالموضوع.
 */
class DocumentNumberServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('serial_settings', function (Blueprint $table) {
            $table->id();
            $table->string('section');
            $table->integer('current_number')->default(0);
            $table->integer('number_of_digits')->default(6);
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->string('separator')->nullable();
            $table->integer('mode')->default(0);
            $table->boolean('reset_yearly')->default(false);
            $table->string('reset_period')->nullable();
            $table->timestamp('last_reset_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('serial_settings');

        parent::tearDown();
    }

    private function service(): DocumentNumberService
    {
        return app(DocumentNumberService::class);
    }

    private function setting(int $currentNumber): SerialSetting
    {
        return SerialSetting::create([
            'section' => 'purchase-order',
            'current_number' => $currentNumber,
            'number_of_digits' => 9,
            'prefix' => 'po2026',
            'separator' => '-',
            'mode' => 0,
            'reset_period' => 'never',
            'is_active' => true,
        ]);
    }

    private function existingOrders(string ...$codes): void
    {
        foreach ($codes as $code) {
            DB::table('purchase_orders')->insert(['code' => $code]);
        }
    }

    #[Test]
    public function it_keeps_the_plain_next_number_when_nothing_is_taken(): void
    {
        $this->setting(6);

        $this->assertSame('po2026-000000007', $this->service()->peek('purchase-order'));
        $this->assertSame('po2026-000000007', $this->service()->next('purchase-order'));
    }

    #[Test]
    public function peek_skips_a_number_already_stored_in_the_table(): void
    {
        // العلّة المبلَّغة: العدّاد عند 6 والرقم 7 محفوظ أصلاً، فكانت الشاشة
        // تعرض 7 ثم يرفضه التحقّق برسالة «الكود مستخدم من قبل»
        $this->setting(6);
        $this->existingOrders('po2026-000000007');

        $this->assertSame('po2026-000000008', $this->service()->peek('purchase-order'));
    }

    #[Test]
    public function next_skips_a_run_of_taken_numbers_and_advances_the_counter(): void
    {
        $setting = $this->setting(6);
        $this->existingOrders('po2026-000000007', 'po2026-000000008', 'po2026-000000009');

        $this->assertSame('po2026-000000010', $this->service()->next('purchase-order'));
        $this->assertSame(10, (int) $setting->fresh()->current_number);
    }

    #[Test]
    public function it_crosses_a_gap_wider_than_one_probe_batch(): void
    {
        $this->setting(0);
        $this->existingOrders(...array_map(
            fn ($n) => 'po2026-'.str_pad((string) $n, 9, '0', STR_PAD_LEFT),
            range(1, 60)
        ));

        $this->assertSame('po2026-000000061', $this->service()->next('purchase-order'));
    }

    #[Test]
    public function peek_does_not_consume_the_number(): void
    {
        $setting = $this->setting(6);
        $this->existingOrders('po2026-000000007');

        $this->service()->peek('purchase-order');

        $this->assertSame(6, (int) $setting->fresh()->current_number);
    }

    #[Test]
    public function a_section_without_a_known_column_generates_as_before(): void
    {
        // `payment-voucher` بلا `column` في السجلّ — لا فحص ولا استعلام
        SerialSetting::create([
            'section' => 'payment-voucher',
            'current_number' => 4,
            'number_of_digits' => 6,
            'prefix' => 'PV',
            'separator' => '-',
            'mode' => 0,
            'reset_period' => 'never',
            'is_active' => true,
        ]);

        $this->assertSame('PV-000005', $this->service()->peek('payment-voucher'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Employee;
use App\Models\TaxSitting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationService as BookableService;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Models\ReservationWorkingHour;
use Modules\Client\Reservations\Services\ReservationAvailabilityService;
use Modules\Client\Reservations\Services\ReservationBookingService;
use Modules\Client\Reservations\Services\ReservationPricingService;
use Modules\Client\Reservations\Services\ReservationStatusTransitionService;
use Tests\TestCase;

/**
 * سلوكُ الحجز: الإنشاء والتعديل ومنعُ التعارض وسيرُ العمل والتسعير.
 *
 * الجانبُ المالي (القبض والفوترة والقيود) في
 * {@see ReservationAccountingTest} — فصلٌ متعمَّد: هذه الاختباراتُ لا تحتاج
 * شجرةَ حسابات، وخلطُهما يجعل فشلَ ربطِ حسابٍ يُسقط اختبارَ منعِ التعارض.
 */
class ReservationBookingTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Employee $employee;

    private BookableService $service;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->branch = Branch::create([
            'name' => 'الفرع الرئيسي',
            'code' => '1',
            'status' => 0,
            'is_main' => true,
        ]);

        $this->client = Client::create([
            'trade_name' => 'عميل الاختبار',
            'code' => 'C-1',
            'phone' => '0500000000',
        ]);

        $this->employee = Employee::create([
            'first_name' => 'أحمد',
            'branch_id' => $this->branch->id,
        ]);

        $this->service = BookableService::create([
            'name' => 'فحص',
            'duration' => 30,
            'buffer_before' => 0,
            'buffer_after' => 10,
            'price' => 200,
            'is_active' => true,
        ]);

        // دوامٌ كاملٌ طوال الأسبوع — فلا يحجب حارسُ أوقات العمل الاختبارات
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            ReservationWorkingHour::create([
                'scope' => ReservationWorkingHour::SCOPE_EMPLOYEE,
                'scope_id' => $this->employee->id,
                'weekday' => $weekday,
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        ReservationSetting::forget();
    }

    private function booking(): ReservationBookingService
    {
        return app(ReservationBookingService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function make(array $overrides = []): Reservation
    {
        return $this->booking()->create(array_merge([
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'branch_id' => $this->branch->id,
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
        ], $overrides));
    }

    // ════════════════════ الحالة 1: حجز بلا دفع ════════════════════

    public function test_creates_reservation_with_number_currency_and_period(): void
    {
        $reservation = $this->make();

        $this->assertNotEmpty($reservation->code, 'الحجز يجب أن يحمل رقم مستند مولَّداً');
        $this->assertSame('10:00:00', $reservation->start_time);
        $this->assertSame('10:30:00', $reservation->end_time, 'النهاية تُشتقّ من مدّة الخدمة');
        $this->assertSame(30, $reservation->duration);
        $this->assertNotEmpty($reservation->currency, 'العملة تُختَم في النموذج');
        $this->assertSame($this->branch->id, $reservation->branch_id);
        $this->assertSame(200.0, (float) $reservation->total);
        $this->assertSame(0.0, (float) $reservation->paid_amount);
        $this->assertSame(200.0, (float) $reservation->remaining_amount);
    }

    public function test_blocked_window_extends_by_service_buffers(): void
    {
        $reservation = $this->make();

        // 10:30 + عشر دقائق تنظيف
        $this->assertSame('10:40:00', $reservation->blocked_end);
        $this->assertSame('10:00:00', $reservation->blocked_start);
    }

    public function test_status_history_records_creation(): void
    {
        $reservation = $this->make();

        $this->assertSame(1, $reservation->statusHistories()->count());
        $this->assertNull($reservation->statusHistories()->first()->from_status);
    }

    // ════════════════════ الحالة 6: منع التعارض ════════════════════

    public function test_rejects_overlapping_reservation_for_same_employee(): void
    {
        $this->make(['start_time' => '10:00']);

        $this->expectException(ReservationException::class);

        $this->make(['start_time' => '10:15']);
    }

    public function test_rejects_reservation_inside_cleanup_buffer_of_previous_one(): void
    {
        // 10:00–10:30 وبعده عشرُ دقائق تنظيف ⇒ الموظّف مشغولٌ حتى 10:40
        $this->make(['start_time' => '10:00']);

        $this->expectException(ReservationException::class);

        $this->make(['start_time' => '10:35']);
    }

    public function test_accepts_reservation_after_cleanup_buffer_ends(): void
    {
        $this->make(['start_time' => '10:00']);

        $second = $this->make(['start_time' => '10:40']);

        $this->assertNotNull($second->id);
    }

    public function test_adjacent_reservations_do_not_conflict_without_buffers(): void
    {
        $this->service->update(['buffer_after' => 0]);

        $this->make(['start_time' => '10:00']);
        $second = $this->make(['start_time' => '10:30']);

        $this->assertNotNull($second->id, 'حجزٌ يبدأ لحظةَ انتهاء سابقه لا يتعارض معه');
    }

    public function test_cancelled_reservation_frees_its_slot(): void
    {
        $first = $this->make(['start_time' => '10:00']);

        app(ReservationStatusTransitionService::class)
            ->transition($first, ReservationStatus::CANCELLED, 'تحرير');

        $second = $this->make(['start_time' => '10:00']);

        $this->assertNotNull($second->id, 'الملغى لا يشغل موعده');
    }

    public function test_overbooking_setting_disables_conflict_check(): void
    {
        $this->make(['start_time' => '10:00']);

        ReservationSetting::current()->update(['allow_overbooking' => true]);
        ReservationSetting::forget();

        $second = $this->make(['start_time' => '10:00']);

        $this->assertNotNull($second->id);
    }

    // ════════════════════ الحالة 7: خارج ساعات العمل ════════════════════

    public function test_rejects_reservation_outside_working_hours(): void
    {
        $this->expectException(ReservationException::class);

        $this->make(['start_time' => '19:00']);
    }

    public function test_rejects_reservation_in_the_past(): void
    {
        $this->expectException(ReservationException::class);

        $this->make(['reservation_date' => now()->subDays(2)->toDateString()]);
    }

    public function test_rejects_reservation_beyond_max_advance_days(): void
    {
        ReservationSetting::current()->update(['max_advance_days' => 5]);
        ReservationSetting::forget();

        $this->expectException(ReservationException::class);

        $this->make(['reservation_date' => now()->addDays(30)->toDateString()]);
    }

    public function test_available_slots_exclude_taken_ones(): void
    {
        $this->make(['start_time' => '10:00']);

        $slots = app(ReservationAvailabilityService::class)->availableSlots(
            now()->addDays(3)->toDateString(),
            $this->service,
            $this->employee->id,
            $this->branch->id
        );

        $starts = array_column($slots, 'start');

        $this->assertNotContains('10:00', $starts);
        $this->assertContains('08:00', $starts);
    }

    // ════════════════════ سير العمل ════════════════════

    public function test_workflow_follows_the_defined_path(): void
    {
        $reservation = $this->make();
        $transitions = app(ReservationStatusTransitionService::class);

        $transitions->transition($reservation, ReservationStatus::CONFIRMED);
        $this->assertNotNull($reservation->confirmed_at);

        $transitions->transition($reservation, ReservationStatus::CHECKED_IN);
        $transitions->transition($reservation, ReservationStatus::IN_PROGRESS);
        $transitions->transition($reservation, ReservationStatus::COMPLETED);

        $this->assertSame(ReservationStatus::COMPLETED, $reservation->fresh()->status);
        $this->assertNotNull($reservation->completed_at);
        $this->assertSame(5, $reservation->statusHistories()->count());
    }

    public function test_rejects_illegal_transition(): void
    {
        $reservation = $this->make();

        app(ReservationStatusTransitionService::class)
            ->transition($reservation, ReservationStatus::CONFIRMED);

        $this->expectException(ReservationException::class);

        // «مؤكَّد» لا تصل إلى «مكتمل» مباشرةً
        app(ReservationStatusTransitionService::class)
            ->transition($reservation, ReservationStatus::COMPLETED);
    }

    public function test_final_status_cannot_be_left(): void
    {
        $reservation = $this->make();
        $transitions = app(ReservationStatusTransitionService::class);

        $transitions->transition($reservation, ReservationStatus::CANCELLED, 'انتهى');

        $this->expectException(ReservationException::class);

        $transitions->transition($reservation, ReservationStatus::PENDING);
    }

    public function test_cannot_mark_no_show_before_appointment_time(): void
    {
        $reservation = $this->make();

        app(ReservationStatusTransitionService::class)
            ->transition($reservation, ReservationStatus::CONFIRMED);

        $this->expectException(ReservationException::class);

        app(ReservationStatusTransitionService::class)
            ->transition($reservation, ReservationStatus::NO_SHOW);
    }

    // ════════════════════ الحالة 5: إعادة الجدولة ════════════════════

    public function test_reschedule_moves_appointment_and_returns_to_pending(): void
    {
        ReservationSetting::current()->update(['reschedule_deadline_hours' => 0]);
        ReservationSetting::forget();

        $reservation = $this->make(['start_time' => '10:00']);
        $oldDate = $reservation->reservation_date->toDateString();

        $newDate = now()->addDays(4)->toDateString();

        $this->booking()->reschedule($reservation, $newDate, '11:00', null, 'طلب العميل');

        $reservation->refresh();

        $this->assertSame($newDate, $reservation->reservation_date->toDateString());
        $this->assertSame('11:00:00', $reservation->start_time);
        $this->assertSame(1, $reservation->reschedule_count);
        $this->assertSame($oldDate, $reservation->rescheduled_from_date->toDateString());
        $this->assertSame(ReservationStatus::PENDING, $reservation->status);
    }

    public function test_reschedule_respects_max_attempts(): void
    {
        ReservationSetting::current()->update([
            'reschedule_deadline_hours' => 0,
            'max_reschedule_attempts' => 1,
        ]);
        ReservationSetting::forget();

        $reservation = $this->make(['start_time' => '10:00']);

        $this->booking()->reschedule($reservation, now()->addDays(4)->toDateString(), '11:00');

        $this->expectException(ReservationException::class);

        $this->booking()->reschedule($reservation, now()->addDays(5)->toDateString(), '12:00');
    }

    // ════════════════════ التسعير والضريبة ════════════════════

    public function test_tax_is_calculated_after_discount(): void
    {
        $tax = TaxSitting::create(['name' => 'ض.ق.م', 'tax' => 15, 'type' => 'excluded']);

        $money = app(ReservationPricingService::class)
            ->quote(200, 20, 'percentage', $tax->id);

        // 200 − 20% = 160، والضريبة 15% منها = 24
        $this->assertSame(40.0, $money['discount_amount']);
        $this->assertSame(160.0, $money['subtotal']);
        $this->assertSame(24.0, $money['tax_amount']);
        $this->assertSame(184.0, $money['total']);
    }

    public function test_inclusive_tax_is_extracted_not_added(): void
    {
        $tax = TaxSitting::create(['name' => 'شاملة', 'tax' => 15, 'type' => 'included']);

        $money = app(ReservationPricingService::class)->quote(115, 0, 'amount', $tax->id);

        $this->assertSame(115.0, $money['total'], 'المبلغ الشامل لا يزيد بالضريبة');
        $this->assertSame(15.0, $money['tax_amount']);
        $this->assertSame(100.0, $money['subtotal']);
    }

    public function test_discount_never_exceeds_base(): void
    {
        $money = app(ReservationPricingService::class)->quote(100, 500, 'amount');

        $this->assertSame(100.0, $money['discount_amount']);
        $this->assertSame(0.0, $money['total'], 'الإجمالي لا يصير سالباً');
    }

    // ════════════════════ الحالة 10: المبلغ بعد الفوترة ════════════════════

    public function test_rejects_payment_greater_than_total(): void
    {
        $this->expectException(ReservationException::class);

        $this->make(['paid_amount' => 9999]);
    }

    public function test_allows_overpayment_when_setting_enabled(): void
    {
        ReservationSetting::current()->update(['allow_overpayment' => true]);
        ReservationSetting::forget();

        $reservation = $this->make(['paid_amount' => 500]);

        $this->assertSame(500.0, (float) $reservation->paid_amount);
    }

    // ════════════════════ التعديل والحذف ════════════════════

    public function test_update_changes_appointment_and_keeps_conflict_rules(): void
    {
        $reservation = $this->make(['start_time' => '10:00']);
        $other = $this->make(['start_time' => '12:00']);

        $this->booking()->update($reservation, ['start_time' => '14:00']);

        $this->assertSame('14:00:00', $reservation->fresh()->start_time);

        $this->expectException(ReservationException::class);

        // نقلُه فوق حجزٍ قائم يُرفض كما يُرفض إنشاؤه هناك
        $this->booking()->update($reservation, ['start_time' => '12:00']);
    }

    public function test_delete_is_soft_and_allowed_without_money(): void
    {
        $reservation = $this->make();

        $this->booking()->delete($reservation);

        $this->assertSoftDeleted('reservations', ['id' => $reservation->id]);
    }

    public function test_delete_is_blocked_when_payment_recorded(): void
    {
        $reservation = $this->make(['paid_amount' => 50]);

        $this->expectException(ReservationException::class);

        $this->booking()->delete($reservation);
    }

    // ════════════════════ الحالة 12: سجلّ النشاط ════════════════════

    public function test_creation_is_written_to_activity_log(): void
    {
        $reservation = $this->make();

        $this->assertDatabaseHas('logs', [
            'type' => 'Reservations',
            'type_id' => $reservation->id,
        ]);
    }

    public function test_status_change_is_written_to_activity_log(): void
    {
        $reservation = $this->make();

        app(ReservationStatusTransitionService::class)
            ->transition($reservation, ReservationStatus::CONFIRMED);

        $this->assertDatabaseHas('logs', [
            'type' => 'Reservations',
            'type_id' => $reservation->id,
            'action' => 'status_changed',
        ]);
    }
}

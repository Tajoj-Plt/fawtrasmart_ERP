<?php

namespace Modules\Client\Reservations\Console;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Employee;
use App\Models\TaxSitting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Reservations\Models\ReservationService as BookableService;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Services\ReservationAvailabilityService;
use Modules\Client\Reservations\Services\ReservationBookingService;
use Modules\Client\Reservations\Services\ReservationPricingService;
use Modules\Client\Reservations\Services\ReservationStatusTransitionService;
use Throwable;

/**
 * فحصٌ ذاتيّ لوحدة الحجوزات — يُشغَّل على القاعدة الحيّة **ثمّ يتراجع**.
 *
 * مجموعةُ الهجرات لا تُبنى على SQLite، فلا يبدأ اختبارُ PHPUnit. فهذا الأمرُ
 * يُجري السيناريوهات على قاعدة التشغيل داخل معاملةٍ تُلغى في النهاية.
 *
 * ما يثبته: الترقيم، والدوامُ من **وردية الموارد البشرية** (ومن لا وردية له
 * لا يُحجز)، والعطلةُ الرسمية، وكشفُ التعارض والتنظيف، والأوقاتُ المتاحة،
 * وسيرُ العمل، والتسعير، وذرّيةُ إعادة الجدولة. ولا مالَ في الحجز ليُفحص:
 * المالُ على فاتورة المبيعات.
 *
 * `--force` لأنّ `next_document_number` يُقدّم عدّادَه في معاملةٍ داخلية.
 */
class ReservationSelfCheck extends Command
{
    protected $signature = 'reservations:self-check
                            {--force : تنفيذ الفحص فعلياً (يتقدّم عدّاد الترقيم بضع خطوات)}';

    protected $description = 'يتحقّق من عمل وحدة الحجوزات كاملةً على القاعدة الحيّة ثمّ يتراجع عن كل ما كتبه';

    private int $passed = 0;

    private int $failed = 0;

    /** @var array<int, string> */
    private array $failures = [];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('هذا الفحص يكتب في القاعدة ثمّ يتراجع، لكنّه يستهلك أرقاماً من عدّادات الترقيم.');
            $this->line('شغّله بـ: php artisan reservations:self-check --force');

            return self::SUCCESS;
        }

        $this->info('فحص وحدة الحجوزات — كل ما يُكتب يُلغى في النهاية');
        $this->newLine();

        try {
            DB::beginTransaction();

            $this->runScenarios();

            DB::rollBack();
            $this->newLine();
            $this->line('تمّ التراجع عن كل البيانات المؤقّتة.');
        } catch (Throwable $e) {
            DB::rollBack();

            $this->newLine();
            $this->error('توقّف الفحص باستثناء غير متوقّع: '.$e->getMessage());
            $this->line('  في '.$e->getFile().':'.$e->getLine());

            return self::FAILURE;
        } finally {
            ReservationSetting::forget();
        }

        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->info("نجح: {$this->passed}");

        if ($this->failed > 0) {
            $this->error("فشل: {$this->failed}");

            foreach ($this->failures as $failure) {
                $this->line('  • '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('كل الفحوص نجحت.');

        return self::SUCCESS;
    }

    // ════════════════════ السيناريوهات ════════════════════

    private function runScenarios(): void
    {
        $actor = User::query()->first();

        if ($actor) {
            auth()->login($actor);
        }

        [$branch, $client, $employee, $service, $tax, $shiftId] = $this->fixtures();

        ReservationSetting::current()->update([
            'default_status' => 'pending',
            'allow_cancellation' => true,
            'allow_cancel_after_confirm' => true,
            'cancellation_deadline_hours' => 0,
            'allow_rescheduling' => true,
            'reschedule_deadline_hours' => 0,
            'max_reschedule_attempts' => 0,
            'allow_overbooking' => false,
            'allow_repeated_booking' => true,
            'min_advance_minutes' => 0,
            'max_advance_days' => 0,
            'max_daily_reservations' => 0,
            'min_duration' => 1,
            'max_duration' => 1440,
            'require_client' => false,
        ]);
        ReservationSetting::forget();

        $booking = app(ReservationBookingService::class);
        $transitions = app(ReservationStatusTransitionService::class);
        $availability = app(ReservationAvailabilityService::class);
        $pricing = app(ReservationPricingService::class);

        // يومُ عملٍ في الوردية — الأحدُ القادم بعد يومين على الأقلّ
        $date = now()->addDays(2)->next('Sunday')->toDateString();

        $make = fn (array $extra = []) => $booking->create(array_merge([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'product_id' => $service->product_id,
            'reservation_date' => $date,
            'start_time' => '10:00',
        ], $extra));

        // ─────────── 1. الإنشاء ───────────
        $this->section('الإنشاء');

        $reservation = $make();

        $this->check('يحمل رقم مستند مولَّداً', ! empty($reservation->code), $reservation->code);
        $this->check('النهاية تُشتقّ من مدّة الخدمة', $reservation->end_time === '10:30:00', $reservation->end_time);
        $this->check('الفترة المحجوزة تشمل وقت التنظيف', $reservation->blocked_end === '10:40:00', $reservation->blocked_end);
        $this->check('الفرع من فرع الموظّف — لا يُختار', (int) $reservation->branch_id === (int) $branch->id, $reservation->branch_id);
        $this->check('لا قبضَ في الحجز: المدفوع صفر والمتبقّي الإجمالي',
            (float) $reservation->paid_amount === 0.0 && abs((float) $reservation->remaining_amount - (float) $reservation->total) < 0.01);
        $this->check('خطّ الحياة يسجّل الإنشاء', $reservation->statusHistories()->count() === 1);
        $this->check('السعر من المنتج', abs((float) $reservation->unit_price - 200) < 0.01, $reservation->unit_price);

        $this->checkBlocked('الحجز بلا موظّف يُرفض', fn () => $make(['employee_id' => null, 'start_time' => '12:00']));

        // ─────────── 2. الوردية ───────────
        $this->section('الدوام من وردية الموارد البشرية');

        $this->checkBlocked('خارج الوردية يُرفض (19:00 والوردية 08–18)', fn () => $make(['start_time' => '19:00']));
        $this->checkBlocked('يوم الراحة في الوردية يُرفض (الجمعة)', fn () => $make([
            'reservation_date' => now()->addDays(2)->next('Friday')->toDateString(), 'start_time' => '10:00',
        ]));

        $noShift = $this->makeEmployee($branch, '[فحص] بلا وردية', null);
        $this->checkBlocked('موظّفٌ بلا وردية لا يُحجز له', fn () => $make(['employee_id' => $noShift->id, 'start_time' => '12:00']));
        $this->check('ولا يظهر في قائمة المؤهَّلين',
            ! $availability->eligibleEmployees(null)->contains('id', $noShift->id)
            && $availability->eligibleEmployees(null)->contains('id', $employee->id));

        if (\Illuminate\Support\Facades\Schema::hasTable('holidays')) {
            $holidayDate = now()->addDays(2)->next('Monday')->toDateString();
            // `holidays` تُلزم قائمةَ عطلٍ — قائمةٌ مؤقّتة تتراجع مع المعاملة
            $listId = DB::table('holiday_lists')->insertGetId(['name' => '[فحص] قائمة', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('holidays')->insert([
                'holiday_list_id' => $listId,
                'holiday_date' => $holidayDate, 'recurrence_type' => 'once', 'is_active' => 1,
                'named' => '[فحص] عطلة', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->checkBlocked('العطلة الرسمية في الموارد البشرية تمنع الحجز',
                fn () => $make(['reservation_date' => $holidayDate, 'start_time' => '10:00']));
        }

        $this->checkBlocked('حجز في الماضي يُرفض', fn () => $make(['reservation_date' => now()->subDays(2)->toDateString()]));

        // ─────────── 3. التعارض ───────────
        $this->section('منع الحجز المزدوج');

        $this->checkBlocked('حجز متداخل يُرفض', fn () => $make(['start_time' => '10:15']));
        $this->checkBlocked('حجز داخل وقت التنظيف يُرفض', fn () => $make(['start_time' => '10:35']));

        $adjacent = null;
        try {
            $adjacent = $make(['start_time' => '10:40']);
            $this->check('حجز بعد انتهاء التنظيف يُقبل', true, $adjacent->code);
        } catch (ReservationException $e) {
            $this->check('حجز بعد انتهاء التنظيف يُقبل', false, $e->getMessage());
        }

        $restrictedProduct = $this->makeProduct('[فحص] خدمة مقصورة', 50);
        $restricted = BookableService::create(['product_id' => $restrictedProduct->id, 'duration' => 30, 'is_active' => true]);
        $restricted->employees()->sync([$this->makeEmployee($branch, '[فحص] آخر', $shiftId)->id]);
        $this->checkBlocked('حجز خدمةٍ عند موظّفٍ غير مؤهَّل يُرفض', fn () => $make([
            'product_id' => $restrictedProduct->id, 'start_time' => '11:00',
        ]));

        $stocked = $this->makeProduct('[فحص] سلعة مخزنية', 80, \App\Enums\ProductType::PRODUCTS->value);
        $this->checkBlocked('منتجٌ غيرُ خدمي يُرفض', fn () => $make(['product_id' => $stocked->id, 'start_time' => '11:00']));

        // ─────────── 4. الأوقات المتاحة ───────────
        $this->section('الأوقات المتاحة');

        $slots = $availability->availableSlots($date, $service, $employee->id);
        $starts = array_column($slots, 'start');

        $this->check('تُولَّد أوقات شاغرة داخل الوردية', count($slots) > 0 && min($starts) >= '08:00' && max($starts) < '18:00', count($slots).' وقت');
        $this->check('الوقت المحجوز مستبعد', ! in_array('10:00', $starts, true));
        $this->check('بلا موظّف لا أوقات', $availability->availableSlots($date, $service, null) === []);

        // ─────────── 5. سير العمل ───────────
        $this->section('سير العمل');

        $transitions->transition($reservation, ReservationStatus::CONFIRMED, 'فحص ذاتي');
        $this->check('التأكيد يختم وقته', $reservation->confirmed_at !== null);

        $this->checkBlocked('انتقال غير مسموح يُرفض (مؤكَّد ← مكتمل)',
            fn () => $transitions->transition($reservation, ReservationStatus::COMPLETED));
        $this->checkBlocked('نقل موعد حجزٍ مؤكَّد من التعديل يُرفض',
            fn () => $booking->update($reservation->fresh(), ['start_time' => '10:05']));

        $transitions->transition($reservation, ReservationStatus::CHECKED_IN);
        $transitions->transition($reservation, ReservationStatus::IN_PROGRESS);
        $transitions->transition($reservation, ReservationStatus::COMPLETED);

        $this->check('بلغ الحجز حالة «مكتمل»', $reservation->status === ReservationStatus::COMPLETED);
        $this->checkBlocked('الحالة النهائية لا يُخرَج منها',
            fn () => $transitions->transition($reservation, ReservationStatus::PENDING));

        $this->section('الإلغاء يحرّر الموعد');

        if ($adjacent) {
            $transitions->transition($adjacent, ReservationStatus::CANCELLED, 'فحص ذاتي',
                ['cancellation_reason' => 'فحص ذاتي', 'cancelled_by' => auth()->id()]);
            $this->check('سبب الإلغاء محفوظ', $adjacent->fresh()->cancellation_reason === 'فحص ذاتي');

            try {
                $reused = $make(['start_time' => '10:40']);
                $this->check('الموعد المحرَّر يُعاد حجزه', true, $reused->code);
            } catch (ReservationException $e) {
                $this->check('الموعد المحرَّر يُعاد حجزه', false, $e->getMessage());
            }
        }

        // ─────────── 6. التسعير ───────────
        $this->section('التسعير والضريبة');

        $money = $pricing->quote(200, 20, 'percentage', $tax?->id);
        $this->check('الخصم النسبي يُحسب صحيحاً', abs($money['discount_amount'] - 40) < 0.01, $money['discount_amount']);

        if ($tax) {
            $this->check('الضريبة تُحسب بعد الخصم', abs($money['tax_amount'] - 24) < 0.01, $money['tax_amount']);
        }

        $capped = $pricing->quote(100, 500, 'amount');
        $this->check('الخصم لا يتجاوز الأساس', abs($capped['total']) < 0.01, $capped['total']);

        // ─────────── 7. إعادة الجدولة الذرّية ───────────
        $this->section('إعادة الجدولة — ذرّيةً');

        $make(['start_time' => '13:00']);
        $mover = $make(['start_time' => '15:00']);

        $this->checkBlocked('النقل إلى موعدٍ مشغول يُرفض',
            fn () => $booking->reschedule($mover, $date, '13:00', null, 'فحص'));
        $mover->refresh();

        $this->check('بعد الرفض: الحالة والموعد والعدّاد كما هي',
            $mover->status === ReservationStatus::PENDING
            && substr((string) $mover->start_time, 0, 5) === '15:00'
            && (int) $mover->reschedule_count === 0);

        $newDate = now()->addDays(2)->next('Tuesday')->toDateString();
        $booking->reschedule($mover, $newDate, '11:00', null, 'فحص ذاتي');
        $mover->refresh();

        $this->check('الموعد انتقل وعاد «بانتظار التأكيد»',
            $mover->reservation_date->toDateString() === $newDate && $mover->status === ReservationStatus::PENDING);
        $this->check('عدّاد إعادة الجدولة زاد والموعد السابق محفوظ',
            (int) $mover->reschedule_count === 1 && $mover->rescheduled_from_date !== null);
    }

    // ════════════════════ التهيئة ════════════════════

    /**
     * بياناتُ الفحص — تُنشأ داخل المعاملة وتُلغى معها: ورديةٌ مؤقّتة 08–18
     * من الأحد إلى الخميس، وموظّفٌ عليها، وخدمة.
     *
     * @return array{0:Branch,1:Client,2:Employee,3:BookableService,4:?TaxSitting,5:int}
     */
    private function fixtures(): array
    {
        $branch = Branch::query()->first()
            ?? Branch::create(['name' => 'فحص', 'code' => 'SC1', 'status' => 0, 'is_main' => false]);

        $client = Client::query()->first()
            ?? Client::create(['trade_name' => '[فحص ذاتي] عميل مؤقّت', 'code' => 'SELFCHECK-'.substr(md5((string) getmypid()), 0, 6)]);

        $shiftId = DB::table('shifts')->insertGetId(['name' => '[فحص ذاتي] وردية', 'type' => 1, 'created_at' => now(), 'updated_at' => now()]);

        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day) {
            DB::table('shift_days')->insert([
                'shift_id' => $shiftId, 'day' => $day,
                'working_day' => in_array($day, ['friday', 'saturday'], true) ? 0 : 1,
                'start_time' => '08:00:00', 'end_time' => '18:00:00',
                // `shift_days` تُلزم نوافذَ الدخول والخروج ومهلةَ السماح بلا قيمٍ افتراضية
                'login_start_time' => '07:00:00', 'login_end_time' => '10:00:00',
                'logout_start_time' => '16:00:00', 'logout_end_time' => '20:00:00',
                'grace_period' => 0, 'delay_calculation' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $employee = $this->makeEmployee($branch, '[فحص]', $shiftId);

        $serviceProduct = $this->makeProduct('[فحص ذاتي] خدمة مؤقّتة', 200);
        $service = BookableService::create([
            'product_id' => $serviceProduct->id,
            'duration' => 30,
            'buffer_before' => 0,
            'buffer_after' => 10,
            'is_active' => true,
        ]);
        $service->setRelation('product', $serviceProduct);

        return [$branch, $client, $employee, $service, TaxSitting::where('tax', 15)->first(), $shiftId];
    }

    private function makeEmployee(Branch $branch, string $name, ?int $shiftId): Employee
    {
        // `employees` تُلزم `email` و`Job_role_id` و`created_by` بلا قيمٍ افتراضية
        $employee = Employee::create([
            'first_name' => $name,
            'nickname' => 'مؤقّت',
            'email' => 'selfcheck-'.uniqid().'@example.invalid',
            'Job_role_id' => \App\Models\JobRole::query()->value('id') ?? 1,
            'created_by' => auth()->id() ?? 1,
            'branch_id' => $branch->id,
        ]);

        DB::table('employees')->where('id', $employee->id)->update(['shift_id' => $shiftId]);

        return $employee->refresh();
    }

    private function makeProduct(string $name, float $price, string $type = 'services'): \App\Models\Product
    {
        return \App\Models\Product::create([
            'name' => $name,
            'type' => $type,
            'sale_price' => $price,
            'status' => 'active',
            'category_id' => \App\Models\Category::query()->value('id') ?? 1,
            'created_by' => auth()->id() ?? 1,
        ]);
    }

    // ════════════════════ أدوات الفحص ════════════════════

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<fg=cyan>── {$title}</>");
    }

    private function check(string $label, bool $passed, $detail = null): void
    {
        if ($passed) {
            $this->passed++;
            $this->line('  <fg=green>✓</> '.$label.($detail !== null ? " <fg=gray>({$detail})</>" : ''));

            return;
        }

        $this->failed++;
        $this->failures[] = $label.($detail !== null ? " — {$detail}" : '');
        $this->line('  <fg=red>✗</> '.$label.($detail !== null ? " <fg=gray>({$detail})</>" : ''));
    }

    /** يتوقّع أن ترمي العمليةُ `ReservationException` — أي أن الحارس حيّ. */
    private function checkBlocked(string $label, callable $operation): void
    {
        try {
            $operation();
            $this->check($label, false, 'لم يُمنع!');
        } catch (ReservationException $e) {
            $this->check($label, true, mb_substr($e->getMessage(), 0, 70));
        }
    }
}

<?php

namespace Modules\Client\Reservations\Services;

use App\Support\OperationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Events\ReservationCreated;
use Modules\Client\Reservations\Events\ReservationRescheduled;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationService as BookableService;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Models\ReservationStatusHistory;

/**
 * إنشاءُ الحجز وتعديلُه وإعادةُ جدولته وحذفُه — المعاملةُ والقفل.
 *
 * ═══════════════ لماذا القفل؟ ═══════════════
 *
 * فحصُ التوفّر وحدَه **لا يمنع الحجز المزدوج**. موظّفا استقبالٍ يحجزان
 * الموعدَ نفسَه في اللحظة نفسها: يفحص الأولُ فيجده خالياً، ويفحص الثاني
 * فيجده خالياً (لأن الأول لم يكتب بعد)، ثمّ يكتبان معاً — فيخرج موعدٌ
 * بحجزَين ولا خطأ يظهر.
 *
 * والحلُّ ليس فهرساً فريداً: الفريدُ يمنع التطابقَ التامّ ولا يمنع
 * **التداخل** (10:00–11:00 مع 10:30–11:30 صفّان مختلفان تماماً).
 *
 *     DB::transaction ┬─► قفلُ صفوف الموظف في ذلك اليوم (lockForUpdate)
 *                     ├─► فحصُ التوفّر — يقرأ ما خلف القفل
 *                     └─► الكتابة
 *
 * ═══════════════ ورقمُ المستند داخل المعاملة كذلك ═══════════════
 *
 * `next_document_number()` يقفل صفَّ العدّاد بنفسه، فطلبان متزامنان لا
 * ينتجان الرقمَ نفسَه. ووضعُه داخل معاملتنا يعني أن رقماً استُهلك في
 * محاولةٍ فشلت يعود بالتراجع — فلا ثغرةَ في التسلسل.
 *
 * ═══════════════ ولا مالَ في الحجز ═══════════════
 *
 * الحجزُ موعدٌ وسعرٌ معروض — لا قبضَ فيه ولا قيد. المالُ كلُّه على فاتورة
 * المبيعات التي يُحوَّل إليها الحجز من شاشته («تحويل إلى فاتورة»)، بقيود
 * المبيعات نفسِها وحساب العميل نفسِه. فلا حساباتٍ مكرّرة (طلب المستخدم،
 * 2026-09-10). `paid_amount` يبقى صفراً و`remaining_amount` = الإجمالي.
 *
 * ═══════════════ والفرعُ من الموظّف ═══════════════
 *
 * الحجزُ لا يُختار له فرع: يُنسب إلى الفرع الرئيسي لموظّفه
 * (`employee_branch.is_main`، وإلّا `employees.branch_id`) — لنطاق الرؤية
 * والتقارير وحدها.
 */
class ReservationBookingService
{
    public function __construct(
        protected ReservationAvailabilityService $availability,
        protected ReservationPricingService $pricing,
        protected ReservationStatusTransitionService $transitions,
    ) {}

    /**
     * إنشاءُ حجز.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Reservation
    {
        $settings = ReservationSetting::current();
        $service = $this->resolveBookable($data);

        [$date, $start, $end, $duration] = $this->resolveWindow($data, $service, $settings);

        $employeeId = ! empty($data['employee_id']) ? (int) $data['employee_id'] : null;
        $clientId = $data['client_id'] ?? null;

        if (! $employeeId) {
            throw ReservationException::because(__('reservations.errors.employee_required'));
        }

        if ($settings->require_client && ! $clientId) {
            throw ReservationException::because(__('reservations.errors.client_required'));
        }

        // الفرعُ من الموظّف — لا يُختار (انظر ترويسة الصنف)
        $branchId = $this->branchForEmployee($employeeId) ?? current_branch_id();

        $this->assertEmployeeEligible($service, $employeeId);

        $money = $this->pricing->quoteForService(
            $service,
            isset($data['unit_price']) && $data['unit_price'] !== '' ? (float) $data['unit_price'] : null,
            (float) ($data['discount_value'] ?? 0),
            (string) ($data['discount_type'] ?? 'amount'),
            $data['tax_id'] ?? null,
            // «بلا ضريبة» المُرسَلة صراحةً تعني بلا ضريبة — لا العودةَ إلى ضريبة الخدمة
            array_key_exists('tax_id', $data)
        );

        /*
            الحالةُ عند الإنشاء: مسودةٌ أو انتظار — والتأكيدُ لمن يملك صلاحيتَه.

            كان أيُّ `status` يُكتب كما وصل: «مكتمل» أو «حضر» بصلاحية الإنشاء
            وحدَها، بلا انتقالٍ ولا صفٍّ في خطّ الحياة ولا حدث. الآن ما بعد
            الانتظار يُرفض، والتأكيدُ **ينتقل** إليه بعد الإنشاء من الباب
            الوحيد (`ReservationStatusTransitionService`): يُختَم `confirmed_at`،
            ويُكتب الصفُّ، ويُطلق `ReservationConfirmed` فيصل الإشعارُ للعميل.
        */
        $status = ReservationStatus::from_value($data['status'] ?? $settings->default_status);
        $storable = ReservationStatus::storableAtCreate(! auth()->check() || hasPermission('reservations.confirm'));

        if (! in_array($status, $storable, true)) {
            throw ReservationException::because(
                __('reservations.more.status_not_storable', ['status' => $status->label()])
            );
        }

        $confirmAfterCreate = $status === ReservationStatus::CONFIRMED;

        if ($confirmAfterCreate) {
            $status = ReservationStatus::PENDING;
        }

        return DB::transaction(function () use (
            $data, $service, $date, $start, $end, $duration,
            $branchId, $employeeId, $clientId, $money, $status, $confirmAfterCreate
        ) {
            // القفل قبل الفحص — انظر ترويسة الصنف
            $this->lockDay($date, $employeeId);

            $this->availability->assertSlotAvailable(
                $date, $start, $end, $employeeId, $branchId, $service, null, $clientId
            );

            [$blockedStart, $blockedEnd] = $this->blockedWindow($start, $end, $service);

            $reservation = new Reservation([
                'code' => next_document_number('reservation'),
                'branch_id' => $branchId,
                'client_id' => $clientId,
                'employee_id' => $employeeId,
                // المنتجُ هويّةُ الخدمة؛ والملفُّ يُربط حين يكون محفوظاً فقط
                'service_id' => $service?->exists ? $service->id : null,
                'product_id' => $service?->product_id,
                'reservation_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'blocked_start' => $blockedStart,
                'blocked_end' => $blockedEnd,
                'duration' => $duration,
                'status' => $status,
                'tax_id' => $money['tax_id'],
                'unit_price' => $money['unit_price'],
                'subtotal' => $money['subtotal'],
                'discount_value' => (float) ($data['discount_value'] ?? 0),
                'discount_type' => (string) ($data['discount_type'] ?? 'amount'),
                'discount_amount' => $money['discount_amount'],
                'tax_amount' => $money['tax_amount'],
                'total' => $money['total'],
                // لا قبضَ في الحجز — المالُ على فاتورة المبيعات
                'paid_amount' => 0,
                'remaining_amount' => $money['total'],
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? 'back_office',
                'created_by' => auth()->id(),
            ]);

            // العملة والفرع والفترة تُختَم في النموذج، لا هنا
            $reservation->save();

            ReservationStatusHistory::create([
                'reservation_id' => $reservation->id,
                'from_status' => null,
                'to_status' => $status->value,
                'note' => __('reservations.timeline.created'),
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);

            $this->appointments()->sync($reservation);

            OperationLog::record(
                'created',
                'Reservations',
                $reservation,
                "إنشاء الحجز {$reservation->code} للعميل {$reservation->client_name} بتاريخ {$date} الساعة ".substr($start, 0, 5)
            );

            DB::afterCommit(fn () => event(new ReservationCreated($reservation)));

            // أُنشئ «مؤكَّداً»: الانتقالُ الحقيقيّ — ختمٌ وصفٌّ في خطّ الحياة وحدثٌ ومزامنةُ موعد
            if ($confirmAfterCreate) {
                $this->transitions->transition(
                    $reservation,
                    ReservationStatus::CONFIRMED,
                    __('reservations.more.confirmed_at_create')
                );
            }

            return $reservation;
        });
    }

    /**
     * تعديلُ حجز — بالقيود نفسِها التي حكمت إنشاءه.
     *
     * **والموعدُ لا يُنقل من هنا بعد التأكيد.** نقلُ حجزٍ مؤكَّد إعادةُ
     * جدولة: لها مهلةٌ وحدٌّ لعدد المرّات، وتُعيده إلى الانتظار، وتحفظ
     * الموعدَ السابق في خطّ حياته. وكان نموذجُ التعديل يتجاوز ذلك كلَّه —
     * بابٌ خلفيٌّ لإعادة جدولةٍ بلا حدٍّ ولا أثر. وبعد حضور العميل لا يُنقل
     * الموعدُ أصلاً: الخدمةُ تجري فيه.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Reservation $reservation, array $data): Reservation
    {
        if ($reservation->status->isFinal()) {
            throw ReservationException::because(
                __('reservations.errors.cannot_edit_final', ['status' => $reservation->status->label()])
            );
        }

        /*
            السعرُ لا يُعدَّل بعد صدور الفاتورة. تعديلُه يترك الفاتورةَ
            على مبلغها والحجزَ على مبلغٍ آخر — وأيُّهما الصحيح سؤالٌ بلا
            جواب. التصحيحُ يمرّ بمستندات المبيعات، وهو قرارٌ محاسبيّ صريح.
        */
        $touchesMoney = $this->touchesMoney($reservation, $data);

        if ($reservation->isInvoiced() && $touchesMoney) {
            throw ReservationException::because(__('reservations.errors.money_locked_by_invoice'));
        }

        $settings = ReservationSetting::current();
        $service = (array_key_exists('product_id', $data) || array_key_exists('service_id', $data))
            ? $this->resolveBookable($data)
            : $reservation->bookingProfile();

        $merged = array_merge([
            'reservation_date' => $reservation->reservation_date?->toDateString(),
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'duration' => $reservation->duration,
        ], $data);

        [$date, $start, $end, $duration] = $this->resolveWindow($merged, $service, $settings);

        $employeeId = array_key_exists('employee_id', $data)
            ? (! empty($data['employee_id']) ? (int) $data['employee_id'] : null)
            : $reservation->employee_id;
        $clientId = $data['client_id'] ?? $reservation->client_id;

        if (! $employeeId) {
            throw ReservationException::because(__('reservations.errors.employee_required'));
        }

        // الفرعُ يتبع الموظّف — تبديلُ الموظّف ينقل الحجزَ إلى فرعه
        $branchId = $this->branchForEmployee($employeeId) ?? $reservation->branch_id;

        $movesSlot = $date !== $reservation->reservation_date?->toDateString()
            || $start !== $this->pad($reservation->start_time)
            || $end !== $this->pad($reservation->end_time)
            || (int) $employeeId !== (int) $reservation->employee_id;

        if ($movesSlot) {
            if (in_array($reservation->status, [ReservationStatus::CHECKED_IN, ReservationStatus::IN_PROGRESS], true)) {
                throw ReservationException::because(__('reservations.errors.started_cannot_move'));
            }

            if ($reservation->status === ReservationStatus::CONFIRMED) {
                throw ReservationException::because(__('reservations.errors.confirmed_use_reschedule'));
            }
        }

        if ((int) $employeeId !== (int) $reservation->employee_id || (int) $service?->product_id !== (int) $reservation->product_id) {
            $this->assertEmployeeEligible($service, $employeeId);
        }

        /*
            فرقُ القيم يُلتقط **قبل** الحفظ: `OperationLog::diff()` تقرأ
            `getOriginal()`، وبعد `save()` تصير الأصولُ هي المحفوظةَ نفسَها
            فيعود الفرقُ فارغاً دائماً.
        */
        $tracked = ['reservation_date', 'start_time', 'end_time', 'employee_id', 'service_id', 'client_id', 'total', 'notes'];
        $changes = [];

        DB::transaction(function () use (
            $reservation, $data, $service, $date, $start, $end, $duration,
            $branchId, $employeeId, $clientId, $touchesMoney, $tracked, &$changes
        ) {
            // الحالةُ والفاتورةُ تُقرآن ثانيةً خلف القفل — ربّما تغيّرتا منذ فتح النموذج
            $reservation->lockForWrite();

            if ($reservation->status->isFinal()) {
                throw ReservationException::because(
                    __('reservations.errors.cannot_edit_final', ['status' => $reservation->status->label()])
                );
            }

            // `lockForWrite` أعاد قراءةَ الأعمدة — فتُقرأ الفاتورةُ من جديد لا من ذاكرةٍ سبقت القفل
            $reservation->unsetRelation('invoice');

            if ($reservation->isInvoiced() && $touchesMoney) {
                throw ReservationException::because(__('reservations.errors.money_locked_by_invoice'));
            }

            $this->lockDay($date, $employeeId);

            /*
                الحارسُ يُسأل فقط حين يتغيّر ما يراه: الموعدُ أو الموظّفُ
                (التعارض والوردية) أو الخدمةُ (الفواصل والحدّ اليومي) أو
                العميلُ (التكرار). تعديلُ الملاحظات أو السعر على حجزٍ مضى
                وقتُه كان يُرفض بـ«وقتٌ مضى» مع أن موعدَه لم يُمسّ.
            */
            $slotTouched = $date !== $reservation->reservation_date?->toDateString()
                || $start !== $this->pad($reservation->start_time)
                || $end !== $this->pad($reservation->end_time)
                || (int) $employeeId !== (int) $reservation->employee_id
                || (int) $service?->product_id !== (int) $reservation->product_id
                || (int) $clientId !== (int) $reservation->client_id;

            if ($slotTouched) {
                $this->availability->assertSlotAvailable(
                    $date, $start, $end, $employeeId, $branchId, $service, $reservation->id, $clientId
                );
            }

            [$blockedStart, $blockedEnd] = $this->blockedWindow($start, $end, $service);

            $reservation->fill([
                'client_id' => $clientId,
                'employee_id' => $employeeId,
                'branch_id' => $branchId,
                // المنتجُ هويّةُ الخدمة؛ والملفُّ يُربط حين يكون محفوظاً فقط
                'service_id' => $service?->exists ? $service->id : null,
                'product_id' => $service?->product_id,
                'reservation_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'blocked_start' => $blockedStart,
                'blocked_end' => $blockedEnd,
                'duration' => $duration,
                // `??` كان يمنع مسحَ الملاحظة: الفارغُ المُرسَل يُقرأ «لم يُرسَل»
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $reservation->notes,
                'updated_by' => auth()->id(),
            ]);

            if ($touchesMoney) {
                /*
                    الضريبةُ: ما أُرسل — ولو فارغاً، فـ«بلا ضريبة» اختيارٌ لا
                    غياب — وإلّا ضريبةُ الحجز المحفوظة كما هي. وفي الحالين
                    الاختيارُ صريح فلا يُعاد إلى ضريبة الخدمة.
                */
                $money = $this->pricing->quoteForService(
                    $service,
                    isset($data['unit_price']) && $data['unit_price'] !== '' ? (float) $data['unit_price'] : null,
                    (float) ($data['discount_value'] ?? $reservation->discount_value),
                    (string) ($data['discount_type'] ?? $reservation->discount_type),
                    array_key_exists('tax_id', $data) ? $data['tax_id'] : $reservation->tax_id,
                    true
                );

                $reservation->fill([
                    'tax_id' => $money['tax_id'],
                    'unit_price' => $money['unit_price'],
                    'subtotal' => $money['subtotal'],
                    'discount_value' => (float) ($data['discount_value'] ?? $reservation->discount_value),
                    'discount_type' => (string) ($data['discount_type'] ?? $reservation->discount_type),
                    'discount_amount' => $money['discount_amount'],
                    'tax_amount' => $money['tax_amount'],
                    'total' => $money['total'],
                    'remaining_amount' => $money['total'],
                ]);
            }

            $changes = OperationLog::diff($reservation, $tracked);

            $reservation->save();

            $this->appointments()->sync($reservation);
        });

        OperationLog::record('updated', 'Reservations', $reservation, "تعديل الحجز {$reservation->code}", $changes);

        return $reservation;
    }

    /**
     * إعادةُ جدولة — نقلُ الحجز إلى موعدٍ آخر (وموظّفٍ آخر إن شاء) مع حفظ
     * موعده السابق. **في معاملةٍ واحدة.**
     *
     * كانت الحالةُ تنتقل إلى «معاد جدولته» وتلتزم، ثمّ يُفحص الموعدُ الجديد
     * في معاملةٍ ثانية. فإن كان مشغولاً ظهرت رسالةُ الخطأ — والحجزُ قد
     * صار «معاد جدولته» على موعده **القديم**، وهي حالةٌ لا تشغل موعداً:
     * تحرّر مقعدَه لغيره وتتركه عالقاً. الآن يتراجع كلُّ شيء معاً.
     *
     * والحالةُ تمرّ بـ`RESCHEDULED` ثمّ تعود إلى `PENDING`: المرورُ يكتب
     * صفّاً في خطّ الحياة يُقرأ منه تقريرُ إعادة الجدولة، والعودةُ لأنّ
     * الموعدَ الجديد ينتظر تأكيداً كأيّ حجزٍ جديد.
     *
     * **والموظّفُ يبقى ما لم يُبدَّل صراحةً** (`$employeeId = null` تعني «كما
     * هو»)، وإن بُدّل تبعه الفرعُ: الحجزُ يُنسب إلى فرع موظّفه — كما في
     * الإنشاء والتعديل — فلا يبقى على فرع الموظّف القديم فيغيب عن قائمة
     * فرعه الجديد ويدخل عدَّ فرعٍ لم يعد يخصّه.
     */
    public function reschedule(
        Reservation $reservation,
        $date,
        string $start,
        ?string $end = null,
        ?string $note = null,
        ?int $employeeId = null
    ): Reservation {
        $settings = ReservationSetting::current();
        $service = $reservation->bookingProfile();

        [$newDate, $newStart, $newEnd, $duration] = $this->resolveWindow([
            'reservation_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'duration' => $reservation->duration,
        ], $service, $settings);

        $newEmployee = $employeeId ?: (int) $reservation->employee_id;
        $employeeChanges = (int) $newEmployee !== (int) $reservation->employee_id;

        if ($employeeChanges) {
            $this->assertEmployeeEligible($service, $newEmployee);
        }

        $newBranch = $employeeChanges
            ? ($this->branchForEmployee($newEmployee) ?? $reservation->branch_id)
            : $reservation->branch_id;

        return DB::transaction(function () use (
            $reservation, $newDate, $newStart, $newEnd, $duration, $service, $newEmployee, $newBranch, $note
        ) {
            $reservation->lockForWrite();

            $old = [
                'reservation_date' => $reservation->reservation_date?->toDateString(),
                'start_time' => $this->pad($reservation->start_time),
                'end_time' => $this->pad($reservation->end_time),
                'employee_id' => $reservation->employee_id,
            ];

            $new = [
                'reservation_date' => $newDate,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'employee_id' => $newEmployee,
            ];

            if ($old == $new) {
                throw ReservationException::because(__('reservations.errors.reschedule_same_slot'));
            }

            // الحارسُ يفحص المُهَل وحدَّ المحاولات على الموعد **القديم**
            $this->transitions->transition(
                $reservation,
                ReservationStatus::RESCHEDULED,
                $note ?: __('reservations.timeline.moved_from', [
                    'date' => $old['reservation_date'],
                    'time' => substr($old['start_time'], 0, 5),
                ])
            );

            $this->lockDay($newDate, $newEmployee);

            $this->availability->assertSlotAvailable(
                $newDate, $newStart, $newEnd,
                $newEmployee, $newBranch,
                $service, $reservation->id, $reservation->client_id
            );

            [$blockedStart, $blockedEnd] = $this->blockedWindow($newStart, $newEnd, $service);

            $reservation->fill([
                'reservation_date' => $newDate,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'blocked_start' => $blockedStart,
                'blocked_end' => $blockedEnd,
                'duration' => $duration,
                'employee_id' => $newEmployee,
                'branch_id' => $newBranch,
                'rescheduled_from_date' => $old['reservation_date'],
                'rescheduled_from_start' => $old['start_time'],
                'reschedule_count' => (int) $reservation->reschedule_count + 1,
                'updated_by' => auth()->id(),
            ]);

            $reservation->save();

            // ويعود إلى الانتظار: الموعدُ الجديد يستحقّ تأكيداً جديداً
            $this->transitions->transition(
                $reservation,
                ReservationStatus::PENDING,
                __('reservations.timeline.new_slot', [
                    'date' => $newDate,
                    'time' => substr($newStart, 0, 5),
                ])
            );

            OperationLog::record(
                'rescheduled',
                'Reservations',
                $reservation,
                "إعادة جدولة الحجز {$reservation->code} من {$old['reservation_date']} ".substr($old['start_time'], 0, 5)
                ." إلى {$newDate} ".substr($newStart, 0, 5),
                ['old' => $old, 'new' => $new]
            );

            DB::afterCommit(fn () => event(new ReservationRescheduled($reservation)));

            return $reservation;
        });
    }

    /**
     * حذفُ حجز — وحدَه ما لا أثرَ ماليّاً له يُحذف.
     *
     * والحذفُ ناعم (`SoftDeletes`): مستندٌ رآه العميلُ لا يختفي بلا أثر.
     * أمّا المرتبطُ بفاتورةٍ أو بأيّ حركةٍ ماليّة — ولو رُدّت كلُّها — فلا
     * يُحذف أصلاً: يُلغى، فالإلغاءُ يترك الحجزَ شاهداً على قيوده.
     */
    public function delete(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $reservation->lockForWrite();
            $reservation->unsetRelation('invoice');

            // بفاتورةٍ **قائمة** — معرّفٌ يشير إلى فاتورةٍ حُذفت لا يمنع الحذف
            if ($reservation->isInvoiced()) {
                throw ReservationException::because(__('reservations.errors.delete_invoiced'));
            }

            if ((float) $reservation->paid_amount > 0 || $reservation->payments()->exists()) {
                throw ReservationException::because(__('reservations.errors.delete_with_payments'));
            }

            $reservation->delete();

            $this->appointments()->retire($reservation);
        });

        OperationLog::record('deleted', 'Reservations', $reservation, "حذف الحجز {$reservation->code}");
    }

    // ════════════════════ أدوات ════════════════════

    /** موعدُ الحجز في شاشة المواعيد — انظر `ReservationAppointmentSync`. */
    protected function appointments(): ReservationAppointmentSync
    {
        return app(ReservationAppointmentSync::class);
    }

    /**
     * قفلُ صفوف اليوم للموظف — الخطوةُ التي تمنع الحجز المزدوج فعلاً.
     *
     * القفلُ على **صفوف الموظف في ذلك اليوم** لا على الجدول: قفلُ الجدول
     * يُسلسل كلَّ حجوزات المنشأة خلف بعضها. وبلا موظّفٍ محدَّد لا تعارضَ
     * يُمنَع، فلا قفلَ يلزم.
     */
    protected function lockDay($date, ?int $employeeId): void
    {
        if (! $employeeId) {
            return;
        }

        Reservation::query()
            ->whereDate('reservation_date', Carbon::parse($date)->toDateString())
            ->where('employee_id', $employeeId)
            ->lockForUpdate()
            ->pluck('id');
    }

    /**
     * الخدمةُ المحجوزة — **منتجٌ خدمي** وملفُّ حجزه.
     *
     * `product_id` هو المدخلُ الأصلي. و`service_id` وحدَه مقبولٌ للتوافق مع
     * الحجوز والنداءات القديمة: يُقرأ منه منتجُه. وملفٌّ قديمٌ بلا منتج يُحجَز
     * كما كان — لا تُكسر بياناتٌ سابقة.
     *
     * والرفضُ صريح: سلعةٌ مخزنية لا تُحجَز (`ServiceProductCatalog::resolve`)،
     * ومنتجٌ معطَّل أو ملفٌّ عطّله المسؤولُ لا يُحجزان.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveBookable(array $data): ?BookableService
    {
        $productId = $data['product_id'] ?? null;

        if (! $productId && ! empty($data['service_id'])) {
            $legacy = BookableService::with('product')->find($data['service_id']);

            if (! $legacy) {
                throw ReservationException::because(__('reservations.errors.service_missing'));
            }

            if (! $legacy->product_id) {
                if (! $legacy->is_active) {
                    throw ReservationException::because(
                        __('reservations.errors.service_inactive', ['service' => $legacy->displayName()])
                    );
                }

                return $legacy;
            }

            $productId = $legacy->product_id;
        }

        if (! $productId) {
            return null;
        }

        $profile = BookableService::profileFor(ServiceProductCatalog::resolve($productId));

        if ($profile->exists && ! $profile->is_active) {
            throw ReservationException::because(
                __('reservations.errors.service_inactive', ['service' => $profile->displayName()])
            );
        }

        return $profile;
    }

    /**
     * الموظّفُ مؤهَّلٌ للخدمة؟ — فحصُ الخادم لا فلترُ القائمة وحده.
     *
     * منتقي الموظفين في النموذج يعرض المؤهَّلين فقط، لكنّ القائمةَ ليست
     * حارساً: طلبٌ معدَّل (أو شاشةُ تعديلٍ فُتحت قبل تغيير الأهليّة) يحجز
     * لخدمةٍ عند من لا يقدّمها. وقائمةُ المؤهَّلين الفارغة تعني «الجميع».
     */
    protected function assertEmployeeEligible(?BookableService $service, ?int $employeeId): void
    {
        // قيدُ الإعدادات (قسمٌ أو مسمّى) يسري على كلّ حجز — بخدمةٍ أو بدونها
        if ($employeeId && ! ReservationSetting::current()->employeeAllowed($employeeId)) {
            throw ReservationException::because(__('reservations.errors.employee_outside_filter'));
        }

        // ملفٌّ غيرُ محفوظ لا قائمةَ مؤهَّلين له — أي «الجميع»
        if (! $service || ! $employeeId || ! $service->exists) {
            return;
        }

        $eligible = $service->employees()->pluck('employees.id')->map(fn ($id) => (int) $id)->all();

        if ($eligible !== [] && ! in_array($employeeId, $eligible, true)) {
            throw ReservationException::because(
                __('reservations.errors.employee_not_eligible', ['service' => $service->displayName()])
            );
        }
    }

    /**
     * هل يمسّ هذا التعديلُ المال فعلاً؟
     *
     * نموذجُ التعديل يرسل الحقولَ كلَّها في كلّ حفظ — فوجودُ المفتاح لا
     * يعني تغيّره. والمقارنةُ بالقيمة، وإلّا امتنع تعديلُ ملاحظةٍ على حجزٍ
     * مفوتَر لأنّ السعرَ وصل مع الطلب كما هو.
     *
     * @param  array<string, mixed>  $data
     */
    protected function touchesMoney(Reservation $reservation, array $data): bool
    {
        $same = fn ($a, $b) => (string) ($a ?? '') === (string) ($b ?? '');

        if (array_key_exists('product_id', $data) && ! $same($data['product_id'], $reservation->product_id)) {
            return true;
        }

        if (! array_key_exists('product_id', $data) && array_key_exists('service_id', $data)
            && ! $same($data['service_id'], $reservation->service_id)) {
            return true;
        }

        if (array_key_exists('tax_id', $data) && ! $same($data['tax_id'], $reservation->tax_id)) {
            return true;
        }

        if (array_key_exists('discount_type', $data) && ! $same($data['discount_type'], $reservation->discount_type)) {
            return true;
        }

        foreach (['unit_price', 'discount_value'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== '' && $data[$key] !== null
                && abs((float) $data[$key] - (float) $reservation->{$key}) > 0.009) {
                return true;
            }
        }

        return false;
    }

    /**
     * التاريخُ والوقتان والمدّة — مستنتَجةً بالترتيب الصحيح.
     *
     * وقتُ النهاية يُشتقّ من المدّة إن لم يُرسَل، والمدّةُ من الخدمة إن لم
     * تُرسَل، والافتراضُ العامّ آخرَ الأمر. فلا يُطلَب من المستخدم إدخالُ
     * ما يعرفه النظام.
     *
     * @param  array<string, mixed>  $data
     * @return array{0:string,1:string,2:string,3:int}
     */
    protected function resolveWindow(array $data, ?BookableService $service, ReservationSetting $settings): array
    {
        $date = Carbon::parse($data['reservation_date'] ?? now())->toDateString();
        $start = $this->pad($data['start_time'] ?? '09:00');

        $duration = (int) ($data['duration'] ?? 0)
            ?: (int) ($service?->duration ?? 0)
            ?: (int) $settings->default_duration;

        $end = ! empty($data['end_time'])
            ? $this->pad($data['end_time'])
            : gmdate('H:i:s', min(86399, $this->toSeconds($start) + $duration * 60));

        // المدّةُ المخزَّنة هي الفعليّة بين الوقتين — لا التي طُلبت
        $duration = (int) round(($this->toSeconds($end) - $this->toSeconds($start)) / 60);

        return [$date, $start, $end, max(0, $duration)];
    }

    /**
     * فرعُ الموظّف الرئيسي — الحجزُ يُنسب إليه ولا يُختار له فرع.
     *
     * الموظّفُ قد يتبع أكثر من فرع (`employee_branch`)؛ الرئيسيُّ منها
     * (`is_main`) أوّلاً، ثمّ أقدمُها، ثمّ `employees.branch_id`.
     */
    public function branchForEmployee(?int $employeeId): ?int
    {
        if (! $employeeId) {
            return null;
        }

        $branchId = DB::table('employee_branch')
            ->where('employee_id', $employeeId)
            ->orderByDesc('is_main')
            ->orderBy('id')
            ->value('branch_id');

        $branchId = $branchId ?: DB::table('employees')->where('id', $employeeId)->value('branch_id');

        return $branchId ? (int) $branchId : null;
    }

    /**
     * الفترةُ المحجوزة فعلاً على تقويم الموظّف — تُخزَّن مع الحجز.
     *
     * العميلُ يرى `start_time`–`end_time` ويدفع مقابلها، والموظّفُ مشغولٌ
     * بها وبتحضيرها وتنظيفها. انظر
     * `2026_09_08_000009_add_blocked_window_to_reservations_table`.
     *
     * @return array{0:string,1:string}
     */
    protected function blockedWindow(string $start, string $end, ?BookableService $service): array
    {
        $before = (int) ($service?->buffer_before ?? 0);
        $after = (int) ($service?->buffer_after ?? 0);

        return [
            gmdate('H:i:s', max(0, $this->toSeconds($start) - $before * 60)),
            gmdate('H:i:s', min(86399, $this->toSeconds($end) + $after * 60)),
        ];
    }

    protected function pad($time): string
    {
        $time = substr((string) $time, 0, 8);

        return strlen($time) === 5 ? $time.':00' : $time;
    }

    protected function toSeconds(string $time): int
    {
        [$h, $m, $s] = array_pad(array_map('intval', explode(':', $this->pad($time))), 3, 0);

        return ($h * 3600) + ($m * 60) + $s;
    }
}

<?php

namespace Modules\Client\Reservations\Models;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\TaxSitting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إعدادات وحدة الحجوزات — صفٌّ واحد (singleton).
 *
 *     ReservationSetting::current()->allow_overbooking
 *
 * الجدولُ `reservation_settings` هو نفسُه الجدولُ الذي كان اسمه
 * `reservations` قبل تصحيح التسمية، ويقرؤه أيضاً `App\Models\BookingSetting`
 * القديم — أُبقي توافقاً خلفياً ولا يُبنى عليه جديد.
 *
 * **كلُّ مفتاحٍ هنا يحرس شيئاً.** مفتاحٌ يُعرَض في الشاشة ولا يقرؤه أحد
 * يَعِد المستخدمَ بضبطٍ لا يقع — وهو عطلٌ صامت أسوأ من غياب المفتاح.
 * قائمةُ من يقرأ كلَّ مفتاح في `docs/reservations/DEVELOPER_GUIDE.md`.
 */
class ReservationSetting extends Model
{
    protected $table = 'reservation_settings';

    protected $fillable = [
        // عام
        'is_enabled', 'default_branch_id', 'default_status', 'allow_guest_booking',
        'allow_repeated_booking', 'allow_overbooking', 'max_daily_reservations',
        // من يُحجز له — قسمٌ أو مسمّى وظيفي
        'employee_filter', 'employee_department_ids', 'employee_job_title_ids',
        // المواعيد
        'default_duration', 'min_duration', 'max_duration', 'buffer_time', 'slot_interval',
        'min_advance_minutes', 'max_advance_days', 'treat_unset_days_as_closed',
        // الإلغاء
        'allow_cancellation', 'cancellation_deadline_hours', 'allow_cancel_after_confirm',
        'cancellation_fee_type', 'cancellation_fee_value',
        // إعادة الجدولة
        'allow_rescheduling', 'reschedule_deadline_hours', 'max_reschedule_attempts',
        // العميل
        'require_client', 'allow_new_client', 'require_mobile', 'require_email',
        // الإشعارات
        'notify_created', 'notify_confirmed', 'notify_cancelled', 'notify_rescheduled',
        'notify_reminder', 'notify_reminder_employee', 'notify_completed', 'notify_no_show', 'reminder_offsets',
        // المال
        'require_deposit', 'deposit_type', 'deposit_value', 'allow_overpayment',
        'auto_invoice_on', 'default_payment_method_id', 'default_treasury_id', 'default_tax_id',
        // متقدّم
        'calendar_default_view', 'payment_online', 'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'allow_guest_booking' => 'boolean',
        'allow_repeated_booking' => 'boolean',
        'allow_overbooking' => 'boolean',
        'max_daily_reservations' => 'integer',
        'employee_department_ids' => 'array',
        'employee_job_title_ids' => 'array',
        'default_duration' => 'integer',
        'min_duration' => 'integer',
        'max_duration' => 'integer',
        'buffer_time' => 'integer',
        'slot_interval' => 'integer',
        'min_advance_minutes' => 'integer',
        'max_advance_days' => 'integer',
        'treat_unset_days_as_closed' => 'boolean',
        'allow_cancellation' => 'boolean',
        'cancellation_deadline_hours' => 'integer',
        'allow_cancel_after_confirm' => 'boolean',
        'cancellation_fee_value' => 'decimal:2',
        'allow_rescheduling' => 'boolean',
        'reschedule_deadline_hours' => 'integer',
        'max_reschedule_attempts' => 'integer',
        'require_client' => 'boolean',
        'allow_new_client' => 'boolean',
        'require_mobile' => 'boolean',
        'require_email' => 'boolean',
        'notify_created' => 'boolean',
        'notify_confirmed' => 'boolean',
        'notify_cancelled' => 'boolean',
        'notify_rescheduled' => 'boolean',
        'notify_reminder' => 'boolean',
        'notify_reminder_employee' => 'boolean',
        'notify_completed' => 'boolean',
        'notify_no_show' => 'boolean',
        'require_deposit' => 'boolean',
        'deposit_value' => 'decimal:2',
        'allow_overpayment' => 'boolean',
    ];

    /**
     * @var self|null memoized لعمر الطلب — تُسأل من كل شاشة ومن كل تحقّق.
     */
    private static ?self $cached = null;

    /**
     * الصفُّ الوحيد — يُنشأ بالافتراضات إن لم يوجد بعد.
     *
     * **و`refresh()` ليست زينة.** `firstOrCreate([], [])` تُدرج صفّاً بلا
     * أعمدة، فتُطبّق القاعدةُ افتراضاتِ الأعمدة الخمسين — لكنّ النموذجَ
     * العائد يحمل `id` وحده وكلُّ إعدادٍ فيه `null`. فأوّلُ قراءةٍ بعد
     * تركيبٍ جديد ترى «مدّة افتراضية = صفر» و«مهلُ تذكيرٍ = لا شيء»،
     * والقاعدةُ تحمل 30 و«1440,120». الجلبُ من القاعدة يُنهي الفرق.
     */
    public static function current(): self
    {
        if (static::$cached !== null) {
            return static::$cached;
        }

        $row = static::query()->first();

        if (! $row) {
            $row = static::query()->create([]);
            $row->refresh();
        }

        return static::$cached = $row;
    }

    /** يُنسى الصفُّ المخزَّن — بعد الحفظ وفي الاختبارات. */
    public static function forget(): void
    {
        static::$cached = null;
    }

    // ════════════════════ العلاقات ════════════════════

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    public function defaultTax(): BelongsTo
    {
        return $this->belongsTo(TaxSitting::class, 'default_tax_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ════════════════════ قراءات مشتقّة ════════════════════

    /**
     * مهلُ التذكير بالدقائق، مرتَّبةً تنازلياً ومنقّاةً.
     *
     * الحقلُ نصٌّ حرّ يكتبه المستخدم («1440, 120, 30»)، فقد يحمل فراغاتٍ
     * وقيماً فارغةً وأصفاراً وتكراراً. والتنقيةُ هنا مرّةً واحدة — لا في
     * أمر الكرون الذي يقرؤه كل يوم.
     *
     * @return array<int, int>
     */
    public function reminderOffsets(): array
    {
        $raw = array_map('trim', explode(',', (string) $this->reminder_offsets));

        $minutes = array_filter(array_map('intval', $raw), fn (int $m) => $m > 0);

        $minutes = array_values(array_unique($minutes));
        rsort($minutes);

        return $minutes;
    }

    /**
     * يقصر استعلامَ الموظفين على القسم أو المسمّى المضبوط في الإعدادات.
     *
     * `all` أو قائمةٌ فارغة = بلا قيد — فحفظُ «حسب القسم» بلا قسمٍ مختار
     * لا يُخلي القائمةَ من الجميع.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public function constrainEmployees($query, string $table = 'employees')
    {
        [$column, $ids] = $this->employeeFilterColumn();

        return $column ? $query->whereIn($table.'.'.$column, $ids) : $query;
    }

    /** هل الموظّفُ داخل قيد الإعدادات؟ — لفحص الحفظ. */
    public function employeeAllowed(?int $employeeId): bool
    {
        [$column, $ids] = $this->employeeFilterColumn();

        if (! $column || ! $employeeId) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table('employees')->where('id', $employeeId)->whereIn($column, $ids)->exists();
    }

    /** @return array{0: ?string, 1: array<int, int>} */
    protected function employeeFilterColumn(): array
    {
        $map = [
            'department' => ['department_id', $this->employee_department_ids],
            'job_title' => ['job_title_id', $this->employee_job_title_ids],
        ];

        [$column, $ids] = $map[$this->employee_filter ?? 'all'] ?? [null, []];
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));

        return $ids === [] ? [null, []] : [$column, $ids];
    }

    /** هل الوحدةُ مفعَّلة في إعداداتها؟ (غير سؤال «شاشة التطبيقات») */
    public function enabled(): bool
    {
        return (bool) $this->is_enabled;
    }
}

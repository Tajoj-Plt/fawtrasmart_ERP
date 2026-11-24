<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodicInvoice extends Model
{
    use \App\Traits\BelongsToBranch;
    use HasFactory;

    /**
     * الحقول القابلة للتعبئة.
     *
     * كانت تُسقط `created_by` و`currency` و`shipping_tax` و`tax_rate`
     * و`send_copy` وغيرها، والمتحكّم يمرّرها إلى `create()` — فتُرمى قبل
     * أن تصل إلى الاستعلام بلا خطأ ولا تحذير.
     */
    protected $fillable = [
        'invoice_number',
        'currency',
        'details_subscription', // تفاصيل الاشتراك
        'client_id',
        'branch_id',
        'created_by',
        'employee_id',
        'store_house_id',
        'first_invoice_date',
        'repeat_count',
        'end_date', // آخر يوم استحقاق يُجدوَل
        'repeat_type',
        'repeat_interval',
        'invoice_days_offset', // إصدار الفاتورة قبل عدد معين من الأيام
        'total',
        'grand_total',
        'subtotal',
        'status',
        'shipping_cost',
        'shipping_tax',
        'shipping_tax_id',
        'total_tax',
        'tax_type',
        'tax_rate',
        'payment_method',
        'treasury_id',

        /* الإيداع — ما تحمله الفاتورة نفسُها في شاشة إنشائها.
           حلّا محلّ `payment_type` بقيمتيه: انظر الهجرة
           `2026_08_28_120000_replace_payment_type_with_deposit_on_periodic_invoices` */
        'advance_payment',
        'is_paid',
        'discount_type',
        'discount_amount',
        'total_discount',
        'notes',
        'attachments',
        'is_active',
        'auto_generate',
        'send_copy',
        'show_from_to_dates',
        'disable_partial_payment',
        'payment_terms', // شروط الدفع
    ];

    /** أنواع التكرار — المفتاح يُخزَّن والتسمية تُترجَم */
    public const REPEAT_TYPES = [
        1 => 'daily',
        2 => 'weekly',
        3 => 'monthly',
        4 => 'quarterly',
        5 => 'yearly',
    ];

    protected $casts = [
        'first_invoice_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'auto_generate' => 'boolean',
        'send_copy' => 'boolean',
        'show_from_to_dates' => 'boolean',
        'disable_partial_payment' => 'boolean',
        'is_paid' => 'boolean',
        'advance_payment' => 'decimal:2',
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
    ];

    // العلاقة مع العميل
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // العلاقة مع عناصر الفاتورة
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'periodic_invoice_id');
    }

    /** مُنشئ الفاتورة الدورية */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** مسؤول المبيعات */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** طريقة الدفع المضبوطة في «طرق الدفع» */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method');
    }

    // العلاقة مع عناصر الفاتورة (نفس العلاقة باسم مختلف للتوافق)
    public function invoice_items()
    {
        return $this->hasMany(InvoiceItem::class, 'periodic_invoice_id');
    }

    // العلاقة مع الفواتير
    public function invoices()
    {
        return $this->hasManyThrough(
            Invoice::class,
            PeriodicInvoiceInstance::class,
            'periodic_invoice_id', // Foreign key on periodic_invoice_instances table...
            'id', // Foreign key on invoices table...
            'id', // Local key on periodic_invoices table...
            'invoice_id', // Local key on periodic_invoice_instances table...
        );
    }

    // العلاقة مع instances الفواتير
    public function instances()
    {
        return $this->hasMany(PeriodicInvoiceInstance::class);
    }

    /**
     * تاريخ الإصدار التالي بعد تاريخٍ معلوم.
     *
     * **ما كان** — العمود `repeat_type` يعني ثلاثة أشياء مختلفة في ثلاثة
     * مواضع من الميزة الواحدة:
     *
     *   • شاشة الإنشاء : 1=يوم، 2=أسبوع، 3=شهر، 4=سنة
     *   • التحقّق      : `in:1,2,3,4,5`
     *   • هذه الدالّة  : 1=أسبوعي، 2=كل أسبوعين، 3=شهري، 4=كل شهرين،
     *                    5=سنوي، 6=كل سنتين
     *
     * فمن يختار «يوم» في الشاشة يُجدوَل له إصدارٌ **أسبوعي**، ومن يختار
     * «سنة» يُجدوَل له **كل شهرين**. المعنى الآن واحد في `REPEAT_TYPES`
     * ويُقرأ منه هنا وفي الشاشة وملفّ اللغة.
     *
     * @return \Carbon\Carbon|null
     */
    public function calculateNextDueDate($currentDate)
    {
        $date = \Carbon\Carbon::parse($currentDate);

        // الفاصل أقلّه واحد: `repeat_interval` صفراً أو فارغاً يعني تكراراً
        // بلا تقدّم — حلقةٌ لا تنتهي في مولّد الفواتير
        $interval = max(1, (int) ($this->repeat_interval ?: 1));

        return match ((int) $this->repeat_type) {
            1 => $date->addDays($interval),      // يومي
            2 => $date->addWeeks($interval),     // أسبوعي
            3 => $date->addMonths($interval),    // شهري
            4 => $date->addMonths($interval * 3), // ربع سنوي
            5 => $date->addYears($interval),     // سنوي
            default => null,
        };
    }

    /**
     * موعد استحقاق النسخة رقم `$number` (تبدأ من 1).
     *
     * يُحسَب بالتقدّم من تاريخ أوّل فاتورة، فلا يتراكم انحرافُ الشهور:
     * ٣١ يناير + شهر = ٢٨ فبراير، وإضافة شهرٍ إليه تُعطي ٢٨ مارس لا ٣١.
     * الحساب من الأصل في كل مرّة يُبقي اليوم ثابتاً ما أمكن.
     */
    public function dueDateFor(int $number): ?\Carbon\Carbon
    {
        if ($number < 1 || ! $this->first_invoice_date) {
            return null;
        }

        $date = \Carbon\Carbon::parse($this->first_invoice_date);

        for ($i = 1; $i < $number; $i++) {
            $next = $this->calculateNextDueDate($date);

            if (! $next) {
                return null;
            }

            $date = $next;
        }

        return $date;
    }

    /**
     * أقصى عدد نسخٍ يُجدوَل لاشتراكٍ بلا عدد — حارسُ حلقةٍ لا حدٌّ تجاري.
     *
     * اشتراكٌ يومي بتاريخ نهايةٍ بعد عشر سنوات يعني ٣٦٥٠ صفّاً؛ ما فوق ذلك
     * خطأُ إدخالٍ لا عقد.
     */
    public const MAX_INSTANCES = 3660;

    /** جدول النسخ بترتيبها — للعرض */
    public function scheduleRows()
    {
        return $this->hasMany(PeriodicInvoiceInstance::class)->orderBy('instance_number');
    }

    /** مهلة الإصدار بالأيام — قبل الاستحقاق، ولا تكون سالبة */
    public function offsetDays(): int
    {
        return max(0, (int) ($this->invoice_days_offset ?: 0));
    }

    /**
     * يوم إصدار النسخة: الاستحقاق ناقصَ مهلة الإصدار.
     *
     * «تاريخ أوّل فاتورة» في الشاشة هو **الاستحقاق** — اليوم الذي يجب أن
     * يسدّد فيه العميل. والمهلة «يُصدَر قبل الاستحقاق بـ» تُقدِّم الإصدار
     * لا الاستحقاق: فاتورةٌ تستحقّ ١٠ أكتوبر بمهلة ٩ أيام تصدر ١ أكتوبر
     * بتاريخ ١ أكتوبر، وأمام العميل حتى ١٠ أكتوبر.
     */
    public function issueDateFor(\Carbon\Carbon $dueDate): \Carbon\Carbon
    {
        return $dueDate->copy()->subDays($this->offsetDays());
    }

    /**
     * مواعيد الاستحقاق كلُّها — حتى عدد الفواتير أو تاريخ النهاية، أيُّهما أسبق.
     *
     * @return array<int, \Carbon\Carbon> رقمُ النسخة ← يوم استحقاقها
     */
    public function scheduleDates(): array
    {
        if (! $this->first_invoice_date) {
            return [];
        }

        $count = (int) $this->repeat_count;
        $end = $this->end_date ? \Carbon\Carbon::parse($this->end_date)->endOfDay() : null;

        // بلا عددٍ ولا نهاية لا جدول: اشتراكٌ مفتوحٌ بلا حدّ لا يُحفظ أصلاً
        if ($count < 1 && ! $end) {
            return [];
        }

        $limit = $count > 0 ? min($count, self::MAX_INSTANCES) : self::MAX_INSTANCES;
        $dates = [];

        for ($number = 1; $number <= $limit; $number++) {
            $date = $this->dueDateFor($number);

            if (! $date || ($end && $date->gt($end))) {
                break;
            }

            $dates[$number] = $date;
        }

        return $dates;
    }

    /**
     * يكتب جدول النسخ في `periodic_invoice_instances` — يُنادى بعد كل حفظ.
     *
     * ما صدر لا يُمسّ: رقمُه ويومُه وفاتورتُه تبقى كما هي مهما عُدِّل
     * الاشتراك. وما لم يصدر يُعاد حسابُه من الإعدادات الجديدة، وما خرج من
     * الجدول (عددٌ أُنقص، نهايةٌ قُدِّمت) يُلغى لا يُحذف — فيبقى أثرُه.
     *
     * @return array{scheduled:int, cancelled:int}
     */
    public function syncSchedule(): array
    {
        $dates = $this->scheduleDates();
        $rows = $this->instances()->get()->keyBy('instance_number');
        $scheduled = 0;
        $cancelled = 0;

        foreach ($dates as $number => $dueDate) {
            $row = $rows->get($number);

            if ($row && $row->invoice_id) {
                continue; // صدرت — لا تُعاد جدولتُها
            }

            $attributes = [
                'due_date' => $dueDate->toDateString(),
                'issue_date' => $this->issueDateFor($dueDate)->toDateString(),
            ];

            if ($row) {
                // المُلغاة تعود مجدولة إن عادت إلى الجدول؛ والمُخفقة تبقى بخطئها حتى تُعاد
                $row->fill($attributes + [
                    'status' => (int) $row->status === PeriodicInvoiceInstance::STATUS_CANCELLED
                        ? PeriodicInvoiceInstance::STATUS_SCHEDULED
                        : $row->status,
                ])->save();
            } else {
                $this->instances()->create($attributes + [
                    'instance_number' => $number,
                    'status' => PeriodicInvoiceInstance::STATUS_SCHEDULED,
                ]);
            }

            $scheduled++;
        }

        foreach ($rows as $number => $row) {
            if (! isset($dates[$number]) && ! $row->invoice_id
                && (int) $row->status !== PeriodicInvoiceInstance::STATUS_CANCELLED) {
                $row->update(['status' => PeriodicInvoiceInstance::STATUS_CANCELLED]);
                $cancelled++;
            }
        }

        return ['scheduled' => $scheduled, 'cancelled' => $cancelled];
    }
}

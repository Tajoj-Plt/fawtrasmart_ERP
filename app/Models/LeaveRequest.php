<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    /**
     * الحقول التي يمكن تعبئتها
     *
     * @var array
     */
    protected $fillable = ['employee_id', 'request_type', 'leave_type_id', 'start_date', 'end_date', 'days', 'description', 'status', 'approved_by', 'approved_at', 'rejection_reason', 'attachments'];

    /**
     * الحقول التي يجب أن تكون من نوع تاريخ
     *
     * @var array
     */
    /*
        Laravel 11 أسقط دعم `protected $dates` نهائياً — كان يُتجاهَل بصمت،
        فتبقى هذه الحقول نصوصاً لا كائنات Carbon، وأولُ `->format()` عليها
        يرمي «Call to a member function format() on string».
        التحويلُ إلى $casts هو البديل المعتمد.
    */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * اعتمادُ الإجازة أو رفعُ اعتمادها يُعيد احتسابَ أيامها التي مضت.
     *
     * كانت أيامُ الحضور لا تعرف بالإجازة إلا في المعالجة التالية لها:
     * غيابٌ حُسب وخُصم، ثم اعتُمدت إجازةٌ عن اليوم نفسِه، فيبقى الغيابُ
     * والخصم. وإلغاءُ الاعتماد يترك الأيامَ مكتوبةً «إجازة». ولأن مسارات
     * الاعتماد خمسة (موافقة، تحديث الحالة، معالجة الإجراء، إلغاء الاعتماد،
     * إرجاع المرفوض)، فالربطُ هنا في النموذج — لا في كلٍّ منها.
     */
    protected static function booted(): void
    {
        static::updated(function (LeaveRequest $leave) {
            if (! $leave->wasChanged('status')) {
                return;
            }

            if ($leave->status !== 'approved' && $leave->getOriginal('status') !== 'approved') {
                return;
            }

            $leave->resyncAttendanceDays();
        });
    }

    /** يُعيد معالجةَ أيام الإجازة من بدايتها حتى اليوم (لا المستقبل). */
    public function resyncAttendanceDays(): void
    {
        $employee = Employee::find($this->employee_id);

        if (! $employee || ! $this->start_date) {
            return;
        }

        $processor = app(\App\Services\AttendanceProcessorService::class);
        $end = ($this->end_date ?? $this->start_date)->copy()->min(now()->startOfDay());

        for ($date = $this->start_date->copy(); $date->lte($end); $date->addDay()) {
            try {
                $processor->processEmployeeDay($employee, $date->copy());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('تعذّرت إعادة احتساب يوم الإجازة', [
                    'leave_request_id' => $this->id,
                    'date' => $date->toDateString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * العلاقة مع الموظف
     *
     * @return BelongsTo
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * العلاقة مع الموافق على الطلب
     *
     * @return BelongsTo
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * الحصول على نوع الطلب كسلسلة نصية مقروءة
     *
     * @return string
     */
    public function getRequestTypeTextAttribute()
    {
        return [
            'leave' => 'إجازة',
            'emergency' => 'إجازة طارئة',
            'sick' => 'إجازة مرضية',
        ][$this->request_type] ?? $this->request_type;
    }

    /**
     * الحصول على نوع الإجازة كسلسلة نصية مقروءة
     *
     * @return string
     */
    public function getLeaveTypeTextAttribute()
    {
        return [
            'annual' => 'إجازة اعتيادية',
            'casual' => 'إجازة عرضية',
            'sick' => 'إجازة مرضية',
            'unpaid' => 'إجازة بدون راتب',
        ][$this->leave_type] ?? $this->leave_type;
    }

    /**
     * الحصول على حالة الطلب كسلسلة نصية مقروءة
     *
     * @return string
     */
    public function getStatusTextAttribute()
    {
        return [
            'pending' => 'قيد الانتظار',
            'approved' => 'موافق عليها',
            'rejected' => 'مرفوضة',
        ][$this->status] ?? $this->status;
    }

    /**
     * الحصول على المرفقات كمصفوفة
     *
     * @return array
     */
    public function getAttachmentsArrayAttribute()
    {
        return $this->attachments ? json_decode($this->attachments, true) : [];
    }

    /**
     * نطاق الاستعلام للطلبات المعلقة
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * نطاق الاستعلام للطلبات المقبولة
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * نطاق الاستعلام للطلبات المرفوضة
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * التحقق مما إذا كان الطلب معلقاً
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * التحقق مما إذا كان الطلب مقبولاً
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * التحقق مما إذا كان الطلب مرفوضاً
     *
     * @return bool
     */
    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}

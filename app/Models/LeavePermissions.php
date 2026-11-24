<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePermissions extends Model
{
    use HasFactory;

    protected $table = 'leave_permissions';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'type',
        'leave_type',
        'submission_date',
        'notes',
        'attachments',
    ];

    /*
        Laravel 11 أسقط دعم `protected $dates` نهائياً — كان يُتجاهَل بصمت،
        فتبقى هذه الحقول نصوصاً لا كائنات Carbon، وأولُ `->format()` عليها
        يرمي «Call to a member function format() on string».
        التحويلُ إلى $casts هو البديل المعتمد.
    */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'submission_date' => 'date',
    ];

    // العلاقات
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /*
        نوعُ الإذن — يُقرأ في حساب اليوم لا في العرض وحده.

        كان `1`/`2` مكتوبَين بأرقامهما في `AttendanceStampService` وفي
        استعلامات الشاشات؛ والرقمُ في استعلامٍ لا يقول أيَّهما «الوصول
        المتأخر»، فيُقرأ خطأً فيرفع عقوبةً لم يُؤذَن فيها.
    */
    public const LATE_ARRIVAL = 1;

    public const EARLY_LEAVE = 2;

    // الثوابت للتسهيل
    const LEAVE_TYPES = [
        self::LATE_ARRIVAL => 'الوصول المتأخر',
        self::EARLY_LEAVE => 'الانصراف المبكر',
    ];

    const TYPES = [
        1 => 'إجازة اعتيادية',
        2 => 'إجازة عرضية',
    ];

    // Helper methods
    public function getLeaveTypeTextAttribute()
    {
        return self::LEAVE_TYPES[$this->leave_type] ?? '';
    }

    public function getTypeTextAttribute()
    {
        return self::TYPES[$this->type] ?? '';
    }
}

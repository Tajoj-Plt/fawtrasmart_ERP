<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'client_id',
        'branch_id',
        'attendance_determinant_id',
        'latitude',
        'longitude',
        'client_relation_id' // أضفنا هذا الحقل
    ];

    // علاقة مع الموظف
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    // علاقة مع الفرع
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    // علاقة مع العميل
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    // علاقة جديدة مع الملاحظة (Note)
    public function note()
    {
        return $this->belongsTo(ClientRelation::class, 'client_relation_id');
    }

    /**
     * موقعُ العميل المسجَّل وحده، لا صفوفُ الملاحظات.
     *
     * حفظُ ملاحظةٍ يحوّل صفَّ GPS الموظف إلى صفٍّ بـ`client_id` و`client_relation_id`
     * وإحداثياتِ الموظف لحظتها — فكان «أحدثُ موقعٍ للعميل» مكانَ من كتب الملاحظة،
     * ومديرٌ يكتب من مكتب الرياض ينقل عميلَ الدمام إلى الرياض على الخريطة وفي خط السير.
     */
    public function scopeClientPoint($query)
    {
        return $query->whereNull('client_relation_id');
    }

public function attendanceDeterminant()
{
    return $this->belongsTo(AttendanceDeterminant::class);
}
}

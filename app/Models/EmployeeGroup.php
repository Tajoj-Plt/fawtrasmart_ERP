<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeGroup extends Pivot
{
    protected $table = 'employee_group';

    protected $fillable = [
        'employee_id',
        'group_id',
        'direction_id',
        'expires_at',
    ];

    /*
        Laravel 11 أسقط دعم `protected $dates` نهائياً — كان يُتجاهَل بصمت،
        فتبقى هذه الحقول نصوصاً لا كائنات Carbon، وأولُ `->format()` عليها
        يرمي «Call to a member function format() on string».
        التحويلُ إلى $casts هو البديل المعتمد.
    */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // تقدر تضيف دوال خاصة هنا لو حاب
    public function employee()
    {
        return $this->belongsTo(User::class);
    }
}

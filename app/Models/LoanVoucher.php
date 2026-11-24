<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_date',
        'borrower_name',
        'amount',
        'description',
        'account_id',
        'treasury_id',
        'attachment',
        'status',
        'repayment_date',
        'interest_rate',
        'loan_type',
    ];

    /*
        Laravel 11 أسقط دعم `protected $dates` نهائياً — كان يُتجاهَل بصمت،
        فتبقى هذه الحقول نصوصاً لا كائنات Carbon، وأولُ `->format()` عليها
        يرمي «Call to a member function format() on string».
        التحويلُ إلى $casts هو البديل المعتمد.
    */
    protected $casts = [
        'voucher_date' => 'date',
        'repayment_date' => 'date',
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function treasury()
    {
        return $this->belongsTo(Treasury::class, 'treasury_id');
    }

    public function details()
    {
        return $this->hasMany(LoanVoucherDetail::class);
    }
}

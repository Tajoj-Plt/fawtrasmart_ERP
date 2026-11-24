<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\StampsOwnerContext;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChequeBook extends Model
{
    /*
        الفترةُ المحاسبية تُختَم لحظةَ الإنشاء من تاريخ المستند، فتثبت معه
        ما بقي — بدل أن تُستنتَج بمطابقة التاريخ في كلّ شاشة.
    */
    use BelongsToAccountingPeriod;

    /**
     * فرعُ المستند: يُختَم عند الإنشاء (`StampsOwnerContext`) ويُصفّى عند
     * القراءة (`BelongsToBranch::scopeForBranch`).
     *
     * كان الجدول بلا عمود فرعٍ إطلاقاً، فتبديلُ الفرع في الشريط العلوي لا
     * يجد ما يُصفّي به. والصفوفُ التي سبقت العمود تبقى ظاهرةً في كل الفروع
     * (`orWhereNull`) فلا يختفي تاريخٌ عند التشغيل.
     */
    use BelongsToBranch, StampsOwnerContext;

    /** التاريخُ الحاكم لفترة هذا المستند. */
    public function accountingDateColumn(): string
    {
        return 'created_at';
    }

    use HasFactory;

    protected $table = 'cheque_books';

    protected $fillable = ['id', 'bank_id', 'cheque_book_number', 'currency', 'start_serial_number', 'end_serial_number', 'status', 'notes', 'created_by', 'created_at', 'updated_at', 'accounting_period_id'];

    /**
     * علاقة مع حساب البنك (من جدول الحسابات)
     */
    public function bank()
    {
        return $this->belongsTo(Account::class, 'bank_id');
    }

    public function cheques()
    {
        return $this->hasMany(PayableCheque::class, 'cheque_book_id');
    }

    /**
     * من أنشأ الدفتر.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

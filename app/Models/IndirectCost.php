<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentCurrency;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class IndirectCost extends Model
{
    use BelongsToBranch, HasDocumentCurrency;

    protected $table = 'indirect_costs';

    protected $fillable = [
        'account_id', 'from_date', 'to_date', 'based_on', 'total',
        // نصيبُ الوحدة وأساسُ التوزيع — يحسبهما الخادم عند الحفظ،
        // انظر Modules\Manufacturing\Services\IndirectCostAllocator
        'unit_rate', 'allocation_base',
        'currency',
        // رقم المستند من شاشة «الترقيم المتسلسل» — القسم `indirect-costs`
        'code',
        // الفرع — `null` تعني «كل الفروع»، انظر BelongsToBranch::scopeForBranch
        'branch_id',
    ];

    public function indirectCostItems()
    {
        return $this->hasMany(IndirectCostItem::class, 'indirect_costs_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}

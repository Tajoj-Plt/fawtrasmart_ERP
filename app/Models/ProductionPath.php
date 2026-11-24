<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class ProductionPath extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'name', 'code', 'created_by', 'updated_by',
        // الفرع — `null` تعني «كل الفروع»، انظر BelongsToBranch::scopeForBranch
        'branch_id',
    ];

    public function stages()
    {
        return $this->hasMany(ProductionStage::class, 'production_paths_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}

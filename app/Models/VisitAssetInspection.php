<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitAssetInspection extends Model
{
    use HasFactory;

    public const CONDITION_OK = 'ok';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_MISSING = 'missing';

    protected $fillable = [
        'visit_id',
        'client_company_asset_id',
        'condition',
        'found_quantity',
        'photo_path',
        'notes',
        'inspected_by',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function asset()
    {
        return $this->belongsTo(ClientCompanyAsset::class, 'client_company_asset_id');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public static function conditionLabel(string $condition): string
    {
        return match ($condition) {
            self::CONDITION_DAMAGED => 'تالف',
            self::CONDITION_MISSING => 'ناقص',
            default => 'سليم',
        };
    }

    public static function conditionBadgeClass(string $condition): string
    {
        return match ($condition) {
            self::CONDITION_DAMAGED => 'danger',
            self::CONDITION_MISSING => 'warning',
            default => 'success',
        };
    }
}

<?php

namespace Modules\RentalManagement\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\RentalManagement\Services\OwnerLedgerService;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * مالك العقار — كيانٌ مستقلّ عن ملفّ العملاء، له اتفاقية إدارة أملاك وحسابٌ
 * جارٍ في دليل الحسابات يُقيَّد عليه ما يُحصَّل له وما يُخصم منه.
 */
class Owner extends RealEstateModel
{
    use SoftDeletes;

    protected $table = 're_owners';

    protected $casts = ['agreement_value' => 'float', 'is_vat_registered' => 'boolean'];

    protected static function booted(): void
    {
        static::updated(fn (self $owner) => app(OwnerLedgerService::class)->syncAccountName($owner));
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function buildings()
    {
        return $this->hasMany(Building::class, 'owner_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'owner_id');
    }

    public function entries()
    {
        return $this->hasMany(OwnerEntry::class, 'owner_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    public function getStatusMetaAttribute(): array
    {
        return $this->vocab(V::OWNER_STATUSES, 'status');
    }

    public function getAgreementLabelAttribute(): string
    {
        $type = V::label(V::AGREEMENT_TYPES, $this->agreement_type);

        return $this->agreement_type === 'percentage'
            ? $type.' — '.rtrim(rtrim(number_format($this->agreement_value, 2), '0'), '.').'%'
            : $type.' — '.number_format($this->agreement_value, 2);
    }

    public function isPercentage(): bool
    {
        return $this->agreement_type === 'percentage';
    }
}

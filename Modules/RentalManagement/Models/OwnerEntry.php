<?php

namespace Modules\RentalManagement\Models;

use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * حركة في دفتر المالك: له (credit) يزيد رصيده، وعليه (debit) ينقصه.
 */
class OwnerEntry extends RealEstateModel
{
    protected $table = 're_owner_entries';

    /**
     * أنواع حركات دفتر المالك. ما زاد على مفردات الوحدة: الضريبة المحصَّلة
     * لحسابه، ضريبة عمولة المكتب، ما خُصم له من تأمين مستأجر، وصيانةٌ
     * استُردّت له من المستأجر.
     */
    public const TYPES = V::OWNER_ENTRY_TYPES + [
        'tax' => ['label' => 'ضريبة قيمة مضافة محصَّلة لحسابه', 'tone' => 'info'],
        'commission_tax' => ['label' => 'ضريبة عمولة الإدارة', 'tone' => 'warning'],
        'deposit' => ['label' => 'خصم من تأمين مستأجر', 'tone' => 'success'],
        'recovery' => ['label' => 'صيانة محصَّلة من المستأجر', 'tone' => 'success'],
    ];

    protected $casts = ['entry_date' => 'date', 'amount' => 'float'];

    /** الحركة العاكسة لهذه — وجودها يعني أنّ الحركة أُلغيت. */
    public function reversal()
    {
        return $this->hasOne(self::class, 'reverses_entry_id');
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id')->withTrashed();
    }

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id')->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id')->withTrashed();
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function getTypeMetaAttribute(): array
    {
        return ['label' => V::label(self::TYPES, $this->type), 'tone' => V::tone(self::TYPES, $this->type)];
    }

    public function getSignedAmountAttribute(): float
    {
        return $this->direction === 'credit' ? $this->amount : -$this->amount;
    }
}

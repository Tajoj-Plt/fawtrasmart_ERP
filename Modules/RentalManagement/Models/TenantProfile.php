<?php

namespace Modules\RentalManagement\Models;

use App\Models\Client;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * بيانات المستأجر الشخصية فوق ملفّ العميل المركزي — صفٌّ واحد لكل عميل.
 */
class TenantProfile extends RealEstateModel
{
    protected $table = 're_tenant_profiles';

    /** نوع المستأجر: فرد أو شركة (يملكها هذا النموذج لا المفردات المشتركة). */
    public const TYPES = [
        'individual' => ['label' => 'فرد', 'tone' => 'info'],
        'company' => ['label' => 'شركة / مؤسسة', 'tone' => 'primary'],
    ];

    protected $casts = [
        'id_issue_date' => 'date',
        'id_expiry_date' => 'date',
        'monthly_income' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function getIdTypeLabelAttribute(): string
    {
        return V::label(V::ID_TYPES, $this->id_type);
    }

    public function getTypeLabelAttribute(): string
    {
        return V::label(self::TYPES, $this->tenant_type ?: 'individual');
    }

    public function isCompany(): bool
    {
        return $this->tenant_type === 'company';
    }

    public function idExpired(): bool
    {
        return $this->id_expiry_date !== null && $this->id_expiry_date->isPast();
    }
}

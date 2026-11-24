<?php

namespace Modules\RentalManagement\Models;

use App\Models\Client;
use App\Models\User;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * العلاقة العقارية لعميلٍ مركزي — ليست قاعدة عملاء ثانية.
 */
class CustomerRelationship extends RealEstateModel
{
    protected $table = 're_customer_relationships';

    protected $casts = [
        'roles' => 'array',
        'last_activity_at' => 'datetime',
    ];

    /**
     * الأدوار التي تُختار في النموذج. «مالك» ليس منها: الملّاك كيانٌ مستقلّ
     * (`re_owners`) — والصفوف القديمة التي تحمله تبقى معروضةً بتسميتها.
     */
    public const SELECTABLE_ROLES = [
        'tenant' => V::RELATIONSHIP_ROLES['tenant'],
        'prospect_tenant' => V::RELATIONSHIP_ROLES['prospect_tenant'],
        'prospect_buyer' => V::RELATIONSHIP_ROLES['prospect_buyer'],
        'buyer' => V::RELATIONSHIP_ROLES['buyer'],
        'investor' => V::RELATIONSHIP_ROLES['investor'],
        'broker' => V::RELATIONSHIP_ROLES['broker'],
    ];

    /** نشاطٌ على العميل (معاينة، مهمة، دفعة) يحدّث «آخر نشاط» في علاقاته كلّها. */
    public static function touchActivity($clientId): void
    {
        if ($clientId) {
            static::query()->where('client_id', $clientId)->update(['last_activity_at' => now()]);
        }
    }

    /**
     * عميلٌ مسجَّل استفسر عن عرضٍ أو عاينه: تُنشأ له علاقة «محتمل» على العرض
     * إن لم تكن، وإلّا يُحدَّث نشاطها. لا يُنشأ صفٌّ ثانٍ للعرض نفسه.
     */
    public static function recordProspect(int $clientId, Listing $listing): self
    {
        $relationship = static::query()->where('client_id', $clientId)
            ->where(fn ($q) => $q->where('listing_id', $listing->id)
                ->when($listing->unit_id, fn ($w) => $w->orWhere('unit_id', $listing->unit_id)))
            ->orderByRaw('listing_id = ? desc', [$listing->id])->first();

        if ($relationship) {
            $relationship->forceFill(['last_activity_at' => now()] + ($relationship->listing_id ? [] : ['listing_id' => $listing->id]))->save();
            static::touchActivity($clientId);

            return $relationship;
        }

        static::touchActivity($clientId);

        return static::create([
            'client_id' => $clientId,
            'roles' => [$listing->listing_type === 'sale' ? 'prospect_buyer' : 'prospect_tenant'],
            'listing_id' => $listing->id,
            'unit_id' => $listing->unit_id,
            'building_id' => $listing->building_id,
            'relationship_status' => 'follow_up',
            'assigned_user_id' => $listing->assigned_agent_id,
            'last_activity_at' => now(),
        ]);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getStatusMetaAttribute(): array
    {
        return $this->vocab(V::RELATIONSHIP_STATUSES, 'relationship_status');
    }
}

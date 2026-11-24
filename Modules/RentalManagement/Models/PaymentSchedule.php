<?php

namespace Modules\RentalManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\RentalManagement\Support\RealEstateSettings;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class PaymentSchedule extends Model
{
    protected $table = 're_payment_schedules';

    protected $guarded = ['id'];

    /** أنواع الاستحقاق: الإيجار والتأمين، و«صيانة» تُحمَّل على المستأجر وتُحصَّل كأيّ قسط. */
    public const KINDS = V::SCHEDULE_KINDS + [
        'maintenance' => ['label' => 'صيانة على المستأجر'],
    ];

    protected $casts = ['due_date' => 'date'];

    public function getKindLabelAttribute(): string
    {
        return V::label(self::KINDS, $this->kind);
    }

    public function maintenanceCost()
    {
        return $this->belongsTo(MaintenanceCost::class, 'maintenance_cost_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'schedule_id');
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->amount_due - (float) $this->amount_paid, 2);
    }

    /**
     * الحالة كما هي **الآن**: العمود يُحدَّث عند الدفع وفي الكنس اليومي، لكنّ
     * قسطاً فات أجلُه صباح اليوم متأخّرٌ ولو لم يمرّ الكنس بعد — فالعرض
     * يحسبها ولا يثق بالمخزَّن وحده.
     */
    public function getLiveStatusAttribute(): string
    {
        if (in_array($this->status, ['paid', 'cancelled'], true)) {
            return $this->status;
        }

        $grace = (int) RealEstateSettings::get('overdue_grace_days', 0);

        return match (true) {
            $this->due_date->copy()->addDays($grace)->lt(now()->startOfDay()) => 'overdue',
            (float) $this->amount_paid > 0 => 'partial',
            $this->due_date->lte(now()->endOfDay()) => 'due',
            default => 'scheduled',
        };
    }

    public function getStatusMetaAttribute(): array
    {
        return ['label' => V::label(V::SCHEDULE_STATUSES, $this->live_status), 'tone' => V::tone(V::SCHEDULE_STATUSES, $this->live_status)];
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['paid', 'cancelled']);
    }

    public function scopeOverdue($query)
    {
        return $query->open()->whereDate('due_date', '<', now()->subDays((int) RealEstateSettings::get('overdue_grace_days', 0))->toDateString());
    }

    /**
     * الحالة مشتقّةٌ من المبالغ والتاريخ لا مكتوبةٌ باليد: الدفع الجزئي لا
     * يجعل الاستحقاق «مدفوعاً»، والمتأخّر يبقى متأخّراً ولو دُفع بعضُه.
     */
    public function refreshStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $grace = (int) RealEstateSettings::get('overdue_grace_days', 0);
        $late = $this->due_date->copy()->addDays($grace)->lt(now()->startOfDay());

        $this->status = match (true) {
            $this->balance <= 0 => 'paid',
            $late => 'overdue',
            (float) $this->amount_paid > 0 => 'partial',
            $this->due_date->lte(now()->endOfDay()) => 'due',
            default => 'scheduled',
        };

        $this->save();
    }
}

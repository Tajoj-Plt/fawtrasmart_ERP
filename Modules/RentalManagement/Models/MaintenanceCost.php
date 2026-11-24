<?php

namespace Modules\RentalManagement\Models;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

/**
 * بند تكلفة صيانة. يحمل قيده وطرفه المقابل (خزينة أو ذمة مورّد) وعملته
 * وقت التسجيل، ومن حُمِّل به (المالك / المستأجر / المنشأة).
 */
class MaintenanceCost extends Model
{
    protected $table = 're_maintenance_costs';

    protected $guarded = ['id'];

    protected $casts = ['cost_date' => 'date'];

    public function request()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id')->withTrashed();
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function treasuryAccount()
    {
        return $this->belongsTo(Account::class, 'treasury_account_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** قسط «صيانة» المنشأ على المستأجر، إن كانت التكلفة عليه. */
    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'schedule_id');
    }

    /** الطرف الذي دُفعت منه التكلفة — للعرض. */
    public function getPaidFromLabelAttribute(): string
    {
        if ($this->supplier_id) {
            return 'ذمة المورّد '.($this->supplier->trade_name ?? '');
        }

        return $this->treasury_account_id ? ($this->treasuryAccount->name ?? '—') : 'بلا قيد';
    }
}

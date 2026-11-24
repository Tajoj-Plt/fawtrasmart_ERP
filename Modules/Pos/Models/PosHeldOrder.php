<?php

namespace Modules\Pos\Models;

use App\Models\Branch;
use App\Models\CashierDevice;
use App\Models\Client;
use App\Models\PosSession;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * سلّةٌ معلَّقة — لا فاتورة.
 *
 * الفرقُ جوهريّ: لم يُخصم لها مخزون، ولا قيدَ لها، ولا رقمَ مستندٍ استُهلك.
 * وضعُها في `invoices` بحالة `held` — كما كانت الشيفرة تفترض — يُلوّث كلَّ
 * استعلامٍ على الفواتير، وكلُّ تقريرٍ ينسى استثناءها يحتسبها مبيعاتٍ حقيقية.
 */
class PosHeldOrder extends Model
{
    use HasDocumentCurrency;
    use SoftDeletes;
    use StampsOwnerContext;

    /*
        السلّةُ المعلَّقة ليست مستنداً محاسبياً: لا قيدَ لها ولا رقمَ مستند،
        فلا فترةَ تُنسَب إليها. و«صاحبُها» هو الكاشير — عمودُه `cashier_id`.
    */
    protected array $ownerContextMap = ['user_id' => 'cashier_id'];

    /** السلّةُ تتبع جلستَها. */
    protected function ownerBranchFromDocument(): ?int
    {
        return $this->branchOfSession($this->session_id) ?: $this->branchOfDevice($this->device_id);
    }

    protected $table = 'pos_held_orders';

    protected $fillable = [
        'session_id', 'device_id', 'branch_id', 'cashier_id', 'client_id',
        'label', 'payload', 'estimated_total', 'currency', 'items_count',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'estimated_total' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    /* ═══════════════ العلاقات ═══════════════ */

    public function session()
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function device()
    {
        return $this->belongsTo(CashierDevice::class, 'device_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /* ═══════════════ النطاقات ═══════════════ */

    /**
     * ما زال صالحاً — لم تنتهِ صلاحيتُه.
     *
     * `whereNull` أولاً: سلّةٌ بلا تاريخِ انتهاء صالحةٌ دائماً، وإغفالُ ذلك
     * يُخفيها كلَّها.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeForSession(Builder $query, $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeForCashier(Builder $query, $cashierId): Builder
    {
        return $query->where('cashier_id', $cashierId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

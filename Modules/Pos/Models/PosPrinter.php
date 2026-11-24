<?php

namespace Modules\Pos\Models;

use App\Models\Branch;
use App\Models\CashierDevice;
use App\Models\Concerns\StampsOwnerContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * طابعةٌ مربوطةٌ بجهازٍ أو بفرع.
 *
 * `pos_general_settings.invoice_template` كان الضبطَ الوحيد — `thermal` أو
 * `electronic`. لا عرضَ ورق، ولا عددَ نسخ، ولا طابعةَ مطبخٍ منفصلة، ولا
 * ربطَ طابعةٍ بجهاز. فمنشأةٌ بجهازين وطابعتين لا تستطيع التمييز بينهما.
 */
class PosPrinter extends Model
{
    use SoftDeletes;
    use StampsOwnerContext;

    /** الطابعةُ المربوطةُ بجهازٍ تتبع فرعَه. */
    protected function ownerBranchFromDocument(): ?int
    {
        return $this->branchOfDevice($this->device_id);
    }

    protected $table = 'pos_printers';

    protected $fillable = [
        'name', 'device_id', 'branch_id', 'type', 'connection', 'address',
        'paper_width', 'copies', 'auto_cut', 'open_drawer',
        'is_default', 'is_active', 'settings', 'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'auto_cut' => 'boolean',
        'open_drawer' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'paper_width' => 'integer',
        'copies' => 'integer',
    ];

    /** إيصالُ البيع الحراري. */
    public const TYPE_RECEIPT = 'receipt';

    /** فاتورةٌ بمقاس A4. */
    public const TYPE_A4 = 'a4';

    /** طابعةُ المطبخ — نسخةُ التحضير لا نسخةُ العميل. */
    public const TYPE_KITCHEN = 'kitchen';

    /** ملصقاتُ الباركود. */
    public const TYPE_LABEL = 'label';

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            self::TYPE_RECEIPT => 'إيصال حراري',
            self::TYPE_A4 => 'فاتورة A4',
            self::TYPE_KITCHEN => 'طابعة مطبخ',
            self::TYPE_LABEL => 'ملصقات باركود',
            default => (string) $type,
        };
    }

    public static function connectionLabel(?string $connection): string
    {
        return match ($connection) {
            'usb' => 'USB',
            'network' => 'شبكة',
            'bluetooth' => 'بلوتوث',
            'cloud' => 'سحابية',
            default => (string) $connection,
        };
    }

    /* ═══════════════ العلاقات ═══════════════ */

    public function device()
    {
        return $this->belongsTo(CashierDevice::class, 'device_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ═══════════════ النطاقات ═══════════════ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * طابعاتُ جهازٍ بعينه — الخاصّةُ به، ثم العامّةُ لفرعه.
     *
     * الترتيبُ مقصود: طابعةٌ مربوطةٌ بالجهاز أخصُّ من طابعةٍ عامّةٍ للفرع.
     */
    public function scopeForDevice(Builder $query, ?int $deviceId, ?int $branchId = null): Builder
    {
        return $query->where(function ($q) use ($deviceId, $branchId) {
            $q->where('device_id', $deviceId);

            if ($branchId) {
                $q->orWhere(fn ($inner) => $inner->whereNull('device_id')->where('branch_id', $branchId));
            }
        })->orderByRaw('device_id IS NULL')->orderByDesc('is_default');
    }
}

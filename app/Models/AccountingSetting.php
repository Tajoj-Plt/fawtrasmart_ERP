<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إعداد حساب محاسبي واحد — ربط عملية بحساب من شجرة الحسابات.
 *
 * طبقة إعدادات مستقلّة: لا يقرأها اليوم أي متحكّم أو خدمة خارج شاشة
 * «الحسابات المحاسبية»، والنظام ما زال يعمل بثوابت `SystemAccountIds`.
 * الربط الفعلي مرحلة تالية.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $group
 * @property int|null $account_id
 * @property string|null $account_code
 * @property bool $is_active
 */
class AccountingSetting extends Model
{
    use HasFactory;

    protected $table = 'accounting_settings';

    protected $fillable = [
        'key',
        'name',
        'description',
        'group',
        'account_id',
        'account_code',
        'is_active',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * الحساب المربوط من شجرة الحسابات الموجودة (`accounts`).
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** الإعدادات المفعَّلة فقط. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** إعداد بمفتاحه البرمجي. */
    public function scopeKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /** الإعدادات التي لم يُختَر لها حساب بعد — تُبرَز في الشاشة. */
    public function scopeUnmapped(Builder $query): Builder
    {
        return $query->whereNull('account_id');
    }

    /**
     * نصّ العرض: «44 - المبيعات»، أو تنبيه حين لا حساب.
     */
    public function getAccountLabelAttribute(): ?string
    {
        if (! $this->relationLoaded('account') || ! $this->account) {
            return null;
        }

        return trim(($this->account->code ?? '—').' - '.$this->account->name);
    }
}

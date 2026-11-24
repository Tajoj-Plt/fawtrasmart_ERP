<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLimit extends Model
{
    protected $table = 'credit_limit';
    
    protected $fillable = [
        'client_id',
        'value',
    ];

    /**
     * العلاقة مع العميل
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * البحث عن حد ائتماني حسب العميل
     */
    public static function getClientCreditLimit($clientId)
    {
        return self::where('client_id', $clientId)->first();
    }

    /**
     * تحديث أو إنشاء حد ائتماني للعميل
     */
    public static function updateOrCreateForClient($clientId, $value)
    {
        return self::updateOrCreate(
            ['client_id' => $clientId],
            ['value' => $value]
        );
    }
}

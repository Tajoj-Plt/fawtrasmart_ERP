<?php

namespace App\Helpers;

use App\Models\Client;

/**
 * مساعد للعميل الافتراضي في النظام.
 *
 * الاستخدام:
 *   DefaultClientHelper::id()        // 1
 *   DefaultClientHelper::get()       // instance
 *   DefaultClientHelper::isDefault($clientId)
 */
class DefaultClientHelper
{
    /**
     * معرّف العميل الافتراضي
     */
    public static function id(): int
    {
        return Client::DEFAULT_CLIENT_ID;
    }

    /**
     * جلب نموذج العميل الافتراضي
     */
    public static function get(): ?Client
    {
        return Client::find(self::id());
    }

    /**
     * التحقق من كون معرّف معين هو العميل الافتراضي
     */
    public static function isDefault($clientId): bool
    {
        return (int) $clientId === self::id();
    }

    /**
     * جلب العميل الافتراضي أو عميل آخر محدد
     * (للاستخدام في fallback)
     */
    public static function getOr($clientId): ?Client
    {
        if (empty($clientId) || self::isDefault($clientId)) {
            return self::get();
        }

        return Client::find($clientId);
    }

    /**
     * إرجاع معرّف العميل الافتراضي إذا كان المُمرّر فارغاً
     */
    public static function resolve($clientId = null): int
    {
        return ! empty($clientId) ? (int) $clientId : self::id();
    }
}

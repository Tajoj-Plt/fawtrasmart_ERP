<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات الرواتب — صفّ `payroll_settings` الواحد، مع كاش كـ CurrencySettings.
 *
 * كانت القراءة بـ`DB::table('payroll_settings')` في كل موضع، بلا إبطال
 * عند الحفظ. هنا نقطة واحدة: `value()` للعمود و`put()` للكتابة.
 */
class PayrollSettings
{
    protected const CACHE_KEY = 'payroll_settings.row';

    protected const CACHE_TTL = 3600;

    protected static mixed $resolved = false;

    /**
     * الصف الوحيد — أو null إن لم يُزرع الجدول أو كان فارغاً.
     */
    public static function row(): ?object
    {
        if (self::$resolved !== false) {
            return self::$resolved;
        }

        try {
            if (! Schema::hasTable('payroll_settings')) {
                return self::$resolved = null;
            }

            self::$resolved = Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL,
                fn () => DB::table('payroll_settings')->first()
            );
        } catch (\Throwable $e) {
            self::$resolved = null;
        }

        return self::$resolved;
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        $row = self::row();
        if (! $row || ! isset($row->{$key}) || $row->{$key} === '') {
            return $default;
        }

        return $row->{$key};
    }

    /** @return array<string, mixed> */
    public static function toArray(): array
    {
        $row = self::row();

        return $row ? (array) $row : [];
    }

    /**
     * يكتب الصف (تحديث إن وُجد، وإلا إدراج) ويُبطل الكاش.
     *
     * @param  array<string, mixed>  $data
     */
    public static function put(array $data): void
    {
        if (! Schema::hasTable('payroll_settings')) {
            return;
        }

        $columns = Schema::getColumnListing('payroll_settings');
        $data['updated_at'] = $data['updated_at'] ?? now();
        $data = array_intersect_key($data, array_flip($columns));

        $existing = DB::table('payroll_settings')->first();
        if ($existing) {
            $id = $existing->id ?? null;
            $query = DB::table('payroll_settings');
            if ($id) {
                $query->where('id', $id);
            }
            $query->update($data);
        } else {
            $defaults = array_intersect_key([
                'send_salary_slips' => false,
                'default_template' => 'ar',
                'auto_pay_approved_sales_periods' => true,
                'created_at' => now(),
            ], array_flip($columns));
            DB::table('payroll_settings')->insert($defaults + $data);
        }

        self::forget();
    }

    public static function forget(): void
    {
        self::$resolved = false;
        Cache::forget(self::CACHE_KEY);
    }
}

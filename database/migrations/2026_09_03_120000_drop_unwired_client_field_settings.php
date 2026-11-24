<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حذف بطلب المستخدم — خمسة من صفوف «الحقول الإضافية» في إعدادات وحدة
 * العملاء («type»، «birth_date»، «national_id»، «addresses»، «link»)
 * لا يقرأها create.blade.php ولا edit.blade.php إطلاقاً (بخلاف «image»،
 * «location»، «credit_limit»، «credit_duration»، «opening_balance» —
 * الخمسة الباقية، وكلّها مربوطة فعلاً بحقلٍ في النموذج). فتفعيل أيٍّ من
 * الخمسة المحذوفة أو تعطيله لم يكن يُحدث أثراً قط، وشاشة الإعدادات كانت
 * تُظهر تنبيه «غير مربوط بأي حقلٍ» عليها دائماً.
 *
 * `GeneralClientSettingSeeder` و`ClientController::create()/edit()` (نسخة
 * القيم الافتراضية حين يكون الجدول فارغاً) عُدِّلا معها فلا يُعاد زرعُها.
 */
return new class extends Migration
{
    private const DROPPED_KEYS = ['type', 'birth_date', 'national_id', 'addresses', 'link'];

    private const NAMES = [
        'type' => 'النوع',
        'birth_date' => 'تاريخ الميلاد',
        'national_id' => 'رقم الهوية الوطنية',
        'addresses' => 'عناوين متعددة',
        'link' => 'الرابط',
    ];

    public function up(): void
    {
        DB::table('general_client_settings')
            ->whereIn('key', self::DROPPED_KEYS)
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        foreach (self::DROPPED_KEYS as $key) {
            $exists = DB::table('general_client_settings')->where('key', $key)->exists();

            if (! $exists) {
                DB::table('general_client_settings')->insert([
                    'key' => $key,
                    'name' => self::NAMES[$key],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};

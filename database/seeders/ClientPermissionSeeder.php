<?php

namespace Database\Seeders;

use App\Models\ClientPermission;
use App\Support\ClientPortalPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * زرعُ مفاتيح بوّابة العميل في `client_permissions`.
 *
 * **يُعاد تشغيله**: الشاشة «تحديثات النظام» تعرض هذا الزرع كخطوةٍ يضغطها
 * صاحبُ النظام متى شاء، وقاعدةٌ قائمةٌ فيها المفاتيح أصلاً. الصيغة القديمة
 * (`ClientPermission::create()` لكل مفتاح) كانت تنفجر عند أوّل صفٍّ موجود:
 * «Duplicate entry 'activate_client_registration' for key
 * `client_permissions_key_unique`» — فتتوقّف الخطوة كلُّها. الزرعُ الآن
 * **بالمفتاح الناقص وحده**.
 *
 * **`is_active` لا تُلمَس لصفٍّ موجود**: تفعيلُ المفاتيح قرارُ صاحب النظام
 * من شاشة «صلاحيات بوابة العميل»؛ زرعٌ يُعيد كتابتها يفتح للعملاء ما أُطفئ
 * عمداً. تُكتب عند الإنشاء فقط.
 *
 * **المفاتيح من `ClientPortalPermissions`** لا من قائمةٍ ثانيةٍ هنا: مفتاحٌ
 * يُضاف في الكود ولا صفَّ له يظهر في الإعدادات موسوماً «غير مزروع في
 * القاعدة» ومعطَّلاً، فقائمتان تفترقان تعني مفتاحاً لا يمكن تفعيله.
 *
 * @see \App\Support\ClientPortalPermissions
 */
class ClientPermissionSeeder extends Seeder
{
    /**
     * مفاتيح تُزرع **مطفأةً**: كشف الحساب يعرض حركة الحساب المحاسبي كاملةً
     * (أرصدةً افتتاحيةً وتسويات)، وفتحُه لكلّ العملاء بمجرّد رفع تحديثٍ
     * قرارٌ لا يُتّخذ نيابةً عن صاحب النظام.
     *
     * @var array<int, string>
     */
    private const SEED_INACTIVE = [
        'view_account_statement',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('client_permissions')) {
            return;
        }

        $existing = DB::table('client_permissions')->pluck('key')->all();
        $seeded = 0;

        foreach (ClientPortalPermissions::all() as $key => $definition) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            ClientPermission::query()->create([
                'key' => $key,
                'name' => $definition['label'],
                'is_active' => ! in_array($key, self::SEED_INACTIVE, true),
            ]);

            $seeded++;
        }

        ClientPortalPermissions::forget();

        $this->command?->info($seeded > 0
            ? "تمّ زرع {$seeded} مفتاحاً من مفاتيح بوّابة العميل."
            : 'مفاتيح بوّابة العميل مزروعةٌ مسبقاً — لا جديد.');
    }
}

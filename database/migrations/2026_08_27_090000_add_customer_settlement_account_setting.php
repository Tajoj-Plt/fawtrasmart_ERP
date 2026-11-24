<?php

use App\Models\AccountingSetting;
use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاح «تسوية أرصدة العملاء» في «الحسابات المحاسبية».
 *
 * أُضيف مع شاشة «محفظة العملاء» في المبيعات — أداة تسوية سريعة لرصيد
 * عميل من قائمة واحدة، بدل فتح ملفّ كل عميل على حدة. تُعيد استخدام آلية
 * الرصيد الافتتاحي نفسها (`Account::createOrUpdateOpeningBalanceJournalEntry`)
 * لكن بحساب مقابل مستقلّ عن حساب الرصيد الافتتاحي العام.
 *
 * **بلا ربطٍ ولا ثابتٍ احتياطي عمداً**: هذا مفهومٌ إداري جديد لا حساباً
 * قائماً في الشجرة، فلا كودَ يُبحث عنه ولا معرّفَ `SystemAccountIds` يُخمَّن
 * منه. يُنشأ الصفّ بلا `account_id` فيظهر في الشاشة «غير مضبوط»، والمحاسب
 * وحده من يربطه بالحساب الذي يريده. والمتحكّم (`CustomerWalletController`)
 * يرفض كتابة أي قيدٍ ما دام هذا المفتاح غير مربوط — لا يسقط على حسابٍ
 * افتراضي لم يختره أحد.
 *
 * ترحيلٌ لا سيدر: السيدرات لا تُشغَّل عند رفع نسخةٍ إلى خادم قائم (انظر
 * ترحيل 2026_08_26_140000 لنفس السبب)، والمفتاح مضافٌ فعلاً إلى
 * `AccountingSettingsSeeder::definitionsUnlinked()` للتركيبات الجديدة.
 */
return new class extends Migration
{
    private const KEY = 'customer_settlement_account';

    public function up(): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        if (AccountingSetting::where('key', self::KEY)->exists()) {
            return;
        }

        AccountingSetting::create([
            'key' => self::KEY,
            'name' => 'تسوية أرصدة العملاء',
            'description' => 'الحساب المقابل عند تسوية رصيد عميل من شاشة «محفظة العملاء» في المبيعات',
            'group' => 'العملاء والذمم',
            'account_id' => null,
            'account_code' => null,
            'is_active' => true,
        ]);

        AccountingSettings::forget();
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_settings')) {
            return;
        }

        AccountingSetting::where('key', self::KEY)->delete();

        AccountingSettings::forget();
    }
};

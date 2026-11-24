<?php

namespace Database\Seeders;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\Branch;
use App\Models\StoreHouse;
use App\Support\AccountingSettings;
use Illuminate\Database\Seeder;

/**
 * المستودع الرئيسي — منشأة تُنصَّب حديثاً بلا مستودع، وشاشات المخزون
 * (المنتجات، أذون الصرف/الاستلام) تفترض وجود مستودع واحد على الأقل.
 *
 * ينزل معه حسابٌ في شجرة الحسابات تحت حساب «المخزون»، بنفس ما يفعله
 * `StorehouseController::store()` عند إنشاء مستودعٍ من الشاشة — وإلا بقي
 * المستودع بلا حساب، فتُسقَط قيود تكلفة المبيعات والأرصدة الافتتاحية له.
 */
class MainStoreHouseSeeder extends Seeder
{
    public function run(): void
    {
        $storehouse = StoreHouse::where('major', 1)->first();

        if ($storehouse) {
            $this->command?->info('  المستودع الرئيسي موجود مسبقاً — تُخطّي.');
        } else {
            $storehouse = StoreHouse::create([
                'name' => 'المستودع الرئيسي',
                'status' => 'active',
                'major' => 1,
                // مثل store(): المستودع مربوط بفرع دائماً — هنا فرع المنشأة الرئيسي
                'branch_id' => Branch::where('is_main', true)->value('id'),
            ]);

            $this->command?->info('  أُنشئ المستودع الرئيسي.');
        }

        $this->ensureAccount($storehouse);
    }

    /** حساب المستودع في شجرة الحسابات — نفس منطق StorehouseController::store(). */
    protected function ensureAccount(StoreHouse $storehouse): void
    {
        if (Account::where('storehouse_id', $storehouse->id)->exists()) {
            $this->command?->info('  حساب المستودع الرئيسي موجود مسبقاً — تُخطّي.');

            return;
        }

        $parentAccount = AccountingSettings::account('inventory', SystemAccountIds::INVENTORY);

        if (! $parentAccount) {
            $this->command?->warn('  لم يُنشأ حساب المستودع الرئيسي: حساب «المخزون» الأب غير موجود. اضبطه من شاشة «الحسابات المحاسبية» ثم أعد تشغيل هذا السيدر.');

            return;
        }

        $account = new Account;
        $account->name = $storehouse->name;
        $account->type_accont = 0; // نوع الحساب (مستودع)
        $account->is_active = 1;
        $account->parent_id = $parentAccount->id;
        $account->balance_type = 'debit';
        $account->account_type = 'assets';
        $account->balance = 0;
        $account->storehouse_id = $storehouse->id;
        $account->code = $this->generateAccountCode($parentAccount->id);
        $account->save();

        $this->command?->info("  أُنشئ حساب المستودع الرئيسي رقم: {$account->code}.");
    }

    /**
     * رمز حساب فريد تحت حسابٍ أب — نفس `StorehouseController::generateAccountCode()`.
     */
    protected function generateAccountCode(int $parentId): string
    {
        $parent = Account::find($parentId);
        $baseCode = $parent ? ($parent->code ?: $parent->id) : $parentId;

        $childrenCount = Account::where('parent_id', $parentId)->count();
        $uniqueCode = $baseCode.str_pad($childrenCount + 1, 3, '0', STR_PAD_LEFT);

        while (Account::where('code', $uniqueCode)->exists()) {
            $childrenCount++;
            $uniqueCode = $baseCode.str_pad($childrenCount + 1, 3, '0', STR_PAD_LEFT);
        }

        return $uniqueCode;
    }
}

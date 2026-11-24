<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Store\ShippingMethod;
use App\Models\Account;
use App\Constants\SystemAccountIds;
use Illuminate\Support\Facades\DB;

class CreateShippingMethodAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipping:create-accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'إنشاء حسابات لجميع طرق الشحن التي لا تملك حسابات';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 بدء إنشاء حسابات لطرق الشحن...');
        $this->newLine();

        try {
            // جلب جميع طرق الشحن
            $allShippingMethods = ShippingMethod::all();
            $this->info("📊 إجمالي طرق الشحن: {$allShippingMethods->count()}");
            $this->newLine();

            // عرض جميع طرق الشحن مع حساباتها
            $this->info('📋 قائمة طرق الشحن:');
            $table = [];
            foreach ($allShippingMethods as $method) {
                $table[] = [
                    'ID' => $method->id,
                    'الاسم' => $method->name,
                    'account_id' => $method->account_id ?? 'NULL',
                    'الحالة' => $method->account_id ? '✅ لديه حساب' : '❌ بدون حساب'
                ];
            }
            $this->table(['ID', 'الاسم', 'account_id', 'الحالة'], $table);
            $this->newLine();

            // جلب طرق الشحن بدون حسابات
            $shippingMethodsWithoutAccount = ShippingMethod::whereNull('account_id')->get();

            if ($shippingMethodsWithoutAccount->isEmpty()) {
                $this->info('✅ جميع طرق الشحن لديها حسابات بالفعل');
                return Command::SUCCESS;
            }

            $this->warn("⚠️  وجدنا {$shippingMethodsWithoutAccount->count()} طرق شحن بدون حسابات");
            $this->newLine();

            if (!$this->confirm('هل تريد إنشاء حسابات لهذه الطرق؟', true)) {
                $this->info('تم الإلغاء');
                return Command::SUCCESS;
            }

            DB::beginTransaction();

            $created = 0;
            $failed = 0;

            foreach ($shippingMethodsWithoutAccount as $shippingMethod) {
                try {
                    $this->info("🔨 إنشاء حساب لـ: {$shippingMethod->name}");

                    // إنشاء حساب لشركة الشحن
                    $account = $this->createShippingCompanyAccount($shippingMethod);
                    
                    // ربط الحساب بطريقة الشحن
                    $shippingMethod->account_id = $account->id;
                    $shippingMethod->save();
                    
                    $this->info("   ✅ تم إنشاء الحساب: [{$account->code}] {$account->name}");
                    $this->info("   ✅ تم ربط الحساب بطريقة الشحن");
                    $this->newLine();
                    
                    $created++;
                } catch (\Exception $e) {
                    $this->error("   ❌ فشل إنشاء حساب لـ {$shippingMethod->name}");
                    $this->error("   خطأ: {$e->getMessage()}");
                    $this->newLine();
                    $failed++;
                }
            }

            DB::commit();

            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ تم بنجاح: $created");
            if ($failed > 0) {
                $this->error("❌ فشل: $failed");
            }
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ حدث خطأ: {$e->getMessage()}");
            $this->error("الملف: {$e->getFile()}");
            $this->error("السطر: {$e->getLine()}");
            return Command::FAILURE;
        }
    }

    /**
     * إنشاء حساب لشركة الشحن
     */
    private function createShippingCompanyAccount(ShippingMethod $shippingMethod)
    {
        // الحصول على حساب شركات الشحن الرئيسي
        $parentAccount = Account::find(SystemAccountIds::SHIPPING_COMPANIES);
        
        if (!$parentAccount) {
            throw new \Exception('حساب شركات الشحن الرئيسي غير موجود (ID: ' . SystemAccountIds::SHIPPING_COMPANIES . ')');
        }

        // إنشاء كود الحساب
        $lastAccountCode = Account::where('parent_id', SystemAccountIds::SHIPPING_COMPANIES)
            ->orderBy('code', 'desc')
            ->value('code');
        
        $nextCode = $lastAccountCode ? intval($lastAccountCode) + 1 : intval($parentAccount->code) * 10 + 1;

        // إنشاء الحساب
        $account = Account::create([
            'name' => 'شركة شحن: ' . $shippingMethod->name,
            'code' => (string)$nextCode,
            'parent_id' => SystemAccountIds::SHIPPING_COMPANIES,
            'type' => 'sub',
            'account_type' => Account::LIABILITIES,
            'balance_type' => Account::CREDIT,
            'balance' => 0,
            'is_active' => true,
            'is_system' => false,
        ]);

        return $account;
    }
}

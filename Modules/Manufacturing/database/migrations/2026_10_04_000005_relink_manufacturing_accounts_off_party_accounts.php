<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حساباتُ التصنيع لا تُربط بحسابات عملاء أو مورّدين.
 *
 * ── ما كان ───────────────────────────────────────────────────────────
 *
 * شاشةُ «الحسابات المحاسبية» تُملأ ابتداءً بمعرّفاتٍ ثابتة
 * (`SystemAccountIds`: 82، 83، 3201…). وفي قاعدةٍ استُورد دليلُ حساباتها من
 * نظامٍ آخر تقع تلك المعرّفاتُ على **حسابات عملاء**: «مخزون تحت التشغيل»
 * مربوطٌ بحساب بقالة، و«مخزون البضاعة الجاهزة» بحساب سوق. فكلُّ قيدِ
 * تصنيعٍ يُرحَّل على ذمّة عميل — يتغيّر رصيدُه بلا فاتورةٍ ولا سند.
 *
 * ── ما تفعله ────────────────────────────────────────────────────────
 *
 * لكل مفتاحٍ من مفاتيح التصنيع: إن كان حسابُه مفقوداً، أو حسابَ عميلٍ أو
 * مورّد، أو من نوعٍ غير نوعه (أصلٌ مكان مصروف)، يُعاد ربطُه:
 *
 *   ١) بحسابٍ قائمٍ بالاسم نفسِه من النوع الصحيح، ليس حسابَ طرف؛
 *   ٢) وإلّا يُنشأ حسابٌ فرعيّ تحت الأب المناسب (المخزون / مصروفات التصنيع).
 *
 * والربطُ السليم لا يُمسّ. والقيودُ المُرحَّلة سابقاً على حساباتٍ خاطئة لا
 * تُنقل هنا — نقلُها قرارٌ محاسبيّ يُتَّخذ بقيدِ تسويةٍ لا بهجرة.
 */
return new class extends Migration
{
    /** المفتاح ⟵ [الاسم، النوع، مفتاحُ الأب] */
    private array $targets = [
        'raw_materials_inventory' => ['مخزون المواد الخام', 'assets', 'inventory'],
        'work_in_progress_inventory' => ['مخزون تحت التشغيل - تصنيع', 'assets', 'inventory'],
        'finished_goods_inventory' => ['مخزون البضاعة الجاهزة', 'assets', 'inventory'],
        'waste_inventory' => ['مخزون المواد الهالكة', 'assets', 'inventory'],
        'manufacturing_overhead' => ['تكاليف التشغيل والتصنيع', 'expenses', 'expenses'],
        'production_variance' => ['انحراف تكاليف الإنتاج', 'expenses', 'expenses'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('accounting_settings') || ! Schema::hasTable('accounts')) {
            return;
        }

        $expensesParent = $this->expensesParentId();
        $inventoryParent = $this->inventoryParentId();

        foreach ($this->targets as $key => [$name, $type, $parentKind]) {
            $setting = DB::table('accounting_settings')->where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            $account = $setting->account_id ? DB::table('accounts')->where('id', $setting->account_id)->first() : null;

            if ($account && $this->isSound($account, $type)) {
                continue;
            }

            $parentId = $parentKind === 'inventory' ? $inventoryParent : $expensesParent;
            $replacement = $this->findOrCreate($name, $type, $parentId);

            if (! $replacement) {
                continue;
            }

            DB::table('accounting_settings')->where('id', $setting->id)->update([
                'account_id' => $replacement->id,
                'account_code' => $replacement->code,
                'updated_at' => now(),
            ]);
        }

        try {
            \Illuminate\Support\Facades\Cache::forget('accounting_settings.map');
        } catch (\Throwable $e) {
            // الكاش غير متاح أثناء الترحيل — يُحدَّث عند أوّل قراءة
        }
    }

    public function down(): void
    {
        // لا رجوع: إعادةُ الربط بحساب عميلٍ ليست حالةً يُعاد إليها
    }

    /** حسابٌ قائم، من النوع الصحيح، وليس حسابَ عميلٍ أو مورّد. */
    private function isSound(object $account, string $type): bool
    {
        if (! empty($account->client_id) || ! empty($account->supplier_id)) {
            return false;
        }

        return empty($account->account_type) || $account->account_type === $type;
    }

    private function findOrCreate(string $name, string $type, ?int $parentId): ?object
    {
        $existing = DB::table('accounts')
            ->where('name', $name)
            ->whereNull('client_id')
            ->whereNull('supplier_id')
            ->where(function ($q) use ($type) {
                $q->where('account_type', $type)->orWhereNull('account_type');
            })
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! $parentId) {
            return null;
        }

        $parent = DB::table('accounts')->where('id', $parentId)->first();

        if (! $parent) {
            return null;
        }

        $id = DB::table('accounts')->insertGetId([
            'name' => $name,
            'code' => $this->nextCode($parent),
            'parent_id' => $parent->id,
            'type' => 'sub',
            'account_type' => $type,
            'balance_type' => $type === 'assets' ? 'debit' : 'debit',
            'balance' => 0,
            'opening_balance' => 0,
            'is_active' => 1,
            'is_system' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('accounts')->where('id', $id)->first();
    }

    /** أوّلُ كودٍ حرّ تحت الأب: كودُه متبوعاً برقمٍ تسلسليّ. */
    private function nextCode(object $parent): string
    {
        for ($n = 1; $n < 1000; $n++) {
            $code = $parent->code.$n;

            if (! DB::table('accounts')->where('code', $code)->exists()) {
                return $code;
            }
        }

        return $parent->code.'-'.uniqid();
    }

    /** أبُ حسابات المخزون — من الإعدادات، ثمّ بالاسم. */
    private function inventoryParentId(): ?int
    {
        foreach (['inventory_parent', 'warehouse_accounts_parent', 'inventory'] as $key) {
            $id = DB::table('accounting_settings')->where('key', $key)->value('account_id');

            if ($id && $this->partyFree($id, 'assets')) {
                return (int) $id;
            }
        }

        $id = DB::table('accounts')
            ->where('account_type', 'assets')
            ->whereNull('client_id')
            ->whereNull('supplier_id')
            ->where('name', 'like', '%المخزون%')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /** أبُ مصروفات التصنيع — من الإعدادات، ثمّ جذرُ المصروفات. */
    private function expensesParentId(): ?int
    {
        $id = DB::table('accounting_settings')->where('key', 'manufacturing_expenses_parent')->value('account_id');

        if ($id && $this->partyFree($id, 'expenses')) {
            return (int) $id;
        }

        $id = DB::table('accounts')
            ->where('account_type', 'expenses')
            ->whereNull('parent_id')
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function partyFree($accountId, string $type): bool
    {
        $account = DB::table('accounts')->where('id', $accountId)->first();

        return $account && $this->isSound($account, $type);
    }
};

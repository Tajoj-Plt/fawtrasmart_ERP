<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حسابات تصنيف المصروفات + صلاحية «كل الحسابات في سند الصرف».
 *
 * من لا يملك `account.expenses.all_accounts` يرى في سند الصرف العام حسابات
 * التصنيف المختار وحدها، وفي «لعميل» عملاء خطّ سيره. والربط تصنيف ↔ حساب لم
 * يكن له جدول (المطابقة بالاسم في `ExpensesCategory::accounts()` تخمين)،
 * فيُنشأ هنا ويُملأ ممّا استُعمل فعلاً في السندات السابقة كي لا تفرغ قائمة
 * المندوب يوم التشغيل — والمحاسب يعدّله من «تصنيفات المصروفات».
 *
 * الصلاحية تُمنح لمن يصرف ويملك `data_scope_all` (المدير ومن فوقه): هم من
 * كانوا يرون كل شيء، فلا يتغيّر عليهم شيء.
 */
return new class extends Migration
{
    private const PERMISSION = 'account.expenses.all_accounts';

    public function up(): void
    {
        if (! Schema::hasTable('expenses_category_accounts')) {
            Schema::create('expenses_category_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expenses_category_id');
                $table->unsignedBigInteger('account_id');
                $table->timestamps();

                $table->unique(['expenses_category_id', 'account_id'], 'expenses_category_account_unique');
                $table->index('account_id');
            });

            $this->backfillFromHistory();
        }

        $this->grantPermission();
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses_category_accounts');

        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $ids = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->pluck('id');
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            $this->forgetPermissionCache();
        }
    }

    /** كل زوج (تصنيف، حساب) ورد في سند صرفٍ عام غير معكوس، والحساب ما زال موجوداً. */
    private function backfillFromHistory(): void
    {
        if (! Schema::hasTable('expenses') || ! Schema::hasColumn('expenses', 'expenses_category_id')) {
            return;
        }

        $pairs = DB::table('expenses')
            ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
            ->join('expenses_categories', 'expenses_categories.id', '=', 'expenses.expenses_category_id')
            ->whereNull('accounts.client_id')
            ->whereNull('accounts.supplier_id')
            ->when(Schema::hasColumn('expenses', 'reversed_at'), fn ($q) => $q->whereNull('expenses.reversed_at'))
            ->when(Schema::hasColumn('expenses', 'expense_type'), fn ($q) => $q->where(
                fn ($w) => $w->whereNull('expenses.expense_type')->orWhere('expenses.expense_type', 'general')
            ))
            ->distinct()
            ->get(['expenses.expenses_category_id', 'expenses.account_id']);

        // حساباتُ المصروفات الطرفية وحدها — ما تعرضه شاشة السند العام أصلاً
        try {
            $leafIds = app(\Modules\Account\Http\Controllers\Finance\ExpensesController::class)->getExpenseLeafAccounts()->pluck('id')->map(fn ($id) => (int) $id)->all();
        } catch (\Throwable $e) {
            $leafIds = null;
        }

        foreach ($pairs as $pair) {
            if ($leafIds !== null && ! in_array((int) $pair->account_id, $leafIds, true)) {
                continue;
            }

            DB::table('expenses_category_accounts')->insertOrIgnore([
                'expenses_category_id' => $pair->expenses_category_id,
                'account_id' => $pair->account_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $holders = fn (string $name) => DB::table('role_has_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.name', $name)
            ->pluck('rp.role_id')
            ->all();

        foreach (array_intersect($holders('account.expenses.create'), $holders('data_scope_all')) as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};

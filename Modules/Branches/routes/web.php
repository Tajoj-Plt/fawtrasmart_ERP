<?php

use App\Constants\permissions\BranchesPermissions;
use App\Constants\planFeatures\BranchesPlanFeatures;
use Illuminate\Support\Facades\Route;
use Modules\Branches\Http\Controllers\BranchesController;
use Modules\Branches\Http\Controllers\TerritoryGroupsController;

Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath', 'check.branch', 'auth'],
], function () {

    /*
     * تبديل الفرع النشط — خارج بوابة صلاحية `branches` عمداً:
     * كل مستخدم يبدّل فرعه من الترويسة، والمتحكّم نفسه يتحقّق من أن الفرع
     * ضمن فروعه المسموح بها. لو وضعناه خلف البوابة لتعطّل مبدّل الفروع
     * في ترويسة النظام لكل من لا يدير الفروع.
     */
    Route::get('branches/switch-branch/{branch}', [BranchesController::class, 'switchBranch'])->name('branch.switch');

    /*
     * كل ما تبقّى يدير الهيكل التنظيمي — إنشاء وحذف فروع، ودمج مجموعات
     * ينقل آلاف العملاء. كان الموديول كلّه خلف `auth` فقط، فأي موظف مسجَّل
     * دخوله يستطيع استدعاء مسار الحذف أو الضم مباشرة. إخفاء الرابط من
     * القائمة ليس حمايةً — الحماية هنا.
     */
    /*
     * كانت البوّابة هنا صلاحيةً واحدة خشنة (`permission:branches`): من مُنحها
     * ملك كل شيء في الوحدة — حذفَ الفروع، ونسخَ الإعدادات على فروعٍ أخرى،
     * ودمجَ المجموعات الذي ينقل آلاف العملاء دفعةً واحدة.
     *
     * صار لكل مسارٍ صلاحيتُه من [\App\Constants\permissions\BranchesPermissions]،
     * فيُمنح المستخدمُ ما يفعله لا الوحدةَ كلَّها. والبوّابة الجامعة بقيت
     * شرطاً أوّلَ بصيغة «أيُّ صلاحية فروع» — تردّ من لا شأن له بالوحدة أصلاً
     * قبل أن يصل إلى حارس المسار.
     */
    Route::middleware([
        'permission.role:'.implode('|', BranchesPermissions::all()),
        // وسورُ الباقة بجانبه: من لم يشترِ شيئاً من الفروع يُردّ هنا
        // بـ402 قبل أن يصل إلى حارس الصلاحية
        'plan:'.implode('|', BranchesPlanFeatures::all()),
    ])->group(function () {

        // =============================================
        // مجموعات المناطق (Territory Groups) - منقولة من موديول Client
        // =============================================
        Route::prefix('group')->group(function () {
            // حذف/ضم/تراجع = عمليات مدمّرة، لذلك POST/DELETE محميّة بـ CSRF وليست روابط GET.
            Route::delete('/destroy/{id}', [TerritoryGroupsController::class, 'destroy'])->name('groups.group_client_destroy')->middleware(['permission.role:branches.groups.delete', 'plan:'.BranchesPlanFeatures::TERRITORY_GROUPS]);
            Route::get('/merge/{id}', [TerritoryGroupsController::class, 'merge_groups_page'])->name('groups.merge_groups_page')->middleware(['permission.role:branches.groups.merge', 'plan:'.BranchesPlanFeatures::MERGE]);
            Route::post('/merge/{id}', [TerritoryGroupsController::class, 'merge_groups'])->name('groups.merge_groups')->middleware(['permission.role:branches.groups.merge', 'plan:'.BranchesPlanFeatures::MERGE, 'plan.limit:'.BranchesPlanFeatures::LIMIT_TRANSFERS]);
            Route::get('/merge-select/page', [TerritoryGroupsController::class, 'merge_select_page'])->name('groups.merge_select_page')->middleware(['permission.role:branches.groups.merge', 'plan:'.BranchesPlanFeatures::MERGE]);
            Route::post('/merge-select/bulk', [TerritoryGroupsController::class, 'merge_groups_bulk'])->name('groups.merge_groups_bulk')->middleware(['permission.role:branches.groups.merge', 'plan:'.BranchesPlanFeatures::MERGE, 'plan.limit:'.BranchesPlanFeatures::LIMIT_TRANSFERS]);
            Route::get('/bulk-merge/page', [TerritoryGroupsController::class, 'bulk_merge_page'])->name('groups.bulk_merge_page')->middleware(['permission.role:branches.groups.merge', 'plan:'.BranchesPlanFeatures::MERGE]);
            Route::post('/bulk-merge', [TerritoryGroupsController::class, 'bulk_merge'])->name('groups.bulk_merge')->middleware(['permission.role:branches.groups.merge', 'plan:'.BranchesPlanFeatures::MERGE, 'plan.limit:'.BranchesPlanFeatures::LIMIT_TRANSFERS]);
            Route::get('/merge-log/list', [TerritoryGroupsController::class, 'merge_logs'])->name('groups.merge_logs')->middleware(['permission.role:branches.groups.view', 'plan:'.BranchesPlanFeatures::TERRITORY_GROUPS]);
            Route::post('/merge-log/revert/{log}', [TerritoryGroupsController::class, 'revert_merge'])->name('groups.revert_merge')->middleware(['permission.role:branches.groups.revert', 'plan:'.BranchesPlanFeatures::MERGE]);
            Route::post('/transfer-clients', [TerritoryGroupsController::class, 'transfer_clients'])->name('groups.transfer_clients')->middleware(['permission.role:branches.groups.transfer_clients', 'plan:'.BranchesPlanFeatures::MERGE, 'plan.limit:'.BranchesPlanFeatures::LIMIT_TRANSFERS]);
            Route::get('/get-clients-by-node/{nodeKey}', [TerritoryGroupsController::class, 'getClientsByNode'])->name('groups.get_clients_by_node')->middleware(['permission.role:branches.groups.view', 'plan:'.BranchesPlanFeatures::TERRITORY_GROUPS]);
        });

        Route::prefix('branches')->group(function () {
            Route::get('/', [BranchesController::class, 'dashboard'])->name('branches.dashboard')->middleware(['permission.role:branches.dashboard', 'plan:'.BranchesPlanFeatures::DASHBOARD]);
            Route::get('/index', [BranchesController::class, 'index'])->name('branches.index')->middleware(['permission.role:branches.view', 'plan:'.BranchesPlanFeatures::BRANCHES]);
            Route::get('/export', [BranchesController::class, 'export'])->name('branches.export')->middleware(['permission.role:branches.export', 'plan:'.BranchesPlanFeatures::EXPORT]);
            Route::get('/create', [BranchesController::class, 'create'])->name('branches.create')->middleware(['permission.role:branches.create', 'plan:'.BranchesPlanFeatures::BRANCHES, 'plan.limit:'.BranchesPlanFeatures::LIMIT_BRANCHES]);
            Route::post('/store', [BranchesController::class, 'store'])->name('branches.store')->middleware(['permission.role:branches.create', 'plan:'.BranchesPlanFeatures::BRANCHES, 'plan.limit:'.BranchesPlanFeatures::LIMIT_BRANCHES]);
            Route::get('/show/{id}', [BranchesController::class, 'show'])->name('branches.show')->middleware(['permission.role:branches.show', 'plan:'.BranchesPlanFeatures::BRANCHES]);
            Route::get('/edit/{id}', [BranchesController::class, 'edit'])->name('branches.edit')->middleware(['permission.role:branches.edit', 'plan:'.BranchesPlanFeatures::BRANCHES]);
            Route::put('/update/{id}', [BranchesController::class, 'update'])->name('branches.update')->middleware(['permission.role:branches.edit', 'plan:'.BranchesPlanFeatures::BRANCHES]);
            Route::post('/bulk-assign-parent', [BranchesController::class, 'bulkAssignParent'])->name('branches.bulk_assign_parent')->middleware(['permission.role:branches.hierarchy', 'plan:'.BranchesPlanFeatures::HIERARCHY, 'plan.limit:'.BranchesPlanFeatures::LIMIT_TRANSFERS]);
            Route::patch('/updateStatus/{id}', [BranchesController::class, 'updateStatus'])->name('branches.updateStatus')->middleware(['permission.role:branches.toggle_status', 'plan:'.BranchesPlanFeatures::BRANCHES]);
            Route::delete('/delete/{id}', [BranchesController::class, 'destroy'])->name('branches.destroy')->middleware(['permission.role:branches.delete', 'plan:'.BranchesPlanFeatures::BRANCHES]);
            Route::get('/settings', [BranchesController::class, 'settings'])->name('branches.settings')->middleware(['permission.role:branches.settings.view', 'plan:'.BranchesPlanFeatures::SETTINGS]);
            Route::post('/settings', [BranchesController::class, 'settings_store'])->name('branches.settings_store')->middleware(['permission.role:branches.settings.edit', 'plan:'.BranchesPlanFeatures::SETTINGS]);
            // نسخ الإعدادات والإرجاع للافتراضي يمسّان فروعاً كثيرة دفعةً
            // واحدة، لذلك POST محميّة بـ CSRF لا روابط GET.
            Route::post('/settings/copy', [BranchesController::class, 'settingsCopy'])->name('branches.settings_copy')->middleware(['permission.role:branches.settings.copy', 'plan:'.BranchesPlanFeatures::SETTINGS]);
            Route::post('/settings/reset', [BranchesController::class, 'settingsReset'])->name('branches.settings_reset')->middleware(['permission.role:branches.settings.reset', 'plan:'.BranchesPlanFeatures::SETTINGS]);
            Route::get('/settings/get-settings', [BranchesController::class, 'getSettings'])->name('settings.get')->middleware(['permission.role:branches.settings.view', 'plan:'.BranchesPlanFeatures::SETTINGS]);
            Route::get('/migrate-region-groubs', [BranchesController::class, 'migrateRegionGroubsForm'])->name('branches.migrate_region_groubs')->middleware(['permission.role:branches.migrate', 'plan:'.BranchesPlanFeatures::HIERARCHY]);
            Route::post('/migrate-region-groubs', [BranchesController::class, 'migrateRegionGroubsSubmit'])->name('branches.migrate_region_groubs_submit')->middleware(['permission.role:branches.migrate', 'plan:'.BranchesPlanFeatures::HIERARCHY, 'plan.limit:'.BranchesPlanFeatures::LIMIT_TRANSFERS]);
        });
    });
});

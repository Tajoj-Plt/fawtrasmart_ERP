<?php

use App\Constants\planFeatures\StockPlanFeatures;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Manufacturing\Http\Controllers\BOMController;
use Modules\Manufacturing\Http\Controllers\IndirectCostsController;
use Modules\Manufacturing\Http\Controllers\ManufacturingDashboardController;
use Modules\Manufacturing\Http\Controllers\ManufacturingOrdersController;
use Modules\Manufacturing\Http\Controllers\ManufacturingReportsController;
use Modules\Manufacturing\Http\Controllers\ProductionPlansController;
use Modules\Manufacturing\Http\Controllers\SettingsController;
use Modules\Manufacturing\Http\Controllers\TestManufacturingCalculatorController;
use Modules\Manufacturing\Http\Controllers\WorkstationsController;

/*
|--------------------------------------------------------------------------
| مسارات وحدة التصنيع
|--------------------------------------------------------------------------
|
| كانت هذه المسارات كلّها في `Modules/Stock/routes/web.php` تحت البادئة
| `stock/` — قوائمُ المواد وأوامرُ التصنيع ومحطّاتُ العمل والتكاليفُ غير
| المباشرة، خمسٌ وسبعون مساراً في ملفٍّ من تسعمئة سطر يخصّ المخزون.
|
| **أسماء المسارات لم تتغيّر** (`BOM.index`، `manufacturing.orders.index`، …)
| — تغيّرت البادئة وحدها من `stock/` إلى `manufacturing/`. فكلّ `route('…')`
| في المشروع يظلّ صحيحاً، ولا يحتاج تغييراً.
|
| والعناوين القديمة تحت `stock/` تُوجَّه إلى الجديدة أدناه، فلا تنكسر
| مفضّلةٌ ولا رابطٌ محفوظ.
|
*/

Route::middleware(['auth'])->group(function () {
    Route::group(
        [
            'prefix' => LaravelLocalization::setLocale(),
            'middleware' => [
                'localeSessionRedirect',
                'localizationRedirect',
                'localeViewPath',
                'check.branch',
                'auth',
                // «الفترة المحاسبية» المختارة تصير تاريخَي التقرير قبل
                // أن يقرأهما المتحكّم — كما في وحدتَي المخزون والحسابات
                'accounting.period',
            ],
        ],
        function () {
            /*
                ثلاثةُ أسوارٍ متعاقبة على الوحدة كلّها — كما في وحدة المخزون.

                ١. `plan:` — أاشترت المنشأة شيئاً من التصنيع أصلاً؟ الوحدة
                   فيها تسعةٌ وستون مساراً، ووضعُ الحدّ على كلٍّ منها يعني
                   أن نسيان واحدٍ يفتحه كلَّه — والمنسيُّ عادةً مسارُ AJAX
                   لا شاشة، فلا يلاحظ أحدٌ غيابَ حارسه. فموضعُه المجموعة.

                   وكانت الوحدة **بلا حدّ باقةٍ إطلاقاً** بينما نظيراتُها
                   محروسة: منشأةٌ على باقةٍ لا تشمل التصنيع تفتح شاشاته
                   كلَّها وتُرحّل قيودَه إلى دفترها.

                   والشرط «واحدةٌ على الأقل» لا «كلّها» — كما في وحدة
                   المخزون حرفاً بحرف. فالوحدة أربعُ ميزاتٍ مستقلّة في
                   الكتالوج (`stock_manufacturing` لأوامر التصنيع،
                   و`stock_bom`، و`stock_workstations`، و`stock_reports`)،
                   وباقةٌ تشمل قوائم المواد وحدها يجب أن تفتح شاشتها —
                   وحارسُ كل شاشةٍ داخلَها يتكفّل بالباقي. ولو اشتُرط
                   `stock_manufacturing` على المجموعة لَرُدَّت تلك الباقةُ
                   عن الوحدة بأسرها ومعها ما اشترته فعلاً.

                ١ب. `plan.limit:` على مسارات الإنشاء وحدها — السقف لا
                   الميزة. الحدود الثلاثة (`manufacturing_orders`،
                   `bom_lists`، `workstations`) كانت **تُقاس وتُرفَع إلى
                   فوترة ولا تُلزَم**: ترى فوترةُ التجاوزَ ولا يمنعه النظام.
                   انظر [\App\Constants\planFeatures\StockPlanFeatures].

                ٢. `permission.role:` — على كلّ مسار بصلاحيته هو، أدناه.

                ٣. الميدل وير `module` المُلحق بمجموعة `web` كلّها — يمنع
                   الوحدةَ المعطَّلة من «شاشة التطبيقات» (البادئة
                   `manufacturing` مسجَّلة في `ApplicationRegistry::groups()`).

                والحارسُ صار `permission.role` لا `permission` في المسارات
                كلّها: الأوّل يقرأ `users.role_id ← role_has_permissions`
                مباشرةً — وهو مصدرُ الصلاحيات الوحيد في هذا النظام — بينما
                الثاني وسيطُ Spatie الذي يمرّ على `Gate` ويقرأ معه
                `model_has_permissions` و`model_has_roles`: جدولان لا
                يكتبهما النظام ولا تعرضهما شاشةُ الأدوار، فصفٌّ قديمٌ فيهما
                يمنح صلاحيةً لا يراها أحدٌ في الشاشة ولا يستطيع سحبَها.
                وهو نفسُ ما تفعله وحدة المخزون في مسارات الأذون.
            */
            Route::prefix('manufacturing')
                ->middleware(['auth', 'plan:'.implode('|', StockPlanFeatures::manufacturingOnly())])
                ->group(function () {
                    /*
                        كان هذا المسار وحيداً تحت بادئة `products` في وحدة
                        المخزون — بعيداً عن بقيّة مسارات التكاليف غير
                        المباشرة. جُمع إليها هنا.
                    */
                    Route::post('/IndirectCosts/get-restrictions-by-date', [IndirectCostsController::class, 'getRestrictionsByDate'])
                        ->name('indirectcosts.getRestrictionsByDate')
                        ->middleware([
                            'permission.role:stock.indirect_costs.view',
                            // خارج مجموعة `IndirectCosts` فيلزمه حدُّ الباقة صراحةً —
                            // ومسارُ AJAX أوّلُ ما يُنسى حارسُه
                            'plan:'.StockPlanFeatures::MANUFACTURING,
                        ]);

                    // ===== BOM (قوائم مواد الإنتاج) =====
                    /*
                        مدخل الوحدة.

                        كان التصنيع قسماً داخل المخزون بلا صفحةٍ تجمع حاله —
                        يُدخَل إليه إلى «أوامر التصنيع» مباشرةً. ولمّا صار وحدةً
                        قائمة لزمته لوحةٌ أولى كسائر الوحدات، تقود إليها بطاقتُه
                        في الصفحة الرئيسية.
                    */
                    /*
                        الصلاحية هنا **أيّ** واحدةٍ من أربع، لا
                        `stock.manufacturing_orders.view` وحدها.

                        بطاقةُ «التصنيع» في الصفحة الرئيسية تظهر لمن يملك أياً
                        من الأربع (`@canany` في `resources/views/dashboard.blade.php`)،
                        وكان هذا المسار — وهو وجهةُ البطاقة — يشترط الأولى
                        وحدها. فمن يملك `stock.bom.view` أو `stock.workstations.view`
                        أو `stock.indirect_costs.view` دونها يرى البطاقة ثم
                        يُردّ بـ403 عند نقرها: الوحدة «لا تفتح» وهو يراها.

                        واللوحةُ بابُ دخولٍ عامّ لا فعلٌ بعينه — تعرض عدّادات
                        الأوامر وقوائم المواد والمحطّات والتكاليف — فشرطُها
                        أن يملك المستخدم شيئاً في الوحدة، لا أن يملك أوامرَ
                        التصنيع تحديداً. وكلُّ شاشةٍ داخلها تظلّ محروسةً
                        بصلاحيتها هي.
                    */
                    Route::get('/dashboard', [ManufacturingDashboardController::class, 'index'])
                        ->name('manufacturing.dashboard')
                        ->middleware('permission.role:stock.manufacturing_orders.view|stock.bom.view|stock.workstations.view|stock.indirect_costs.view');

                    /*
                        ===== تقارير التصنيع =====

                        كان الشريط الجانبي واللوحة يشيران إلى
                        `ReportsProduction.index` في وحدة التقارير — وهو مسارٌ
                        يستدعي `RentalsController@production`، دالةً لا وجود
                        لها. فكانت «تقارير التصنيع» تُنتج 500 لا تقريراً.

                        وأسماء المسارات هنا تبدأ بـ`manufacturing.reports.`
                        وحدها؛ ويظلّ `ReportsProduction.index` قائماً في وحدة
                        التقارير لغيرها، لا يُمَسّ.
                    */
                    /*
                        وحدُّ الباقة `stock_reports`: هو مفتاحُ التقارير
                        الوحيد في كتالوج فوترة — لا مفتاحَ خاصّاً بتقارير
                        التصنيع — وبه تُحرَس شاشاتُ التقارير في المشروع
                        كلِّها. فإن أضافت فوترة مفتاحاً خاصّاً لاحقاً،
                        فموضعُ تبديله هنا وفي `manufacturingOnly()`.
                    */
                    Route::prefix('reports')
                        ->middleware([
                            'permission.role:stock.reports.manufacturing',
                            'plan:'.StockPlanFeatures::REPORTS,
                        ])
                        ->group(function () {
                            Route::get('/', [ManufacturingReportsController::class, 'index'])
                                ->name('manufacturing.reports.index');
                            Route::get('/orders', [ManufacturingReportsController::class, 'orders'])
                                ->name('manufacturing.reports.orders');
                            Route::get('/costs', [ManufacturingReportsController::class, 'costs'])
                                ->name('manufacturing.reports.costs');
                            Route::get('/workstations', [ManufacturingReportsController::class, 'workstations'])
                                ->name('manufacturing.reports.workstations');
                            Route::get('/waste', [ManufacturingReportsController::class, 'waste'])
                                ->name('manufacturing.reports.waste');
                            Route::get('/boms', [ManufacturingReportsController::class, 'boms'])
                                ->name('manufacturing.reports.boms');
                        });

                    // قوائم مواد الإنتاج — ميزتها `stock_bom` وسقفُها `bom_lists`
                    Route::prefix('BOM')->middleware('plan:'.StockPlanFeatures::BOM)->group(function () {
                        Route::get('/index', [BOMController::class, 'index'])
                            ->name('BOM.index')
                            ->middleware('permission.role:stock.bom.view');
                        Route::get('/data', [BOMController::class, 'getData'])
                            ->name('BOM.getData')
                            ->middleware('permission.role:stock.bom.view');
                        /*
                            السقف يُفحص على `create` و`store` معاً.

                            المنعُ الحقيقي على `store` — وهو وحده ما لا
                            يُلتفّ عليه. ووضعُه على `create` كذلك لئلّا
                            يملأ المستخدم وصفةً بعشرين مادّةً ثمّ يُردّ
                            عند الحفظ. وهي قاعدة [\App\Http\Middleware\EnforcePlanLimit]
                            نفسُها كما تطبّقها وحدة المخزون.
                        */
                        Route::get('/create', [BOMController::class, 'create'])
                            ->name('BOM.create')
                            ->middleware(['permission.role:stock.bom.create', 'plan.limit:'.StockPlanFeatures::LIMIT_BOM_LISTS]);
                        Route::get('/edit/{id}', [BOMController::class, 'edit'])
                            ->name('Bom.edit')
                            ->middleware('permission.role:stock.bom.edit');
                        Route::get('/show/{id}', [BOMController::class, 'show'])
                            ->name('Bom.show')
                            ->middleware('permission.role:stock.bom.show');
                        Route::post('/store', [BOMController::class, 'store'])
                            ->name('Bom.store')
                            ->middleware(['permission.role:stock.bom.create', 'plan.limit:'.StockPlanFeatures::LIMIT_BOM_LISTS]);
                        Route::put('/update/{id}', [BOMController::class, 'update'])
                            ->name('Bom.update')
                            ->middleware('permission.role:stock.bom.edit');
                        Route::delete('/delete/{id}', [BOMController::class, 'destroy'])
                            ->name('Bom.destroy')
                            ->middleware('permission.role:stock.bom.delete');
                        /*
                            حُذفت خمسة مسارات — المرحلة ١.

                            أربعةٌ منها تشير إلى دوالّ **لا وجود لها** في
                            المتحكّم، فكلّ نقرة عليها تُلقي
                            BadMethodCallException:

                              Bom.updatePassword · Bom.login_to
                              Bom.export_view    · Bom.export

                            و`Bom.finish` كانت تستدعي `finish()` — مسار
                            التصنيع الموازي الذي يصرف مخزوناً ويُرحّل قيداً
                            غير متوازن من داخل شاشة قائمة المواد. حُذفت
                            الدالّة، فحُذف مسارها معها.

                            الإنهاء الحقيقي موضعه أمر التصنيع:
                            `manufacturing.orders.complete`.
                        */
                    });

                    // ===== Manufacturing Orders =====
                    // ميزتها `stock_manufacturing` وعدّادُها الشهري `manufacturing_orders`
                    Route::prefix('orders')->middleware('plan:'.StockPlanFeatures::MANUFACTURING)->group(function () {
                        Route::get('/index', [ManufacturingOrdersController::class, 'index'])
                            ->name('manufacturing.orders.index')
                            ->middleware('permission.role:stock.manufacturing_orders.view');
                        /*
                            `manufacturing_orders` عدّادٌ **شهري** لا سقفٌ
                            متزامن: يُعدّ ما أُنشئ في الفترة الجارية لا ما
                            هو قائم. فحذفُ أمرٍ لا يُرجع رصيداً استُهلك —
                            وهو ما تقوله `UsageReporter` بـ`kind => quota`.
                        */
                        Route::get('/create', [ManufacturingOrdersController::class, 'create'])
                            ->name('manufacturing.orders.create')
                            ->middleware(['permission.role:stock.manufacturing_orders.create', 'plan.limit:'.StockPlanFeatures::LIMIT_MANUFACTURING_ORDERS]);
                        Route::get('/ajax/data', [ManufacturingOrdersController::class, 'getData'])
                            ->name('manufacturing.orders.data')
                            ->middleware('permission.role:stock.manufacturing_orders.view');
                        /*
                            حُذف مسارا `finish` و`finish.process`.

                            الأول كان يستدعي `view('manufacturing.orders.finish')`
                            — بالنقطة بدل `::`، وإلى ملفٍ غير موجود أصلاً.
                            والثاني مسارُ إنهاءٍ ثانٍ لا تستدعيه أي واجهة،
                            وسلوكه المحاسبي يخالف `completeOrder` كلياً:
                            بلا قيود، وبمصدر إذن مختلف، ولا يحفظ معرّف
                            الإذن فيعجز التراجع عن إيجاده.

                            المسار الحيّ الوحيد للإنهاء: `orders/{id}/complete`.
                        */
                        Route::post('/{id}/undo-completion', [ManufacturingOrdersController::class, 'undoCompletion'])
                            ->name('manufacturing.orders.undo-completion')
                            ->middleware('permission.role:stock.manufacturing_orders.undo_finish');
                        Route::post('/{id}/close', [ManufacturingOrdersController::class, 'closeOrder'])
                            ->name('manufacturing.orders.close')
                            ->middleware('permission.role:stock.manufacturing_orders.close');
                        Route::post('/{id}/reopen', [ManufacturingOrdersController::class, 'reopenOrder'])
                            ->name('manufacturing.orders.reopen')
                            ->middleware('permission.role:stock.manufacturing_orders.reopen');
                        Route::post('/{id}/add-note', [ManufacturingOrdersController::class, 'addNote'])
                            ->name('manufacturing.orders.addNote')
                            ->middleware('permission.role:stock.manufacturing_orders.notes');
                        Route::get('/{id}/get-notes', [ManufacturingOrdersController::class, 'getNotes'])
                            ->name('manufacturing.orders.getNotes')
                            ->middleware('permission.role:stock.manufacturing_orders.notes');
                        Route::delete('/note/{noteId}', [ManufacturingOrdersController::class, 'deleteNote'])
                            ->name('manufacturing.orders.deleteNote')
                            ->middleware('permission.role:stock.manufacturing_orders.notes');
                        Route::post('/add-general-note', [ManufacturingOrdersController::class, 'addNote'])
                            ->name('manufacturing.orders.addGeneralNote')
                            ->middleware('permission.role:stock.manufacturing_orders.notes');
                        Route::get('/get-general-notes', [ManufacturingOrdersController::class, 'getNotes'])
                            ->name('manufacturing.orders.getGeneralNotes')
                            ->middleware('permission.role:stock.manufacturing_orders.notes');
                        Route::delete('/delete-general-note/{noteId}', [ManufacturingOrdersController::class, 'deleteNote'])
                            ->name('manufacturing.orders.deleteGeneralNote')
                            ->middleware('permission.role:stock.manufacturing_orders.notes');
                        Route::get('/ajax/filter-options', [ManufacturingOrdersController::class, 'getFilterOptions'])
                            ->name('manufacturing.orders.filter-options')
                            ->middleware('permission.role:stock.manufacturing_orders.view');
                        Route::post('/store', [ManufacturingOrdersController::class, 'store'])
                            ->name('manufacturing.orders.store')
                            ->middleware(['permission.role:stock.manufacturing_orders.create', 'plan.limit:'.StockPlanFeatures::LIMIT_MANUFACTURING_ORDERS]);
                        Route::get('/edit/{id}', [ManufacturingOrdersController::class, 'edit'])
                            ->name('manufacturing.orders.edit')
                            ->middleware('permission.role:stock.manufacturing_orders.edit');
                        Route::get('/show/{id}', [ManufacturingOrdersController::class, 'show'])
                            ->name('manufacturing.orders.show')
                            ->middleware('permission.role:stock.manufacturing_orders.show');
                        Route::post('/update/{id}', [ManufacturingOrdersController::class, 'update'])
                            ->name('manufacturing.orders.update')
                            ->middleware('permission.role:stock.manufacturing_orders.edit');
                        /*
                            كان `GET … 'delete'` — والدالة `delete` لا وجود
                            لها في المتحكّم (الموجود `destroy`)، فزرّ الحذف
                            يرمي BadMethodCallException دائماً. وكان GET
                            يعني أن `<img src="…/delete/5">` في أي بريد
                            يحذف الأمر عند فتحه من متصفّح مسجَّل الدخول.
                        */
                        Route::delete('/delete/{id}', [ManufacturingOrdersController::class, 'destroy'])
                            ->name('manufacturing.orders.delete')
                            ->middleware('permission.role:stock.manufacturing_orders.delete');

                        // إدارة حالات أمر التصنيع
                        Route::get('/{id}/receive-materials-form', [ManufacturingOrdersController::class, 'showReceiveMaterialsForm'])
                            ->name('manufacturing.orders.show-receive-materials')
                            ->middleware('permission.role:stock.manufacturing_orders.receive_materials');
                        Route::post('/{id}/receive-materials', [ManufacturingOrdersController::class, 'receiveMaterials'])
                            ->name('manufacturing.orders.receive-materials')
                            ->middleware('permission.role:stock.manufacturing_orders.receive_materials');
                        Route::get('/{id}/return-materials-form', [ManufacturingOrdersController::class, 'showReturnMaterialsForm'])
                            ->name('manufacturing.orders.show-return-materials')
                            ->middleware('permission.role:stock.manufacturing_orders.return_materials');
                        Route::post('/{id}/return-materials', [ManufacturingOrdersController::class, 'returnMaterials'])
                            ->name('manufacturing.orders.return-materials')
                            ->middleware('permission.role:stock.manufacturing_orders.return_materials');
                        Route::post('/{id}/complete', [ManufacturingOrdersController::class, 'completeOrder'])
                            ->name('manufacturing.orders.complete')
                            ->middleware('permission.role:stock.manufacturing_orders.finish');
                        Route::post('/{id}/cancel', [ManufacturingOrdersController::class, 'cancelOrder'])
                            ->name('manufacturing.orders.cancel')
                            ->middleware('permission.role:stock.manufacturing_orders.cancel');
                    });

                    // ===== Manufacturing Test Calculator =====
                    // حاسبةُ تكلفةِ أمرٍ قبل إنشائه — تتبع ميزة أوامر التصنيع
                    Route::prefix('test-calculator')->middleware('plan:'.StockPlanFeatures::MANUFACTURING)->group(function () {
                        Route::get('/', [TestManufacturingCalculatorController::class, 'index'])
                            ->name('stock.manufacturing.test-calculator')
                            ->middleware('permission.role:stock.manufacturing_orders.view');
                        Route::post('/', [TestManufacturingCalculatorController::class, 'calculate'])
                            ->name('stock.manufacturing.test-calculator.calculate')
                            ->middleware('permission.role:stock.manufacturing_orders.cost_breakdown');
                    });

                    /*
                        ===== Indirect Costs =====

                        لا مفتاحَ لها في الكتالوج، فتُلحق بأقرب ميزةٍ تتبعها
                        فعلاً: التكلفةُ غير المباشرة تُحمَّل على **أوامر
                        التصنيع** ولا معنى لها بدونها. ولا يُخترع لها مفتاح —
                        المخترعُ لا يعرفه الكتالوج فيُعامل «مسموحاً» دائماً،
                        فيبدو حارساً وهو ليس بشيء (انظر رأس [StockPlanFeatures]).

                        ولا سقفَ لها: لا عدّادَ لها في `UsageReporter`.
                    */
                    Route::prefix('IndirectCosts')->middleware('plan:'.StockPlanFeatures::MANUFACTURING)->group(function () {
                        Route::get('/index', [IndirectCostsController::class, 'index'])
                            ->name('manufacturing.indirectcosts.index')
                            ->middleware('permission.role:stock.indirect_costs.view');
                        Route::get('/create', [IndirectCostsController::class, 'create'])
                            ->name('manufacturing.indirectcosts.create')
                            ->middleware('permission.role:stock.indirect_costs.create');
                        Route::post('/store', [IndirectCostsController::class, 'store'])
                            ->name('manufacturing.indirectcosts.store')
                            ->middleware('permission.role:stock.indirect_costs.create');
                        Route::get('/edit/{id}', [IndirectCostsController::class, 'edit'])
                            ->name('manufacturing.indirectcosts.edit')
                            ->middleware('permission.role:stock.indirect_costs.edit');
                        Route::put('/update/{id}', [IndirectCostsController::class, 'update'])
                            ->name('manufacturing.indirectcosts.update')
                            ->middleware('permission.role:stock.indirect_costs.edit');
                        Route::delete('/delete/{id}', [IndirectCostsController::class, 'destroy'])
                            ->name('manufacturing.indirectcosts.destroy')
                            ->middleware('permission.role:stock.indirect_costs.delete');
                        Route::get('/indirectcosts-data', [IndirectCostsController::class, 'getData'])
                            ->name('manufacturing.indirectcosts.getData')
                            ->middleware('permission.role:stock.indirect_costs.view');
                        Route::get('/indirectcosts-stats', [IndirectCostsController::class, 'getStats'])
                            ->name('manufacturing.indirectcosts.getStats')
                            ->middleware('permission.role:stock.indirect_costs.view');
                        Route::post('/indirectcosts-export', [IndirectCostsController::class, 'export'])
                            ->name('manufacturing.indirectcosts.export')
                            ->middleware('permission.role:stock.indirect_costs.export');
                        Route::post('/indirectcosts/get-restrictions-by-date', [IndirectCostsController::class, 'getRestrictionsByDate'])
                            ->name('manufacturing.indirectcosts.getRestrictionsByDate')
                            ->middleware('permission.role:stock.indirect_costs.view');
                        Route::get('/show/{id}', [IndirectCostsController::class, 'show'])
                            ->name('manufacturing.indirectcosts.show')
                            ->middleware('permission.role:stock.indirect_costs.show');
                    });

                    /*
                        ===== خطط الإنتاج =====

                        الطبقةُ فوق أوامر التصنيع: خطّةٌ ببنود، ثمّ توليدُ
                        أمرِ تصنيعٍ عن كلّ بند. ميزتُها `stock_manufacturing`
                        — ما تُنتجه أوامرُ تصنيع، فلا تُفتح لباقةٍ لا تشملها.

                        والتوليدُ عليه `plan.limit:manufacturing_orders`
                        حارساً أوّل — يردّ المستخدم قبل المحاولة إن نفد
                        العدّادُ كلُّه. أمّا العددُ الحقيقي فيُسأل عنه داخل
                        المتحكّم (خطّةٌ بعشرة بنود تحتاج عشرة)، إذ لا يُعرف
                        من المسار — انظر `GeneratesOrdersFromPlan`.

                        وكلُّ ما يغيّر شيئاً POST/PUT/DELETE لا GET — درسُ
                        `Paths/delete` أدناه: رابطٌ يغيّر الحالةَ بمجرّد فتحه
                        تكفيه صورةٌ في بريد.
                    */
                    Route::prefix('ProductionPlans')->middleware('plan:'.StockPlanFeatures::MANUFACTURING)->group(function () {
                        Route::get('/index', [ProductionPlansController::class, 'index'])
                            ->name('manufacturing.production-plans.index')
                            ->middleware('permission.role:stock.production_plans.view');
                        Route::get('/data', [ProductionPlansController::class, 'getData'])
                            ->name('manufacturing.production-plans.getData')
                            ->middleware('permission.role:stock.production_plans.view');
                        Route::get('/create', [ProductionPlansController::class, 'create'])
                            ->name('manufacturing.production-plans.create')
                            ->middleware('permission.role:stock.production_plans.create');
                        Route::post('/store', [ProductionPlansController::class, 'store'])
                            ->name('manufacturing.production-plans.store')
                            ->middleware('permission.role:stock.production_plans.create');
                        Route::get('/edit/{id}', [ProductionPlansController::class, 'edit'])
                            ->name('manufacturing.production-plans.edit')
                            ->middleware('permission.role:stock.production_plans.edit');
                        Route::put('/update/{id}', [ProductionPlansController::class, 'update'])
                            ->name('manufacturing.production-plans.update')
                            ->middleware('permission.role:stock.production_plans.edit');
                        Route::get('/show/{id}', [ProductionPlansController::class, 'show'])
                            ->name('manufacturing.production-plans.show')
                            ->middleware('permission.role:stock.production_plans.show');
                        Route::delete('/delete/{id}', [ProductionPlansController::class, 'destroy'])
                            ->name('manufacturing.production-plans.delete')
                            ->middleware('permission.role:stock.production_plans.delete');

                        // ── انتقالات الحالة ──
                        Route::post('/approve/{id}', [ProductionPlansController::class, 'approve'])
                            ->name('manufacturing.production-plans.approve')
                            ->middleware('permission.role:stock.production_plans.approve');
                        Route::post('/revert/{id}', [ProductionPlansController::class, 'revertToDraft'])
                            ->name('manufacturing.production-plans.revert')
                            ->middleware('permission.role:stock.production_plans.approve');
                        Route::post('/cancel/{id}', [ProductionPlansController::class, 'cancel'])
                            ->name('manufacturing.production-plans.cancel')
                            ->middleware('permission.role:stock.production_plans.approve');

                        // ── التوليد ──
                        Route::post('/generate-orders/{id}', [ProductionPlansController::class, 'generateOrders'])
                            ->name('manufacturing.production-plans.generate-orders')
                            ->middleware([
                                'permission.role:stock.production_plans.generate_orders',
                                'plan.limit:'.StockPlanFeatures::LIMIT_MANUFACTURING_ORDERS,
                            ]);
                    });

                    // ===== Workstations =====
                    // محطّات العمل — ميزتها `stock_workstations` وسقفُها `workstations`
                    Route::prefix('Workstations')->middleware('plan:'.StockPlanFeatures::WORKSTATIONS)->group(function () {
                        Route::get('/index', [WorkstationsController::class, 'index'])
                            ->name('manufacturing.workstations.index')
                            ->middleware('permission.role:stock.workstations.view');
                        Route::get('/create', [WorkstationsController::class, 'create'])
                            ->name('manufacturing.workstations.create')
                            ->middleware(['permission.role:stock.workstations.create', 'plan.limit:'.StockPlanFeatures::LIMIT_WORKSTATIONS]);
                        Route::get('/data', [WorkstationsController::class, 'getData'])
                            ->name('manufacturing.workstations.getData')
                            ->middleware('permission.role:stock.workstations.view');
                        Route::post('/store', [WorkstationsController::class, 'store'])
                            ->name('manufacturing.workstations.store')
                            ->middleware(['permission.role:stock.workstations.create', 'plan.limit:'.StockPlanFeatures::LIMIT_WORKSTATIONS]);
                        Route::get('/edit/{id}', [WorkstationsController::class, 'edit'])
                            ->name('manufacturing.workstations.edit')
                            ->middleware('permission.role:stock.workstations.edit');
                        Route::post('/update/{id}', [WorkstationsController::class, 'update'])
                            ->name('manufacturing.workstations.update')
                            ->middleware('permission.role:stock.workstations.edit');
                        Route::delete('/delete/{id}', [WorkstationsController::class, 'destroy'])
                            ->name('manufacturing.workstations.delete')
                            ->middleware('permission.role:stock.workstations.delete');
                        Route::get('/show/{id}', [WorkstationsController::class, 'show'])
                            ->name('manufacturing.workstations.show')
                            ->middleware('permission.role:stock.workstations.show');
                    });

                    /*
                        ===== Manufacturing Settings =====

                        إعداداتُ الوحدة ومساراتُ الإنتاج تتبع ميزة أوامر
                        التصنيع: مفاتيحُها (تجاوز الكمية، حجز المواد،
                        الحالات اليدوية) تضبط سلوكَ الأوامر، ومسارُ الإنتاج
                        مرجعٌ يُختار فيها.
                    */
                    Route::prefix('Settings')->middleware('plan:'.StockPlanFeatures::MANUFACTURING)->group(function () {
                        Route::get('/index', [SettingsController::class, 'index'])
                            ->name('Manufacturing.settings.index')
                            ->middleware('permission.role:stock.settings.manufacturing');
                        Route::get('/General', [SettingsController::class, 'General'])
                            ->name('manufacturing.settings.general')
                            ->middleware('permission.role:stock.settings.manufacturing');
                        Route::post('/general_settings/update', [SettingsController::class, 'general_settings'])
                            ->name('Manufacturing.general_settings.update')
                            ->middleware('permission.role:stock.settings.manufacturing');
                        Route::get('/Manual', [SettingsController::class, 'Manual'])
                            ->name('Manufacturing.settings.Manual')
                            ->middleware('permission.role:stock.settings.manufacturing');
                        Route::post('/order_manual_status/update', [SettingsController::class, 'order_manual_status'])
                            ->name('Manufacturing.order_manual_status.update')
                            ->middleware('permission.role:stock.settings.manufacturing');

                        // ===== Manufacturing Paths Settings =====
                        Route::prefix('Paths')->group(function () {
                            Route::get('/index', [SettingsController::class, 'paths_index'])
                                ->name('manufacturing.paths.index')
                                ->middleware('permission.role:stock.production_paths.view');
                            Route::post('/manufacturing/paths/ajax', [SettingsController::class, 'paths_ajax'])
                                ->name('manufacturing.paths.ajax')
                                ->middleware('permission.role:stock.production_paths.view');
                            Route::get('/create', [SettingsController::class, 'paths_create'])
                                ->name('manufacturing.paths.create')
                                ->middleware('permission.role:stock.production_paths.create');
                            Route::post('/store', [SettingsController::class, 'paths_store'])
                                ->name('manufacturing.paths.store')
                                ->middleware('permission.role:stock.production_paths.create');
                            Route::get('/edit/{id}', [SettingsController::class, 'paths_edit'])
                                ->name('manufacturing.paths.edit')
                                ->middleware('permission.role:stock.production_paths.edit');
                            Route::put('/update/{id}', [SettingsController::class, 'paths_update'])
                                ->name('manufacturing.paths.update')
                                ->middleware('permission.role:stock.production_paths.edit');
                            /*
                                `DELETE` لا `GET` — المرحلة ١.

                                كان `Route::get('/delete/{id}', …)`: رابطٌ يحذف
                                مسار الإنتاج وكل مراحله بمجرّد فتحه، بلا رمز
                                CSRF ولا تأكيد من الخادم. فصورةٌ في بريد بمصدر
                                `…/Paths/delete/3` تكفي لحذف المسار عند فتح
                                البريد من متصفّح مسجَّل الدخول. ومثله أي زحف
                                آلي يتبع الروابط.

                                والحذف يمسّ `production_stages` أيضاً — ومراحل
                                الإنتاج مرجعٌ إلزامي في بنود أوامر التصنيع.
                            */
                            Route::delete('/delete/{id}', [SettingsController::class, 'paths_destroy'])
                                ->name('manufacturing.paths.delete')
                                ->middleware('permission.role:stock.production_paths.delete');
                            Route::get('/show/{id}', [SettingsController::class, 'paths_show'])
                                ->name('manufacturing.paths.show')
                                ->middleware('permission.role:stock.production_paths.view');
                        });

                    });
                });
        }
    );
});

/*
|--------------------------------------------------------------------------
| توجيه العناوين القديمة
|--------------------------------------------------------------------------
|
| ما كان تحت `stock/BOM/…` و`stock/Workstations/…` وأخواتها يقود الآن إلى
| نظيره تحت `manufacturing/…`. التوجيه دائم (301) فيُحدّث المتصفّح وجهته،
| ويحمل المسار الباقي كما هو — فلا تضيع صفحةٌ بعينها.
|
*/

Route::middleware(['auth'])->group(function () {
    Route::group(
        [
            'prefix' => LaravelLocalization::setLocale(),
            'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
        ],
        function () {
            Route::get('stock/{section}/{rest?}', function (string $section, ?string $rest = null) {
                /*
                    القسم `manufacturing` لا يُكرَّر.

                    كان السطر: `redirect('manufacturing/'.$section.…)` — فالقيمة
                    `manufacturing` تُضاف إلى نفسها:

                      /ar/stock/BOM/index                    → /ar/manufacturing/BOM/index          ✓
                      /ar/stock/manufacturing/orders/create  → /manufacturing/manufacturing/…       ✗ 404

                    وهي بالذات القيمة التي تغطّي العناوين القديمة لأوامر
                    التصنيع — أكثر شاشات الوحدة استعمالاً. فكل مفضّلة أو رابط
                    محفوظ تحت `stock/manufacturing/…` كان يسقط بصفحة غير موجودة.
                */
                $target = $section === 'manufacturing'
                    ? 'manufacturing'
                    : 'manufacturing/'.$section;

                return redirect($target.($rest ? '/'.$rest : ''), 301);
            })
                ->where('section', 'BOM|Workstations|IndirectCosts|Settings|manufacturing')
                ->where('rest', '.*')
                ->name('manufacturing.legacy_redirect');
        }
    );
});

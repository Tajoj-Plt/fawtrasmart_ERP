<?php

namespace App\Observers;

use App\Constants\planFeatures\AccountPlanFeatures;
use App\Constants\planFeatures\BranchesPlanFeatures;
use App\Constants\planFeatures\HrPlanFeatures;
use App\Constants\planFeatures\PosPlanFeatures;
use App\Constants\planFeatures\PurchasesPlanFeatures;
use App\Constants\planFeatures\SalesPlanFeatures;
use App\Constants\planFeatures\StockPlanFeatures;
use App\Constants\SystemAccountIds;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashierDevice;
use App\Models\Category;
use App\Models\ChequeBook;
use App\Models\CollectionsTarget;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\CreditNotification;
use App\Models\CustomShift;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExchangeReason;
use App\Models\Expense;
use App\Models\GiftOffer;
use App\Models\GroupMergeLog;
use App\Models\InventoryAdjustment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\LeaveRequest;
use App\Models\Machine;
use App\Models\notifications;
use App\Models\Offer;
use App\Models\OrderSource;
use App\Models\PayableCheque;
use App\Models\PaymentsProcess;
use App\Models\PeriodicInvoice;
use App\Models\PosSession;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuotation;
use App\Models\Quote;
use App\Models\Receipt;
use App\Models\ReceivedCheque;
use App\Models\Region_groub;
use App\Models\SalaryAdvanceRequest;
use App\Models\SalarySlip;
use App\Models\shifts_pos;
use App\Models\ShippingOption;
use App\Models\Store\Brand;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\Template;
use App\Models\Treasury;
use App\Models\WarehousePermits;
use App\Services\Fawtra\SubscriptionGate;
use App\Services\Fawtra\UsagePush;
use App\Support\AccountingSettings;
use Illuminate\Database\Eloquent\Model;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosHeldOrder;
use Modules\Pos\Models\PosPrinter;

/**
 * الحارس الأخير لحدود الباقة — على النموذج لا على المسار.
 *
 * ─────────────────── لماذا هذه الطبقة والمسارات محروسة؟ ───────────────────
 *
 * لأن حارس المسار (`plan.limit`) يحرس **الباب الذي مررت به**، والصفوف تُنشأ
 * من أبوابٍ أخرى كثيرة في هذه الوحدة:
 *
 *   • `StorehouseController::generateAllWarehousesOpeningBalance()` يُنشئ
 *     إذناً مخزنياً **لكل مستودع** في نداءٍ واحد — لا واحداً.
 *   • `InventoryManagementController::adjustment()` يُنشئ إذناً عند اعتماد
 *     الجرد، وهو مسار «اعتماد» لا مسار «إنشاء إذن».
 *   • `ProductsController::add_manual_stock_adjust()` و`compiled_store()`
 *     يُنشئان أذوناً كأثرٍ جانبي.
 *   • `ProductsController::import()` يُنشئ منتجاتٍ بعدد أسطر ملفّ Excel —
 *     عددٍ لا يُعرف قبل قراءة الملف، فلا يصلح وسيطاً على المسار.
 *   • أوامر الطرفية والمهامّ في الطابور والبذور لا تمرّ بأي مسار أصلاً.
 *
 * وكل بابٍ يُكتشف اليوم يُحرَس اليوم، ثم يُكتب بابٌ جديد بعد شهرين فلا
 * يحرسه أحد. أمّا هنا فالسؤال يُطرح على **الصفّ نفسه** لحظة إنشائه، فلا
 * يهمّ من أنشأه ولا من أين.
 *
 * ─────────────────── ولماذا يبقى حارس المسار إذن؟ ───────────────────
 *
 * للرسالة لا للمنع: `plan.limit` يردّ المستخدم قبل أن يملأ نموذجاً برسالةٍ
 * تقول ما الحدّ وكم بقي، وهذا يرمي استثناءً في منتصف الحفظ. الأوّل تجربةُ
 * استعمال، والثاني ضمانةٌ لا تُخترق.
 *
 * ─────────────────── الكلفة ───────────────────
 *
 * صفر على ميزةٍ بلا حدّ: `checkLive()` تقرأ اللقطة المحفوظة، فترى
 * `unlimited` وتخرج بلا استعلام. والعدّ لا يقع إلا على ميزةٍ لها سقف رقمي —
 * ومرّةً واحدة في الطلب، إذ يُحدَّث العدد بالزيادة والنقصان بعد ذلك بدل
 * إعادة العدّ (انظر `created()` و`deleted()`).
 */
class PlanLimitObserver
{
    /**
     * النموذج إلى مفتاح حدّه في كتالوج فوترة.
     *
     * ما ليس هنا لا يُحرَس — وهو الصواب: النموذج الذي لا حدّ لباقةٍ عليه
     * ليس محلّ هذا الفحص أصلاً.
     *
     * **القيمة نصٌّ أو دالّة.** الدالّة لجدولٍ يحمل أكثر من نوع مستند تحت
     * عمود `type`: جدول `invoices` وحده يحمل فاتورةَ بيعٍ ومرتجعاً، ولكلٍّ
     * حدُّه المستقلّ في الباقة. وردُّ `null` منها يعني «هذا الصفّ لا يخصّ
     * ميزةً محدودة» فيمرّ بلا فحص — وهو المطلوب لأنواعٍ لا تُعَدّ أصلاً في
     * `UsageReporter::measure()`.
     *
     * @return array<class-string, string|callable(Model): ?string>
     */
    protected static function map(): array
    {
        return [
            /* ══════════ المخزون ══════════ */
            Product::class => StockPlanFeatures::LIMIT_PRODUCTS,
            Category::class => StockPlanFeatures::LIMIT_CATEGORIES,
            Brand::class => StockPlanFeatures::LIMIT_BRANDS,
            PriceList::class => StockPlanFeatures::LIMIT_PRICE_LISTS,
            StoreHouse::class => StockPlanFeatures::LIMIT_STOREHOUSES,
            WarehousePermits::class => StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS,
            InventoryAdjustment::class => StockPlanFeatures::LIMIT_ADJUSTMENTS,

            /* ══════════ المبيعات ══════════ */

            // جدولٌ واحد لثلاثة أنواع: البيع والمرتجع وما سواهما. والتمييز
            // بنفس شرط `UsageReporter::measure()` حرفاً بحرف، وإلّا حَرَسَ
            // المراقبُ ما لا يُعَدّ أو عدَّ العدّادُ ما لا يُحرَس
            Invoice::class => static fn (Model $m): ?string => match ($m->type) {
                'normal', 'pos', 'online' => SalesPlanFeatures::LIMIT_INVOICES,
                'returned' => SalesPlanFeatures::LIMIT_RETURN_INVOICES,
                default => null,
            },

            // `payments_process` يحمل مقبوضات العملاء ومدفوعات الموردين معاً
            PaymentsProcess::class => static fn (Model $m): ?string => $m->type === 'client payments'
                ? SalesPlanFeatures::LIMIT_PAYMENTS
                : null,

            // `templates` يحمل قوالب مستنداتٍ شتّى؛ والحدّ لقوالب الفواتير وحدها
            Template::class => static fn (Model $m): ?string => $m->type === 'invoice'
                ? SalesPlanFeatures::LIMIT_INVOICE_TEMPLATES
                : null,

            CreditNotification::class => SalesPlanFeatures::LIMIT_CREDIT_NOTES,
            Quote::class => SalesPlanFeatures::LIMIT_QUOTES,
            ProductExchange::class => SalesPlanFeatures::LIMIT_PRODUCT_EXCHANGES,
            notifications::class => SalesPlanFeatures::LIMIT_NOTIFICATIONS,
            PeriodicInvoice::class => SalesPlanFeatures::LIMIT_PERIODIC_INVOICES,
            Offer::class => SalesPlanFeatures::LIMIT_OFFERS,
            GiftOffer::class => SalesPlanFeatures::LIMIT_GIFT_OFFERS,
            CollectionsTarget::class => SalesPlanFeatures::LIMIT_COLLECTIONS_TARGETS,
            ShippingOption::class => SalesPlanFeatures::LIMIT_SHIPPING_OPTIONS,
            OrderSource::class => SalesPlanFeatures::LIMIT_ORDER_SOURCES,
            ExchangeReason::class => SalesPlanFeatures::LIMIT_EXCHANGE_REASONS,

            /*
             * **`Installment` مستثنىً عن قصد.** حدُّ `installment_plans`
             * يعدّ **الاتفاقيات** لا الأقساط (`distinct()->count('invoice_id')`
             * في `measure()`)، واتفاقيةٌ واحدة تُنشئ صفّاً لكل دفعة. فزيادةُ
             * العدّاد بواحدٍ مع كل صفّ تجعل اتفاقيةً من اثني عشر قسطاً تستهلك
             * اثني عشر مقعداً — ومنعاً كاذباً عند السقف. يحرسها وسيط
             * `plan.limit` على مسار الإنشاء وحده.
             *
             * و**`sales_users` كذلك**: المقعد يُقاس بمن يملك صلاحية مبيعات لا
             * بعدد صفوف `users`، فإنشاءُ مستخدمٍ بلا صلاحية مبيعات لا يشغل
             * مقعداً — ولا نموذجَ يُعبّر عن ذلك.
             */

            /* ══════════ المشتريات ══════════
             *
             * الطبقةُ الرابعة للوحدة، وكانت غائبةً كلَّها: لا نموذجَ واحدٌ
             * من المشتريات تحت هذا المراقب. فالصفوفُ التي تُنشأ من أمرٍ
             * مجدول أو استيرادٍ أو عمليةٍ جماعية — ولا تمرّ بمسارٍ ولا
             * بوسيط — كانت تتجاوز السقف بلا أن يشعر به أحد.
             *
             * والمفاتيح الأربعة وحدها هي ما يشحنه الكتالوج: المرتجعات
             * والإشعارات المدينة وسندات الصرف لا حدَّ لها، فلا يُخترع لها
             * مفتاحٌ يبدو حارساً وليس بشيء.
             */

            // `purchase_invoices` جدولٌ لأربعة أنواع عبر عمود `type`،
            // و`UsageReporter::measure()` يعدّ `invoice` وحده — فالشرط
            // هنا نسختُه حرفاً بحرف، وإلّا حَرَسَ المراقبُ ما لا يُعَدّ
            PurchaseInvoice::class => static fn (Model $m): ?string => $m->type === 'invoice'
                ? PurchasesPlanFeatures::LIMIT_INVOICES
                : null,

            PurchaseOrder::class => PurchasesPlanFeatures::LIMIT_ORDERS,
            PurchaseQuotation::class => PurchasesPlanFeatures::LIMIT_QUOTATIONS,
            Supplier::class => PurchasesPlanFeatures::LIMIT_SUPPLIERS,

            /* ══════════ الحسابات والمالية ══════════ */

            JournalEntry::class => AccountPlanFeatures::LIMIT_JOURNAL_ENTRIES,
            Expense::class => AccountPlanFeatures::LIMIT_EXPENSES,

            // «سندات القبض» مفتاحُها `incomes` ونموذجُها `Receipt` — الاسمان
            // لا يتطابقان، والجدول `receipts`
            Receipt::class => AccountPlanFeatures::LIMIT_INCOMES,

            PayableCheque::class => AccountPlanFeatures::LIMIT_PAYABLE_CHEQUES,
            ReceivedCheque::class => AccountPlanFeatures::LIMIT_RECEIVED_CHEQUES,
            Treasury::class => AccountPlanFeatures::LIMIT_TREASURIES,
            CostCenter::class => AccountPlanFeatures::LIMIT_COST_CENTERS,
            ChequeBook::class => AccountPlanFeatures::LIMIT_CHECK_BOOKS,

            /*
                **الأصل الثابت حسابٌ لا صفٌّ في جدولٍ خاص.**

                و`Account` يُنشأ في مواضع كثيرة لا علاقة لأكثرها بالأصول:
                خزينةٌ جديدة تُنشئ حساباً، ومورّدٌ جديد يُنشئ حساباً، وعميلٌ
                كذلك. فربطُ النموذج كلِّه بحدّ الأصول يجعل إضافة عميلٍ تستهلك
                مقعدَ أصلٍ ثابت — ومنعاً كاذباً عند السقف.

                فالتمييز بالأب، بنفس شرط `UsageReporter::measure()` حرفاً
                بحرف: ما كان أبوه أبَ الأصول الثابتة فهو أصل، وما سواه لا
                يخصّ هذا الحدّ. واختلافُ الشرطين يُنتج مراقباً يحرس ما لا
                يُعَدّ أو عدّاداً يعدّ ما لا يُحرَس.
            */
            Account::class => static fn (Model $m): ?string => (int) $m->parent_id
                === (int) AccountingSettings::id('fixed_assets', SystemAccountIds::FIXED_ASSETS)
                    ? AccountPlanFeatures::LIMIT_ASSETS
                    : null,

            /* ══════════ الفروع والمناطق ══════════ */

            /*
                **الفرع والمجموعة صفّان في جدولٍ واحد** — `branches` —
                يفرّقهما `is_territory`. فحدٌّ واحد لهما يجعل كلَّ مجموعةٍ
                تستهلك مقعدَ فرع.

                والشرط هنا مطابقٌ لِما في `UsageReporter::measure()` حرفاً
                بحرف: `is_territory` قد تكون `NULL` في صفوفٍ قديمة، والفرع
                يُعرَّف بـ«ليست واحداً» لا بـ«تساوي صفراً». واختلافُ الشرطين
                يُنتج مراقباً يحرس ما لا يُعَدّ.
            */
            Branch::class => static fn (Model $m): ?string => (int) ($m->is_territory ?? 0) === 1
                ? BranchesPlanFeatures::LIMIT_TERRITORY_GROUPS
                : BranchesPlanFeatures::LIMIT_BRANCHES,

            /*
                `Region_groub` يرث `Branch` ويكتب في الجدول نفسه — **وأحداث
                Eloquent تُطلق باسم الصنف الفعلي لا الأب**. فمراقبٌ مسجَّل على
                `Branch` وحده لا يرى صفّاً أُنشئ عبر `Region_groub`، ويمرّ
                إنشاء المجموعات كلُّه بلا حدّ.
            */
            Region_groub::class => static fn (Model $m): ?string => (int) ($m->is_territory ?? 0) === 1
                ? BranchesPlanFeatures::LIMIT_TERRITORY_GROUPS
                : BranchesPlanFeatures::LIMIT_BRANCHES,

            /* سجلّ الدمج هو أثرُ عملية النقل الباقي — وبه تُعَدّ. */
            GroupMergeLog::class => BranchesPlanFeatures::LIMIT_TRANSFERS,

            /* ══════════ الموارد البشرية ══════════ */

            /*
                `employees` هو الحدّ الحاكم للوحدة: كل ما سواه يُضرب فيه —
                قسيمةٌ لكل موظف، وسجلُّ حضورٍ لكل موظفٍ كل يوم. فمنعُ الموظف
                الزائد يمنع تضخّم البقيّة تلقائياً.
            */
            Employee::class => HrPlanFeatures::LIMIT_EMPLOYEES,
            Department::class => HrPlanFeatures::LIMIT_DEPARTMENTS,
            Machine::class => HrPlanFeatures::LIMIT_DEVICES,
            CustomShift::class => HrPlanFeatures::LIMIT_SHIFTS,
            Contract::class => HrPlanFeatures::LIMIT_CONTRACTS,

            SalarySlip::class => HrPlanFeatures::LIMIT_PAYSLIPS,
            LeaveRequest::class => HrPlanFeatures::LIMIT_LEAVE_REQUESTS,
            SalaryAdvanceRequest::class => HrPlanFeatures::LIMIT_ADVANCE_REQUESTS,

            /*
                سجلّات الحضور تُنشأ بالآلاف من سحب أجهزة البصمة — والمراقب
                يفحصها صفّاً صفّاً. والكلفة صفر ما دامت الميزة بلا حدّ (الفحص
                يخرج من اللقطة بلا استعلام)، وعند وجود سقفٍ يُعدّ مرّةً واحدة
                في الطلب ثم يُحدَّث بالزيادة — انظر `created()`.
            */
            /*
                لا حارسَ لحدّ الحضور — عن قصد.

                كان `AttendanceRecord` هنا، وهو نموذجٌ لم يعد يُنشأ (حُذفت
                شاشتُه وصارت `AttendanceStampService` تكتب الختمَ وسجلَّ
                اليوم مباشرةً) — فالإسنادُ ميّت.

                ولا يُوضَع `AttendanceDays` ولا `AttendancePunch` مكانَه:
                `creating()` أدناه **ترمي** عند بلوغ الحدّ، وكلاهما يُنشَأ
                في مسارَين لا يحتملان الرمي — لحظةَ مسح البطاقة على باب
                الفرع، وفي كرون الليل الذي يولّد صفَّ كل موظفٍ ولو غائباً.
                فنفادُ الحصّة كان سيعني: الموظفُ واقفٌ أمام الجهاز ولا
                يُقبل حضورُه، والكرونُ يموت في منتصف المنشأة فتبقى نصفُ
                أيامها بلا حساب.

                ونظامُ حضورٍ يمنع تسجيلَ الحضور خطأٌ أفدحُ من حصّةٍ تُتجاوَز.

                ولا قياسَ له كذلك: حدُّ الحضور أُحيل كلُّه إلى
                `LIMIT_EMPLOYEES` — وهو سقفٌ مُلزَمٌ أعلاه — لأن الكلفة
                تتبع عددَ الموظفين لا عددَ أيامهم، وأيامُ الحضور مشتقّةٌ
                منه حسابياً (موظفٌ ≈ ٢٢ سجلاً شهرياً).
            */

            /* ══════════ نقاط البيع ══════════ */

            /*
                `pos_devices` هو الحدّ الحاكم للوحدة — نقطةُ البيع تُسعَّر
                بالطرفية: منشأةٌ بثلاثة صناديق تشغّل ثلاث نسخٍ من الشاشة في
                وقتٍ واحد، لا لأنها تبيع ثلاثة أضعاف.

                وفاتورةُ نقطة البيع **ليست هنا**: تُعدّ في `sales_invoices`
                ضمن قسم المبيعات (`Invoice::class` أعلاه يشمل `type = pos`).
                وتكرارُ عدّادها هنا يجعل الفاتورة الواحدة تستهلك رصيدين.
            */
            CashierDevice::class => PosPlanFeatures::LIMIT_DEVICES,
            shifts_pos::class => PosPlanFeatures::LIMIT_SHIFTS,
            PosPrinter::class => PosPlanFeatures::LIMIT_PRINTERS,

            /*
                الجلسةُ لها حدّان: سقفٌ متزامن على المفتوح في وقتٍ واحد
                (`pos_concurrent_sessions`) وعدّادٌ شهري على ما يُفتح
                (`pos_sessions`). والمراقبُ يُلزم الشهري — أمّا المتزامن
                فموضعُه `plan.limit` على مسار الفتح، إذ يقيس المفتوحَ الآن
                لا المُنشأ في الفترة.
            */
            PosSession::class => PosPlanFeatures::LIMIT_SESSIONS,
            PosCashMovement::class => PosPlanFeatures::LIMIT_CASH_MOVEMENTS,
            PosHeldOrder::class => PosPlanFeatures::LIMIT_HELD_ORDERS,
        ];
    }

    /**
     * مفتاحُ حدّ هذا الصفّ — أو `null` إن كان لا يخصّ ميزةً محدودة.
     */
    protected static function featureFor(Model $model): ?string
    {
        $entry = static::map()[$model::class] ?? null;

        if ($entry === null || is_string($entry)) {
            return $entry;
        }

        return $entry($model);
    }

    /**
     * قبل الإدراج — وهي اللحظة الوحيدة التي يمكن المنع فيها.
     *
     * الرمي هنا يقع **داخل المعاملة** إن كانت مفتوحة، فترتدّ كلّها ولا يبقى
     * نصفُ مستند: إذنٌ بلا بنوده، أو منتجٌ بلا رصيده الافتتاحي.
     */
    public function creating(Model $model): void
    {
        $feature = static::featureFor($model);

        if ($feature === null) {
            return;
        }

        $result = app(SubscriptionGate::class)->checkLive($feature, 1);

        if ($result['allowed']) {
            return;
        }

        throw new PlanLimitExceededException(
            $feature,
            (string) $result['reason'],
            (int) $result['remaining'],
        );
    }

    /**
     * بعد الإدراج — يُرفع العدّاد المحفوظ بواحد.
     *
     * **لماذا زيادةٌ لا إعادةَ عدّ؟** لأن الاستيراد الجماعي يُنشئ آلاف
     * الصفوف في طلبٍ واحد، وإعادة `COUNT(*)` مع كلٍّ منها تحوّل استيراداً
     * ثوانيَه إلى دقائق. والزيادة دقيقةٌ تماماً: نحن من أنشأ الصفّ ونعرف
     * أنه واحد.
     *
     * وبلا هذا التحديث يقرأ الصفّ الألفُ العددَ الذي قرأه الأوّل، فيمرّ
     * الاستيراد كلّه فوق السقف بفحصٍ واحدٍ صحيحٍ في أوّله — وهي الثغرة
     * نفسها التي تُغلق هنا.
     */
    public function created(Model $model): void
    {
        $feature = static::featureFor($model);

        if ($feature !== null) {
            SubscriptionGate::bumpLiveCount($feature, 1);

            // تُعلَّم الميزة ليُرفَع استهلاكها إلى فوترة **بعد** إرسال
            // الاستجابة — لا نداءَ شبكة هنا. انظر [UsagePush] لماذا لا
            // يُنادى فوترة داخل مسار الحفظ
            UsagePush::mark($feature);
        }
    }

    /**
     * بعد الحذف — يُنقص العدّاد.
     *
     * السقف المتزامن يقيس ما يشغل مكاناً الآن، فحذفُ صفٍّ يُفرغ مقعداً
     * فوراً. أمّا العدّاد الدوري (الأذون، الجرد) فلا ينقص بالحذف عند فوترة،
     * لكنّ قياسنا المحلّي يعدّ الصفوف الموجودة — فيتّسق الطرفان في المزامنة
     * التالية (انظر `UsageReporter::pushQuota()`: العلامة المائية تُخفَّض بلا
     * طلبٍ حين ينقص الرقم).
     */
    public function deleted(Model $model): void
    {
        $feature = static::featureFor($model);

        if ($feature !== null) {
            SubscriptionGate::bumpLiveCount($feature, -1);

            // الحذف يُرفَع أيضاً: سقفٌ متزامن نقص عددُه يجب أن ينقص عند
            // فوترة كذلك، وإلا بقي العميل ممنوعاً من الإنشاء بعد أن أفرغ
            // مقعداً بيده
            UsagePush::mark($feature);
        }
    }

    /** النماذج التي تُراقَب — يقرؤها `AppServiceProvider` فلا تُكرَّر القائمة. */
    public static function models(): array
    {
        return array_keys(static::map());
    }
}

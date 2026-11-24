<?php

namespace App\Services\Fawtra;

use App\Constants\SystemAccountIds;
use App\Models\FawtraSubscription;
use App\Support\AccountingSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * رفع الاستهلاك المحلّي إلى لوحة فوترة — **مرّة كل دورة لا مع كل عملية**.
 *
 * المزامنة وحدها كانت تسحب ولا تدفع، فتبقى أرقام الاستهلاك عند فوترة صفراً
 * مهما أصدر النظام من فواتير. وهذا الملف هو النصف الآخر: يقيس ما جرى محلياً
 * ويرفعه في نفس الدورة الدورية.
 *
 * ─────────────────────── لماذا القياس من الجداول لا من العدّادات؟ ───────────
 * البديل أن يُستدعى عدّاد في كل متحكّم يُنشئ فاتورة. وهو أهشّ: متحكّمٌ واحد
 * يُنسى فيصير الرقم كاذباً، وكل استثناء في مسار الحفظ يترك العدّاد أمام
 * الواقع أو خلفه. أمّا العدّ من الجداول فيقول ما حدث فعلاً، ويصحّح نفسه بعد
 * أي عطل، ويعمل بأثر رجعي على نظامٍ رُبط بعد شهور من العمل.
 *
 * ─────────────────────── نوعا الحدود ───────────────────────
 *   `quota` عدّاد دوري يتراكم (فواتير الشهر) — يُرسَل **الفارق** عبر
 *           `usage/report` مع `Idempotency-Key` فلا يُحتسب مرّتين لو ضاعت
 *           الاستجابة بعد الكتابة.
 *   `cap`   سقف متزامن (عروض نشطة، مقاعد) — يُرسَل **العدد المطلق** عبر
 *           `usage/cap`، فهو لا يتراكم أصلاً ولا يحتاج علامة مائية.
 *
 * ولا يُرسَل شيء إن لم يتغيّر: دورة هادئة تعني صفر طلبات، لا تسعة عشر.
 */
class UsageReporter
{
    /** أقصى كمّية يقبلها مسار الرفع في الطلب الواحد (حدّ الواجهة). */
    protected const MAX_QUANTITY = 10000;

    /**
     * ما يُقاس محلياً لكل ميزة في كتالوج فوترة.
     *
     * المفاتيح هي مفاتيح فوترة نفسها. وما لا يُقاس محلياً غائبٌ عن هذه
     * الخريطة عن قصد — و`document_emails` منها: إرسال المستند يمرّ بـ
     * `Mail::send` مباشرةً بلا سجلّ محلي، ورفعُ رقمٍ مُخمَّن أسوأ من عدم
     * الرفع لأنه يُنقص رصيد العميل بلا سند.
     *
     * @return array<string, array{kind:string, table:string}>
     */
    public static function catalog(): array
    {
        return [
            // ---------- عدّادات دورية ----------
            'sales_invoices' => ['kind' => 'quota', 'table' => 'invoices'],
            'return_invoices' => ['kind' => 'quota', 'table' => 'invoices'],
            'quotes' => ['kind' => 'quota', 'table' => 'quotes'],
            'credit_notes' => ['kind' => 'quota', 'table' => 'credit_notifications'],
            'sales_payments' => ['kind' => 'quota', 'table' => 'payments_process'],
            'product_exchanges' => ['kind' => 'quota', 'table' => 'product_exchanges'],
            'internal_notifications' => ['kind' => 'quota', 'table' => 'notifications'],

            // ثلاثة مفاتيح لقسم المشتريات — لا تصل اليوم في لقطة فوترة (كتالوجها
            // الحالي لا يذكر أوامر الشراء ولا طلبات عروض الأسعار ولا عروض
            // أسعار الموردين إطلاقاً)، فحلقة push() في FawtraSubscription::usage()
            // لا تصل إليها أصلاً ولا شيء يُرفَع بها. مضافة مسبقاً بنفس نمط
            // تسمية البقية (`sales_invoices`, `quotes`...) لتفعيلها فوراً بلا
            // كودٍ إضافي من جهتنا يوم تُعرَّف هذه الميزات في لوحة فوترة —
            // عندها فقط تصبح `SubscriptionGate::check()` في
            // PurchaseRequestsController/QuotationsController/ViewPurchasePriceController
            // فحصاً فعلياً لا استثناءً دائم السماح.
            'purchase_orders' => ['kind' => 'quota', 'table' => 'purchase_orders'],

            /*
             * «طلبات عروض الأسعار» — مفتاحُها عند فوترة `quotations` لا
             * `purchase_quotations`. وكان المدخل الوحيد هنا بالاسم الثاني،
             * فلا يطابق شيئاً ترسله فوترة: حلقةُ `push()` تمرّ على ما ترسله
             * هي، فلا تصل إلى مدخلٍ باسمٍ لا وجود له — وعدّادُ الميزة عندهم
             * يبقى صفراً مهما أُنشئ محلياً.
             *
             * الاسم الصحيح أُضيف على الجدول نفسه، والقديم بقي لأن
             * `QuotationsController` ما زال يبني بوّابته عليه؛ حذفُه اليوم
             * يترك المتحكّم يشير إلى ما لا يُقاس. مدخلٌ زائدٌ لا يضرّ: ما
             * لا ترسله فوترة لا تصل إليه الحلقة أصلاً.
             */
            'quotations' => ['kind' => 'quota', 'table' => 'purchase_quotations'],
            'purchase_quotations' => ['kind' => 'quota', 'table' => 'purchase_quotations'],
            'purchase_price_quotations' => ['kind' => 'quota', 'table' => 'purchase_quotations_view'],
            // جدول `purchase_invoices` مشترك بين أربعة أنواع مستندات عبر عمود
            // `type` — العدّ الفعلي (لا هذا الكتالوج) يقتصر على `invoice` فقط،
            // بنفس نمط `sales_invoices` أدناه في measure()
            'purchase_invoices' => ['kind' => 'quota', 'table' => 'purchase_invoices'],

            // ---------- سقوف متزامنة ----------
            'periodic_invoices' => ['kind' => 'cap', 'table' => 'periodic_invoices'],
            'installment_plans' => ['kind' => 'cap', 'table' => 'installments'],
            'offers' => ['kind' => 'cap', 'table' => 'offers'],
            'gift_offers' => ['kind' => 'cap', 'table' => 'gift_offers'],
            'collections_targets' => ['kind' => 'cap', 'table' => 'collections_targets'],
            'invoice_templates' => ['kind' => 'cap', 'table' => 'templates'],
            'shipping_options' => ['kind' => 'cap', 'table' => 'shipping_options'],
            'order_sources' => ['kind' => 'cap', 'table' => 'order_sources'],
            'exchange_reasons' => ['kind' => 'cap', 'table' => 'exchange_reasons'],
            'sales_users' => ['kind' => 'cap', 'table' => 'users'],
            // «الموردون» سقفٌ متزامن لا عدّاد دوري: العبرة بكم مورّداً في
            // السجلّ الآن لا بكم أُضيف هذا الشهر. وفوترة **تُعرِّفه فعلاً** في
            // كتالوجها (خلافاً لمفاتيح المشتريات الأخرى أعلاه)، فكان غيابه من
            // هنا يعني أن عدّاد الموردين عندها يبقى صفراً مهما أُضيف محلياً.
            // والعدّ يقع على `default` في measure() — عدُّ صفوف الجدول كلِّه.
            'suppliers' => ['kind' => 'cap', 'table' => 'suppliers'],

            /*
            |------------------------------------------------------------------
            | الحسابات والمالية
            |------------------------------------------------------------------
            |
            | المفاتيح كما ترسلها فوترة في القسم `account` من اللقطة، ومصدرُها
            | `fawtra_dashboard/config/account_features.php`.
            |
            | **أسماء الجداول لا تتبع أسماء المفاتيح هنا**: «سندات القبض»
            | مفتاحُها `incomes` وجدولُها `receipts`، و«دفاتر الشيكات»
            | مفتاحُها `check_books` وجدولُها `cheque_books`. فالجدول يُؤخذ من
            | نموذج المتحكّم الذي يُنشئ الصفّ لا من اشتقاق الاسم.
            |
            | و`assets` وحدَها لا جدول لها: الأصل الثابت **حسابٌ** تحت أب
            | الأصول الثابتة لا صفٌّ في جدولٍ مستقل — انظر حالتها في
            | `measure()`.
            */
            // ---------- عدّادات دورية ----------
            'journal_entries' => ['kind' => 'quota', 'table' => 'journal_entries'],
            'expenses' => ['kind' => 'quota', 'table' => 'expenses'],
            'incomes' => ['kind' => 'quota', 'table' => 'receipts'],
            'payable_cheques' => ['kind' => 'quota', 'table' => 'payable_cheques'],
            'received_cheques' => ['kind' => 'quota', 'table' => 'received_cheques'],

            // ---------- سقوف متزامنة ----------
            'treasuries' => ['kind' => 'cap', 'table' => 'treasuries'],
            'cost_centers' => ['kind' => 'cap', 'table' => 'cost_centers'],
            'check_books' => ['kind' => 'cap', 'table' => 'cheque_books'],
            'assets' => ['kind' => 'cap', 'table' => 'accounts'],

            /*
            |------------------------------------------------------------------
            | الفروع والمناطق
            |------------------------------------------------------------------
            |
            | **الفرع والمجموعة صفّان في الجدول نفسه** (`branches`)، يفرّقهما
            | العمود `is_territory`. فلو عُدَّ الجدول كلُّه لكلٍّ منهما لاستنفد
            | كلُّ واحدٍ سقفَ الآخر: منشأةٌ بثلاثة فروع وعشرين مجموعة تقرأ
            | «٢٣ فرعاً» فتُمنع عند سقفِ عشرة وهي لم تبلغ نصفه.
            |
            | والترشيح في `measure()` لا هنا — الجدول واحد والشرط مختلف.
            |
            | و`branch_transfers` عدّادٌ لعمليات الدمج والنقل، وسجلُّها
            | `group_merge_logs` — وهو الجدول الوحيد الذي يبقى بعد العملية.
            */
            'branches' => ['kind' => 'cap', 'table' => 'branches'],
            'territory_groups' => ['kind' => 'cap', 'table' => 'branches'],
            'branch_transfers' => ['kind' => 'quota', 'table' => 'group_merge_logs'],

            /*
            |------------------------------------------------------------------
            | الموارد البشرية
            |------------------------------------------------------------------
            |
            | `employees` هو الحدّ الحاكم: كل ما سواه في هذه الوحدة يُضرب فيه
            | — قسيمةٌ لكل موظف، وسجلُّ حضورٍ لكل موظفٍ كل يوم.
            |
            | و`departments` يعدّ الأقسام وحدها؛ المسمّيات الوظيفية والمستويات
            | جداولُها منفصلة ولا حدَّ لها في الكتالوج، فلا تُضاف بالتخمين.
            */
            // ---------- عدّادات دورية ----------
            'payslips' => ['kind' => 'quota', 'table' => 'salary_slips'],
            'leave_requests' => ['kind' => 'quota', 'table' => 'leave_requests'],
            'advance_requests' => ['kind' => 'quota', 'table' => 'salary_advance_requests'],
            /*
                لا مفتاحَ `attendance_records` هنا — أُحيل حدُّه إلى `employees`.

                كان يَعُدّ طبقةَ «الجلسات» التي لم تعد تُكتب، فتجمّد عند
                رقمه. وإعادةُ ربطه بسجلّ الحضور الحيّ (`attendance_days`)
                تُنتج ×٢٢ أقربَ مفاتيح الموارد البشرية إليه — نحو ٢٢ سجلاً
                لكل موظفٍ شهرياً — فأيُّ حدٍّ مُعايَرٍ على العدّاد القديم
                يُخترق في أيام.

                والكلفةُ الحقيقية تتبع **عددَ الموظفين** لا عددَ أيامهم:
                أيامُ الحضور مشتقّةٌ منه حسابياً (موظفٌ = ٢٢ سجلاً شهرياً)،
                و`employees` سقفٌ مُلزَمٌ فعلاً في `PlanLimitObserver`.
                فقياسان لكلفةٍ واحدة أحدُهما يكفي.

                ولا يُكسر شيءٌ بغيابه: `push()` تتخطّى أيَّ مفتاحٍ تعرفه
                فوترةُ ولا يعرفه الكتالوج (`skipped`) بلا خطأ.
            */

            // ---------- سقوف متزامنة ----------
            'employees' => ['kind' => 'cap', 'table' => 'employees'],
            'departments' => ['kind' => 'cap', 'table' => 'departments'],
            'attendance_devices' => ['kind' => 'cap', 'table' => 'machines'],
            'shifts' => ['kind' => 'cap', 'table' => 'custom_shifts'],
            'contracts' => ['kind' => 'cap', 'table' => 'contracts'],

            /*
            |------------------------------------------------------------------
            | المخزون
            |------------------------------------------------------------------
            |
            | المفاتيح هنا هي **مفاتيح فوترة نفسها** كما تصل في اللقطة
            | (`usage[].key` في القسم `stock`)، لا أسماءً من عندنا: أي حرفٍ
            | مختلف يجعل `SubscriptionGate::limit()` تُرجع «ميزة غير متابَعة»
            | فتُصبح بلا حدّ — وهي ثغرةٌ صامتة لا تُنتج خطأً يُلاحَظ.
            |
            | وغيابها قبل اليوم لم يكن مجرّد نقصٍ في الرفع: بلا مدخلٍ هنا لا
            | تعرف `measure()` جدولَ الميزة، فيتعذّر قياسها محلياً أصلاً —
            | ومعه يتعذّر إلزام الحدّ في لحظة الإنشاء.
            |
            | ثلاثة منها (`bom_lists`, `workstations`, `manufacturing_orders`)
            | جداولها في وحدة التصنيع لا وحدة المخزون، وقد صار التصنيع وحدةً
            | مستقلّة. تُقاس وتُرفَع من هنا لأن الكتالوج واحد، أمّا إلزام
            | حدودها في لحظة الإنشاء فموضعُه متحكّمات تلك الوحدة.
            */
            // ---------- عدّادات دورية ----------
            'warehouse_permits' => ['kind' => 'quota', 'table' => 'warehouse_permits'],
            'inventory_adjustments' => ['kind' => 'quota', 'table' => 'inventory_adjustments'],
            'manufacturing_orders' => ['kind' => 'quota', 'table' => 'manufactur_orders'],

            // ---------- سقوف متزامنة ----------
            'products' => ['kind' => 'cap', 'table' => 'products'],
            'storehouses' => ['kind' => 'cap', 'table' => 'store_houses'],
            'price_lists' => ['kind' => 'cap', 'table' => 'price_lists'],
            'product_categories' => ['kind' => 'cap', 'table' => 'categories'],
            'brands' => ['kind' => 'cap', 'table' => 'brands'],
            'bom_lists' => ['kind' => 'cap', 'table' => 'production_materials'],
            'workstations' => ['kind' => 'cap', 'table' => 'work_stations'],

            /*
            |------------------------------------------------------------------
            | نقاط البيع
            |------------------------------------------------------------------
            |
            | الوحدةُ كانت **خارج القياس كلّه**: لا مدخلَ لها هنا، فلا
            | `measure()` تعرف جداولها ولا `push()` ترفع استهلاكها — ومعه
            | يتعذّر إلزامُ حدودها في لحظة الإنشاء أصلاً.
            |
            | وفاتورةُ نقطة البيع ليست هنا: تُقاس في `sales_invoices` ضمن
            | قسم المبيعات (`type IN (normal, pos, online)`)، وتكرارُها هنا
            | يجعلها تستهلك رصيدين.
            |
            | و`pos_concurrent_sessions` **لا يُقاس هنا**: هو سقفٌ على
            | المفتوح في لحظةٍ بعينها لا على المُنشأ في فترة، فقياسُه
            | بعدّ الصفوف يُنتج رقماً لا معنى له. موضعُ إلزامه
            | `plan.limit` على مسار الفتح، ويقرأ الجلسات النشطة وحدها.
            */
            // ---------- عدّادات دورية ----------
            'pos_sessions' => ['kind' => 'quota', 'table' => 'pos_sessions'],
            'pos_cash_movements' => ['kind' => 'quota', 'table' => 'pos_cash_movements'],
            'pos_held_orders' => ['kind' => 'quota', 'table' => 'pos_held_orders'],

            // ---------- سقوف متزامنة ----------
            'pos_devices' => ['kind' => 'cap', 'table' => 'cashier_devices'],

            /*
                السقفُ المتزامن الحقيقي: الجلساتُ **المفتوحة الآن** لا
                المُنشأة في فترة.

                جهازٌ واحد يتناوب عليه ثلاثة كاشيرين في ثلاث ورديات استعمالٌ
                واحد لا ثلاثة — فالعدُّ بالإنشاء يُنتج رقماً لا يقيس ما
                اشتراه العميل.
            */
            'pos_concurrent_sessions' => ['kind' => 'cap', 'table' => 'pos_sessions'],
            'pos_printers' => ['kind' => 'cap', 'table' => 'pos_printers'],
            'pos_shifts' => ['kind' => 'cap', 'table' => 'shifts_pos'],

            /*
            |------------------------------------------------------------------
            | المتجر الإلكتروني
            |------------------------------------------------------------------
            |
            | المفاتيح هنا هي **مفاتيح فوترة نفسها** كما في
            | `fawtra_dashboard/config/online_store_features.php`.
            |
            | العلامات والأصناف والمنتجات **غائبة عمداً**: جداولها مشتركة مع
            | المخزون (`brands`, `categories`, `products`) وتُقاس مرّةً واحدة
            | ضمن حدود [\App\Constants\planFeatures\StockPlanFeatures] أعلاه —
            | مدخلٌ ثانٍ هنا بنفس الجدول يُضاعف العدّ المرفوع لفوترة.
            */
            // ---------- عدّادات دورية ----------
            'online_orders' => ['kind' => 'quota', 'table' => 'online_orders'],

            // ---------- سقوف متزامنة ----------
            'store_coupons' => ['kind' => 'cap', 'table' => 'store_coupons'],
            'store_shipping_methods' => ['kind' => 'cap', 'table' => 'shipping_methods'],
            'store_banners' => ['kind' => 'cap', 'table' => 'store_banners'],
        ];
    }

    /**
     * رفع ما تغيّر من الاستهلاك.
     *
     * @return array{reported:int, caps:int, skipped:int, failed:int, blocked:array<int, string>}
     */
    public function push(FawtraClient $client, FawtraSubscription $record, ?array $only = null): array
    {
        $state = $record->usage_report_state ?? [];
        $summary = ['reported' => 0, 'caps' => 0, 'skipped' => 0, 'failed' => 0, 'blocked' => []];
        $catalog = static::catalog();
        $patched = [];

        foreach ($record->usage() as $item) {
            $key = $item['key'] ?? null;
            $meta = $key ? ($catalog[$key] ?? null) : null;

            if ($meta === null) {
                $summary['skipped']++;

                continue;
            }

            // رفعٌ موجَّه: `$only` تحصره في الميزات التي تغيّرت فعلاً في هذا
            // الطلب. الدورة الدورية تمرّرها `null` فترفع كل شيء كما كانت
            if ($only !== null && ! in_array($key, $only, true)) {
                $summary['skipped']++;

                continue;
            }

            try {
                $outcome = $meta['kind'] === 'cap'
                    ? $this->pushCap($client, $key, $state, $item)
                    : $this->pushQuota($client, $record, $item, $state);
            } catch (Throwable $e) {
                // ميزةٌ أخفقت لا توقف البقية: الرفع تصحيحٌ تراكمي يُعاد
                // في الدورة التالية، لا عملية ذرّية
                Log::warning('فوترة: تعذّر رفع استهلاك '.$key, ['error' => $e->getMessage()]);
                $summary['failed']++;

                continue;
            }

            $summary[$outcome['bucket']] = ($summary[$outcome['bucket']] ?? 0) + 1;

            if ($outcome['blocked'] ?? false) {
                $summary['blocked'][] = $key;
            }

            if (isset($outcome['state'])) {
                $state[$key] = $outcome['state'];
            }

            if (isset($outcome['patch'])) {
                $patched[$key] = $outcome['patch'];
            }
        }

        $record->forceFill([
            'usage_report_state' => $state,
            'last_usage_report_at' => now(),
        ])->save();

        // اللقطة تُصحَّح بما ردّته فوترة فوراً: بغيرها تعرض الشاشة أرقاماً
        // قبل الرفع حتى الدورة التالية، فيظنّ المستخدم أن الرفع لم يحدث
        if ($patched !== []) {
            $this->patchSnapshot($record, $patched);
        }

        return $summary;
    }

    /* ==================== الرفع ==================== */

    /**
     * سقف متزامن: يُرسَل العدد المطلق، ولا يُرسَل إن لم يتغيّر.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function pushCap(FawtraClient $client, string $feature, array $state, array $item = []): array
    {
        $count = $this->measure($feature, null, null);

        if ($count === null) {
            return ['bucket' => 'skipped'];
        }

        /*
            **لا يكفي أن نقارن بما أرسلناه — نقارن بما تقوله اللوحة فعلاً.**

            كانت المقارنة بعلامتنا المحلّية وحدها: «أرسلتُ ٦ من قبل، والعدد
            ما زال ٦، إذن لا شيء يُرسَل». وهي صحيحةٌ ما دامت اللوحة تحتفظ بما
            أرسلناه — **وهي لا تحتفظ به دائماً**: تغييرُ الباقة أو إعادةُ
            تركيبها يُصفّر عدّاداتها عندهم، فتصير اللوحة على صفرٍ ونحن نظنّ
            أننا أبلغناها ستّة. ولا شيء يكسر هذا التعادل أبداً — فيبقى
            الانحراف إلى الأبد، وتعرض شاشة الاشتراك «٠ من ١» بينما يمنع
            الإنشاءَ حدٌّ محسوبٌ من ستّة.

            وقد وقع فعلاً: باقةٌ أُعيد ضبطها فصار `used=0` عندهم و`count=6`
            عندنا، والمستخدم يرى شاشةً تقول «لا استهلاك» ومنعاً يقول «بلغت
            الحدّ».

            فالشرط الآن: يُرسَل ما لم يتّفق **الثلاثة** — ما قسناه، وما
            أرسلناه، وما تقوله اللوحة في آخر لقطة.
        */
        $reported = $state[$feature]['count'] ?? null;
        $onDashboard = array_key_exists('used', $item) ? (int) $item['used'] : null;

        if ($reported === $count && ($onDashboard === null || $onDashboard === $count)) {
            return ['bucket' => 'skipped'];
        }

        $response = $client->post('usage/cap', ['feature' => $feature, 'count' => $count]);

        if (! $response['ok']) {
            return $this->failure($feature, $response);
        }

        return [
            'bucket' => 'caps',
            'state' => ['count' => $count, 'at' => now()->toIso8601String()],
            'patch' => $response['data'] ?? [],
        ];
    }

    /**
     * عدّاد دوري: يُرسَل الفارق بين ما قيس الآن وما رُفع من قبل في الفترة نفسها.
     *
     * العلامة المائية محفوظة بمفتاح الفترة، فبداية شهرٍ جديد تُصفّرها من غير
     * جدولٍ إضافي ولا مهمّة تنظيف.
     *
     * @param  array<string, mixed>  $item  عنصر الاستهلاك من اللقطة
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function pushQuota(FawtraClient $client, FawtraSubscription $record, array $item, array $state): array
    {
        $feature = $item['key'];
        $window = $this->window($item['period'] ?? null, $record);

        if ($window === null) {
            return ['bucket' => 'skipped'];
        }

        [$periodKey, $from, $to] = $window;

        /*
            الأساس هو **ما تقوله اللوحة** لا ما نظنّ أننا أرسلناه.

            العلامة المائية المحلّية تكفي ما دامت اللوحة تحتفظ بما وصلها، وهي
            لا تحتفظ به دائماً: تغيير الباقة أو إعادة ضبطها يُصفّر عدّاداتها،
            فنحسب الفارق من رقمٍ لم يعد موجوداً عندهم ونرسل واحداً بينما
            ينقصهم إحدى وثلاثون. واللوحة صاحبةُ الرقم، فهي المرجع فيه.

            وتبقى العلامة أساساً احتياطياً حين لا تُرسل اللقطة `used` — لقطةٌ
            أقدم من هذه الإضافة مثلاً.
        */
        $watermark = ($state[$feature]['period'] ?? null) === $periodKey
            ? (int) ($state[$feature]['reported'] ?? 0)
            : 0;

        $previous = array_key_exists('used', $item) ? (int) $item['used'] : $watermark;

        // ميزةٌ رُفض رفعها لبلوغ الحدّ لا يُعاد الطلب لها حتى تتغيّر الفترة:
        // إعادة المحاولة كل ربع ساعة تستهلك حدّ الطلبات بلا فائدة
        if (($state[$feature]['blocked_period'] ?? null) === $periodKey) {
            return ['bucket' => 'skipped'];
        }

        $total = $this->measure($feature, $from, $to);

        if ($total === null) {
            return ['bucket' => 'skipped'];
        }

        $delta = $total - $previous;

        if ($delta <= 0) {
            // الرقم قد ينقص بحذف مستندات: العلامة تُخفَّض بلا طلب، فلا يُرفع
            // لاحقاً استهلاكٌ مضاعف عند إعادة الإنشاء
            return [
                'bucket' => 'skipped',
                'state' => ['period' => $periodKey, 'reported' => max(0, $total), 'at' => now()->toIso8601String()],
            ];
        }

        $quantity = min($delta, self::MAX_QUANTITY);
        $newTotal = $previous + $quantity;

        $response = $client->post(
            'usage/report',
            ['feature' => $feature, 'quantity' => $quantity],
            // المفتاح مشتقّ من (الميزة، الفترة، المجموع بعد الرفع): إعادة
            // المحاولة بعد استجابةٍ ضائعة تُعيد نتيجة الأولى بلا قيدٍ ثانٍ
            'usage:'.$feature.':'.$periodKey.':'.$newTotal
        );

        if (! $response['ok']) {
            $result = $this->failure($feature, $response);

            // بلوغ الحدّ ليس عطلاً: الرقم المحلّي تجاوز ما تسمح به الباقة
            if (($response['code'] ?? null) === 'LIMIT_REACHED') {
                $result['blocked'] = true;
                $result['state'] = [
                    'period' => $periodKey,
                    'reported' => $previous,
                    'blocked_period' => $periodKey,
                    'at' => now()->toIso8601String(),
                ];
            }

            return $result;
        }

        return [
            'bucket' => 'reported',
            'state' => ['period' => $periodKey, 'reported' => $newTotal, 'at' => now()->toIso8601String()],
            'patch' => $response['data'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    protected function failure(string $feature, array $response): array
    {
        Log::warning('فوترة: رُفض رفع استهلاك '.$feature, [
            'code' => $response['code'] ?? null,
            'message' => $response['message'] ?? null,
        ]);

        return ['bucket' => 'failed'];
    }

    /**
     * تصحيح اللقطة بما ردّته فوترة (`used` و`remaining`) بلا نداء جديد.
     *
     * @param  array<string, array<string, mixed>>  $patched
     */
    protected function patchSnapshot(FawtraSubscription $record, array $patched): void
    {
        $snapshot = $record->snapshot;

        /*
            **البنيتان مقبولتان — والقراءة كانت تعرف واحدةً فقط.**

            `FawtraSubscription::usage()` تقرأ `snapshot.usage.usage` وإلا
            `snapshot.usage` مباشرةً، لأن `/sync` يُرسل الاستهلاك **قائمةً
            مسطَّحة** بينما بنيةٌ أخرى تغلّفه. أمّا هنا فكان القارئ يعرف
            الغلاف وحده، فيخرج بـ`return` صامتٍ على اللقطة المسطَّحة — وهي
            الشكل الذي تصل به فعلاً اليوم.

            والأثر أن تصحيح اللقطة بما ردّته فوترة **لم يكن يقع أبداً**: تُرفع
            الأرقام وتردّ الواجهة `used` و`remaining` المحدَّثين، ثم يُرمى
            الردّ وتبقى الشاشة على أرقامٍ قبل الرفع حتى المزامنة التالية.
            وبلا خطأ ولا سطرٍ في سجلّ — وهو تحديداً ما يجعل عطلاً كهذا يعمّر.
        */
        $nested = is_array($snapshot['usage']['usage'] ?? null);
        $rows = $nested ? $snapshot['usage']['usage'] : ($snapshot['usage'] ?? null);

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $index => $row) {
            $data = $patched[$row['key'] ?? ''] ?? null;

            if ($data === null) {
                continue;
            }

            $used = $data['used'] ?? $data['count'] ?? null;
            $remaining = $data['remaining'] ?? null;

            if ($used !== null) {
                $rows[$index]['used'] = (int) $used;
            }

            if ($remaining !== null) {
                $rows[$index]['remaining'] = (int) $remaining;
            }

            $allowance = (int) ($row['allowance'] ?? -1);

            if ($allowance > 0 && $used !== null) {
                $rows[$index]['percent'] = (int) min(100, round(((int) $used / $allowance) * 100));
                $rows[$index]['exhausted'] = (int) $used >= $allowance;
            }
        }

        // تُعاد إلى موضعها الذي قُرئت منه — لا إلى الغلاف دائماً: الكتابة في
        // `usage.usage` على لقطةٍ مسطَّحة تُنشئ مفتاحاً لا يقرؤه أحد وتترك
        // الأصل على حاله
        if ($nested) {
            $snapshot['usage']['usage'] = $rows;
        } else {
            $snapshot['usage'] = $rows;
        }

        $record->forceFill(['snapshot' => $snapshot])->save();

        SubscriptionGate::forget();
    }

    /* ==================== القياس ==================== */

    /**
     * نافذة الفترة كما تسمّيها فوترة.
     *
     * الوسم يصل نصّاً عربياً في اللقطة (`يومي`/`شهري`/`طوال مدة الباقة`)،
     * ومفتاح الفترة هنا يوافق ما يبنيه الطرف الآخر: تاريخ اليوم، أو `Y-m`،
     * أو مدّة الاشتراك — فلا ينفصل عدّادانا عن عدّاديهما.
     *
     * @return array{0:string, 1:?Carbon, 2:?Carbon}|null
     */
    protected function window(?string $periodLabel, FawtraSubscription $record): ?array
    {
        return match ($periodLabel) {
            'يومي' => [now()->toDateString(), now()->startOfDay(), now()->endOfDay()],
            'شهري' => [now()->format('Y-m'), now()->startOfMonth(), now()->endOfMonth()],
            'طوال مدة الباقة' => [
                'sub-'.($record->snapshot['subscription']['id'] ?? 'x'),
                $this->subscriptionStart($record),
                now()->endOfDay(),
            ],
            // سقفٌ متزامن وصل إلى هنا، أو وسمٌ لا نعرفه: لا يُرفع بالتخمين
            default => null,
        };
    }

    protected function subscriptionStart(FawtraSubscription $record): ?Carbon
    {
        $start = $record->snapshot['subscription']['start_date'] ?? null;

        try {
            return $start ? Carbon::parse($start)->startOfDay() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * عدّ ميزةٍ واحدة محلياً.
     *
     * كل استعلام محروسٌ بوجود الجدول والعمود: النظام قابل لتعطيل مديولات
     * كاملة، وغيابُ جدول لا يجوز أن يُسقط المهمّة الدورية كلّها.
     */
    public function measure(string $feature, ?Carbon $from, ?Carbon $to): ?int
    {
        $meta = static::catalog()[$feature] ?? null;

        if ($meta === null || ! Schema::hasTable($meta['table'])) {
            return null;
        }

        $table = $meta['table'];
        $query = DB::table($table);

        // الصفّ المحذوف حذفاً ناعماً لا يشغل مكاناً ولا يُحتسب: عدُّه يُنقص
        // رصيد العميل بما لم يعد موجوداً، ويجعل سقفاً بلغ حدَّه غيرَ قابلٍ
        // للتفريغ بالحذف إطلاقاً. (`brands` أوّل جدولٍ هنا له `deleted_at`.)
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        // نافذة الفترة تُطبَّق على `created_at` — وهو العمود الوحيد الذي
        // يقول متى وقع الحدث. جدولٌ بلا هذا العمود لا يصلح للعدّ الدوري
        if ($from !== null) {
            if (! Schema::hasColumn($table, 'created_at')) {
                return null;
            }

            $query->whereBetween('created_at', [$from, $to ?? now()]);
        }

        return match ($feature) {
            'sales_invoices' => (int) $query->whereIn('type', ['normal', 'pos', 'online'])->count(),
            'return_invoices' => (int) $query->where('type', 'returned')->count(),
            'purchase_invoices' => (int) $query->where('type', 'invoice')->count(),
            'sales_payments' => (int) $query->where('type', 'client payments')->count(),

            // اتفاقية التقسيط صفوفٌ عدّة (قسط لكل دفعة)، والسقف يعدّ الاتفاقيات
            'installment_plans' => (int) $query->distinct()->count('invoice_id'),

            'invoice_templates' => (int) $query->where('type', 'invoice')->count(),
            'gift_offers' => (int) $this->activeScope($query, $table)
                ->where(function ($q) use ($table) {
                    if (Schema::hasColumn($table, 'end_date')) {
                        $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
                    }
                })->count(),

            'periodic_invoices', 'offers', 'shipping_options',
            'order_sources', 'exchange_reasons', 'collections_targets' => (int) $this->activeScope($query, $table)->count(),

            'sales_users' => $this->salesUsers(),

            /*
                سقوف المخزون تُعدّ **كل الصفوف** لا «النشِط» منها.

                `activeScope()` لا تصلح لها: أعمدة الحالة في هذه الجداول ليست
                رايات نشاط أصلاً — `price_lists.status` قيمتها `0` في كل صفّ،
                و`warehouse_permits.status` تحمل `pending`/`approved`/`rejected`.
                فترشيحُها بـ`whereIn('status', ['active', 1, '1'])` يُخرج صفر
                في جدولٍ ملآن — وعددٌ أقلّ من الحقيقة في **سقف** ليس نقصَ
                دقّةٍ بل ثغرة: يسمح بتجاوز الحدّ بلا حدود.

                والسقف هنا يقيس ما يشغل مكاناً في النظام، والصفّ المعطَّل
                يشغله كما يشغله النشِط — فتعطيلُ منتجٍ لا يُفرغ مقعداً.
            */
            'products', 'storehouses', 'price_lists', 'product_categories',
            'brands', 'bom_lists', 'workstations' => (int) $query->count(),

            /*
                سقوفُ نقاط البيع تُعدّ كل الصفوف كذلك.

                وجهازُ كاشيرٍ «معطَّل» (`device_status = inactive`) يشغل
                مقعده كما يشغله العامل: هو مسجَّلٌ في النظام، وله تاريخُ
                جلساتٍ وتقارير، ويكفي تغييرُ حقلٍ ليعود. والعدُّ بالنشِط
                وحده يجعل تعطيلَ الجهاز وسيلةً لتجاوز السقف.
            */
            'pos_devices', 'pos_printers', 'pos_shifts' => (int) $query->count(),

            /*
                الجلساتُ المتزامنة: ما يشغل طرفيةً الآن.

                والمعلَّقة (`suspended`) تُعدّ مع النشِطة: الكاشيرُ الذي علّق
                جلسته لم يُقفل عهدته، فالجهازُ ما زال مشغولاً بها ولا يفتح
                عليه غيرُه. و`pending_review` و`closed` خارجُهما: عُدَّ درجُهما
                وسُلِّمت.
            */
            'pos_concurrent_sessions' => (int) $query
                ->whereIn('status', ['active', 'suspended'])
                ->count(),

            /*
                **الأصل الثابت حسابٌ لا صفٌّ في جدولٍ خاص.**

                `AssetsController` يُنشئ لكل أصلٍ حساباً تحت أب «الأصول
                الثابتة»، فلا يوجد جدول `assets` أصلاً. وعدُّ جدول `accounts`
                كلِّه يُنتج آلاف الحسابات مكان عشرات الأصول — رقمٌ يستنفد سقفاً
                من خمسين قبل أن يُنشأ أصلٌ واحد.

                والأب يُقرأ من «الحسابات المحاسبية» لا من الثابت المكتوب:
                المنشأة قد تُعيد توجيهه، وقراءةُ الثابت وحده تعدّ تحت أبٍ
                مهجور. والأبناء المباشرون هم الأصول — كما تقرؤهم الشاشة نفسها.
            */
            'assets' => (int) $query
                ->where('parent_id', AccountingSettings::id('fixed_assets', SystemAccountIds::FIXED_ASSETS))
                ->count(),

            /*
                سقوف الحسابات تُعدّ كل الصفوف كذلك — للسبب نفسه أعلاه:
                `treasuries.status` و`cheque_books.status` ليستا رايتَي نشاط،
                وخزينةٌ معطَّلة تشغل مقعدها كما تشغله العاملة.
            */
            'treasuries', 'cost_centers', 'check_books' => (int) $query->count(),

            /*
                الفرع والمجموعة يتقاسمان جدول `branches` — والترشيح بالعمود
                `is_territory` هو ما يفصل السقفين.

                و`is_territory` قد تكون `NULL` في صفوفٍ قديمة سبقت العمود،
                فالفرع يُعدّ بـ«ليست واحداً» لا بـ«تساوي صفراً»: الشرط الثاني
                يُسقط تلك الصفوف من العدّ فيظهر سقفٌ أوسع ممّا هو، وهو نقصٌ
                في الحراسة لا في العرض.
            */
            'branches' => (int) $query->where(function ($q) {
                $q->where('is_territory', 0)->orWhereNull('is_territory');
            })->count(),

            'territory_groups' => (int) $query->where('is_territory', 1)->count(),

            /*
                سقوف الموارد البشرية تُعدّ كل الصفوف — كما في بقيّة السقوف:
                موظفٌ موقوف يشغل ملفَّه، وجهازُ بصمةٍ معطَّل يشغل ترخيصَه.
            */
            'employees', 'departments', 'attendance_devices',
            'shifts', 'contracts' => (int) $query->count(),

            default => (int) $query->count(),
        };
    }

    /**
     * قياسٌ حيٌّ لميزة — بنافذة فترتها كما تسمّيها اللقطة.
     *
     * **لماذا يلزم قياسٌ حيّ وفي اللقطة عمود `used`؟** لأن `used` في اللقطة
     * رقمٌ عمرُه ما بين مزامنتين (ربع ساعة)، ولأن رفعَه إلى فوترة يقع في
     * الدورة الدورية لا مع كل عملية. فمن يريد **منع** التجاوز في لحظة
     * الإنشاء لا يجوز أن يسأل رقماً قديماً: ربع ساعةٍ تكفي لإنشاء آلاف
     * الصفوف فوق السقف، ثم تأتي المزامنة فتجد الحدّ متجاوَزاً ولا تملك
     * إرجاعه.
     *
     * فالحدّ (`allowance`) يُقرأ من اللقطة — وهو وحده مِلكُ فوترة — أمّا
     * المستهلَك فيُعدّ من قاعدة البيانات الآن.
     *
     * @return int|null null حين لا تُقاس الميزة محلياً (لا مدخل لها في
     *                  الكتالوج، أو جدولها غير موجود، أو وسم فترتها مجهول)
     */
    public function liveCount(string $feature, ?string $periodLabel = null, ?FawtraSubscription $record = null): ?int
    {
        $meta = static::catalog()[$feature] ?? null;

        if ($meta === null) {
            return null;
        }

        // السقف المتزامن لا نافذة له: يُعدّ الموجود كلّه
        if ($meta['kind'] === 'cap') {
            return $this->measure($feature, null, null);
        }

        $record ??= FawtraSubscription::current();

        if ($record === null) {
            return null;
        }

        $window = $this->window($periodLabel, $record);

        if ($window === null) {
            return null;
        }

        return $this->measure($feature, $window[1], $window[2]);
    }

    /**
     * تصفية «النشِط» على اختلاف تسميته بين الجداول.
     *
     * الأعمدة هنا ليست موحّدة في هذا النظام (`is_active` و`active` و`status`
     * رقماً ونصّاً)، والسقف يقيس ما يشغل مكاناً فعلاً لا ما في الأرشيف.
     */
    protected function activeScope($query, string $table)
    {
        if (Schema::hasColumn($table, 'is_active')) {
            return $query->where('is_active', 1);
        }

        if (Schema::hasColumn($table, 'active')) {
            return $query->where('active', 1);
        }

        if (Schema::hasColumn($table, 'status')) {
            // `status` نصّي في بعض الجداول ورقميّ في غيرها — كلا القيمتين
            // تعني «نشط»، وأيّهما لا يطابق لا يضرّ
            return $query->whereIn('status', ['active', 1, '1']);
        }

        return $query;
    }

    /**
     * مقاعد المبيعات: من يملك صلاحية مبيعات واحدة على الأقل.
     *
     * تُحسب من الصلاحية لا من عمود `role`: الكتالوج عند فوترة يعرّفها
     * «مستخدمو قسم المبيعات»، ومن يملك صلاحية مبيعات هو مستخدم مبيعات ولو
     * كان دوره مسمّى بغير ذلك.
     */
    protected function salesUsers(): ?int
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            return null;
        }

        try {
            $direct = DB::table('model_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                ->where('permissions.name', 'like', 'sales.%')
                ->pluck('model_has_permissions.model_id');

            $viaRoles = DB::table('model_has_roles')
                ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', 'like', 'sales.%')
                ->pluck('model_has_roles.model_id');

            return $direct->merge($viaRoles)->unique()->count();
        } catch (Throwable $e) {
            Log::warning('فوترة: تعذّر عدّ مستخدمي المبيعات', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

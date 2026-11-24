<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أوامر التوريد: الأعمدة التي يكتبها المتحكّم ولا وجود لها في الجدول.
 *
 * ═══════════════════════════════════════════════════════════════════
 * 1. `created_by` — عمودٌ يُقرأ في أربعة مواضع ولم يُخلق قطّ
 * ═══════════════════════════════════════════════════════════════════
 *
 * `SupplyOrdersController::index()` يكتب:
 *
 *     $query->where('created_by', auth()->id());
 *
 * لكلّ من لا يملك `supply_orders_view_all`. والعمود غير موجود في
 * `create_supply_orders_table`، فالاستعلام يسقط بـ:
 *
 *     SQLSTATE[42S22]: Unknown column 'created_by' in 'where clause'
 *
 * و`edit()` و`update()` و`destroy()` تقارن `$order->created_by != auth()->id()`
 * فتقرأ `null` — و`null != 5` صحيحٌ دائماً، فمن يملك
 * `supply_orders_edit_delete_own` وحدها يُمنَع من كلّ أمرٍ أنشأه بنفسه.
 *
 * أي أن نصف نظام الصلاحيات في هذا القسم لم يعمل يوماً.
 *
 * ═══════════════════════════════════════════════════════════════════
 * 2. `assigned_to` — فلترٌ في الشاشة بلا عمود
 * ═══════════════════════════════════════════════════════════════════
 *
 * قائمة «التعيين إلى» موجودة في نموذج البحث و`index()` يفلتر بها، ولا
 * عمود. فاختيار موظّفٍ من القائمة يعطي 500 لا نتيجةً فارغة.
 *
 * ═══════════════════════════════════════════════════════════════════
 * 3. `status_id` — معنيان متعارضان لعمودٍ واحد
 * ═══════════════════════════════════════════════════════════════════
 *
 * `supply_orders.status` عمود `tinyInteger` تعليقه في الهجرة الأصلية:
 * «1: Pending, 2: In Progress, 3: Completed, 4: Cancelled» — أي قائمةٌ
 * مثبَّتة. لكن `updateStatus()` يعامله معرّفَ صفٍّ في جدول `statuses`:
 *
 *     $status = Statuses::find($validatedData['status']);
 *
 * وشاشة القائمة `partials/table` تقارنه بنصوص (`$order->status == 'pending'`)
 * وهو رقم — ففي PHP 8 المقارنة كاذبةٌ دائماً، وكلّ أمر توريد في النظام
 * يظهر «غير محدد» بنقطةٍ حمراء مهما كانت حالته.
 *
 * ثلاثة معانٍ لعمودٍ واحد. الحلّ: `status_id` مفتاحاً صريحاً إلى
 * `statuses`، ويبقى `status` كما هو للبيانات القديمة ولا يُقرأ بعد اليوم.
 *
 * ═══════════════════════════════════════════════════════════════════
 * 4. `currency` — رقمٌ من قائمةٍ مثبَّتة بدل رمز العملة
 * ═══════════════════════════════════════════════════════════════════
 *
 * العمود `tinyInteger` بتعليق «1: SAR, 2: USD, 3: EUR, 4: GBP, 5: CNY»
 * والتحقّق `'currency' => 'nullable|in:1,2,3,4,5'`، وشاشة الإنشاء تكتب
 * الخمسة في القالب. وفي النظام جدول `system_currencies` وشاشة عملات
 * وصنف `App\Support\CurrencySettings` — لا شيء منها يصل إلى هنا. فمن ضبط
 * عملة منشأته على الدينار لا يجدها في القائمة، وشاشة العرض تطبع الرقم
 * الخام إلى جانب المبلغ: «1500.00 1».
 *
 * يُعاد تسمية القديم `currency_legacy` (لا يُحذف: البيانات القديمة تبقى
 * قابلةً للمراجعة) ويحلّ محلّه عمود نصّي يحمل رمز العملة — وهو ما تتوقّعه
 * `Modules\Sales\Http\Controllers\Concerns\UsesDocumentCurrency` في بقيّة
 * مستندات المبيعات.
 *
 * ═══════════════════════════════════════════════════════════════════
 * 5. `statuses` — جدولٌ مشترَك بين قسمين لا يعرف أحدهما الآخر
 * ═══════════════════════════════════════════════════════════════════
 *
 * `App\Models\Statuses::clients()` علاقةٌ إلى `clients.status_id`. فشاشة
 * «قائمة الحالات» في أوامر التوريد تسرد `Statuses::all()` — أي حالات
 * العملاء أيضاً — وتعديلُ اسم حالةٍ فيها يغيّر تصنيف العملاء.
 *
 * عمود `section` يفصل القائمتين. الصفوف القائمة تبقى بلا قسم («عامّة»)
 * فلا يتغيّر شيءٌ لدى العملاء، وحالات أوامر التوريد الجديدة تُخلَق
 * بقسمها.
 *
 * و`is_deletable` عمودٌ يقرأه قالب `edit_status.blade.php` منذ اليوم
 * الأول ولا وجود له، فزرّ الحذف لم يظهر قطّ في تلك الشاشة.
 */
return new class extends Migration
{
    /** خريطة العملات المثبَّتة في العمود القديم. */
    private const LEGACY_CURRENCIES = [
        1 => 'SAR',
        2 => 'USD',
        3 => 'EUR',
        4 => 'GBP',
        5 => 'CNY',
    ];

    /**
     * حالات أوامر التوريد الافتراضية — تُزرَع مرّةً واحدة إن خلا القسم.
     *
     * `state`: 1 مفتوح، 0 مغلق. المغلق يعني أن الأمر خرج من قائمة العمل،
     * وعليه تُبنى إحصاءات الشاشة.
     */
    private const SEED_STATUSES = [
        ['name' => 'مسودة', 'color' => '#6e6b7b', 'state' => 1, 'sort_order' => 1, 'is_default' => 1],
        ['name' => 'قيد التجهيز', 'color' => '#ff9f43', 'state' => 1, 'sort_order' => 2, 'is_default' => 0],
        ['name' => 'جاري الشحن', 'color' => '#00cfe8', 'state' => 1, 'sort_order' => 3, 'is_default' => 0],
        ['name' => 'تم التوريد', 'color' => '#28c76f', 'state' => 0, 'sort_order' => 4, 'is_default' => 0],
        ['name' => 'ملغى', 'color' => '#ea5455', 'state' => 0, 'sort_order' => 5, 'is_default' => 0],
    ];

    public function up(): void
    {
        $this->upgradeStatuses();
        $this->upgradeSupplyOrders();
        $this->convertCurrency();
        $this->seedSupplyStatuses();
        $this->backfill();
    }

    /**
     * أعمدة `statuses`: القسم والترتيب والافتراضية وقابلية الحذف.
     */
    private function upgradeStatuses(): void
    {
        if (! Schema::hasTable('statuses')) {
            return;
        }

        Schema::table('statuses', function (Blueprint $table) {
            // `null` = حالةٌ عامّة كما كانت: لا يتغيّر شيءٌ لدى العملاء
            if (! Schema::hasColumn('statuses', 'section')) {
                $table->string('section', 40)->nullable()->after('id')->index();
            }

            if (! Schema::hasColumn('statuses', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('state');
            }

            // الحالة الافتراضية لأمرٍ جديد — كان `store()` يثبّت `status = 1`
            if (! Schema::hasColumn('statuses', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('sort_order');
            }

            // يقرأه `edit_status.blade.php` منذ اليوم الأول ولا وجود له
            if (! Schema::hasColumn('statuses', 'is_deletable')) {
                $table->boolean('is_deletable')->default(true)->after('is_default');
            }
        });
    }

    /**
     * أعمدة `supply_orders` الناقصة.
     */
    private function upgradeSupplyOrders(): void
    {
        Schema::table('supply_orders', function (Blueprint $table) {
            // ── المسؤولية والفرع ──────────────────────────────────
            if (! Schema::hasColumn('supply_orders', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('status')->index();
            }

            if (! Schema::hasColumn('supply_orders', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }

            // عزل الفروع: بقيّة مستندات المبيعات تحمل فرعها، وأوامر التوريد
            // كانت الجدول الوحيد الذي يراه كلُّ فرعٍ كاملاً
            if (! Schema::hasColumn('supply_orders', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('updated_by')->index();
            }

            if (! Schema::hasColumn('supply_orders', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('employee_id')->index();
            }

            // ── الحالة ────────────────────────────────────────────
            if (! Schema::hasColumn('supply_orders', 'status_id')) {
                $table->unsignedBigInteger('status_id')->nullable()->after('status')->index();
            }

            // ── طريقة الدفع ───────────────────────────────────────
            // من `payment_methods` — نفس ما تستعمله الفاتورة وسند القبض
            if (! Schema::hasColumn('supply_orders', 'payment_method')) {
                $table->unsignedBigInteger('payment_method')->nullable()->after('budget');
            }

            // ── قالب الطباعة المختار ──────────────────────────────
            if (! Schema::hasColumn('supply_orders', 'document_template_id')) {
                $table->unsignedBigInteger('document_template_id')->nullable()->after('payment_method');
            }
        });

        // فهرسٌ مركّب لأكثر فلترٍ تُشغّله الشاشة: النطاق الزمني ثم الحالة
        $this->addIndexIfMissing('supply_orders', ['start_date', 'status_id'], 'supply_orders_start_status_index');
    }

    /**
     * `currency` من رقمٍ مثبَّت إلى رمز عملة نصّي.
     *
     * القديم يُحفَظ باسم `currency_legacy` ولا يُحذف: المراجعة المحاسبية
     * قد تحتاج ما كان مكتوباً وقت الحفظ، والهجرةُ التي تحذف عموداً بيانات
     * لا يمكن التراجع عنها.
     */
    private function convertCurrency(): void
    {
        $hasLegacy = Schema::hasColumn('supply_orders', 'currency_legacy');
        $current = Schema::hasColumn('supply_orders', 'currency');

        // العمود النصّي موجودٌ سلفاً (هجرةٌ أُعيد تشغيلها) — لا شيء يُفعل
        if ($hasLegacy && $current) {
            return;
        }

        if ($current && ! $hasLegacy) {
            Schema::table('supply_orders', function (Blueprint $table) {
                $table->renameColumn('currency', 'currency_legacy');
            });
        }

        if (! Schema::hasColumn('supply_orders', 'currency')) {
            Schema::table('supply_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('supply_orders', 'currency')) {
                    $table->string('currency', 10)->nullable()->after('budget');
                }
            });
        }
    }

    /**
     * حالات القسم الافتراضية — إن لم يكن فيه شيء بعد.
     */
    private function seedSupplyStatuses(): void
    {
        if (! Schema::hasTable('statuses') || ! Schema::hasColumn('statuses', 'section')) {
            return;
        }

        if (DB::table('statuses')->where('section', 'supply-order')->exists()) {
            return;
        }

        $now = now();

        foreach (self::SEED_STATUSES as $status) {
            DB::table('statuses')->insertOrIgnore($status + [
                'section' => 'supply-order',
                // المزروعة لا تُحذف: حذفُ آخر حالةٍ يترك الشاشة بلا قائمة
                'is_deletable' => $status['is_default'] ? 0 : 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * ملء الأعمدة الجديدة للصفوف القائمة.
     */
    private function backfill(): void
    {
        // ── العملة ────────────────────────────────────────────────
        if (Schema::hasColumn('supply_orders', 'currency_legacy')) {
            foreach (self::LEGACY_CURRENCIES as $code => $iso) {
                DB::table('supply_orders')
                    ->whereNull('currency')
                    ->where('currency_legacy', $code)
                    ->update(['currency' => $iso]);
            }
        }

        // ما لا يقابله رمزٌ في الخريطة يأخذ عملة النظام: صفٌّ بلا عملة
        // يُطبَع بلا رمز ويُقرأ في التقارير كأنّه بلا قيمة
        try {
            DB::table('supply_orders')
                ->whereNull('currency')
                ->update(['currency' => \App\Support\CurrencySettings::code()]);
        } catch (\Throwable $e) {
            // إعدادات العملة غير مهيّأة بعد — تُقرأ الافتراضية وقت العرض
        }

        // ── الحالة ────────────────────────────────────────────────
        // `status` القديم كان يُستعمَل معرّفَ صفٍّ في `statuses` من
        // `updateStatus()`. ما يقابله صفٌّ موجود يُربَط، وما لا يقابله
        // يُترك فارغاً فتتولّاه الحالة الافتراضية في العرض
        if (Schema::hasColumn('supply_orders', 'status_id') && Schema::hasTable('statuses')) {
            $ids = DB::table('statuses')->pluck('id')->all();

            if ($ids !== []) {
                DB::table('supply_orders')
                    ->whereNull('status_id')
                    ->whereIn('status', $ids)
                    ->update(['status_id' => DB::raw('status')]);
            }
        }
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        try {
            if (Schema::hasTable($table) && ! $this->indexExists($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        } catch (\Throwable $e) {
            // فهرسٌ مكرّر أو محرّكٌ لا يدعم الاسم — الفهرس تحسينٌ لا شرط
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn ($index) => ($index['name'] ?? null) === $name);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function down(): void
    {
        Schema::table('supply_orders', function (Blueprint $table) {
            foreach ([
                'created_by', 'updated_by', 'branch_id', 'assigned_to',
                'status_id', 'payment_method', 'document_template_id',
            ] as $column) {
                if (Schema::hasColumn('supply_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // العملة تعود رقماً: يُسقَط النصّي ويُعاد اسم القديم
        if (Schema::hasColumn('supply_orders', 'currency_legacy')) {
            if (Schema::hasColumn('supply_orders', 'currency')) {
                Schema::table('supply_orders', fn (Blueprint $t) => $t->dropColumn('currency'));
            }

            Schema::table('supply_orders', fn (Blueprint $t) => $t->renameColumn('currency_legacy', 'currency'));
        }

        if (Schema::hasTable('statuses')) {
            DB::table('statuses')->where('section', 'supply-order')->delete();

            Schema::table('statuses', function (Blueprint $table) {
                foreach (['section', 'sort_order', 'is_default', 'is_deletable'] as $column) {
                    if (Schema::hasColumn('statuses', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

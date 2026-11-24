<?php

use App\Support\CurrencySettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الأعمدة التي تنقص جداولَ التصنيع كي تسري عليها «الإعدادات العامة».
 *
 * ── ما كان ───────────────────────────────────────────────────────────
 *
 * هجرة `2026_09_02_000005` أضافت `branch_id` إلى جدولين اثنين —
 * `manufactur_orders` و`production_materials` — وقالت صراحةً إن التطبيق
 * الفعلي مؤجَّل. وبقيت ثلاثةُ جداولَ في الوحدة بلا عمود فرعٍ إطلاقاً:
 *
 *   · `indirect_costs`   — تكاليفُ غير مباشرة تُحمَّل على أوامرَ مُفرَّعة،
 *                          وهي نفسُها بلا فرع.
 *   · `work_stations`    — محطّاتٌ في مصنعٍ بعينه، تُعرض في كل فرع.
 *   · `production_paths` — مساراتُ إنتاجٍ كذلك.
 *
 * ونقصان آخران في الجدولين الأوّلين:
 *
 *   · `work_stations.currency` — الجدول يحمل `total_cost` و`cost_wages`
 *     و`cost_origin`: ثلاثةَ مبالغ بلا عملة. هجرة
 *     `2026_08_19_200000_add_currency_to_stock_documents` أعطت العملةَ
 *     لبنود المحطّة (`work_stations_costs`) وأغفلت المحطّة نفسَها، فتُطبع
 *     تكلفتُها بالعملة الافتراضية الحالية مهما تبدّلت بعد إنشائها.
 *
 *   · `indirect_costs.code` — لا رقمَ مستندٍ للتكلفة غير المباشرة أصلاً:
 *     تُشار إليها في الشاشات بـ`id` الخام، فلا بادئة ولا طول ولا تسلسل من
 *     شاشة «الترقيم المتسلسل». والقسمُ يُعرَّف في `DocumentSections` تحت
 *     المفتاح `indirect-costs`.
 *
 * ── لماذا `nullable` في كل ما سبق ───────────────────────────────────
 *
 * نفسُ تعاقد [\App\Traits\BelongsToBranch] المتَّبع في الوحدة:
 * `branch_id = null` تعني «كل الفروع» لا «بلا فرع»، و`scopeForBranch`
 * تُرجع صفوفَ الفرع المطلوب **ومعها** الصفوفَ الفارغة. فالصفوف القائمة
 * تبقى مرئيّةً للجميع كما هي اليوم، ولا يفقد أحدٌ شيئاً بهذه الهجرة.
 *
 * و`nullOnDelete`: حذفُ فرعٍ لا يجوز أن يحذف محطّةَ عملٍ لها قيودُ إهلاك
 * ولا تكلفةً غير مباشرة رُحِّلت محاسبياً.
 *
 * و`indirect_costs.code` يُترك `null` للقائم — لا يُولَّد له رقمٌ بأثرٍ
 * رجعي: توليدُ الأرقام هنا يعني تسلسلاً مخترَعاً لا يقابل شيئاً في
 * الدفتر، والعمودُ الفارغ يُعرض بـ`id` كما كان.
 */
return new class extends Migration
{
    /** الجداول التي تنقصها نسبةُ الفرع. */
    private array $branchTables = ['indirect_costs', 'work_stations', 'production_paths'];

    public function up(): void
    {
        foreach ($this->branchTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('branch_id')->nullable()->after('id')
                    ->constrained('branches')->nullOnDelete();
            });
        }

        // عملة المحطّة — تُجمَّد معها كما تُجمَّد عملةُ أي مستند
        if (Schema::hasTable('work_stations') && ! Schema::hasColumn('work_stations', 'currency')) {
            Schema::table('work_stations', function (Blueprint $blueprint) {
                $blueprint->string('currency', 10)->nullable()->after('total_cost');
            });

            /*
                المحطّات القائمة تُملأ بالعملة الافتراضية الحالية.

                هذا هو ما كانت الشاشاتُ تطبعه عليها فعلاً قبل هذه الهجرة —
                `CurrencySettings::format()` تُنسّق بالافتراضية دائماً —
                فالملء تثبيتٌ للواقع لا تغييرٌ له، وبه تتوقّف قيمُها عن
                التبدّل مع كل تبديلٍ للعملة الافتراضية بعد اليوم.
            */
            $default = $this->defaultCurrencyCode();

            if ($default !== null) {
                DB::table('work_stations')->whereNull('currency')->update(['currency' => $default]);
            }
        }

        // رقم مستند التكلفة غير المباشرة — قسم `indirect-costs`
        if (Schema::hasTable('indirect_costs') && ! Schema::hasColumn('indirect_costs', 'code')) {
            Schema::table('indirect_costs', function (Blueprint $blueprint) {
                $blueprint->string('code')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->branchTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('branch_id');
            });
        }

        if (Schema::hasTable('work_stations') && Schema::hasColumn('work_stations', 'currency')) {
            Schema::table('work_stations', function (Blueprint $blueprint) {
                $blueprint->dropColumn('currency');
            });
        }

        if (Schema::hasTable('indirect_costs') && Schema::hasColumn('indirect_costs', 'code')) {
            Schema::table('indirect_costs', function (Blueprint $blueprint) {
                $blueprint->dropIndex(['code']);
                $blueprint->dropColumn('code');
            });
        }
    }

    /**
     * رمز العملة الافتراضية — أو `null` إن لم يُهيَّأ جدول العملات بعد.
     *
     * الترحيل قد يُشغَّل على قاعدةٍ لم تُزرع فيها العملات، فلا يجوز أن
     * تُسقط قراءةُ الإعداد الترحيلَ كلَّه.
     */
    private function defaultCurrencyCode(): ?string
    {
        try {
            return CurrencySettings::code();
        } catch (Throwable $e) {
            return null;
        }
    }
};

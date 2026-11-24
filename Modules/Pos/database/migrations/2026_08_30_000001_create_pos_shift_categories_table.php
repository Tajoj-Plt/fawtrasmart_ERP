<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيفاتُ الورديات — الجدولُ الذي لم يكن.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 * شاشةُ الوردية تعرض حقلاً اسمه «التصنيف الرئيسي»، وخلفه:
 *
 *     shifts_pos.parent_id  →  shifts_pos.id
 *
 * أي أنّ «التصنيف» **ورديةٌ كاملةٌ أخرى**: لها وقتُ بدءٍ ونهاية، وأيّامُ
 * دوام، ومهلةُ تأخّر، وموظّفٌ مسنَد، وجلساتٌ تُفتح عليها، ويفحصها
 * `windowCheck()` كأيّ وردية.
 *
 * فترتّب على ذلك ثلاثةُ عيوب:
 *
 *   • **لا سبيل لإنشاء تصنيف** إلّا بإنشاء ورديةٍ وهميّةٍ وتركِ أبيها
 *     فارغاً — ثم تظهر تلك الوهميّةُ في قائمة الورديات، وفي تقرير مبيعات
 *     الورديات، وفي قائمة الورديات المتاحة لفتح جلسة.
 *
 *   • **كلُّ ورديةٍ جذرٍ تصير تصنيفاً بالقوّة**: القائمةُ مبنيّةٌ على
 *     `whereNull('parent_id')`، فالوردية الصباحية الحقيقية تُعرض كخيارِ
 *     تصنيفٍ للوردية المسائية.
 *
 *   • ولا مكانَ **يُدار** منه التصنيف: لا قائمةَ ولا إضافةَ ولا تعديل.
 *
 * وشاهدُ ذلك في البيانات: أربعُ ورديات، **صفرٌ** منها يستعمل `parent_id`.
 * الحقلُ معروضٌ منذ أوّل يوم ولم يُستعمل قطّ.
 *
 * ─────────────────────── وما صار ───────────────────────
 *
 * التصنيفُ كِيانٌ مستقلّ: اسمٌ ولونٌ ووصفٌ وترتيبٌ وفرعٌ وحالة — **بلا**
 * مدًى زمنيٍّ ولا موظّفٍ ولا جلسات. لأنّه تسميةٌ لا دوام.
 *
 * ─────────────────────── و`parent_id` ───────────────────────
 *
 * يبقى العمودُ كما هو — لا يُحذف ولا يُفرَّغ. الهجرةُ تقرأ منه ولا تكتب
 * فيه: لكلِّ أبٍ مستعمَلٍ فعلاً يُنشأ تصنيفٌ بنفس الاسم وتُنقَل إليه
 * أبناؤُه. فمن كان عنده شجرةٌ لا يفقدها، ومن لم تكن عنده لا يتغيّر شيء.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_shift_categories')) {
            Schema::create('pos_shift_categories', function (Blueprint $table) {
                $table->id();

                $table->string('name', 190);
                $table->text('description')->nullable();

                /*
                    اللونُ ليس زينة: الوردياتُ تُقرأ في جداول ولوحاتٍ طويلة،
                    والتصنيفُ الملوَّن يُميَّز بالنظرة لا بقراءة الاسم.
                */
                $table->string('color', 9)->nullable();

                // ترتيبُ العرض — «صباحي» قبل «مسائي» لا أبجدياً
                $table->unsignedInteger('sort_order')->default(0);

                $table->boolean('is_active')->default(true);

                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index('branch_id');
                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasColumn('shifts_pos', 'category_id')) {
            Schema::table('shifts_pos', function (Blueprint $table) {
                if (! Schema::hasColumn('shifts_pos', 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable()->after('name');
                }
                if (! SchemaGuard::hasIndex('shifts_pos', 'shifts_pos_category_id_index')) {
                    $table->index('category_id');
                }
            });
        }

        $this->convertLegacyTree();
    }

    /**
     * شجرةُ `parent_id` القديمة ⇐ تصنيفاتٌ حقيقية.
     *
     * الأبُ يبقى ورديةً كما هو — لا يُحوَّل ولا يُحذف، فقد تكون له جلساتٌ
     * وتقارير. ويُنشأ **بجانبه** تصنيفٌ بنفس اسمه، ويُنقَل إليه أبناؤُه.
     */
    private function convertLegacyTree(): void
    {
        if (! Schema::hasColumn('shifts_pos', 'parent_id')) {
            return;
        }

        $parentIds = DB::table('shifts_pos')
            ->whereNotNull('parent_id')
            ->distinct()
            ->pluck('parent_id');

        foreach ($parentIds as $parentId) {
            $parent = DB::table('shifts_pos')->where('id', $parentId)->first();

            if (! $parent) {
                continue;
            }

            $categoryId = DB::table('pos_shift_categories')->insertGetId([
                'name' => $parent->name,
                'description' => 'حُوِّل من شجرة الورديات القديمة.',
                'branch_id' => $parent->branch_id ?? null,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('shifts_pos')
                ->where('parent_id', $parentId)
                ->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shifts_pos', 'category_id')) {
            Schema::table('shifts_pos', function (Blueprint $table) {
                $table->dropIndex(['category_id']);
                $table->dropColumn('category_id');
            });
        }

        Schema::dropIfExists('pos_shift_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خطط الإنتاج — الطبقةُ التي كانت ناقصةً فوق أوامر التصنيع.
 *
 * ── الفجوة ───────────────────────────────────────────────────────────
 *
 * للوحدة أوامرُ تصنيع وقوائمُ مواد ومحطّاتٌ ومسارات — ولا شيء يجمع
 * أمراً بأمر. فمن أراد إنتاجَ شهرٍ كامل أنشأ عشرين أمراً واحداً واحداً،
 * يكرّر في كلٍّ منها المسؤولَ والعميلَ والمستودعَ والفترة، ثم لا يجد
 * بعدها شاشةً تقول «هذه خطّةُ مارس، أُنجز منها كذا».
 *
 * وقسمُ الترقيم `production-plan` قائمٌ في [\App\Support\DocumentSections]
 * منذ البداية — بادئة `PP` وتسمية «خطة إنتاج» — بجدولٍ `null`: مستندٌ
 * يُضبط ترقيمُه في الشاشة ولا جدولَ خلفه. هذه الهجرة تسدّ ذلك.
 *
 * ── التصميم: رأسٌ وبنود ──────────────────────────────────────────────
 *
 * الرأسُ يحمل ما يتكرّر في كل أمرٍ سيُولَّد — المسؤول والعميل والحساب
 * والمستودع والفترة — والبندُ يحمل ما يختلف: المنتج وقائمةُ موادّه
 * ومسارُه وكميّتُه المخطّطة. فتوليدُ الأوامر نسخٌ مباشر: بندٌ ⇦ أمر.
 *
 * ولهذا كانت الأعمدةُ الإلزامية على `manufactur_orders` —
 * `production_material_id` و`production_path_id` — إلزاميةً على البند
 * أيضاً: بندٌ ينقصه أحدُهما لا يمكن توليدُ أمرِه أصلاً، فمنعُه عند
 * الحفظ أرحمُ من اكتشافه عند التوليد.
 *
 * ── `manufactur_order_id` على البند ──────────────────────────────────
 *
 * العمودُ المفتاح في هذا التصميم: يجعل التوليد **قابلاً للتكرار بلا
 * ازدواج**. البندُ الذي يحمل أمراً لا يُولَّد ثانيةً.
 *
 * و`nullOnDelete` لا `cascade`: حذفُ أمرِ تصنيعٍ يُفرِغ الخانة فيعود
 * البندُ قابلاً للتوليد — وهو المقصود. أمّا الحذفُ المتتالي فيمحو بندَ
 * الخطّة نفسه لأنّ أمراً وُلِّد عنه ثمّ حُذف.
 *
 * ── الفرع ────────────────────────────────────────────────────────────
 *
 * `branch_id` كما في جداول الوحدة كلّها (هجرتا `2026_09_02_000005`
 * و`2026_09_09_000001`) وسمةِ [\App\Traits\BelongsToBranch]: `nullable`
 * تعني «كل الفروع»، و`nullOnDelete` كي لا يحذف حذفُ الفرعِ خططَه.
 *
 * ── `hasTable` في الرأس ──────────────────────────────────────────────
 *
 * كما في هجرات الوحدة الأخرى: إعادةُ التشغيل على قاعدةٍ فيها الجدولان
 * لا تُسقط الترحيل. والمخطّطُ هنا مطابقٌ عموداً بعمود لما قد يكون
 * أُنشئ من قبل، فلا يختلف ما في القاعدة باختلاف أيّ الطريقين أنشأه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_plans')) {
            Schema::create('production_plans', function (Blueprint $table) {
                $table->id();

                $table->foreignId('branch_id')->nullable()
                    ->constrained('branches')->nullOnDelete();

                $table->string('name');

                /*
                    فريدٌ كما في `manufactur_orders.code`.

                    الرقمُ يُصدَر من `issueDocumentNumber` بقفلٍ داخل
                    معاملة، لكنّ القيدَ هنا هو الضمانةُ الأخيرة ضدّ أيّ
                    مسارٍ يكتب الرقمَ بيده — استيرادٍ أو تصحيحٍ يدويّ.
                */
                $table->string('code', 50)->unique();

                $table->date('from_date');
                $table->date('to_date');

                /*
                    `VARCHAR` لا `ENUM` — عن قصد.

                    الهجرة `2026_08_22_000005` تروي ما جرى حين كانت حالةُ
                    أمر التصنيع `ENUM`: كُتبت `'closed'` و`'in_progress'`
                    وهما خارج القائمة، فخُزِّنتا **سلسلةً فارغة** في MySQL
                    غير الصارم — ففقد الأمرُ حالته وسقط من كل مرشِّح.
                    والقائمةُ الحاكمة هنا [\App\Enums\ProductionPlanStatus]،
                    وإضافةُ حالةٍ إليها يجب ألّا تحتاج `ALTER` يُنسى.
                */
                $table->string('status', 20)->default('draft');

                /*
                    رأسُ الخطّة — ما يُنسخ إلى كل أمرٍ يُولَّد منها.

                    و`employee_id` **قابلٌ للفراغ رغم أنّ الشاشة تطلبه**:
                    `nullOnDelete` تعني أنّ حذفَ سجلّ المسؤول لا يحذف
                    خططَه — تُفرَّغ الخانةُ وتبقى الخطّة، ثم يرفض التوليدُ
                    حتى يُعيَّن مسؤولٌ جديد (العمودُ المقابل على
                    `manufactur_orders` إلزاميّ). والبديلُ `cascade` يمحو
                    خطط الشهر كلَّها لأنّ موظّفاً غادر.
                */
                $table->foreignId('employee_id')->nullable()
                    ->constrained('employees')->nullOnDelete();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('storehouse_id')->nullable();

                $table->text('notes')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                // سؤالا القائمة: «خطط هذا الفرع؟» و«الخطط النشطة؟»
                $table->index(['branch_id', 'status'], 'production_plans_branch_status_idx');

                // وسؤالُ مرشِّح الفترة
                $table->index(['from_date', 'to_date'], 'production_plans_period_idx');
            });
        }

        if (! Schema::hasTable('production_plan_items')) {
            Schema::create('production_plan_items', function (Blueprint $table) {
                $table->id();

                /*
                    `cascadeOnDelete` هنا وحده صحيح: البندُ لا معنى له بلا
                    خطّته. وحذفُ الخطّة نفسِه ممنوعٌ متى وُلِّد عنها أمر —
                    انظر `ProductionPlan::hasGeneratedOrders()`.
                */
                $table->foreignId('production_plan_id')
                    ->constrained('production_plans')->cascadeOnDelete();

                /*
                    و`cascadeOnDelete` على المراجع الثلاثة مقصودٌ لا مُغفَل:
                    البديلُ `restrictOnDelete` يمنع حذفَ قائمةِ موادٍ تشير
                    إليها مسوّدةُ خطّةٍ قديمة — و`BOMController::destroy`
                    يحذف بلا `try` فيخرج الرفضُ صفحةَ ٥٠٠ في شاشةٍ قائمة.
                    الثمنُ أنّ حذفَ قائمةِ موادٍ يُنقص بنداً من خطّةٍ؛ وهو
                    مقبولٌ لأنّ الحذفَ نفسَه يمحو بنودَ القائمة وقيودَها.
                */
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('production_material_id')
                    ->constrained('production_materials')->cascadeOnDelete();
                $table->foreignId('production_path_id')
                    ->constrained('production_paths')->cascadeOnDelete();

                /*
                    `DECIMAL(18,4)` كما ضُبطت كميّاتُ التصنيع كلُّها في
                    هجرة `2026_09_02_000001`. الكميّةُ الكسريّة أصلٌ في
                    التصنيع — ٢٫٥ كجم لا ٢.
                */
                $table->decimal('planned_quantity', 18, 4);

                // الأمرُ المولَّد عن هذا البند — `nullOnDelete`، انظر رأس الملف
                $table->foreignId('manufactur_order_id')->nullable()
                    ->constrained('manufactur_orders')->nullOnDelete();

                $table->string('notes', 500)->nullable();
                $table->timestamps();

                // «ما بنودُ هذه الخطّة التي لم تُولَّد بعد؟» — سؤالُ التوليد
                $table->index(['production_plan_id', 'manufactur_order_id'], 'production_plan_items_pending_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plan_items');
        Schema::dropIfExists('production_plans');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أوقات العمل — للفرع وللموظف بجدولٍ واحد.
 *
 * **لماذا جدولٌ واحد بعمود `scope`؟** لأن السؤال المطروح على الجدولين
 * واحدٌ حرفاً: «هل هذا الوقت داخل الدوام؟». وجدولان منفصلان يعنيان
 * دالّتَي تحقّقٍ تفترقان بعد أوّل تعديل — وهو بعينه ما وقع في فلتر الفرع
 * قبل توحيده في `AffiliationTree`.
 *
 * **الصفّ فترةٌ لا يوم.** يومٌ بفترتين (صباحية ومسائية) صفّان بنفس
 * `weekday`، والاستراحةُ بينهما هي الفجوة لا صفٌّ من نوعٍ خاص. فلا يحتاج
 * «تعدّد الفترات» ولا «وقت الاستراحة» بنيةً إضافية.
 *
 * **`is_closed` ليست تكراراً لغياب الصفّ.** غيابُ الصفوف ليوم يعني «لم
 * يُضبَط» — ويقرؤه النظام حسب إعداد `treat_unset_days_as_closed`. أمّا
 * `is_closed` فقرارٌ صريح: «الجمعة مغلقة»، لا يُخلَط بالإهمال.
 *
 * السلَّم عند التحقّق: أوقاتُ الموظف إن ضُبطت، وإلّا أوقاتُ فرعه، وإلّا
 * الإعدادُ العام.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_working_hours')) {
            return;
        }

        Schema::create('reservation_working_hours', function (Blueprint $table) {
            $table->bigIncrements('id');

            /* `branch` أو `employee` — انظر ترويسة الملف */
            $table->string('scope', 20)->default('branch');
            $table->unsignedBigInteger('scope_id');

            /* 0 = الأحد … 6 = السبت — يوافق `Carbon::dayOfWeek` بلا تحويل */
            $table->unsignedTinyInteger('weekday');

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_closed')->default(false);

            $table->timestamps();

            $table->index(['scope', 'scope_id', 'weekday'], 'reservation_working_hours_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_working_hours');
    }
};

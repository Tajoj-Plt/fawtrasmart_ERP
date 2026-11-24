<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهامّ مجدولة محميّة — لا تُعدَّل ولا تُوقَف ولا تُحذف ولا تظهر في الشاشة.
 *
 * **لماذا مستوىً أعلى من `is_system`؟** لأن `is_system` يمنع الحذف وحده
 * (`isDeletable()`)، ويترك المهمة قابلةً للإيقاف وتغيير جدولتها ومعاملاتها.
 * ومزامنة الاشتراك لا يجوز أن تكون كذلك: إيقافها يعني نظاماً يعمل بحدود
 * اشتراكٍ قديم لا يعرف أنه انتهى — وهو ما تمنعه هذه الحماية.
 *
 * والإخفاء ليس تجميلاً: مهمّة تظهر في الشاشة بزرّ «إيقاف» معطَّل تدعو إلى
 * المحاولة وتوليد الأسئلة. وما لا يُدار لا يُعرض.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cron_jobs', 'is_protected')) {
            Schema::table('cron_jobs', function (Blueprint $table) {
                if (! Schema::hasColumn('cron_jobs', 'is_protected')) {
                    $table->boolean('is_protected')->default(false)->after('is_system')
                        ->comment('محميّة: لا تُعدَّل ولا تُحذف ولا تظهر في شاشة المهام');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cron_jobs', 'is_protected')) {
            Schema::table('cron_jobs', function (Blueprint $table) {
                $table->dropColumn('is_protected');
            });
        }
    }
};

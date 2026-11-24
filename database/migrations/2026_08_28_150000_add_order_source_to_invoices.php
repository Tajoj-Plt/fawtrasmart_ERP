<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مصدر الطلب على فاتورة البيع.
 *
 * شاشةُ «مصادر الطلب» تُنشئ المصادر وتضبط مصدراً افتراضياً ومفتاحاً اسمه
 * `order_source_mandatory` يقول «إلزامي»… ولم يكن للفاتورة حقلٌ يحمل
 * المصدر أصلاً، فلا الافتراضيّ يُطبَّق ولا الإلزام يُفحَص: مفتاحٌ يُحفَظ
 * ولا يمنع شيئاً.
 *
 * العمود يحمل المصدر، و`InvoicesController::store()` يردّ الفاتورة متى
 * كان الإلزام مفعَّلاً ولم يُختَر مصدر.
 *
 * `nullOnDelete`: حذفُ مصدرٍ لا يُسقط فواتيرَه — تبقى بلا إشارةٍ إلى
 * مصدرٍ لم يعد موجوداً، ولا يُمنَع المحاسب من قراءة فاتورةٍ رُحِّل قيدُها.
 *
 * @see \App\Models\OrderSource
 * @see \Modules\Sales\Http\Controllers\OrderSourceController
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'order_source_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            /* `integer` موجَبٌ وسالب لا `unsignedInteger` ولا `foreignId`:
               `order_sources.id` يخرج من `$table->integer('id', true)`
               فيصير `int(11)` **بإشارة**، والمفتاح الأجنبي يشترط تطابق
               النوع والإشارة معاً — وإلّا رُدّ بـerrno 150 «constraint is
               incorrectly formed»، وهي رسالةٌ لا تسمّي سببَها. */
            if (! Schema::hasColumn('invoices', 'order_source_id')) {
                $table->integer('order_source_id')
                    ->nullable()
                    ->after('employee_id')
                    ->comment('مصدر الطلب — من شاشة «مصادر الطلب»');
            }

            if (! SchemaGuard::hasForeignKey('invoices', 'invoices_order_source_id_foreign')) {
                $table->foreign('order_source_id')
                    ->references('id')
                    ->on('order_sources')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'order_source_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['order_source_id']);
            $table->dropColumn('order_source_id');
        });
    }
};

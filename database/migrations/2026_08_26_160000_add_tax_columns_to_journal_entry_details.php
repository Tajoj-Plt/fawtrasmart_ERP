<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدةُ الضريبة في سطور القيد.
 *
 * **ما كان**
 *
 * شاشةُ تعديل القيد تقرأ `$detail->tax_type` و`$detail->tax_rate` من سطر
 * القيد — **وليس للجدول عمودٌ بهذين الاسمين**. فالقراءة تعود `null` دائماً،
 * والاختيارُ الضريبيُّ يضيع عند أوّل تعديل.
 *
 * وأسوأُ منه: سطرُ الضريبة الذي وُلِّد عند الحفظ يظهر في شاشة التعديل سطراً
 * عادياً. فمن عدّل قيداً ضريبياً وحفظه خرج بسطرَي ضريبة: القديمُ المعروض
 * والجديدُ المولَّد — والقيدُ يتضخّم بمقدار الضريبة في كلّ تعديل.
 *
 * **ما صار**
 *
 *   • `tax_id` و`tax_type` يحفظان الاختيارَ على السطر نفسه، فيعود كما تُرك.
 *   • `is_tax_line` يميّز السطرَ المولَّد، فتُخفيه شاشةُ التعديل ويُعاد
 *     توليده من الاختيار — سطرٌ واحد لا اثنان.
 *
 * والسطورُ القديمة تُوسَم بوصفها: كلُّ ما بدأ بـ«ضريبة القيمة المضافة» سطرُ
 * ضريبةٍ مولَّد، وهو الوصفُ الذي كتبه المولِّدُ نفسُه ولم يكتبه سواه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_details', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entry_details', 'tax_id')) {
                $table->unsignedBigInteger('tax_id')->nullable()->after('cost_center_id');
            }

            if (! Schema::hasColumn('journal_entry_details', 'tax_type')) {
                $table->string('tax_type', 10)->nullable()->after('tax_id');
            }

            if (! Schema::hasColumn('journal_entry_details', 'is_tax_line')) {
                $table->boolean('is_tax_line')->default(false)->after('tax_type')->index();
            }
        });

        // وسمُ سطور الضريبة القديمة بوصفها — لا مصدر سواه
        DB::table('journal_entry_details')
            ->where('description', 'like', 'ضريبة القيمة المضافة%')
            ->update(['is_tax_line' => true]);
    }

    public function down(): void
    {
        Schema::table('journal_entry_details', function (Blueprint $table) {
            foreach (['is_tax_line', 'tax_type', 'tax_id'] as $column) {
                if (Schema::hasColumn('journal_entry_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

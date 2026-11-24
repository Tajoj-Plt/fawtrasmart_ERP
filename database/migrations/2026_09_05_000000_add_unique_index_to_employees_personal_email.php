<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
    فهرسٌ فريدٌ على البريد الإلكتروني الشخصي.

    العمودُ الشقيق `email` فريدٌ منذ إنشاء الجدول، أمّا `personal_email`
    فبقي بلا فهرسٍ وبلا قاعدة تحقّق (كانت معطَّلةً بتعليق في
    `EmployeeRequest`) — فيُقبل مكرَّراً بين موظفين بلا اعتراض.

    والتحقّقُ وحده لا يكفي حارساً: الاستيراد والبذور والكتابةُ المباشرة
    لا تمرّ به. فالفهرسُ هو الضمانة الأخيرة.

    **الفراغُ لا يُعامَل قيمةً**: MySQL وSQLite كلاهما يعدّ `NULL` مغايراً
    لـ`NULL` في الفهارس الفريدة، فيبقى الموظفون بلا بريدٍ شخصيّ على
    حالهم مهما كثروا. أمّا السلسلةُ الفارغة `''` فقيمةٌ تتصادم مع نظيرتها،
    ولذلك تُحوَّل إلى `NULL` قبل الفهرسة — وهو ما يفعله
    `ConvertEmptyStringsToNull` بالطلبات الجديدة أصلاً، فالهجرةُ تُلحق
    الصفوفَ القديمة بالقاعدة نفسها.

    ═════════════════ والتكرارُ القائم: لماذا لا تتوقّف الهجرة ═════════════════

    كانت الهجرةُ ترمي `RuntimeException` تسمّي العناوين المكرَّرة وتطالب
    بتصحيحها يدوياً، بحجّة أنّ اختيار «النسخة الصحيحة» قرارُ صاحب البيانات.

    والحجّةُ صحيحة، والنتيجةُ خاطئة: الهجرةُ **تُوقف طابور الهجرات كلَّه**.
    فما بعدها لا يُطبَّق، ويبقى النظامُ محجوزاً خلف شاشة «تحديث النظام»
    (`CheckSystemUpdates`) — أي أنّ عقوبةَ بريدٍ مكرَّرٍ بين ثلاثة موظفين
    هي **تعطيلُ المنظومة كلِّها** حتى يُفتح `phpMyAdmin`. والمنشأةُ التي
    تصادف هذا في تحديثٍ ليليّ تصحو على نظامٍ لا يفتح.

    ═════════════════ القاعدة التي حلّت محلَّها ═════════════════

    **الأقدمُ يحتفظ، ومن بعده يُفرَّغ — ولا يضيع شيء.**

      • الأقدم (أصغرُ `id`) هو صاحبُ العنوان: هو من سُجِّل به أوّلاً.
      • من بعده يُفرَّغ عمودُه، **وتُكتب القيمةُ في `notes` الخاصّة به**
        بتاريخها وسببها واسم من شاركه العنوان.

    فالبيانُ محفوظٌ في بطاقة الموظف نفسِه — حيث يراه من يفتحها ليصحّح،
    لا في لوغٍ لا يُقرأ. والقرارُ يبقى لصاحب البيانات: هو يعيد كتابة
    العنوان الصحيح متى شاء، والفهرسُ قائمٌ يمنع تكراراً جديداً.

    وسطرٌ في اللوغ كذلك — لمن يراجع أثرَ الترقية آلياً.
*/
return new class extends Migration
{
    private const INDEX = 'employees_personal_email_unique';

    public function up(): void
    {
        if (Schema::hasIndex('employees', self::INDEX)) {
            return;
        }

        // الفراغُ إلى عدم — وإلّا تصادمت الصفوفُ الفارغةُ فيما بينها
        DB::table('employees')->where('personal_email', '')->update(['personal_email' => null]);

        $this->releaseDuplicates();

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('personal_email', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('employees', self::INDEX)) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }

    /**
     * تفريغُ المكرَّر عن كلِّ من ليس أقدمَ صاحبٍ للعنوان — بحفظ القيمة.
     *
     * والصفوفُ المحذوفةُ ناعماً داخلةٌ في الحساب: `deleted_at` لا يرفع
     * الصفَّ من الجدول، والفهرسُ الفريد يراه.
     */
    private function releaseDuplicates(): void
    {
        $duplicated = DB::table('employees')
            ->select('personal_email')
            ->whereNotNull('personal_email')
            ->groupBy('personal_email')
            ->havingRaw('count(*) > 1')
            ->pluck('personal_email');

        if ($duplicated->isEmpty()) {
            return;
        }

        $hasNotes = Schema::hasColumn('employees', 'notes');
        $today = now()->toDateString();
        $released = [];

        foreach ($duplicated as $email) {
            $rows = DB::table('employees')
                ->where('personal_email', $email)
                ->orderBy('id')
                ->get(['id', 'notes']);

            $keeper = $rows->shift();

            foreach ($rows as $row) {
                $update = ['personal_email' => null];

                if ($hasNotes) {
                    $note = sprintf(
                        'بريدٌ شخصيٌّ مكرَّر أُفرِغ في %s عند إضافة الفهرس الفريد: %s '
                        .'— كان مشتركاً مع الموظف رقم %d. أعِد إدخال العنوان الصحيح.',
                        $today,
                        $email,
                        $keeper->id
                    );

                    $update['notes'] = trim(($row->notes ? $row->notes."\n" : '').$note);
                }

                DB::table('employees')->where('id', $row->id)->update($update);

                $released[] = $row->id;
            }

            Log::warning('تفريغُ بريدٍ شخصيٍّ مكرَّر عند إضافة الفهرس الفريد', [
                'personal_email' => $email,
                'kept_employee_id' => $keeper->id,
                'released_employee_ids' => $rows->pluck('id')->all(),
            ]);
        }

        if ($released) {
            echo PHP_EOL
                .'  تنبيه: أُفرِغ البريدُ الشخصيُّ المكرَّر عن '.count($released).' موظف '
                .'(أرقام: '.implode('، ', $released).'). '
                .'القيمةُ الأصلية محفوظةٌ في ملاحظات كلٍّ منهم.'
                .PHP_EOL;
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * حالة العميل التشغيلية صارت ثنائيةً بقرار: نشط أو موقوف، لا ثالث لهما.
     * قبل هذه الهجرة كان الجدول حرّاً بالكامل بلا `kpi_role` مضبوطاً على
     * أيّ صفّ في أغلب المنشآت — فكانت `Statuses::activeClientStatusIds()`
     * تسقط على احتياطها المكتوب `[1]`، وهو معرِّفٌ قد لا يطابق أيّ صفّ فعلي
     * (مثلما لم يطابق هنا: الصفّ الوحيد الموجود معرّفه غير `1`)، فمؤشّرات
     * «العملاء النشطون» تُحسَب صفراً بصمت.
     *
     * هذه الهجرة **لا تحذف ولا تدمج** حالاتٍ حرّة موجودة أصلاً (قد تكون
     * منشأةٌ ما استعملت أسماء مختلفة لسنوات) — تكتفي بالتأكّد من وجود صفٍّ
     * واحدٍ بـ`kpi_role = active` وآخر بـ`kpi_role = stopped`، وتحمي كليهما
     * من الحذف. توحيدُ الحالات الحرّة الأخرى (إن وُجدت) قرارٌ تشغيليٌّ يدويٌّ
     * من شاشة «حالات العملاء» لا هجرة تلقائية تُعيد تصنيف بيانات عميل بصمت.
     */
    public function up(): void
    {
        $this->ensureRole('active', 'نشط', '#28c76f', true, 0);
        $this->ensureRole('stopped', 'موقوف', '#ea5455', false, 1);
    }

    /**
     * صفٌّ واحدٌ بهذا الدور موجودٌ مسبقاً؟ لا شيء يُفعل. وإلا: صفٌّ بالاسم
     * القياسي موجودٌ بلا دور؟ يُضبط دورُه. وإلا: يُنشأ صفٌّ جديد.
     */
    private function ensureRole(string $role, string $name, string $color, bool $isDefault, int $sortOrder): void
    {
        if (DB::table('statuses')->whereNull('section')->where('kpi_role', $role)->exists()) {
            return;
        }

        $existing = DB::table('statuses')->whereNull('section')->where('name', $name)->first();

        if ($existing) {
            DB::table('statuses')->where('id', $existing->id)->update([
                'kpi_role' => $role,
                'is_deletable' => false,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('statuses')->insertOrIgnore([
            'section' => null,
            'name' => $name,
            'color' => $color,
            'state' => $isDefault ? 1 : 0,
            'kpi_role' => $role,
            'is_default' => $isDefault,
            'is_deletable' => false,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * عكسٌ جزئي فقط: يُطفئ الحماية والدور عن الصفّين القياسيين بالاسم، ولا
     * يحذف أيّ صفّ — حذفُ حالةٍ قد تكون رُبطت بعملاء فعليين بعد هذه الهجرة
     * أخطر من ترك صفٍّ زائدٍ بلا دور.
     */
    public function down(): void
    {
        DB::table('statuses')->whereNull('section')
            ->whereIn('name', ['نشط', 'موقوف'])
            ->update(['kpi_role' => null, 'is_deletable' => true]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يملأ «تابع لـ» (`branch_id`) للمستندات التي أُنشئت بلا عقدة.
 *
 * ── العلّة ────────────────────────────────────────────────────────────
 *
 * `Scopes::resolveBranchId()` كانت تقرأ `employees.branch_id` وتهمل
 * `users.branch_id` — والثاني هو ما يكتبه مبدّلُ الفروع في الهيدر. وعمودُ
 * الموظّف فارغٌ لأربعةٍ من مستخدمي هذه المنشأة، فكلُّ مستندٍ أنشأوه خرج
 * بـ`branch_id = null`: **الخمس والستّون فاتورةَ شراء كلُّها**، وسبعةُ
 * موردين، ومئةٌ واثنتان وخمسون حركةَ دفع.
 *
 * و`BelongsToBranch::scopeForBranch()` تُبقي ما لا عقدةَ له ظاهراً تحت
 * **كلّ** عقدة (`orWhereNull`) — وهو تسامحٌ قُصد به المستنداتُ التي سبقت
 * إضافةَ العمود. فكانت النتيجةُ أن شاشات المشتريات لا تتغيّر إطلاقاً مهما
 * بدّلت الفرع من الهيدر: كلُّ مستنداتها بلا عقدة، فكلُّها تظهر دائماً.
 *
 * ── ما تفعله ─────────────────────────────────────────────────────────
 *
 * تنسب كلَّ مستندٍ بلا عقدة إلى العقدة النشطة لمنشئه (`users.branch_id`) —
 * وهي المعلومةُ نفسُها التي كان يجب أن تُختم لحظةَ الإنشاء، وقد صارت
 * تُختم بعد إصلاح `resolveBranchId()`.
 *
 * ولا تُخمّن: ما لا منشئَ له، أو منشئُه بلا عقدة، يبقى فارغاً — عقدةٌ
 * مخترعةٌ أسوأُ من غيابها.
 *
 * ── لماذا لا تُلمس المصروفات والسندات ────────────────────────────────
 *
 * `expenses` (٢٠٩) و`receipts` (٦١٣) فيهما صفوفٌ بلا عقدة كذلك، وهي تخصّ
 * وحدة الحسابات لا شاشات المبيعات والمشتريات. تُترك على حالها ويبقى
 * `orWhereNull` يُظهرها كما كان — فلا تختفي من شاشةٍ بسبب إصلاحٍ في وحدةٍ
 * أخرى.
 */
return new class extends Migration
{
    /** الجدول ← العمود الذي يحمل منشئه (مفتاحاً إلى `users.id`) */
    private const SOURCES = [
        'purchase_invoices' => 'created_by',
        'purchase_orders' => 'created_by',
        'purchase_quotations' => 'created_by',
        'suppliers' => 'created_by',
        // `payments_process.employee_id` مفتاحٌ إلى `users.id` لا إلى
        // `employees.id` — على خلاف ما يوحي به اسمُه
        'payments_process' => 'employee_id',
    ];

    public function up(): void
    {
        // العقدةُ النشطة لكل مستخدم، مرّةً واحدة
        $userNodes = DB::table('users')
            ->whereNotNull('branch_id')
            ->pluck('branch_id', 'id');

        if ($userNodes->isEmpty()) {
            return;
        }

        foreach (self::SOURCES as $table => $creatorColumn) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'branch_id')
                || ! Schema::hasColumn($table, $creatorColumn)) {
                continue;
            }

            /* تحديثٌ واحد لكل عقدة لا لكل صفّ: عدد العقد بالعشرات وعدد
               الصفوف بالآلاف.

               و`preserveKeys` ضروريّ: `groupBy` تُسقط مفاتيحَ المجموعة
               افتراضياً فتعود `keys()` أرقاماً ترتيبية (0,1,2…) لا
               معرّفاتِ مستخدمين — فتُنسب المستنداتُ إلى عُقدٍ ليست عُقدَ
               منشئيها. */
            foreach ($userNodes->groupBy(fn ($nodeId) => (int) $nodeId, true) as $nodeId => $users) {
                DB::table($table)
                    ->whereNull('branch_id')
                    ->whereIn($creatorColumn, $users->keys()->all())
                    ->update(['branch_id' => (int) $nodeId]);
            }
        }
    }

    public function down(): void
    {
        /* لا رجعة: القيمةُ السابقة كانت `null` — أي «لا يُعرف»، لا قيمةً
           ذاتَ معنى تُستعاد. وإرجاعُ الجميع إلى `null` يمحو معه ما خُتم
           صحيحاً بعد الإصلاح. */
    }
};

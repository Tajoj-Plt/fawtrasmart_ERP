<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الملاحظات بمستنداتها.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * addNote يُنشئ الملاحظة والسطر الذي يربطها بالأمر **معلَّق**:
 *
 *     ClientRelation::create([
 *         'process' => $request->process,
 *         'time' => $request->time,
 *         'date' => $request->date,
 *         // 'quotation_id' => $id,          ← الربط، معطّل
 *         'type' => 'manufacturing_order',
 *     ]);
 *
 * فلا شيء يربط الملاحظة بأمرها. ونتيجته المباشرة في getNotes:
 *
 *     public function getNotes($id)          ← $id مُستقبَل ومُهمَل
 *     {
 *         $notes = ClientRelation::where('type', 'manufacturing_order')
 *             ->with('employee')->orderBy('created_at', 'desc')->get();
 *
 * ⇒ كل أمر تصنيع يعرض ملاحظات **كل** أوامر التصنيع في النظام،
 *   عبر كل الفروع. ولا سبيل للفلترة حتى لو أردنا.
 *
 * ── لماذا reference_id لا quotation_id ───────────────────────────────
 *
 * `client_relations` جدول مشترك بين أنواع مستندات متعددة (عروض أسعار،
 * فواتير، أوامر تصنيع…). تسمية العمود `quotation_id` كانت مقبولة حين
 * كان الجدول لعروض الأسعار وحدها؛ استعمالها لأمر تصنيع اسمٌ مضلّل
 * يؤذي من يقرأ الكود بعدك.
 *
 * ⚠️ إن كان `quotation_id` موجوداً ومستعملاً فعلاً لعروض الأسعار،
 *    فهذه الهجرة **تضيف** عموداً ولا تلمسه. والملاحظات القديمة تبقى
 *    بلا `reference_id` — انظر قسم النقل أدناه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_relations', function (Blueprint $table) {
            if (! Schema::hasColumn('client_relations', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()
                    ->comment('معرّف المستند الذي تخصّه الملاحظة، مع type');
            }
        });

        Schema::table('client_relations', function (Blueprint $table) {
            try {
                if (! SchemaGuard::hasIndex('client_relations', 'cr_type_reference_idx')) {
                    $table->index(['type', 'reference_id'], 'cr_type_reference_idx');
                }
            } catch (\Throwable $e) {
            }
        });

        // ── نقل البيانات القديمة ──────────────────────────────────────
        //
        // إن كان `quotation_id` كان يُستعمل لأمر التصنيع في وقت ما،
        // انقله. الشرط على `type` يحمي ملاحظات عروض الأسعار.
        //
        if (Schema::hasColumn('client_relations', 'quotation_id')) {
            \DB::table('client_relations')
                ->where('type', 'manufacturing_order')
                ->whereNull('reference_id')
                ->whereNotNull('quotation_id')
                ->update(['reference_id' => \DB::raw('quotation_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('client_relations', function (Blueprint $table) {
            try {
                $table->dropIndex('cr_type_reference_idx');
            } catch (\Throwable $e) {
            }
            $table->dropColumn('reference_id');
        });
    }
};

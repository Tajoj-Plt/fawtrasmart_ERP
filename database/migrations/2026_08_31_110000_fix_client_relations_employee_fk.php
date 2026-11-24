<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `client_relations.client_employee_id` كان مربوطاً بمفتاح خارجي إلى
 * `client_employee` — جدولٌ فارغ تماماً (٠ صفوف) لا صلة له بالبيانات
 * الحقيقية. موظفو العملاء الفعليون في `client_employee_notes`، والتحقّق
 * في `ClientController::addnotes()` يتحقّق أصلاً من وجود المعرّف في
 * `client_employee_notes` — فيمرّ التحقّق ثم يفشل الحفظ نفسه بخطإ قيد
 * مرجعي. من كان يملأ حقل «موظف العميل» الإلزامي في شاشة «إضافة ملاحظة»
 * لم تُحفظ ملاحظته قط (لا صفّ واحد في القاعدة بهذا العمود غير فارغ).
 *
 * التصحيح: ربط العمود بالجدول الصحيح.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite لا يدعم إسقاط قيدٍ مرجعيٍّ بالاسم؛ وقاعدةُ الاختبارات تُبنى من
        // هجرات الإنشاء مباشرةً فلا قيدَ قديماً يُعاد توجيهه.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('client_relations', function (Blueprint $table) {
            $table->dropForeign('client_relations_client_employee_id_foreign');
        });

        Schema::table('client_relations', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('client_relations', 'client_relations_client_employee_id_foreign')) {
                $table->foreign('client_employee_id', 'client_relations_client_employee_id_foreign')
                    ->references('id')->on('client_employee_notes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // SQLite لا يدعم إسقاط قيدٍ مرجعيٍّ بالاسم؛ وقاعدةُ الاختبارات تُبنى من
        // هجرات الإنشاء مباشرةً فلا قيدَ قديماً يُعاد توجيهه.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('client_relations', function (Blueprint $table) {
            $table->dropForeign('client_relations_client_employee_id_foreign');
        });

        Schema::table('client_relations', function (Blueprint $table) {
            $table->foreign('client_employee_id', 'client_relations_client_employee_id_foreign')
                ->references('id')->on('client_employee')
                ->nullOnDelete();
        });
    }
};

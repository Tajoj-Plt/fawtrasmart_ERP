<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ختمُ آخر تذكيرٍ بانتهاء العقد.
 *
 * `CheckContractExpirations` كانت تطابق **اليومَ الموافق تماماً**:
 *
 *     whereDate('end_date', now()->addDays($noticeDays))
 *
 * فلا تكرارَ فيها — ولا ضمانَ كذلك: إن فات `schedule:run` يوماً واحداً
 * سقط التنبيهُ عن ذلك العقد ولم يُرسَل أبداً، لأن اليومَ الموافق مضى.
 *
 * وتصحيحُ ذلك يقتضي مدًى لا يوماً؛ والمدى يقتضي كبحاً وإلّا نبّه ثلاثين
 * مرّة. فهذا العمود هو الكابح — بنظير ما في `employee_documents`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contracts', 'last_alert_sent_at')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'last_alert_sent_at')) {
                $table->timestamp('last_alert_sent_at')->nullable()->after('attachments');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contracts', 'last_alert_sent_at')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('last_alert_sent_at');
        });
    }
};

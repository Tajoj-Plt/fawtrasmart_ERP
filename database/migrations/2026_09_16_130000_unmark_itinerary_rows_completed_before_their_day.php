<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صفوفُ خطّ سيرٍ «مكتملة» قبل يومها — لم يزرها أحد.
 *
 * الكودُ القديم على السيرفر كان يُكمل كلَّ صفوف المندوب مع العميل دفعةً واحدة
 * عند أيّ ملاحظة (بلا تقييدٍ بالتاريخ)، فملاحظةُ الأربعاء تُكمل صفَّ الأربعاء
 * القادم مسبقاً. الكودُ الحاليّ (`markCompletedFor`) مقيَّدٌ باليوم ولا يُكمل
 * صفّاً قبل يومه، فأيُّ صفٍّ `DATE(completed_at) < visit_date` أثرٌ لذلك الخطأ.
 *
 * يُعاد «مخطَّطاً» ما لم يبدأ وليس للعميل في يومه دليلُ حضور (زيارة فعلية،
 * ملاحظة، فاتورة) — وما له دليلٌ يُترك. القيمُ السابقة تُحفظ في جدولٍ احتياطيّ
 * و`down()` يُرجعها.
 */
return new class extends Migration
{
    private const BACKUP = 'ecv_premature_completion_backup';

    public function up(): void
    {
        if (! Schema::hasTable('employee_client_visits') || ! Schema::hasColumn('employee_client_visits', 'visit_status')) {
            return;
        }

        if (! Schema::hasTable(self::BACKUP)) {
            Schema::create(self::BACKUP, function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('status')->nullable();
                $table->string('visit_status')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->integer('actual_duration_minutes')->nullable();
                $table->timestamp('repaired_at')->nullable();
            });
        }

        $ids = DB::table('employee_client_visits as ecv')
            ->where('ecv.visit_status', 'completed')
            ->whereNull('ecv.started_at')
            ->whereNotNull('ecv.completed_at')
            ->whereNotNull('ecv.visit_date')
            ->whereRaw('DATE(ecv.completed_at) < DATE(ecv.visit_date)')
            ->whereNotExists(fn ($q) => $q->from('visits as v')
                ->whereColumn('v.client_id', 'ecv.client_id')
                ->whereRaw('DATE(v.visit_date) = DATE(ecv.visit_date)')
                ->where(fn ($w) => $w->whereNull('v.stage')->orWhere('v.stage', '!=', 'cancelled'))
                ->where(fn ($w) => $w->whereNotNull('v.started_at')
                    ->orWhere(fn ($m) => $m->where('v.recording_method', 'manual')
                        ->where(fn ($s) => $s->whereNull('v.stage')->orWhere('v.stage', 'completed')))))
            ->whereNotExists(fn ($q) => $q->from('client_relations as n')
                ->whereColumn('n.client_id', 'ecv.client_id')
                ->whereRaw('DATE(n.created_at) = DATE(ecv.visit_date)')
                ->where(fn ($w) => $w->whereNull('n.type')->orWhere('n.type', '!=', 'invoice'))
                ->where(fn ($w) => $w->whereNull('n.process')->orWhere('n.process', 'not like', 'إشعار%')))
            ->whereNotExists(fn ($q) => $q->from('invoices as i')
                ->whereColumn('i.client_id', 'ecv.client_id')
                ->whereRaw('DATE(i.invoice_date) = DATE(ecv.visit_date)')
                ->whereNull('i.voided_at')
                ->where(fn ($w) => $w->whereNull('i.type')->orWhere('i.type', '!=', 'returned')))
            ->pluck('ecv.id')
            ->all();

        foreach (array_chunk($ids, 500) as $chunk) {
            DB::transaction(function () use ($chunk) {
                $now = now();
                $rows = DB::table('employee_client_visits')->whereIn('id', $chunk)
                    ->get(['id', 'status', 'visit_status', 'completed_at', 'actual_duration_minutes']);

                DB::table(self::BACKUP)->insertOrIgnore($rows->map(fn ($r) => [
                    'id' => $r->id,
                    'status' => $r->status,
                    'visit_status' => $r->visit_status,
                    'completed_at' => $r->completed_at,
                    'actual_duration_minutes' => $r->actual_duration_minutes,
                    'repaired_at' => $now,
                ])->all());

                DB::table('employee_client_visits')->whereIn('id', $chunk)->update([
                    'status' => 'unactive',
                    'visit_status' => 'planned',
                    'completed_at' => null,
                    'actual_duration_minutes' => null,
                ]);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::BACKUP)) {
            return;
        }

        foreach (DB::table(self::BACKUP)->get() as $row) {
            DB::table('employee_client_visits')->where('id', $row->id)->update([
                'status' => $row->status,
                'visit_status' => $row->visit_status,
                'completed_at' => $row->completed_at,
                'actual_duration_minutes' => $row->actual_duration_minutes,
            ]);
        }

        Schema::drop(self::BACKUP);
    }
};

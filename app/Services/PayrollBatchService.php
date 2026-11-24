<?php

namespace App\Services;

use App\Models\PayrollBatch;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * اعتماد صرفات جملةً (§15 TODO 2.8): تجميع payroll_runs واعتمادها دفعة
 * واحدة. الصرف غير المجمّع لا يتغيّر.
 */
class PayrollBatchService
{
    /**
     * @param  array<int>  $runIds
     */
    public function create(
        array $runIds,
        ?string $name = null,
        ?int $payrollId = null,
        ?int $createdBy = null,
        ?string $notes = null
    ): PayrollBatch {
        $runIds = array_values(array_unique(array_map('intval', $runIds)));
        if ($runIds === []) {
            throw new \RuntimeException('اختر صرفات معلّقة لتجميعها.');
        }

        return DB::transaction(function () use ($runIds, $name, $payrollId, $createdBy, $notes) {
            $runs = PayrollRun::query()->lockForUpdate()->whereIn('id', $runIds)->get();
            if ($runs->count() !== count($runIds)) {
                throw new \RuntimeException('بعض الصرفات غير موجودة.');
            }

            foreach ($runs as $run) {
                if (! $run->isPending()) {
                    throw new \RuntimeException('لا تُجمع إلا صرفات معلّقة. الصرفة #'.$run->id.' ليست معلّقة.');
                }
                if ($run->payroll_batch_id) {
                    throw new \RuntimeException('الصرفة #'.$run->id.' ضمن مسير صرف آخر.');
                }
            }

            $periodFrom = $runs->min('period_from');
            $periodTo = $runs->max('period_to');

            $batch = PayrollBatch::create([
                'name' => $name ?: 'مسير صرف '.$runs->count().' عملية',
                'payroll_id' => $payrollId,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'status' => PayrollBatch::STATUS_PENDING,
                'created_by' => $createdBy ?: Auth::id(),
                'notes' => $notes,
            ]);

            PayrollRun::query()->whereIn('id', $runIds)->update(['payroll_batch_id' => $batch->id]);

            return $batch->fresh(['runs']);
        });
    }

    public function approve(PayrollBatch $batch, ?int $approvedBy = null): PayrollBatch
    {
        return DB::transaction(function () use ($batch, $approvedBy) {
            $batch = PayrollBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! $batch->isPending()) {
                throw new \RuntimeException('هذا المسير معتمد مسبقاً.');
            }
            if ($batch->runs()->count() === 0) {
                throw new \RuntimeException('لا صرفات في هذا المسير.');
            }

            $batch->update([
                'status' => PayrollBatch::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $approvedBy ?: Auth::id(),
            ]);

            return $batch->fresh(['runs']);
        });
    }

    /**
     * تجميع ثم اعتماد فوري — اعتماد واحد لمسير صرفات.
     *
     * @param  array<int>  $runIds
     */
    public function createAndApprove(
        array $runIds,
        ?string $name = null,
        ?int $payrollId = null,
        ?int $userId = null,
        ?string $notes = null
    ): PayrollBatch {
        $batch = $this->create($runIds, $name, $payrollId, $userId, $notes);

        return $this->approve($batch, $userId);
    }
}

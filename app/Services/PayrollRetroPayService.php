<?php

namespace App\Services;

use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use Illuminate\Support\Facades\DB;

/**
 * فروقات زيادة بأثر رجعي (§15 TODO 2.7): بند على صرفة معلّقة
 * يُدرج في الصافي عند الصرف، ويبقى بعد عكس الصرف لإعادة الترحيل.
 */
class PayrollRetroPayService
{
    public function add(
        PayrollRun $run,
        string $name,
        float $amount,
        string $reason,
        ?string $periodFrom = null,
        ?string $periodTo = null
    ): PayrollRunItem {
        $name = trim($name);
        $reason = trim($reason);

        if ($name === '') {
            throw new \RuntimeException('اسم البند مطلوب.');
        }
        if ($reason === '') {
            throw new \RuntimeException('سبب التسوية الرجعية مطلوب.');
        }
        if ($amount <= 0.009) {
            throw new \RuntimeException('مبلغ الفروقات يجب أن يكون أكبر من صفر.');
        }

        $periodFrom = $periodFrom ? trim($periodFrom) : null;
        $periodTo = $periodTo ? trim($periodTo) : null;
        if ($periodFrom && $periodTo && $periodFrom > $periodTo) {
            throw new \RuntimeException('فترة الأثر الرجعي غير صحيحة.');
        }

        return DB::transaction(function () use ($run, $name, $amount, $reason, $periodFrom, $periodTo) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! $run->canReceiveRetro()) {
                throw new \RuntimeException('لا تُضاف تسوية رجعية إلا على صرفة معلّقة.');
            }

            $description = $reason;
            if ($periodFrom || $periodTo) {
                $description = 'أثر رجعي عن '.($periodFrom ?: '—').' → '.($periodTo ?: '—').'. '.$reason;
            }

            return PayrollRunItem::create([
                'payroll_run_id' => $run->id,
                'item_type' => PayrollRunItem::TYPE_RETRO,
                'source_table' => null,
                'source_id' => null,
                'name' => $name,
                'amount' => round($amount, 2),
                'description' => $description,
            ]);
        });
    }

    public function remove(PayrollRun $run, PayrollRunItem $item): void
    {
        DB::transaction(function () use ($run, $item) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if (! $run->canReceiveRetro()) {
                throw new \RuntimeException('لا تُحذف تسوية رجعية إلا من صرفة معلّقة.');
            }

            $item = PayrollRunItem::query()
                ->where('payroll_run_id', $run->id)
                ->where('item_type', PayrollRunItem::TYPE_RETRO)
                ->findOrFail($item->id);

            $item->delete();
        });
    }
}

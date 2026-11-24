<?php

namespace App\Console\Commands;

use App\Models\Installment;
use App\Services\Installments\InstallmentCollections;
use App\Services\Installments\InstallmentFinancing;
use Illuminate\Console\Command;
use Throwable;

/**
 * ترحيل تمويل الاتفاقيات القائمة — مرّةً بعد ترحيل `add_installment_financing_posting`.
 *
 * الاتفاقياتُ المحفوظة قبله لم يُرحَّل ربحُها ورسومُها وضريبتُها، فيقف
 * التحصيلُ عليها عند إجمالي الفاتورة. هذا الأمر يرحّل الفرقَ لكلٍّ منها ثمّ
 * يعيد توزيع سنداتها ويعترف بربح ما حُصِّل. وهو بالفرق: تشغيلُه ثانيةً لا
 * يُضاعف شيئاً.
 */
class PostInstallmentFinancing extends Command
{
    protected $signature = 'installments:post-financing
                            {--dry-run : يعرض ما سيُرحَّل ولا يكتب}
                            {--id= : اتفاقيةٌ واحدة بمعرّفها}';

    protected $description = 'ترحيل ربح المرابحة والرسوم الإدارية وضريبتهما للاتفاقيات القائمة';

    public function handle(): int
    {
        $query = Installment::query()->where('status', '!=', Installment::STATUS_CANCELLED)->orderBy('id');

        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        }

        $failed = 0;

        foreach ($query->get() as $agreement) {
            $target = InstallmentFinancing::target($agreement);
            $pending = round(
                ($target['profit'] - (float) $agreement->financing_profit_posted)
                + ($target['fees'] - (float) $agreement->financing_fees_posted)
                + ($target['tax'] - (float) $agreement->financing_tax_posted),
                2
            );

            $this->line(sprintf(
                '%s  ربح %.2f  رسوم %.2f  ضريبة %.2f  — غير مرحَّل %.2f',
                $agreement->code,
                $target['profit'],
                $target['fees'],
                $target['tax'],
                $pending
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            try {
                InstallmentFinancing::sync($agreement);
                InstallmentCollections::apply($agreement->refresh());
            } catch (Throwable $e) {
                $failed++;
                $this->error('  '.$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

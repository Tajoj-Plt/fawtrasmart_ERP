<?php

namespace App\Console\Commands;

use App\Models\FawtraSubscription;
use App\Services\Fawtra\FawtraCredentials;
use App\Services\Fawtra\SubscriptionSync;
use Illuminate\Console\Command;

/**
 * المزامنة الدورية مع لوحة فوترة.
 *
 * يُشغَّل من جدول `cron_jobs` كل ربع ساعة — وهي الفترة التي توصي بها وثيقة
 * الواجهة وتُرجعها في `meta.sync_interval`.
 *
 * ولا يُسجَّل في `Console\Kernel`: نظام الجدولة هنا مقروء من قاعدة البيانات
 * (انظر CLAUDE.md)، والأمر مُدرَج في `CronRegistry` كي يُقبل تشغيله.
 */
class SyncFawtraSubscription extends Command
{
    protected $signature = 'fawtra:sync
                            {--force : تجاهل رمز المزامنة وفترة التراجع}
                            {--status : عرض الحالة المحفوظة دون اتصال}';

    protected $description = 'مزامنة بيانات الاشتراك وحدود الاستخدام مع لوحة تحكّم فوترة';

    public function handle(SubscriptionSync $sync, FawtraCredentials $credentials): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        if (! $credentials->configured()) {
            // ليس خطأً: نظامٌ لم يُربط بعد يعمل بلا قيد. وإرجاع فشلٍ هنا
            // يجعل المهمة المجدولة تظهر «متعثّرة» أبداً على كل تركيب غير مربوط
            $this->components->warn('النظام غير مربوط بلوحة فوترة — لا شيء يُزامَن.');

            return self::SUCCESS;
        }

        $result = $sync->run(force: (bool) $this->option('force'));

        if ($result['ok']) {
            $this->components->info($result['message']);

            if ($result['changed']) {
                $this->showStatus();
            }

            return self::SUCCESS;
        }

        // `BACKOFF` ليس إخفاقاً: المهمة تعمل كما صُمِّمت وتنتظر موعدها
        if ($result['code'] === 'BACKOFF') {
            $this->components->warn($result['message']);

            return self::SUCCESS;
        }

        $this->components->error($result['message']);

        return self::FAILURE;
    }

    protected function showStatus(): int
    {
        $record = FawtraSubscription::current();

        if (! $record) {
            $this->components->warn('لا توجد لقطة اشتراك محفوظة بعد.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>الحالة</>', $record->statusLabel());
        $this->components->twoColumnDetail('الباقة', $record->plan_name ?: '—');
        $this->components->twoColumnDetail('ينتهي في', $record->end_date?->toDateString() ?: '—');
        $this->components->twoColumnDetail('الأيام المتبقية', (string) ($record->days_remaining ?? '—'));
        $this->components->twoColumnDetail('التجديد التلقائي', $record->auto_renew ? 'مفعّل' : 'غير مفعّل');
        $this->components->twoColumnDetail('آخر مزامنة', $record->last_synced_at?->diffForHumans() ?: 'لم تتم');

        if ($record->isStale()) {
            $this->components->warn('اللقطة قديمة — تجاوزت حدّ '.(int) config('fawtra.sync.stale_after') / 3600 .' ساعة.');
        }

        $usage = $record->usage();

        if ($usage !== []) {
            $this->newLine();
            $this->components->info('حدود الاستخدام');

            foreach ($usage as $item) {
                $unlimited = (bool) ($item['unlimited'] ?? false);

                $this->components->twoColumnDetail(
                    $item['label'] ?? $item['key'] ?? '—',
                    $unlimited
                        ? '<fg=green>بلا حدّ</>'
                        : sprintf('%s / %s (%s%%)', $item['used'] ?? 0, $item['allowance'] ?? 0, $item['percent'] ?? 0)
                );
            }
        }

        if ($record->last_error) {
            $this->newLine();
            $this->components->error('آخر خطأ: '.$record->last_error);
        }

        return self::SUCCESS;
    }
}

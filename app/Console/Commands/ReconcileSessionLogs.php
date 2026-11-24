<?php

namespace App\Console\Commands;

use App\Models\SessionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يغلق الجلسات التي انتهت بلا حدث خروج، وينظّف السجل القديم.
 *
 * أكثر الجلسات لا تنتهي بضغطة «خروج»: يُغلق المستخدم المتصفّح، أو تنتهي
 * مهلة الجلسة، أو ينقطع الاتصال. لا حدث `Logout` يقع في أيٍّ من ذلك،
 * فيبقى الصفّ «مفتوحاً» في السجل إلى الأبد ما لم يُصالَح.
 *
 * وقت الإغلاق المسجَّل هو **آخر ظهور** لا لحظة اكتشافنا: من أغلق متصفّحه
 * العاشرة مساءً انتهت جلسته حينها، لا حين شُغّل هذا الأمر صباحاً.
 *
 * يُجدوَل كل عشر دقائق في `app/Console/Kernel.php`.
 */
class ReconcileSessionLogs extends Command
{
    protected $signature = 'sessions:reconcile
                            {--prune= : حذف السجلات الأقدم من هذا العدد من الأيام}
                            {--dry-run : يعرض ما سيتغيّر دون تغييره}';

    protected $description = 'إغلاق جلسات سجلّ الجلسات التي انتهت بلا حدث خروج، وتنظيف القديم';

    public function handle(): int
    {
        if (! Schema::hasTable('session_logs')) {
            $this->error('جدول session_logs غير موجود — شغّل php artisan migrate أولاً.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $this->closeStale($dry);
        $this->prune($dry);

        return self::SUCCESS;
    }

    /**
     * الجلسات المفتوحة التي لم تعد قائمة.
     *
     * المهلة تُقرأ من `config('session.lifetime')` بدل رقم مكتوب هنا،
     * وتُزاد دقيقتين احتياطاً: جلسةٌ حيّة تماماً قد يتأخّر تحديث ظهورها
     * فيُغلق صفّها بالخطأ وهو مفتوح.
     */
    protected function closeStale(bool $dry): void
    {
        $lifetime = (int) config('session.lifetime', 120);
        $cutoff = now()->subMinutes($lifetime + 2);

        $stale = SessionLog::open()
            ->where(function ($query) use ($cutoff) {
                $query->where('last_seen_at', '<', $cutoff)
                    ->orWhere(function ($q) use ($cutoff) {
                        // صفّ بلا ظهور مسجَّل يُقاس بوقت دخوله
                        $q->whereNull('last_seen_at')->where('login_at', '<', $cutoff);
                    });
            })
            ->get();

        // جلسة مفتوحة لكن مُعرّفها اختفى من جدول Laravel: انتهت فعلاً حتى
        // لو كان ظهورها قريباً — يقع هذا عند تنظيف الجلسات أو إنهائها
        $vanished = collect();

        if (Schema::hasTable('sessions') && config('session.driver') === 'database') {
            $open = SessionLog::open()
                ->whereNotNull('session_id')
                ->whereNotIn('id', $stale->pluck('id'))
                // مهلة قصيرة تتفادى الجلسة التي لم يُكتب صفّها بعد
                ->where('login_at', '<', now()->subMinutes(5))
                ->get();

            if ($open->isNotEmpty()) {
                $alive = DB::table('sessions')
                    ->whereIn('id', $open->pluck('session_id')->filter()->all())
                    ->pluck('id')
                    ->all();

                $vanished = $open->reject(fn ($row) => in_array($row->session_id, $alive, true));
            }
        }

        $rows = $stale->concat($vanished);

        if ($rows->isEmpty()) {
            $this->info('لا جلسات معلّقة.');

            return;
        }

        if ($dry) {
            $this->line("ستُغلق {$rows->count()} جلسة معلّقة.");

            foreach ($rows->take(10) as $row) {
                $this->line(sprintf('  #%d %s — آخر ظهور %s', $row->id, $row->actorName(),
                    $row->last_seen_at?->diffForHumans() ?? '—'));
            }

            return;
        }

        foreach ($rows as $row) {
            $endedAt = $row->last_seen_at ?: $row->login_at;

            $row->update([
                'status' => 'expired',
                'logout_reason' => 'expired',
                'logout_at' => $endedAt,
                'duration_seconds' => $row->login_at && $endedAt
                    ? max(0, $endedAt->diffInSeconds($row->login_at, true))
                    : null,
            ]);
        }

        $this->info("أُغلقت {$rows->count()} جلسة انتهت بلا حدث خروج.");
    }

    /** حذف ما تجاوز مدّة الاحتفاظ المطلوبة */
    protected function prune(bool $dry): void
    {
        $days = (int) $this->option('prune');

        if ($days <= 0) {
            return;
        }

        $query = SessionLog::where('login_at', '<', now()->subDays($days))
            ->where('status', '!=', 'active');

        $count = $query->count();

        if ($count === 0) {
            return;
        }

        if ($dry) {
            $this->line("سيُحذف {$count} سجلاً تجاوز {$days} يوماً.");

            return;
        }

        $query->delete();

        $this->info("نُظّف السجل: حُذف {$count} سجلاً تجاوز {$days} يوماً.");
    }
}

<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use App\Models\CronJobExecution;
use Illuminate\Http\Request;

/**
 * سجلّ تنفيذ المهام — الشاشة العامّة عبر كل المهام.
 *
 * مبنيّة على نمط شاشات السجلّات القائمة (سجلّ الأخطاء والأحداث): بحث
 * ومرشّحات ونطاق تاريخ وترقيم صفحات، مع تنظيف يدوي للسجلّات القديمة.
 */
class CronExecutionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings.cron.logs');
    }

    public function index(Request $request)
    {
        $query = CronJobExecution::with(['job', 'executor']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('task_name', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%")
                    ->orWhere('output', 'like', "%{$search}%");
            });
        }

        if ($request->filled('cron_job_id')) {
            $query->where('cron_job_id', $request->integer('cron_job_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('trigger')) {
            $query->where('trigger', $request->string('trigger')->toString());
        }

        // النطاق شامل لليوم المحدَّد: `date_to` وحده يعني منتصف ليله فتختفي
        // تشغيلات اليوم نفسه
        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('started_at', '<=', $request->date('date_to'));
        }

        $executions = $query->latest('started_at')->paginate(\Modules\Sitting\Support\PageSize::get(30))->withQueryString();

        return view('sitting::cron_jobs.executions', [
            'executions' => $executions,
            'jobs' => CronJob::orderBy('name')->get(['id', 'name']),
            'statuses' => CronJobExecution::statuses(),
            'triggers' => CronJobExecution::triggers(),
            'summary' => $this->summary(),
        ]);
    }

    public function show(CronJobExecution $execution)
    {
        $execution->load(['job', 'executor']);

        return view('sitting::cron_jobs.execution_show', ['execution' => $execution]);
    }

    /**
     * حذف السجلّات الأقدم من مدّة يختارها المسؤول.
     */
    public function cleanup(Request $request)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ], [
            'days.required' => __('settings.cron.errors.days_required'),
            'days.min' => __('settings.cron.errors.days_min'),
        ]);

        $cutoff = now()->subDays($data['days']);
        $deleted = 0;

        do {
            $chunk = CronJobExecution::where('created_at', '<', $cutoff)->limit(2000)->delete();
            $deleted += $chunk;
        } while ($chunk > 0);

        return back()->with('success', __('settings.cron.log_cleaned', [
            'count' => number_format($deleted),
            'days' => $data['days'],
        ]));
    }

    public function destroy(CronJobExecution $execution)
    {
        $execution->delete();

        return back()->with('success', __('settings.cron.execution_deleted'));
    }

    /**
     * @return array<string, int>
     */
    protected function summary(): array
    {
        $counts = CronJobExecution::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'success' => (int) ($counts[CronJobExecution::STATUS_SUCCESS] ?? 0),
            'failed' => (int) ($counts[CronJobExecution::STATUS_FAILED] ?? 0),
            'skipped' => (int) ($counts[CronJobExecution::STATUS_SKIPPED] ?? 0),
            'today' => CronJobExecution::whereDate('started_at', today())->count(),
        ];
    }
}

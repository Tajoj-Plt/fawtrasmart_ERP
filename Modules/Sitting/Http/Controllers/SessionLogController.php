<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * سجلّ الجلسات: تاريخ الدخول والخروج.
 *
 * شاشة «جلسات المستخدمين» تعرض الجلسات **القائمة الآن** من جدول
 * `sessions`، وهي حالةٌ لا تاريخ — من دخل وخرج لا يظهر فيها. هذه الشاشة
 * تعرض السجلّ الدائم: كل دخول ومتى انتهى وكم دام، والمحاولات الفاشلة.
 */
class SessionLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index(Request $request)
    {
        $query = SessionLog::with('user');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                $q->where('attempted_email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('device_type')) {
            $query->where('device_type', $request->string('device_type')->toString());
        }

        if ($request->filled('date_from')) {
            $query->where('login_at', '>=', $request->date('date_from')->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('login_at', '<=', $request->date('date_to')->endOfDay());
        }

        return view('sitting::session_logs.index', [
            'logs' => $query->orderByDesc('login_at')->orderByDesc('id')
                ->paginate(\Modules\Sitting\Support\PageSize::get(25))->withQueryString(),
            'stats' => $this->stats(),
            'statuses' => $this->statuses(),
            'devices' => $this->devices(),
            // مستخدمون لهم سجلّ فعلاً — قائمة بكل مستخدمي النظام تصير
            // بلا فائدة حين يتجاوزون المئة
            'users' => User::whereIn('id', SessionLog::query()->distinct()->pluck('user_id')->filter())
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(SessionLog $sessionLog)
    {
        $sessionLog->load('user');

        return view('sitting::session_logs.show', [
            'log' => $sessionLog,
            // بقية جلسات صاحب هذا السجل — سياقٌ يجيب «هل هذا معتاد منه؟»
            'related' => SessionLog::where('id', '!=', $sessionLog->id)
                ->when(
                    $sessionLog->user_id,
                    fn ($q) => $q->where('user_id', $sessionLog->user_id),
                    fn ($q) => $q->where('attempted_email', $sessionLog->attempted_email)
                )
                ->orderByDesc('login_at')->limit(10)->get(),
        ]);
    }

    /**
     * إنهاء جلسة قائمة من الشاشة.
     *
     * يُحذف صفّها من `sessions` فتنقطع فعلاً عند صاحبها، ويُغلق صفّها هنا
     * بسبب «إنهاء من المسؤول». حذفُها من الجدول وحده كان سيترك السجلّ
     * يقول إنها ما زالت مفتوحة.
     */
    public function terminate(SessionLog $sessionLog)
    {
        if ($sessionLog->status !== 'active') {
            return back()->with('error', __('settings.session_logs.not_active'));
        }

        /* تُتلَف الجلسةُ في مخزنها أيّاً كان — ملفّاً أو جدولاً أو Redis.

           كانت تُحذف من جدول `sessions` وحدَه، والمخزنُ هنا ملفّات: فيقول
           الزرُّ «أُنهيت» والجلسةُ قائمةٌ وصاحبُها يعمل. */
        if ($sessionLog->session_id) {
            try {
                app('session')->getHandler()->destroy($sessionLog->session_id);
            } catch (\Throwable $e) {
                report($e);
            }

            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('id', $sessionLog->session_id)->delete();
            }
        }

        $now = now();

        $sessionLog->update([
            'status' => 'ended',
            'logout_reason' => 'forced',
            'logout_at' => $now,
            'duration_seconds' => $sessionLog->login_at
                ? max(0, $now->diffInSeconds($sessionLog->login_at, true))
                : null,
        ]);

        return back()->with('success', __('settings.session_logs.terminated'));
    }

    public function destroy(SessionLog $sessionLog)
    {
        $sessionLog->delete();

        return redirect()->route('SessionLogs.index')->with('success', __('settings.session_logs.deleted'));
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:session_logs,id',
        ]);

        $count = SessionLog::whereIn('id', $request->input('ids'))->delete();

        return back()->with('success', __('settings.session_logs.bulk_deleted', ['count' => $count]));
    }

    /** حذف السجلات القديمة — الجلسات القائمة لا تُمسّ */
    public function cleanup(Request $request)
    {
        $days = (int) $request->input('days', 90);
        $days = max(1, min($days, 3650));

        $count = SessionLog::where('login_at', '<', now()->subDays($days))
            ->where('status', '!=', 'active')
            ->delete();

        return back()->with('success', __('settings.session_logs.cleaned', ['count' => $count, 'days' => $days]));
    }

    // ==================== الداخل ====================

    protected function stats(): array
    {
        $counts = SessionLog::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $of = fn (array $keys) => collect($keys)->sum(fn ($key) => (int) ($counts[$key] ?? 0));

        return [
            'total' => (int) $counts->sum(),
            'active' => $of(['active']),
            'ended' => $of(['ended', 'expired']),
            'failed' => $of(['failed', 'locked']),
            'today' => SessionLog::successful()->where('login_at', '>=', now()->startOfDay())->count(),
            'users_today' => SessionLog::successful()->where('login_at', '>=', now()->startOfDay())
                ->distinct()->count('user_id'),
            // متوسّط المدّة للجلسات المنتهية وحدها: المفتوحة مدّتها لم تكتمل
            'avg_duration' => (int) SessionLog::whereNotNull('duration_seconds')->avg('duration_seconds'),
        ];
    }

    protected function statuses(): array
    {
        $out = [];

        foreach (array_keys(SessionLog::STATUSES) as $key) {
            $out[$key] = __('settings.session_logs.statuses.'.$key);
        }

        return $out;
    }

    protected function devices(): array
    {
        return [
            'desktop' => __('settings.session_logs.devices.desktop'),
            'mobile' => __('settings.session_logs.devices.mobile'),
            'tablet' => __('settings.session_logs.devices.tablet'),
            'bot' => __('settings.session_logs.devices.bot'),
        ];
    }
}

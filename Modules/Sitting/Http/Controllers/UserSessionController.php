<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSessionController extends Controller
{
    /**
     * الشاشةُ كانت بلا فحص صلاحية: القائمةُ الجانبية تخفي رابطَها خلف
     * `settings_edit_general_settings`، والعنوانُ نفسُه مفتوحٌ لكل من سجّل
     * دخوله — يقرأ ويحذف. صار الحارسُ على المتحكّم كما هو على الرابط.
     */
    public function __construct()
    {
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index(Request $request)
    {
        $query = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->select('sessions.*', 'users.name as user_name', 'users.email as user_email');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('sessions.ip_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $timestamp = Carbon::parse($request->date_from)->startOfDay()->timestamp;
            $query->where('sessions.last_activity', '>=', $timestamp);
        }

        if ($request->filled('date_to')) {
            $timestamp = Carbon::parse($request->date_to)->endOfDay()->timestamp;
            $query->where('sessions.last_activity', '<=', $timestamp);
        }

        $sessions = $query->orderBy('last_activity', 'desc')->paginate(\Modules\Sitting\Support\PageSize::get(15));

        // 15 minutes is the typical lifetime for an active session to be considered "Online"
        $activeLimit = Carbon::now()->subMinutes(15)->timestamp;

        $stats = [
            'total' => DB::table('sessions')->count(),
            'active_now' => DB::table('sessions')->where('last_activity', '>=', $activeLimit)->count(),
            'guests' => DB::table('sessions')->whereNull('user_id')->count(),
            'users' => DB::table('sessions')->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
        ];

        // الجدول لا يمتلئ إلا إذا كان مخزّن الجلسات هو قاعدة البيانات.
        // مع `SESSION_DRIVER=file` تبقى الصفحة فارغة أبداً — وهو ما يبدو
        // للمستخدم عطلاً بينما هو إعداد. الشاشة تقول السبب صراحةً.
        $driver = config('session.driver');

        return view('sitting::UserSessions.index', [
            'sessions' => $sessions,
            'stats' => $stats,
            'sessionDriver' => $driver,
            'driverSupported' => $driver === 'database',
        ]);
    }

    public function show($id)
    {
        $session = DB::table('sessions')->where('id', $id)->first();
        if (! $session) {
            abort(404);
        }

        return view('sitting::UserSessions.show', compact('session'));
    }

    public function destroy($id)
    {
        DB::table('sessions')->where('id', $id)->delete();

        // إلى القائمة لا «إلى الخلف»: الحذفُ من صفحة التفاصيل كان يعود إلى
        // صفحة سجلٍّ لم يعد موجوداً فتخرج «غير موجود»
        return redirect()->route('UserSessions.index')->with('success', __('settings.messages.session_ended'));
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:sessions,id',
        ]);

        DB::table('sessions')->whereIn('id', $request->ids)->delete();

        return back()->with('success', __('settings.messages.sessions_ended'));
    }

    public function cleanup()
    {
        // Delete sessions that have been inactive for more than 7 days
        $count = DB::table('sessions')->where('last_activity', '<', now()->subDays(7)->timestamp)->delete();

        return back()->with('success', __('settings.messages.sessions_cleaned_count', ['count' => $count]));
    }
}

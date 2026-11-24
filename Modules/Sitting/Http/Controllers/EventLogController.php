<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use Illuminate\Http\Request;

class EventLogController extends Controller
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
        $query = EventLog::with('user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('id', 'desc')->paginate(\Modules\Sitting\Support\PageSize::get(15));

        $stats = [
            'total' => EventLog::count(),
            'today' => EventLog::whereDate('created_at', today())->count(),
            'actions' => EventLog::distinct('action')->count('action'),
            'users' => EventLog::distinct('user_id')->count('user_id'),
        ];

        return view('sitting::EventLogs.index', compact('logs', 'stats'));
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:event_logs,id',
        ]);

        EventLog::whereIn('id', $request->ids)->delete();

        return back()->with('success', __('settings.messages.records_deleted'));
    }

    public function show($id)
    {
        $log = EventLog::with('user')->findOrFail($id);

        return view('sitting::EventLogs.show', compact('log'));
    }

    public function destroy($id)
    {
        EventLog::where('id', $id)->delete();

        // إلى القائمة لا «إلى الخلف»: الحذفُ من صفحة التفاصيل كان يعود إلى
        // صفحة سجلٍّ لم يعد موجوداً فتخرج «غير موجود»
        return redirect()->route('EventLogs.index')->with('success', __('settings.messages.record_deleted'));
    }

    public function cleanup()
    {
        // Delete logs older than 30 days
        $count = EventLog::where('created_at', '<', now()->subDays(30))->delete();

        return back()->with('success', __('settings.messages.records_cleaned', ['count' => $count]));
    }
}

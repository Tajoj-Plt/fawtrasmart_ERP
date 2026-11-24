<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;

class SmsLogController extends Controller
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
        $query = SmsLog::with('user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status == 'success') {
                $query->whereIn('status', ['success', 'sent']);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('id', 'desc')->paginate(\Modules\Sitting\Support\PageSize::get(15));

        $stats = [
            'total' => SmsLog::count(),
            'sent' => SmsLog::where('status', 'success')->orWhere('status', 'sent')->count(),
            'failed' => SmsLog::where('status', 'failed')->count(),
            'today' => SmsLog::whereDate('created_at', today())->count(),
        ];

        return view('sitting::SmsLogs.index', compact('logs', 'stats'));
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:sms_logs,id',
        ]);

        SmsLog::whereIn('id', $request->ids)->delete();

        return back()->with('success', __('settings.messages.records_deleted'));
    }

    public function show($id)
    {
        $log = SmsLog::findOrFail($id);

        return view('sitting::SmsLogs.show', compact('log'));
    }

    public function destroy($id)
    {
        SmsLog::where('id', $id)->delete();

        // إلى القائمة لا «إلى الخلف»: الحذفُ من صفحة التفاصيل كان يعود إلى
        // صفحة سجلٍّ لم يعد موجوداً فتخرج «غير موجود»
        return redirect()->route('SmsLogs.index')->with('success', __('settings.messages.record_deleted'));
    }

    public function cleanup()
    {
        // Delete logs older than 30 days
        $count = SmsLog::where('created_at', '<', now()->subDays(30))->delete();

        return back()->with('success', __('settings.messages.records_cleaned', ['count' => $count]));
    }
}

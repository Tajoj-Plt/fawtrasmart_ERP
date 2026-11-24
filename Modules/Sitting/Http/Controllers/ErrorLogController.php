<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
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
        $query = ErrorLog::with('user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('error_type', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
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
            'total' => ErrorLog::count(),
            'today' => ErrorLog::whereDate('created_at', today())->count(),
            'types' => ErrorLog::distinct('error_type')->count('error_type'),
            'urls' => ErrorLog::distinct('url')->count('url'),
        ];

        return view('sitting::ErrorLogs.index', compact('logs', 'stats'));
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:error_logs,id',
        ]);

        ErrorLog::whereIn('id', $request->ids)->delete();

        return back()->with('success', __('settings.messages.records_deleted'));
    }

    public function show($id)
    {
        $log = ErrorLog::with('user')->findOrFail($id);

        return view('sitting::ErrorLogs.show', compact('log'));
    }

    public function destroy($id)
    {
        ErrorLog::where('id', $id)->delete();

        // إلى القائمة لا «إلى الخلف»: الحذفُ من صفحة التفاصيل كان يعود إلى
        // صفحة سجلٍّ لم يعد موجوداً فتخرج «غير موجود»
        return redirect()->route('ErrorLogs.index')->with('success', __('settings.messages.record_deleted'));
    }

    public function cleanup()
    {
        // Delete logs older than 30 days
        $count = ErrorLog::where('created_at', '<', now()->subDays(30))->delete();

        return back()->with('success', __('settings.messages.records_cleaned', ['count' => $count]));
    }
}

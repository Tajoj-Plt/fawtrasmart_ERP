<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
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
        $query = Log::where('type_log', 'log')->with(['user:id,name,email,branch_id', 'user.branch:id,name']);
        $statsQuery = Log::where('type_log', 'log');

        /*
            تصفية اختيارية حسب الوحدة — عبر مُعامل استعلامٍ `module` أو عبر
            `defaults('module', ...)` على المسار (كما تفعل وحدة العملاء
            لشاشتها الخاصة). الغياب يبقي الشاشة على سلوكها الحالي: كل
            السجلّات، كما تستعملها شاشة الإعدادات العامة اليوم.

            المطابقة بادئة لا تطابقاً تاماً: `OperationLog::record()` يكتب
            العمود `type` بقيمة `LOG_MODULE` لكل متحكّم (`ClientWallet`،
            `ClientItineraries`...)، فكل وحدةٍ سجّلاتُها تبدأ باسمها.
        */
        $module = $request->route('module') ?: $request->query('module');

        if ($module) {
            $query->where('type', 'like', $module.'%');
            $statsQuery->where('type', 'like', $module.'%');
        }

        // البحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // التصفية حسب التاريخ
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest('created_at')->paginate(\Modules\Sitting\Support\PageSize::get(15));

        // الإحصائيات — تتبع تصفية الوحدة (إن وُجدت) لا الإجمالي العام دائماً،
        // وإلا بدت شاشةٌ مصفّاة بعشر سجلّات بجانب إحصائيةٍ لآلاف سجلّات النظام كلّه
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'today' => (clone $statsQuery)->whereDate('created_at', today())->count(),
        ];

        return view('sitting::ActivityLogs.index', compact('logs', 'stats'));
    }

    public function show($id)
    {
        $log = Log::with('user', 'user.branch')->findOrFail($id);

        return view('sitting::ActivityLogs.show', compact('log'));
    }

    /*
        الحذفُ يتخطّى أثرَ الحركات المالية — انظر `Log::protectedTypes()`.

        كانت الشاشةُ تحذف أيَّ سطر، و«تنظيفُ ما مضى عليه ثلاثون يوماً» يمسح
        كلَّ ما قبل شهرٍ بلا استثناء: فيذهب سردُ من صرف راتباً ومتى ومن أيِّ
        خزينة — وهو الأثرُ الوحيد للصرف التلقائي، إذ لا يكتب في مكانٍ آخر.
    */
    public function destroy($id)
    {
        $log = Log::find($id);

        if ($log && $log->isProtected()) {
            return back()->with('error', 'هذا السجلّ أثرٌ لحركة مالية ولا يُحذف.');
        }

        Log::where('id', $id)->deletable()->delete();

        // إلى القائمة لا «إلى الخلف»: الحذفُ من صفحة التفاصيل كان يعود إلى
        // صفحة سجلٍّ لم يعد موجوداً فتخرج «غير موجود»
        return redirect()->route('ActivityLog.index')->with('success', __('settings.messages.record_deleted'));
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:logs,id',
        ]);

        $protected = Log::whereIn('id', $request->ids)->protectedRecords()->count();

        $deleted = Log::whereIn('id', $request->ids)->deletable()->delete();

        if ($protected > 0) {
            return back()->with('success', sprintf(
                'حُذف %d سجلّاً. وتُرك %d منها لأنّها أثرٌ لحركات مالية لا يُحذف.',
                $deleted,
                $protected
            ));
        }

        return back()->with('success', __('settings.messages.records_deleted'));
    }

    public function cleanup()
    {
        // Delete logs older than 30 days — عدا أثرِ الحركات المالية
        $count = Log::where('type_log', 'log')
            ->deletable()
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        return back()->with('success', __('settings.messages.records_cleaned', ['count' => $count]));
    }
}

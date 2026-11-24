<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftRequest;
use App\Models\Log as ModelsLog;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftManagementController extends Controller
{
    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        $this->middleware('permission.role:shifts.index', ['only' => ['export', 'index', 'search']]);
        $this->middleware('permission.role:shifts.show', ['only' => ['show']]);
        $this->middleware('permission.role:shifts.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:shifts.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:shifts.destroy', ['only' => ['delete']]);
    }

    public function index(Request $request)
    {
        $shifts = Shift::with('days')
            ->orderBy('id', 'DESC')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('hr::shift_management.index', compact('shifts'));
    }

    /**
     * عددُ السجلات في الصفحة — من مُنتقي «عرض … سجل» في القالب.
     *
     * كان `paginate(10)` ثابتاً والمُنتقي معروضٌ فوق الجدول يُعيد تحميلَ
     * الصفحة بـ`per_page` — فيختار المستخدمُ مئةً فتعود له عشرة.
     */
    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    /**
     * البحث والفلترة AJAX
     */
    public function search(Request $request)
    {
        try {
            $query = Shift::with('days');

            // البحث بالكلمات المفتاحية
            if ($request->filled('keywords')) {
                $keywords = $request->input('keywords');
                $query->where('name', 'LIKE', "%{$keywords}%");
            }

            // الفلترة حسب النوع
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            // الفلترة حسب عدد أيام العمل
            if ($request->filled('days')) {
                $workingDaysCount = (int) $request->input('days');
                $query->whereHas('days', function ($q) use ($workingDaysCount) {
                    $q->where('working_day', 1)
                        ->havingRaw('COUNT(*) = ?', [$workingDaysCount]);
                });
            }

            /*
                مُرقَّمٌ لا `->get()`.

                القالبُ المشترك (`partials.shifts_table`) يستدعي
                `currentPage()` و`lastPage()` و`total()` و`previousPageUrl()`
                — وكلُّها من المُرقِّم وحدَه. و`get()` تُعيد مجموعةً عاديّة،
                فيرمي القالبُ «Method … Collection::currentPage does not
                exist»، ويبتلعه `catch` أدناه فيردّ 500 «حدث خطأ أثناء
                البحث». أي أن **كلَّ** تصفيةٍ في هذه الشاشة كانت تفشل.
            */
            $shifts = $query->orderBy('id', 'DESC')
                ->paginate($this->perPage($request))
                ->withQueryString();

            // إرجاع HTML للجدول
            $html = view('hr::shift_management.partials.shifts_table', compact('shifts'))->render();

            return response()->json([
                'success' => true,
                'html' => $html,
                'count' => $shifts->total(),
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.ShiftManagement', 'search');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث',
            ], 500);
        }
    }

    /**
     * تصدير البيانات
     */
    public function export(Request $request)
    {
        try {
            $query = Shift::with('days');

            // تطبيق نفس فلاتر البحث
            if ($request->filled('keywords')) {
                $keywords = $request->input('keywords');
                $query->where('name', 'LIKE', "%{$keywords}%");
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('days')) {
                $workingDaysCount = (int) $request->input('days');
                $query->whereHas('days', function ($q) use ($workingDaysCount) {
                    $q->where('working_day', 1)
                        ->havingRaw('COUNT(*) = ?', [$workingDaysCount]);
                });
            }

            $shifts = $query->orderBy('id', 'DESC')->get();

            // إنشاء ملف Excel
            return $this->generateExcelFile($shifts);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.ShiftManagement', 'export');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التصدير',
            ], 500);
        }
    }

    /**
     * إنشاء ملف Excel
     */
    private function generateExcelFile($shifts)
    {
        $filename = 'shifts_'.date('Y-m-d_H-i-s').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($shifts) {
            $file = fopen('php://output', 'w');

            // إضافة BOM للدعم العربي
            fwrite($file, "\xEF\xBB\xBF");

            // إضافة رؤوس الأعمدة
            fputcsv($file, [
                'اسم الوردية',
                'النوع',
                'عدد أيام العمل',
                'أيام العمل',
                'أيام العطل',
                'وقت البداية',
                'وقت النهاية',
                'فترة سماح التأخير',
                'تاريخ الإنشاء',
            ]);

            $dayNames = [
                'sunday' => 'الأحد',
                'monday' => 'الإثنين',
                'tuesday' => 'الثلاثاء',
                'wednesday' => 'الأربعاء',
                'thursday' => 'الخميس',
                'friday' => 'الجمعة',
                'saturday' => 'السبت',
            ];

            // إضافة البيانات
            foreach ($shifts as $shift) {
                $workingDays = $shift->days->where('working_day', 1);
                $allDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                $workingDaysList = $workingDays->pluck('day')->toArray();
                $daysOff = array_diff($allDays, $workingDaysList);

                $workingDaysNames = array_map(function ($day) use ($dayNames) {
                    return $dayNames[$day] ?? $day;
                }, $workingDaysList);

                $daysOffNames = array_map(function ($day) use ($dayNames) {
                    return $dayNames[$day] ?? $day;
                }, $daysOff);

                $firstDay = $workingDays->first();

                fputcsv($file, [
                    $shift->name,
                    $shift->type == 1 ? 'أساسي' : 'متقدم',
                    count($workingDaysList),
                    implode(', ', $workingDaysNames),
                    implode(', ', $daysOffNames),
                    $firstDay ? $firstDay->start_time : '',
                    $firstDay ? $firstDay->end_time : '',
                    $firstDay ? $firstDay->grace_period.' دقيقة' : '',
                    $shift->created_at ? $shift->created_at->format('Y-m-d H:i') : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function create()
    {
        return view('hr::shift_management.create');
    }

    public function store(ShiftRequest $request)
    {
        try {
            DB::beginTransaction();

            // إنشاء الوردية الجديدة
            $shift = new Shift;
            $shift->name = $request->name;
            $shift->type = $request->type == 'advanced' ? 2 : 1;
            $shift->save();

            // حفظ بيانات الأيام
            $this->saveDaysData($shift, $request);

            // تسجيل العملية في اللوج
            ModelsLog::create([
                'type' => 'Shift_Management',
                'type_id' => $shift->id,
                'type_log' => 'log',
                'description' => 'تم اضافة وردية **'.$shift->name.'**',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('shift_management.index')
                ->with(['success' => 'تم إضافة الوردية الجديدة بنجاح!']);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.ShiftManagement', 'store');

            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with(['error' => 'حدث خطأ أثناء إضافة الوردية. يرجى المحاولة مرة أخرى.']);
        }
    }

    public function edit($id)
    {
        $shift = Shift::with('days')->findOrFail($id);

        return view('hr::shift_management.edit', compact('shift'));
    }

    public function update(ShiftRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $shift = Shift::findOrFail($id);
            $oldName = $shift->name;

            // تحديث بيانات الوردية
            $shift->name = $request->name;
            $shift->type = $request->type == 'advanced' ? 2 : 1;
            $shift->save();

            // حذف الأيام القديمة وإعادة إنشائها
            ShiftDay::where('shift_id', $shift->id)->delete();
            $this->saveDaysData($shift, $request);

            // تسجيل العملية في اللوج

            ModelsLog::create([
                'type' => 'Shift_Management',
                'type_id' => $shift->id,
                'type_log' => 'log',
                'description' => 'تم تحديث بيانات الوردية **'.$shift->name.'**',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('shift_management.index')
                ->with(['success' => 'تم تحديث الوردية بنجاح!']);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.ShiftManagement', 'update');

            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with(['error' => 'حدث خطأ أثناء تحديث الوردية. يرجى المحاولة مرة أخرى.']);
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $shift = Shift::findOrFail($id);

            // تسجيل العملية في اللوج قبل الحذف
            ModelsLog::create([
                'type' => 'Shift_Management',
                'type_id' => $shift->id,
                'type_log' => 'log',
                'description' => 'تم حذف الوردية **'.$shift->name.'**',
                'created_by' => auth()->id(),
            ]);

            // حذف الوردية (سيتم حذف الأيام تلقائياً إذا كان هناك CASCADE)
            $shift->delete();

            DB::commit();

            // إذا كان الطلب AJAX (من البحث)
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف الوردية بنجاح!',
                ]);
            }

            return redirect()->back()->with(['success' => 'تم حذف الوردية بنجاح!']);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.ShiftManagement', 'delete');

            DB::rollBack();

            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حذف الوردية.',
                ], 500);
            }

            return redirect()->back()->with(['error' => 'حدث خطأ أثناء حذف الوردية. يرجى المحاولة مرة أخرى.']);
        }
    }

    public function show($id)
    {
        $shift = Shift::with('days')->findOrFail($id);
        $logs = ModelsLog::where('type', 'shift_management')
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('hr::shift_management.show', compact('shift', 'logs'));
    }

    /**
     * حفظ بيانات أيام الوردية
     */
    private function saveDaysData(Shift $shift, Request $request)
    {
        $daysData = $request->input('days', []);

        // أيام الأسبوع الافتراضية
        $defaultDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        foreach ($defaultDays as $dayKey) {
            $dayData = $daysData[$dayKey] ?? [];

            $shiftDayData = [
                'shift_id' => $shift->id,
                'day' => $dayKey,
                'working_day' => isset($dayData['working_day']) ? 1 : 0,
            ];

            if ($request->type == 'basic') {
                // النمط الأساسي - نفس القواعد لكل الأيام
                $shiftDayData['start_time'] = $request->input('start_time');
                $shiftDayData['end_time'] = $request->input('end_time');
                $shiftDayData['login_start_time'] = $request->input('login_start_time');
                $shiftDayData['login_end_time'] = $request->input('login_end_time');
                $shiftDayData['logout_start_time'] = $request->input('logout_start_time');
                $shiftDayData['logout_end_time'] = $request->input('logout_end_time');
                $shiftDayData['grace_period'] = $request->input('grace_period', 0);
                $shiftDayData['delay_calculation'] = $request->input('delay_calculation', 1);
            } else {
                // النمط المتقدم - قواعد مختلفة لكل يوم
                if ($shiftDayData['working_day']) {
                    $shiftDayData['start_time'] = $dayData['start_time'] ?? null;
                    $shiftDayData['end_time'] = $dayData['end_time'] ?? null;
                    $shiftDayData['login_start_time'] = $dayData['login_start_time'] ?? null;
                    $shiftDayData['login_end_time'] = $dayData['login_end_time'] ?? null;
                    $shiftDayData['logout_start_time'] = $dayData['logout_start_time'] ?? null;
                    $shiftDayData['logout_end_time'] = $dayData['logout_end_time'] ?? null;
                    $shiftDayData['grace_period'] = $dayData['grace_period'] ?? 0;
                    $shiftDayData['delay_calculation'] = $dayData['delay_calculation'] ?? 1;
                } else {
                    // إذا لم يكن يوم عمل، تعيين القيم إلى null
                    $shiftDayData['start_time'] = null;
                    $shiftDayData['end_time'] = null;
                    $shiftDayData['login_start_time'] = null;
                    $shiftDayData['login_end_time'] = null;
                    $shiftDayData['logout_start_time'] = null;
                    $shiftDayData['logout_end_time'] = null;
                    $shiftDayData['grace_period'] = 0;
                    $shiftDayData['delay_calculation'] = 1;
                }
            }

            ShiftDay::create($shiftDayData);
        }
    }
}

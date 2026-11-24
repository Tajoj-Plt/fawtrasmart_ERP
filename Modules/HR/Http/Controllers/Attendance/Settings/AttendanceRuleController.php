<?php

namespace Modules\HR\Http\Controllers\Attendance\Settings;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRule;
use App\Models\Log as ModelsLog;
use App\Models\Shift;
use App\Services\Attendance\AttendanceRuleOverrides;
use App\Support\OperationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceRuleController extends Controller
{
    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        $this->middleware('permission.role:attendance_rules.index', ['only' => ['index', 'search']]);
        $this->middleware('permission.role:attendance_rules.show', ['only' => ['show']]);
        $this->middleware('permission.role:attendance_rules.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:attendance_rules.edit', ['only' => ['edit', 'update', 'toggleStatus']]);
        $this->middleware('permission.role:attendance_rules.destroy', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $perPage = request('per_page', 10);

        $attendanceRules = AttendanceRule::with('shift')
            ->when(request('keywords'), function ($query) {
                $query->where('name', 'like', '%'.request('keywords').'%')
                    ->orWhere('description', 'like', '%'.request('keywords').'%');
            })
            ->when(request('status'), function ($query) {
                $query->where('status', request('status'));
            })
            ->when(request('shift'), function ($query) {
                $query->where('shift_id', request('shift'));
            })
            ->latest()
            ->paginate($perPage);

        $shifts = Shift::all();

        return view('hr::attendance.settings.attendance_rules.index', compact('attendanceRules', 'shifts'));
    }

    public function search(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $attendanceRules = AttendanceRule::with('shift')
                ->when($request->keywords, function ($query) use ($request) {
                    $query->where('name', 'like', '%'.$request->keywords.'%')
                        ->orWhere('description', 'like', '%'.$request->keywords.'%');
                })
                ->when($request->status, function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->when($request->shift, function ($query) use ($request) {
                    $query->where('shift_id', $request->shift);
                })
                ->latest()
                ->paginate($perPage);

            $html = view('hr::attendance.settings.attendance_rules.table-content', compact('attendanceRules'))->render();

            return response()->json([
                'success' => true,
                'html' => $html,
                'count' => $attendanceRules->total(),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.AttendanceRule', 'search');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء البحث: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $shifts = Shift::all();

        return view('hr::attendance.settings.attendance_rules.create', compact('shifts'));
    }

    /**
     * قواعدُ التحقّق — موضعٌ واحدٌ لـstore وupdate.
     *
     * الحقولُ الرقميّة تُولَّد من {@see AttendanceRuleOverrides::fields()}: ما
     * يُقبل في الشاشة هو ما يقرؤه المحرّك لا أكثر — فلا تُحفظ قاعدةٌ تُهمَل بصمت.
     *
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('attendance_rules', 'name')->ignore($ignoreId)],
            'color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'status' => 'required|in:active,inactive',
            // فارغةٌ = كلُّ الورديات؛ وموظفٌ بلا ورديةٍ لا تصله إلّا هذه
            'shift_id' => 'nullable|exists:shifts,id',
            'description' => 'nullable|string|max:1000',
        ];

        foreach (AttendanceRuleOverrides::fields() as $key => $meta) {
            $rules[$key] = 'nullable|numeric|min:'.$meta['min'].'|max:'.$meta['max'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        $messages = [
            'name.required' => 'اسم قاعدة الحضور مطلوب',
            'name.unique' => 'اسم قاعدة الحضور موجود مسبقاً',
            'name.max' => 'اسم قاعدة الحضور يجب أن يكون أقل من 255 حرف',
            'color.required' => 'اللون مطلوب',
            'color.regex' => 'تنسيق اللون غير صحيح',
            'status.required' => 'الحالة مطلوبة',
            'status.in' => 'الحالة يجب أن تكون نشط أو غير نشط',
            'shift_id.exists' => 'الوردية المحددة غير موجودة',
            'description.max' => 'الوصف يجب أن يكون أقل من 1000 حرف',
        ];

        foreach (AttendanceRuleOverrides::fields() as $key => $meta) {
            $messages[$key.'.numeric'] = '«'.$meta['label'].'» يجب أن تكون رقماً.';
            $messages[$key.'.min'] = '«'.$meta['label'].'» لا تقلّ عن '.$meta['min'].' '.$meta['unit'].'.';
            $messages[$key.'.max'] = '«'.$meta['label'].'» لا تزيد عن '.$meta['max'].' '.$meta['unit'].'.';
        }

        return $messages;
    }

    /**
     * حقولُ الصفّ كما تُحفظ — والقيمُ الرقميةُ تُكتب في `formula`.
     *
     * `condition` يُفرَّغ عند الحفظ: المحرّك يقرأ الحقلَين بالمنطق نفسِه،
     * فإبقاءُ نصٍّ قديمٍ فيه يُبقي قيمةً حُذفت من الشاشة ساريةً في الخفاء.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        $payload = [
            'name' => $validated['name'],
            'color' => $validated['color'],
            'status' => $validated['status'],
            'shift_id' => $validated['shift_id'] ?: null,
            'description' => $validated['description'] ?? null,
            'formula' => AttendanceRuleOverrides::compose($validated),
            'condition' => null,
        ];

        return $payload;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validatedData = $request->validate($this->rules(), $this->messages());

        try {
            $validatedData = AttendanceRule::create($this->payload($validatedData));
            ModelsLog::create([
                'type' => 'attendance_rules',
                'type_id' => $validatedData->id,
                'type_log' => 'log',
                'description' => 'تم اضافة قاعدة الحضور '.$request->name,
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('attendance-rules.index')->with('success', 'تم إضافة قاعدة الحضور بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.AttendanceRule', 'store');

            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء إضافة قاعدة الحضور. يرجى المحاولة مرة أخرى.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AttendanceRule $attendanceRule): View
    {
        $attendanceRule = AttendanceRule::findOrFail($attendanceRule->id);

        $logs = ModelsLog::where('type', 'attendance_rules')
            ->where('type_id', $attendanceRule->id)
            ->whereHas('attendance_rules_log') // التأكد من وجود علاقة مع سند الصرف
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('hr::attendance.settings.attendance_rules.show', compact('attendanceRule', 'logs'));
    }

    /**
     * حذف قاعدة الحضور
     */

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AttendanceRule $attendanceRule): View
    {
        $shifts = Shift::all();

        return view('hr::attendance.settings.attendance_rules.edit', compact('attendanceRule', 'shifts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AttendanceRule $attendanceRule): RedirectResponse
    {
        $validatedData = $request->validate($this->rules($attendanceRule->id), $this->messages());

        try {
            $attendanceRule->update($this->payload($validatedData));
            ModelsLog::create([
                'type' => 'attendance_rules',
                'type_id' => $attendanceRule->id,
                'type_log' => 'log',
                'description' => 'تم تعديل قاعدة الحضور '.$request->name,
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('attendance-rules.index')->with('success', 'تم تحديث قاعدة الحضور بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.AttendanceRule', 'update');

            return redirect()->back()->withInput()->with('error', 'حدث خطأ أثناء تحديث قاعدة الحضور. يرجى المحاولة مرة أخرى.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $rule = AttendanceRule::findOrFail($id);
            $ruleName = $rule->name; // احفظ الاسم قبل الحذف

            $rule->delete();

            ModelsLog::create([
                'type' => 'attendance_rules',
                'type_id' => $rule->id,
                'type_log' => 'log',
                'description' => 'تم حذف قاعدة الحضور '.$ruleName,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "تم حذف قاعدة الحضور '{$ruleName}' بنجاح",
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.AttendanceRule', 'destroy');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the status of the attendance rule.
     */
    // في الـ Controller
    public function toggleStatus($id)
    {
        try {
            $rule = AttendanceRule::findOrFail($id);
            $rule->status = $rule->status === 'active' ? 'inactive' : 'active';
            $rule->save();

            ModelsLog::create([
                'type' => 'attendance_rules',
                'type_id' => $rule->id,
                'type_log' => 'log',
                'description' => 'تم '.$rule->status.' قاعدة الحضور في سياسة الاجازات '.$rule->name,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير الحالة بنجاح',
                'new_status' => $rule->status,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.AttendanceRule', 'toggleStatus');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير الحالة: '.$e->getMessage(),
            ], 500);
        }
    }
}

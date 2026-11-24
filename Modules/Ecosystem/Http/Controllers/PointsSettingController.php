<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\PointsSetting;
use App\Models\Branch;
use App\Enums\ActionType;
use App\Enums\UserType;
use App\Enums\CalculationType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PointsSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PointsSetting::with(['branch', 'creator']);

        // Filter by user type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by action type
        if ($request->filled('action_type')) {
            $query->where('action_type', 'like', '%' . $request->action_type . '%');
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Filter by branch
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('action_name', 'like', "%{$search}%")
                  ->orWhere('action_type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $settings = $query->orderBy('user_type')
                         ->orderBy('action_type')
                         ->paginate(20)->withQueryString();

        $branches = Branch::where('status', 'active')->get();
        $userTypes = UserType::values();

        return view('ecosystem::points.settings.index', compact('settings', 'branches', 'userTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $branches = Branch::where('status', 'active')->get();
        $userTypes = UserType::values();
        $calculationTypes = CalculationType::cases();
        $actionTypes = [
            'employee' => ActionType::groupedForUserType('employee'),
            'client' => ActionType::groupedForUserType('client'),
        ];

        return view('ecosystem::points.settings.create', compact('branches', 'userTypes', 'calculationTypes', 'actionTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_type' => 'required|string|in:' . implode(',', UserType::values()),
            'action_type' => 'required|string|max:100',
            'action_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points' => 'required|integer',
            'calculation_type' => 'required|string|in:' . implode(',', CalculationType::values()),
            'calculation_value' => 'nullable|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'max_points' => 'nullable|integer|min:0',
            'tiers_config' => 'nullable|array',
            'is_active' => 'boolean',
            'disabled_reason' => 'nullable|string|max:500',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_active'] = $request->has('is_active');

        // تعيين تاريخ التعطيل إذا كان الإعداد غير نشط
        if (!$validated['is_active']) {
            $validated['disabled_at'] = now();
            $validated['disabled_reason'] = $request->input('disabled_reason');
        } else {
            $validated['disabled_at'] = null;
            $validated['disabled_reason'] = null;
        }
        
        // معالجة tiers_config إذا كان نوع الحساب متدرج
        if ($request->calculation_type === 'tiered' && $request->has('tiers')) {
            $validated['tiers_config'] = $this->processTiersConfig($request->tiers);
        }

        PointsSetting::create($validated);

        return redirect()
            ->route('ecosystem.points.settings.index')
            ->with('success', 'تم إضافة إعداد النقاط بنجاح');
    }
    
    /**
     * معالجة إعدادات المستويات
     */
    private function processTiersConfig(?array $tiers): ?array
    {
        if (empty($tiers)) {
            return null;
        }
        
        $processed = [];
        foreach ($tiers as $tier) {
            if (isset($tier['min']) || isset($tier['max']) || isset($tier['points'])) {
                $processed[] = [
                    'min' => isset($tier['min']) ? (float)$tier['min'] : 0,
                    'max' => isset($tier['max']) && $tier['max'] !== '' ? (float)$tier['max'] : null,
                    'points' => isset($tier['points']) ? (int)$tier['points'] : 0,
                ];
            }
        }
        
        return !empty($processed) ? $processed : null;
    }

    /**
     * Display the specified resource.
     */
    public function show(PointsSetting $setting)
    {
        $setting->load(['branch', 'creator']);
        
        return view('ecosystem::points.settings.show', compact('setting'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PointsSetting $setting)
    {
        $branches = Branch::where('status', 'active')->get();
        $userTypes = UserType::values();
        $calculationTypes = CalculationType::cases();
        $actionTypes = [
            'employee' => ActionType::groupedForUserType('employee'),
            'client' => ActionType::groupedForUserType('client'),
        ];

        return view('ecosystem::points.settings.edit', compact('setting', 'branches', 'userTypes', 'calculationTypes', 'actionTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PointsSetting $setting)
    {
        $validated = $request->validate([
            'user_type' => 'required|string|in:' . implode(',', UserType::values()),
            'action_type' => 'required|string|max:100',
            'action_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points' => 'required|integer',
            'calculation_type' => 'required|string|in:' . implode(',', CalculationType::values()),
            'calculation_value' => 'nullable|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'max_points' => 'nullable|integer|min:0',
            'tiers_config' => 'nullable|array',
            'is_active' => 'boolean',
            'disabled_reason' => 'nullable|string|max:500',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // تحديث تاريخ وسبب التعطيل
        if (!$validated['is_active'] && $setting->is_active) {
            // تم تعطيله الآن
            $validated['disabled_at'] = now();
            $validated['disabled_reason'] = $request->input('disabled_reason');
        } elseif ($validated['is_active'] && !$setting->is_active) {
            // تم تفعيله مجدداً
            $validated['disabled_at'] = null;
            $validated['disabled_reason'] = null;
        } elseif (!$validated['is_active']) {
            // لا يزال معطّلاً - تحديث السبب فقط
            $validated['disabled_reason'] = $request->input('disabled_reason');
        }
        
        // معالجة tiers_config إذا كان نوع الحساب متدرج
        if ($request->calculation_type === 'tiered' && $request->has('tiers')) {
            $validated['tiers_config'] = $this->processTiersConfig($request->tiers);
        } elseif ($request->calculation_type !== 'tiered') {
            $validated['tiers_config'] = null;
        }

        $setting->update($validated);

        return redirect()
            ->route('ecosystem.points.settings.index')
            ->with('success', 'تم تحديث إعداد النقاط بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PointsSetting $setting)
    {
        $setting->delete();

        return redirect()
            ->route('ecosystem.points.settings.index')
            ->with('success', 'تم حذف إعداد النقاط بنجاح');
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(Request $request, PointsSetting $setting)
    {
        $wasActive = $setting->is_active;
        $isNowActive = !$wasActive;

        $updateData = [
            'is_active' => $isNowActive,
        ];

        if (!$isNowActive) {
            // تعطيل — سجّل السبب والتاريخ
            $updateData['disabled_reason'] = $request->input('disabled_reason');
            $updateData['disabled_at'] = now();
        } else {
            // تفعيل — امسح بيانات التعطيل
            $updateData['disabled_reason'] = null;
            $updateData['disabled_at'] = null;
        }

        $setting->update($updateData);

        return response()->json([
            'success' => true,
            'message' => $isNowActive ? 'تم تفعيل الإعداد بنجاح' : 'تم تعطيل الإعداد بنجاح',
            'is_active' => $setting->is_active
        ]);
    }

    /**
     * Bulk delete
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:points_settings,id'
        ]);

        PointsSetting::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإعدادات المحددة بنجاح'
        ]);
    }

    /**
     * Export settings
     */
    public function export()
    {
        $settings = PointsSetting::with(['branch'])->get();

        $data = $settings->map(function($setting) {
            return [
                'نوع المستخدم' => $setting->user_type instanceof \App\Enums\UserType 
                    ? $setting->user_type->label() 
                    : UserType::from($setting->user_type)->label(),
                'نوع الإجراء' => $setting->action_type,
                'اسم الإجراء' => $setting->action_name,
                'الوصف' => $setting->description,
                'النقاط' => $setting->points,
                'نوع الحساب' => $setting->getCalculationTypeLabel(),
                'طريقة الحساب' => $setting->getCalculationDescription(),
                'الحد الأدنى للمعاملة' => $setting->min_amount,
                'الحد الأقصى للمعاملة' => $setting->max_amount,
                'الحد الأقصى للنقاط' => $setting->max_points,
                'الحالة' => $setting->is_active ? 'نشط' : 'غير نشط',
                'الفرع' => $setting->branch?->name ?? 'جميع الفروع',
            ];
        });

        $filename = 'points_settings_' . now()->format('Y-m-d_H-i-s') . '.json';
        
        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }
}

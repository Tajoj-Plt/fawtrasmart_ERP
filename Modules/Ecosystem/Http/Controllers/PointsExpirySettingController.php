<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\PointsExpirySetting;
use App\Models\Ecosystem\PointsTier;
use App\Models\Branch;
use App\Enums\ExpiryType;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PointsExpirySettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PointsExpirySetting::with(['branch']);

        // Filter by user type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by expiry type
        if ($request->filled('expiry_type')) {
            $query->where('expiry_type', $request->expiry_type);
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
                $q->whereHas('branch', function($bq) use ($search) {
                    $bq->where('name', 'like', "%{$search}%");
                });
            });
        }

        $settings = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $branches = Branch::where('status', 'active')->get();

        return view('ecosystem::points.expiry-settings.index', compact('settings', 'branches'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $branches = Branch::where('status', 'active')->get();
        $expiryTypes = ExpiryType::cases();
        $userTypes = UserType::cases();
        $tiers = PointsTier::where('is_active', true)->orderBy('sort_order')->get();

        return view('ecosystem::points.expiry-settings.create', compact('branches', 'expiryTypes', 'userTypes', 'tiers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_type' => 'required|string|in:' . implode(',', UserType::values()),
            'expiry_type' => 'required|string|in:' . implode(',', ExpiryType::values()),
            'expiry_days' => 'nullable|integer|min:1',
            'expiry_month' => 'nullable|integer|min:1|max:12',
            'expiry_day' => 'nullable|integer|min:1|max:31',
            'warning_days' => 'nullable|integer|min:1',
            'reminder_days' => 'nullable|array',
            'reminder_days.*' => 'integer|min:1',
            'grace_period_days' => 'nullable|integer|min:0',
            'exclude_tiers' => 'nullable|array',
            'exclude_tiers.*' => 'integer|exists:points_tiers,id',
            'is_active' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Process reminder days from comma-separated input
        if ($request->filled('reminder_days_input')) {
            $validated['reminder_days'] = array_map('intval', array_filter(
                explode(',', $request->reminder_days_input)
            ));
            sort($validated['reminder_days']);
        }

        PointsExpirySetting::create($validated);

        return redirect()
            ->route('ecosystem.points.expiry-settings.index')
            ->with('success', 'تم إضافة إعداد انتهاء الصلاحية بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(PointsExpirySetting $expiry_setting)
    {
        $expiry_setting->load(['branch']);

        return view('ecosystem::points.expiry-settings.show', compact('expiry_setting'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PointsExpirySetting $expiry_setting)
    {
        $branches = Branch::where('status', 'active')->get();
        $expiryTypes = ExpiryType::cases();
        $userTypes = UserType::cases();
        $tiers = PointsTier::where('is_active', true)->orderBy('sort_order')->get();

        return view('ecosystem::points.expiry-settings.edit', compact('expiry_setting', 'branches', 'expiryTypes', 'userTypes', 'tiers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PointsExpirySetting $expiry_setting)
    {
        $validated = $request->validate([
            'user_type' => 'required|string|in:' . implode(',', UserType::values()),
            'expiry_type' => 'required|string|in:' . implode(',', ExpiryType::values()),
            'expiry_days' => 'nullable|integer|min:1',
            'expiry_month' => 'nullable|integer|min:1|max:12',
            'expiry_day' => 'nullable|integer|min:1|max:31',
            'warning_days' => 'nullable|integer|min:1',
            'reminder_days' => 'nullable|array',
            'reminder_days.*' => 'integer|min:1',
            'grace_period_days' => 'nullable|integer|min:0',
            'exclude_tiers' => 'nullable|array',
            'exclude_tiers.*' => 'integer|exists:points_tiers,id',
            'is_active' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Process reminder days from comma-separated input
        if ($request->filled('reminder_days_input')) {
            $validated['reminder_days'] = array_map('intval', array_filter(
                explode(',', $request->reminder_days_input)
            ));
            sort($validated['reminder_days']);
        } else {
            $validated['reminder_days'] = null;
        }

        // Clear fields not relevant to selected expiry type
        if ($validated['expiry_type'] === ExpiryType::NEVER->value) {
            $validated['expiry_days'] = null;
            $validated['expiry_month'] = null;
            $validated['expiry_day'] = null;
        } elseif ($validated['expiry_type'] === ExpiryType::FIFO->value) {
            $validated['expiry_month'] = null;
            $validated['expiry_day'] = null;
        } elseif (in_array($validated['expiry_type'], [ExpiryType::FIXED_DATE->value, ExpiryType::ANNIVERSARY->value])) {
            $validated['expiry_days'] = null;
        }

        $expiry_setting->update($validated);

        return redirect()
            ->route('ecosystem.points.expiry-settings.index')
            ->with('success', 'تم تحديث إعداد انتهاء الصلاحية بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PointsExpirySetting $expiry_setting)
    {
        $expiry_setting->delete();

        return redirect()
            ->route('ecosystem.points.expiry-settings.index')
            ->with('success', 'تم حذف إعداد انتهاء الصلاحية بنجاح');
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(PointsExpirySetting $expiry_setting)
    {
        $expiry_setting->update([
            'is_active' => !$expiry_setting->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة الإعداد بنجاح',
            'is_active' => $expiry_setting->is_active
        ]);
    }

    /**
     * Bulk delete
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:points_expiry_settings,id'
        ]);

        PointsExpirySetting::whereIn('id', $request->ids)->delete();

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
        $settings = PointsExpirySetting::with(['branch'])->get();

        $data = $settings->map(function($setting) {
            return [
                'نوع المستخدم' => $setting->user_type->label(),
                'نوع الانتهاء' => $setting->expiry_type->label(),
                'أيام الانتهاء' => $setting->expiry_days ?? '-',
                'شهر الانتهاء' => $setting->expiry_month ?? '-',
                'يوم الانتهاء' => $setting->expiry_day ?? '-',
                'أيام التحذير' => $setting->warning_days ?? '-',
                'فترة السماح' => $setting->grace_period_days ?? '-',
                'الحالة' => $setting->is_active ? 'نشط' : 'غير نشط',
                'الفرع' => $setting->branch?->name ?? 'جميع الفروع',
            ];
        });

        $filename = 'expiry_settings_' . now()->format('Y-m-d_H-i-s') . '.json';

        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }
}

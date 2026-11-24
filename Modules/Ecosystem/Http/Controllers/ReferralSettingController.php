<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\ReferralSetting;
use App\Models\Branch;
use App\Enums\ConfirmationType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ReferralSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ReferralSetting::with(['branch']);

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Filter by branch
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by confirmation type
        if ($request->filled('confirmation_type')) {
            $query->where('confirmation_type', $request->confirmation_type);
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

        return view('ecosystem::points.referral-settings.index', compact('settings', 'branches'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $branches = Branch::where('status', 'active')->get();
        $confirmationTypes = ConfirmationType::cases();

        return view('ecosystem::points.referral-settings.create', compact('branches', 'confirmationTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'referrer_points' => 'required|integer|min:0',
            'referred_points' => 'required|integer|min:0',
            'confirmation_type' => 'required|string|in:' . implode(',', ConfirmationType::values()),
            'confirmation_value' => 'nullable|numeric|min:0',
            'max_referrals_per_month' => 'nullable|integer|min:0',
            'max_referrals_total' => 'nullable|integer|min:0',
            'bonus_milestones' => 'nullable|array',
            'is_active' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Process bonus milestones
        if ($request->has('milestones')) {
            $validated['bonus_milestones'] = $this->processMilestones($request->milestones);
        }

        ReferralSetting::create($validated);

        return redirect()
            ->route('ecosystem.points.referral-settings.index')
            ->with('success', 'تم إضافة إعداد الإحالة بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(ReferralSetting $referral_setting)
    {
        $referral_setting->load(['branch']);

        return view('ecosystem::points.referral-settings.show', compact('referral_setting'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReferralSetting $referral_setting)
    {
        $branches = Branch::where('status', 'active')->get();
        $confirmationTypes = ConfirmationType::cases();

        return view('ecosystem::points.referral-settings.edit', compact('referral_setting', 'branches', 'confirmationTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReferralSetting $referral_setting)
    {
        $validated = $request->validate([
            'referrer_points' => 'required|integer|min:0',
            'referred_points' => 'required|integer|min:0',
            'confirmation_type' => 'required|string|in:' . implode(',', ConfirmationType::values()),
            'confirmation_value' => 'nullable|numeric|min:0',
            'max_referrals_per_month' => 'nullable|integer|min:0',
            'max_referrals_total' => 'nullable|integer|min:0',
            'bonus_milestones' => 'nullable|array',
            'is_active' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Process bonus milestones
        if ($request->has('milestones')) {
            $validated['bonus_milestones'] = $this->processMilestones($request->milestones);
        }

        $referral_setting->update($validated);

        return redirect()
            ->route('ecosystem.points.referral-settings.index')
            ->with('success', 'تم تحديث إعداد الإحالة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReferralSetting $referral_setting)
    {
        $referral_setting->delete();

        return redirect()
            ->route('ecosystem.points.referral-settings.index')
            ->with('success', 'تم حذف إعداد الإحالة بنجاح');
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(ReferralSetting $referral_setting)
    {
        $referral_setting->update([
            'is_active' => !$referral_setting->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة الإعداد بنجاح',
            'is_active' => $referral_setting->is_active
        ]);
    }

    /**
     * Bulk delete
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:referral_settings,id'
        ]);

        ReferralSetting::whereIn('id', $request->ids)->delete();

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
        $settings = ReferralSetting::with(['branch'])->get();

        $data = $settings->map(function($setting) {
            return [
                'نقاط المُحيل' => $setting->referrer_points,
                'نقاط المُحال' => $setting->referred_points,
                'نوع التأكيد' => $setting->confirmation_type->label(),
                'قيمة التأكيد' => $setting->confirmation_value,
                'الحد الشهري' => $setting->max_referrals_per_month ?? 'غير محدود',
                'الحد الإجمالي' => $setting->max_referrals_total ?? 'غير محدود',
                'الحالة' => $setting->is_active ? 'نشط' : 'غير نشط',
                'الفرع' => $setting->branch?->name ?? 'جميع الفروع',
            ];
        });

        $filename = 'referral_settings_' . now()->format('Y-m-d_H-i-s') . '.json';

        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    /**
     * Process milestones data from form
     */
    private function processMilestones(?array $milestones): ?array
    {
        if (empty($milestones)) {
            return null;
        }

        $processed = [];
        foreach ($milestones as $milestone) {
            if (!empty($milestone['count']) && !empty($milestone['points'])) {
                $processed[] = [
                    'count' => (int) $milestone['count'],
                    'points' => (int) $milestone['points'],
                ];
            }
        }

        return !empty($processed) ? $processed : null;
    }
}

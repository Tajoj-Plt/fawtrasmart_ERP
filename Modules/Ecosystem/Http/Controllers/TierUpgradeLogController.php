<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\TierUpgradeLog;
use App\Models\Ecosystem\PointsTier;
use App\Models\Branch;
use App\Enums\UserType;
use App\Enums\UpgradeType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TierUpgradeLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = TierUpgradeLog::with(['employee', 'client', 'oldTier', 'newTier', 'upgradedBy']);
        } catch (\Exception $e) {
            \Log::error('Error creating TierUpgradeLog query: ' . $e->getMessage());
            $query = TierUpgradeLog::query()->with(['employee', 'client', 'oldTier', 'newTier', 'upgradedBy']);
        }

        // Filter by user type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by upgrade type
        if ($request->filled('upgrade_type')) {
            $query->where('upgrade_type', $request->upgrade_type);
        }

        // Filter by branch (if user has employee relation)
        if ($request->filled('branch_id')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            })->orWhereHas('client', function($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by tier
        if ($request->filled('tier_id')) {
            $tierId = $request->tier_id;
            $query->where(function($q) use ($tierId) {
                $q->where('old_tier_id', $tierId)
                  ->orWhere('new_tier_id', $tierId);
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('employee', function($qe) use ($search) {
                    $qe->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('client', function($qc) use ($search) {
                    $qc->where('trade_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('oldTier', function($qt) use ($search) {
                    $qt->where('name', 'like', "%{$search}%");
                })->orWhereHas('newTier', function($qt) use ($search) {
                    $qt->where('name', 'like', "%{$search}%");
                });
            });
        }

        // حساب الإحصائيات قبل التصفية الصفحية
        $statsQuery = clone $query;
        $stats = [
            'client_upgrades' => (clone $statsQuery)->where('user_type', UserType::CLIENT)->count(),
            'employee_upgrades' => (clone $statsQuery)->where('user_type', UserType::EMPLOYEE)->count(),
            'automatic_upgrades' => (clone $statsQuery)->where('upgrade_type', UpgradeType::AUTOMATIC)->count(),
            'manual_upgrades' => (clone $statsQuery)->where('upgrade_type', UpgradeType::MANUAL)->count(),
            'total' => $statsQuery->count(),
        ];

        $logs = $query->orderBy('created_at', 'desc')
                     ->paginate(20)->withQueryString();

        $branches = Branch::where('status', 'active')->get();
        $userTypes = UserType::values();
        $upgradeTypes = UpgradeType::values();
        $tiers = PointsTier::orderBy('min_points')->get();

        return view('ecosystem::points.upgrade-logs.index', compact('logs', 'branches', 'userTypes', 'upgradeTypes', 'stats', 'tiers'));
    }

    /**
     * Display the specified resource.
     */
    public function show(TierUpgradeLog $log)
    {
        // Check if the log exists
        if (!$log->exists) {
            return redirect()->route('ecosystem.points.upgrade-logs.index')
                           ->with('error', 'سجل الترقية غير موجود');
        }
        
        // Load relationships with error handling
        try {
            $log->loadMissing(['employee', 'client', 'oldTier', 'newTier', 'upgradedBy']);
        } catch (\Exception $e) {
            // Log the error but continue
            \Log::error('Error loading TierUpgradeLog relationships: ' . $e->getMessage());
        }
        
        return view('ecosystem::points.upgrade-logs.show', compact('log'));
    }

    /**
     * Export logs
     */
    public function export()
    {
        $logs = TierUpgradeLog::with(['employee', 'client', 'oldTier', 'newTier', 'upgradedBy'])->get();

        $data = $logs->map(function($log) {
            return [
                'نوع المستخدم' => $log->user_type->label(),
                'اسم المستخدم' => $log->clientDisplayName,
                'المستوى السابق' => $log->oldTier?->name ?? 'غير متوفر',
                'المستوى الجديد' => $log->newTier?->name ?? 'غير متوفر',
                'نوع الترقية' => $log->upgrade_type->label(),
                'النقاط عند الترقية' => $log->points_at_upgrade,
                'الموظف الذي قام بالترقية' => $log->upgradedBy ? trim($log->upgradedBy->first_name . ' ' . $log->upgradedBy->middle_name . ' ' . $log->upgradedBy->nickname) : 'غير متوفر',
                'التاريخ' => $log->created_at->format('Y-m-d H:i:s'),
                'الملاحظات' => $log->notes ?? 'لا توجد ملاحظات',
            ];
        });

        $filename = 'tier_upgrade_logs_' . now()->format('Y-m-d_H-i-s') . '.json';
        
        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    /**
     * Bulk delete
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:tier_upgrades_log,id'
        ]);

        TierUpgradeLog::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السجلات المحددة بنجاح'
        ]);
    }
}
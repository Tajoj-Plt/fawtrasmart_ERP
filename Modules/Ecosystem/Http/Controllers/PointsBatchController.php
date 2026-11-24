<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\PointsBatch;
use App\Models\Branch;
use App\Enums\BatchStatus;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PointsBatchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PointsBatch::with(['transaction', 'employee', 'client']);

        // Filter by user type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by expiring soon
        if ($request->filled('expiring_soon') && $request->expiring_soon) {
            $query->active()->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(30));
        }

        // Search by user name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('employee', function($eq) use ($search) {
                    $eq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('client', function($cq) use ($search) {
                    $cq->where('trade_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('earned_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('earned_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Stats
        $statsQuery = PointsBatch::query();
        $totalBatches = $statsQuery->count();
        $activeBatches = PointsBatch::where('status', BatchStatus::ACTIVE->value)->where('remaining_points', '>', 0)->count();
        $expiringSoonCount = PointsBatch::where('status', BatchStatus::ACTIVE->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('remaining_points', '>', 0)
            ->count();
        $totalExpiredPoints = PointsBatch::where('status', BatchStatus::EXPIRED->value)->sum('expired_points');

        $batches = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('ecosystem::points.batches.index', compact(
            'batches',
            'totalBatches',
            'activeBatches',
            'expiringSoonCount',
            'totalExpiredPoints'
        ));
    }

    /**
     * Display the specified resource.
     */
    public function show(PointsBatch $batch)
    {
        $batch->load(['transaction', 'employee', 'client']);

        return view('ecosystem::points.batches.show', compact('batch'));
    }

    /**
     * Export batches
     */
    public function export(Request $request)
    {
        $query = PointsBatch::with(['employee', 'client']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $batches = $query->orderBy('created_at', 'desc')->get();

        $data = $batches->map(function($batch) {
            $userName = $batch->user_type === UserType::EMPLOYEE
                ? ($batch->employee?->full_name ?? 'غير معروف')
                : ($batch->client?->full_name ?: ($batch->client?->trade_name ?? 'غير معروف'));

            return [
                'المستخدم' => $userName,
                'النوع' => $batch->user_type->label(),
                'النقاط الأصلية' => $batch->original_points,
                'النقاط المتبقية' => $batch->remaining_points,
                'النقاط المستخدمة' => $batch->used_points,
                'النقاط المنتهية' => $batch->expired_points,
                'تاريخ الكسب' => $batch->earned_at?->format('Y-m-d H:i'),
                'تاريخ الانتهاء' => $batch->expires_at?->format('Y-m-d H:i') ?? 'لا تنتهي',
                'الحالة' => $batch->status->label(),
            ];
        });

        $filename = 'points_batches_' . now()->format('Y-m-d_H-i-s') . '.json';

        return response()->json($data)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }
}

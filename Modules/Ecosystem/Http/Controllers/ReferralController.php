<?php

namespace Modules\Ecosystem\Http\Controllers;

use App\Models\Ecosystem\Referral;
use App\Models\Ecosystem\ReferralSetting;
use App\Models\Client;
use App\Enums\ReferralStatus;
use App\Services\PointsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Referral::with(['referrer', 'referred']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by referrer
        if ($request->filled('referrer_id')) {
            $query->where('referrer_id', $request->referrer_id);
        }

        // Filter by referred client
        if ($request->filled('referred_id')) {
            $query->where('referred_id', $request->referred_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by referral code or client name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('referral_code', 'like', "%{$search}%")
                  ->orWhereHas('referrer', function($q2) use ($search) {
                      $q2->where('trade_name', 'like', "%{$search}%")
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('referred', function($q2) use ($search) {
                      $q2->where('trade_name', 'like', "%{$search}%")
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $referrals = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Statistics
        $totalReferrals = Referral::count();
        $pendingCount = Referral::where('status', ReferralStatus::PENDING->value)->count();
        $confirmedCount = Referral::where('status', ReferralStatus::CONFIRMED->value)->count();
        $totalPointsAwarded = Referral::where('status', ReferralStatus::CONFIRMED->value)
            ->selectRaw('COALESCE(SUM(referrer_points), 0) + COALESCE(SUM(referred_points), 0) as total')
            ->value('total') ?? 0;

        return view('ecosystem::points.referrals.index', compact(
            'referrals', 'totalReferrals', 'pendingCount', 'confirmedCount', 'totalPointsAwarded'
        ));
    }

    /**
     * Display the specified resource.
     */
    public function show(Referral $referral)
    {
        $referral->load(['referrer', 'referred', 'referrerTransaction', 'referredTransaction']);

        return view('ecosystem::points.referrals.show', compact('referral'));
    }

    /**
     * Confirm a referral.
     */
    public function confirm(Referral $referral)
    {
        if (!$referral->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تأكيد هذه الإحالة - الحالة الحالية: ' . $referral->status->label()
            ], 422);
        }

        $referral->update([
            'status' => ReferralStatus::CONFIRMED,
            'confirmed_at' => now(),
        ]);

        // إرسال إشعار تأكيد الإحالة للمُحيل
        try {
            $referral->load(['referrer', 'referred']);
            $referrerName = $referral->referrer?->full_name ?? $referral->referrer?->trade_name ?? 'عميل';
            $referredName = $referral->referred?->full_name ?? $referral->referred?->trade_name ?? 'عميل';

            $notificationService = new PointsNotificationService();
            $notificationService->notifyReferralConfirmed(
                'client',
                null,
                $referral->referrer_id,
                [
                    'referrer_name' => $referrerName,
                    'referred_name' => $referredName,
                    'referrer_points' => $referral->referrer_points ?? 0,
                    'referred_points' => $referral->referred_points ?? 0,
                    'referral_code' => $referral->referral_code,
                ]
            );
        } catch (\Exception $e) {
            \Log::warning('فشل إرسال إشعار تأكيد الإحالة: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد الإحالة بنجاح'
        ]);
    }

    /**
     * Cancel a referral.
     */
    public function cancel(Request $request, Referral $referral)
    {
        if ($referral->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الإحالة ملغية بالفعل'
            ], 422);
        }

        $referral->update([
            'status' => ReferralStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason', null),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الإحالة بنجاح'
        ]);
    }

    /**
     * Export referrals
     */
    public function export()
    {
        $referrals = Referral::with(['referrer', 'referred'])->get();

        $data = $referrals->map(function($referral) {
            return [
                'كود الإحالة' => $referral->referral_code,
                'المُحيل' => $referral->referrer?->name ?? '-',
                'المُحال' => $referral->referred?->name ?? '-',
                'الحالة' => $referral->status->label(),
                'نقاط المُحيل' => $referral->referrer_points ?? 0,
                'نقاط المُحال' => $referral->referred_points ?? 0,
                'تاريخ التأكيد' => $referral->confirmed_at?->format('Y-m-d H:i') ?? '-',
                'تاريخ الإنشاء' => $referral->created_at->format('Y-m-d H:i'),
            ];
        });

        $filename = 'referrals_' . now()->format('Y-m-d_H-i-s') . '.json';

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
            'ids.*' => 'exists:referrals,id'
        ]);

        Referral::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإحالات المحددة بنجاح'
        ]);
    }
}

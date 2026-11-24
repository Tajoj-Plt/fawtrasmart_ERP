<?php

namespace Modules\Integration\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Http\Controllers\Concerns\BuildsAccountingDashboard;
use Modules\Account\Http\Controllers\Finance\TreasuryController as WebTreasuryController;
use Modules\Integration\Http\Controllers\Api\Concerns\DelegatesWebActions;

/**
 * الخزائن والتحويل بينها — القوائم بنطاق المستخدم وشرط الإيداع/السحب
 * لكل خزينة، والتحويل تفويضٌ إلى {@see WebTreasuryController} بحارس
 * فترته وقيده المحاسبي. الصلاحية على المسار (`account.treasury*`).
 */
class TreasuryController extends Controller
{
    use BuildsAccountingDashboard, DelegatesWebActions;

    /** ?direction=deposit (افتراضي، لسند القبض) أو withdraw (لسند الصرف والتحويل). */
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->query('direction') === 'withdraw'
            ? $this->withdrawableTreasuryAccounts()
            : $this->depositableTreasuryAccounts();

        return response()->json([
            'success' => true,
            'data' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'code' => $a->code,
                'balance' => (float) $a->balance,
            ])->values(),
        ]);
    }

    public function transfers($treasuryId): JsonResponse
    {
        // getTransfersData() في الويب تسرد بلا فحص نطاقٍ — الفحص هنا إضافةٌ فوق التفويض
        $this->authorizeTreasuryAccess((int) $treasuryId);

        return app(WebTreasuryController::class)->getTransfersData($treasuryId);
    }

    public function storeTransfer(Request $request): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(WebTreasuryController::class)->transferTreasuryStore($request),
            'تم التحويل بين الخزائن بنجاح'
        );
    }

    public function updateTransfer(Request $request, $id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(WebTreasuryController::class)->transferTreasuryUpdate($request, $id),
            'تم تعديل التحويل بنجاح'
        );
    }
}

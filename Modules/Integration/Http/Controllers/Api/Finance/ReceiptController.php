<?php

namespace Modules\Integration\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Account\Http\Controllers\Concerns\BuildsAccountingDashboard;
use Modules\Account\Http\Controllers\Finance\IncomesController;
use Modules\Integration\Http\Controllers\Api\Concerns\DelegatesWebActions;

/**
 * سندات القبض — تفويضٌ إلى {@see IncomesController}: الترقيم من «الترقيم
 * المتسلسل»، حسابات الخزينة والعميل والضريبة من «الحسابات المحاسبية»،
 * الضرائب من إعداداتها، وحارس الفترة المحاسبية — كلّها في دالّة الويب
 * نفسها. الصلاحية على المسار (`account.incomes.*`).
 */
class ReceiptController extends Controller
{
    use BuildsAccountingDashboard, DelegatesWebActions;

    public function index(Request $request): JsonResponse
    {
        $this->forceDataListResponse($request);

        return app(IncomesController::class)->getData($request);
    }

    /** الحسابات (عملاء المستخدم وفريقه فقط)، الخزائن، التصنيفات، الضرائب، الرقم التالي. */
    public function createData(Request $request): JsonResponse
    {
        $data = app(IncomesController::class)->formData($request->integer('client_id') ?: null);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show($id): JsonResponse
    {
        $receipt = Receipt::with(['account.client', 'treasury', 'incomes_category', 'branch'])->findOrFail($id);
        $this->authorizeCreatorScope($receipt->created_by);

        return response()->json(['success' => true, 'data' => $receipt]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(IncomesController::class)->store($request),
            'تم إنشاء سند القبض بنجاح'
        );
    }

    public function update(Request $request, $id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(IncomesController::class)->update($request, $id),
            'تم تعديل سند القبض بنجاح'
        );
    }

    /** cancel() في الويب توجيهٌ دائماً (لا فرع JSON) — لذا عبر delegateWebAction(). */
    public function cancel($id): JsonResponse
    {
        return $this->delegateWebAction(
            fn () => app(IncomesController::class)->cancel($id),
            'تم إلغاء سند القبض بنجاح'
        );
    }
}

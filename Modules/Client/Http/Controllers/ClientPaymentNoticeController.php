<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientPaymentNotice;
use App\Models\notifications;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * مراجعةُ إشعارات السداد الواردة من بوابة العملاء.
 *
 * ═══════════ الاعتمادُ لا يُنشئ سنداً بنفسه ═══════════
 *
 * «اعتماد» يفتح شاشةَ سند القبض المعتادة معبّأةً بمبلغ الإشعار وتاريخه
 * ومرجعه (`paymentsClient.create?notice=`)، والسندُ يُحفَظ من هناك بمساره
 * الكامل: الخزينةُ والقيدُ والترقيمُ والفترةُ المحاسبية وحدودُ السداد.
 * ويومَ يُحفَظ يُختم الإشعارُ «معتمداً» ويُربط برقم السند.
 *
 * وهذا النمطُ نفسُه المتَّبع في «تحويل الحجز إلى فاتورة»: شاشةٌ واحدةٌ
 * للمال، ومداخلُ متعدّدةٌ تفتحها معبّأة. فنسخةٌ ثانيةٌ من منطق التحصيل هنا
 * تعني قاعدةً تُصلَح في المبيعات وتبقى معطوبةً في العملاء.
 *
 * ═══════════ الصلاحية: صلاحيةُ سند القبض نفسُها ═══════════
 *
 * لا صلاحيةَ جديدة: من يحصّل من العميل (`sales.payments.*`) هو من يراجع
 * إشعاره. وصلاحيةٌ جديدةٌ لا يربطها `permissions:sync` بأيّ دور — فتبقى
 * الشاشةُ مخفيّةً عن الجميع حتى تُربط يدوياً.
 */
class ClientPaymentNoticeController extends Controller
{
    private const LOG_MODULE = 'ClientPaymentNotices';

    public function __construct()
    {
        $this->middleware('permission:sales.payments.view', ['only' => ['index']]);
        $this->middleware('permission:sales.payments.create', ['only' => ['approve', 'reject']]);
    }

    public function index(Request $request)
    {
        $status = (string) $request->input('status', ClientPaymentNotice::STATUS_PENDING);

        $notices = ClientPaymentNotice::query()
            ->with(['client', 'invoice', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', (int) $request->input('client_id')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('client::payment_notices.index', [
            'notices' => $notices,
            'status' => $status,
            'pendingCount' => ClientPaymentNotice::where('status', ClientPaymentNotice::STATUS_PENDING)->count(),
        ]);
    }

    /** يفتح سندَ القبض معبّأً بالإشعار — الاعتمادُ يتمّ بحفظ السند. */
    public function approve(ClientPaymentNotice $notice)
    {
        if (! $notice->isPending()) {
            return back()->with('error', 'هذا الإشعار تمّت مراجعته من قبل.');
        }

        if (! $notice->invoice_id || ! $notice->invoice) {
            return back()->with('error', 'الإشعار بلا فاتورة — سجّل السند من شاشة سندات القبض.');
        }

        return redirect()->route('paymentsClient.create', [
            'id' => $notice->invoice_id,
            'type' => 'invoice',
            'notice' => $notice->id,
        ]);
    }

    public function reject(Request $request, ClientPaymentNotice $notice)
    {
        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:500'],
        ], [], ['review_note' => 'سبب الرفض']);

        if (! $notice->isPending()) {
            return back()->with('error', 'هذا الإشعار تمّت مراجعته من قبل.');
        }

        $notice->update([
            'status' => ClientPaymentNotice::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        OperationLog::record(
            'rejected',
            self::LOG_MODULE,
            $notice,
            'رفض إشعار سداد من بوابة العميل '.($notice->client?->trade_name ?? '#'.$notice->client_id)
        );

        $this->notifyClient($notice, 'لم يُعتمد إشعار السداد', 'لم نتمكّن من اعتماد إشعار سدادك: '.$data['review_note']);

        return back()->with('success', 'تم رفض الإشعار وإبلاغ العميل.');
    }

    /** إشعارُ العميل في بوابته بنتيجة المراجعة. */
    private function notifyClient(ClientPaymentNotice $notice, string $title, string $body): void
    {
        try {
            notifications::create([
                'user_id' => auth()->id(),
                // حسابُ العميل المُرسِل إن عُرف، وإلّا فالعميلُ وحده
                'receiver_id' => $notice->user_id,
                'client_id' => $notice->client_id,
                'title' => $title,
                'description' => $body,
                'type' => 'client_payment_notice_reviewed',
                'read' => 0,
            ]);
        } catch (Throwable $e) {
            Log::warning('تعذّر إشعار العميل بنتيجة مراجعة إشعار السداد', ['error' => $e->getMessage()]);
        }
    }
}

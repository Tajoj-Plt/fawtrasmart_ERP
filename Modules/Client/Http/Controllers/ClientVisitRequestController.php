<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientVisitRequest;
use App\Models\notifications;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * مراجعةُ طلبات الزيارة الميدانية الواردة من بوابة العملاء.
 *
 * ═══════════ الاعتمادُ لا يُنشئ زيارةً بنفسه ═══════════
 *
 * «اعتماد» يختم الطلبَ ويحوّلك إلى «خط سيري اليومي» (`itinerary.daily`)
 * على **اليوم المطلوب**، وهناك تضيف العميلَ إلى خطّ المندوب بمساره الكامل:
 * نطاقُ «تابع لـ»، وحدُّ المُنتقي، والزياراتُ القائمة في ذلك اليوم، ومن
 * أضاف. وهذا نمطُ «تسجيل سند القبض» في إشعارات السداد حرفياً: شاشةٌ واحدةٌ
 * للفعل، ومداخلُ تفتحها.
 *
 * **ما لا تستطيعه هذه الشاشة**: تمريرُ العميل نفسِه إلى تلك الشاشة.
 * `DailyItineraryController::index()` يقرأ `employee_id` و`date` من الطلب
 * ولا يقرأ عميلاً؛ ومُنتقي العملاء فيها بحثٌ حيّ (`itinerary.daily.clients`)
 * لا حقلٌ يُملأ من الرابط. فاسمُ العميل يُعرض في رسالة النجاح ليُبحَث به،
 * واليومُ وحده هو المعبّأ. وإضافةُ تمريرِ عميلٍ إلى تلك الشاشة تعديلٌ في
 * ملفٍّ خارج هذا العمل — يُطلَب صراحةً لا يُقحَم.
 *
 * ═══════════ الصلاحية: صلاحياتُ خطّ السير نفسُها ═══════════
 *
 * لا صلاحيةَ جديدة: من يخطّط خطوطَ السير هو من يقرّر زيارةَ عميلٍ طلبها.
 * وصلاحيةٌ جديدةٌ لا يربطها `permissions:sync` بأيّ دور — فتبقى الشاشةُ
 * مخفيّةً عن الجميع حتى تُربط يدوياً (نفسُ ما بُنيت عليه شاشةُ إشعارات
 * السداد).
 *
 *   • العرض   ← `clients_itinerary_view|clients_daily_itinerary_add_for_others`
 *     (زوجُ `itinerary.daily` نفسُه: من يرى خطوطَ السير أو يضيف لغيره)
 *   • القرار  ← `clients_daily_itinerary_add_for_others|clients_daily_itinerary_add_client`
 *     (الاعتمادُ التزامٌ بزيارة — وهو بعينه ما تحكمه صلاحيتا «إضافة عميل
 *     إلى خطّ سير»)
 */
class ClientVisitRequestController extends Controller
{
    private const LOG_MODULE = 'ClientVisitRequests';

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:clients_itinerary_view|clients_daily_itinerary_add_for_others', ['only' => ['index']]);
        $this->middleware('permission:clients_daily_itinerary_add_for_others|clients_daily_itinerary_add_client', ['only' => ['approve', 'reject']]);
    }

    public function index(Request $request)
    {
        $status = (string) $request->input('status', ClientVisitRequest::STATUS_PENDING);

        $requests = ClientVisitRequest::query()
            ->with(['client', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', (int) $request->input('client_id')))
            // المعلَّقُ يُقرأ بيومه المفضَّل: الأقربُ موعداً أوّلاً، ثمّ الأحدثُ طلباً
            ->orderByRaw('CASE WHEN preferred_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('preferred_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('client::visit_requests.index', [
            'requests' => $requests,
            'status' => $status,
            'pendingCount' => ClientVisitRequest::where('status', ClientVisitRequest::STATUS_PENDING)->count(),
        ]);
    }

    /**
     * يختم الطلبَ «معتمداً» ويفتح «خط سيري اليومي» على اليوم المطلوب.
     *
     * انظر ترويسة الصنف: العميلُ لا يُمرَّر إلى تلك الشاشة — تُعبَّأ باليوم
     * وحده ويُذكَر اسمُ العميل في الرسالة.
     */
    public function approve(ClientVisitRequest $visitRequest)
    {
        if (! $visitRequest->isPending()) {
            return back()->with('error', 'هذا الطلب تمّت مراجعته من قبل.');
        }

        $visitRequest->update([
            'status' => ClientVisitRequest::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $clientName = $visitRequest->client?->trade_name ?? '#'.$visitRequest->client_id;

        OperationLog::record(
            'approved',
            self::LOG_MODULE,
            $visitRequest,
            'اعتماد طلب زيارة ميدانية من بوابة العميل '.$clientName
        );

        $this->notifyClient($visitRequest, 'تم اعتماد طلب الزيارة',
            'اعتمدنا طلبك لزيارةٍ ميدانية ('.$visitRequest->reason.'). سيتواصل معك من يزورك لتحديد الوقت.');

        /* اليومُ المطلوب إن وُجد، وإلّا فاليومُ الجاري: `itinerary.daily`
           يقرأ `date` من الطلب، ويومٌ مضى تردّه قاعدةُ الإضافة هناك. */
        $date = $visitRequest->preferred_date && $visitRequest->preferred_date->gte(today())
            ? $visitRequest->preferred_date->toDateString()
            : today()->toDateString();

        return redirect()
            ->route('itinerary.daily', ['date' => $date])
            ->with('success', 'اعتُمد طلب الزيارة #'.$visitRequest->id.'. أضف العميل «'.$clientName.'» إلى خطّ السير من «إضافة عميل للمسار».');
    }

    public function reject(Request $request, ClientVisitRequest $visitRequest)
    {
        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:500'],
        ], [], ['review_note' => 'سبب الرفض']);

        if (! $visitRequest->isPending()) {
            return back()->with('error', 'هذا الطلب تمّت مراجعته من قبل.');
        }

        $visitRequest->update([
            'status' => ClientVisitRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        OperationLog::record(
            'rejected',
            self::LOG_MODULE,
            $visitRequest,
            'رفض طلب زيارة ميدانية من بوابة العميل '.($visitRequest->client?->trade_name ?? '#'.$visitRequest->client_id)
        );

        $this->notifyClient($visitRequest, 'لم يُعتمد طلب الزيارة',
            'لم نتمكّن من اعتماد طلبك لزيارةٍ ميدانية: '.$data['review_note']);

        return back()->with('success', 'تم رفض الطلب وإبلاغ العميل.');
    }

    /** إشعارُ العميل في بوابته بنتيجة المراجعة. */
    private function notifyClient(ClientVisitRequest $visitRequest, string $title, string $body): void
    {
        try {
            notifications::create([
                'user_id' => auth()->id(),
                // حسابُ العميل المُرسِل إن عُرف، وإلّا فالعميلُ وحده
                'receiver_id' => $visitRequest->user_id,
                'client_id' => $visitRequest->client_id,
                'title' => $title,
                'description' => $body,
                'type' => 'client_visit_request_reviewed',
                'read' => 0,
            ]);
        } catch (Throwable $e) {
            Log::warning('تعذّر إشعار العميل بنتيجة مراجعة طلب الزيارة', ['error' => $e->getMessage()]);
        }
    }
}

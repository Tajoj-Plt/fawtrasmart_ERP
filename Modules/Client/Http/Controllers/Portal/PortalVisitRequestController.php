<?php

namespace Modules\Client\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Clients\ClientPortalNotifier;
use App\Models\Client;
use App\Models\ClientVisitRequest;
use App\Models\notifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Client\Http\Controllers\Concerns\GuardsClientPortal;
use Throwable;

/**
 * طلبُ زيارةٍ ميدانية يرسله العميل من بوابته.
 *
 * ═══════════ لماذا «طلب» لا «زيارة»؟ ═══════════
 *
 * نفسُ قاعدة إشعار السداد: ما يطلبه العميلُ يراجعه موظّف. فالطلبُ **لا
 * يُنشئ زيارةً ولا يضيف العميلَ إلى خطّ سير أحد** — لأن حمولةَ يوم المندوب
 * ومسارَه الجغرافيّ ونطاقَه لا يعرفها العميل، ولو حَجَز الطلبُ وقتاً
 * لَقَلَبَ خطَّ سيرٍ رُتّب بحسابٍ. والموظّفُ يعتمد الطلبَ ثمّ يضيف العميلَ
 * من «خط سيري اليومي» بمساره الكامل.
 *
 * ═══════════ طلبٌ معلَّقٌ واحد ═══════════
 *
 * طلبٌ ثانٍ والأوّلُ لم يُراجَع لا يزيد شيئاً: هو الرغبةُ نفسُها مكرَّرةً،
 * ويُنتج إشعاريْ موظفين وصفّين يُعتمدان فيُزار العميلُ مرّتين. فيُردّ
 * الثاني برسالةٍ تقول إنّ الأوّل مفتوح — لا برسالة خطأ غامضة.
 *
 * ولا يُقيَّد بغير المعلَّق: طلبٌ اعتُمد ونُفّذ لا يمنع طلبَ الشهر القادم.
 */
class PortalVisitRequestController extends Controller
{
    use GuardsClientPortal;

    /** مفتاحُ «صلاحيات بوابة العميل» الذي يفتح هذه الشاشات */
    private const PERMISSION = 'request_field_visit';

    /** طلباتُ العميل ومعها نموذجُ طلبٍ جديد. */
    public function index()
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $requests = ClientVisitRequest::query()
            ->where('client_id', $clientId)
            ->with('reviewer:id,name')
            ->orderByDesc('id')
            ->paginate(15);

        return view('client::portal.visit_requests', [
            'portalClient' => Client::find($clientId),
            'requests' => $requests,
            // النموذجُ يُخفى ما دام طلبٌ معلَّقاً: زرٌّ يُضغَط ليُردّ صاحبُه
            // أسوأ من زرٍّ لا يظهر
            'pending' => ClientVisitRequest::where('client_id', $clientId)
                ->where('status', ClientVisitRequest::STATUS_PENDING)
                ->first(),
        ]);
    }

    public function store(Request $request)
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $data = $request->validate([
            // اليومُ مقترحٌ لا موعد — ولذلك `nullable`: عميلٌ يريد زيارةً
            // «متى تيسّر» لا يُجبَر على اختيار يومٍ لا يعنيه
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'time_window' => ['nullable', 'string', 'in:'.implode(',', array_keys(ClientVisitRequest::TIME_WINDOWS))],
            'reason' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'preferred_date.after_or_equal' => 'لا يمكن طلبُ زيارةٍ في يومٍ مضى.',
        ], [
            'preferred_date' => 'اليوم المفضَّل',
            'time_window' => 'الفترة',
            'reason' => 'سبب الزيارة',
            'note' => 'تفاصيل إضافية',
        ]);

        // انظر ترويسة الصنف: المعلَّقُ واحد
        $open = ClientVisitRequest::where('client_id', $clientId)
            ->where('status', ClientVisitRequest::STATUS_PENDING)
            ->first();

        if ($open) {
            return back()->withInput()->with('error',
                'لديك طلبُ زيارةٍ قيد المراجعة بالفعل (#'.$open->id.'). انتظر ردَّنا عليه أو اسحبه ثمّ أرسل طلباً جديداً.');
        }

        $visitRequest = ClientVisitRequest::create([
            'client_id' => $clientId,
            'user_id' => auth()->id(),
            'preferred_date' => $data['preferred_date'] ?? null,
            'time_window' => $data['time_window'] ?? 'any',
            'reason' => $data['reason'],
            'note' => $data['note'] ?? null,
            'status' => ClientVisitRequest::STATUS_PENDING,
        ]);

        $this->notifyStaff($visitRequest);

        return redirect()
            ->route('clients.portal.visit_requests.index')
            ->with('success', 'تم استلام طلب الزيارة. سيراجعه موظّفونا ويصلك ردٌّ عليه.');
    }

    /** يسحب العميلُ طلبه ما دام لم يُراجَع. */
    public function cancel(Request $request, ClientVisitRequest $visitRequest)
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        /* طلبُ عميلٍ آخر يُعامَل كغير موجود لا كممنوع: «ممنوع» تؤكّد أنّ
           الرقمَ قائم — وهذا وحده تسريب. نفسُ قاعدة سحب إشعار السداد. */
        if ($visitRequest->client_id !== $clientId) {
            abort(404);
        }

        if (! $visitRequest->isPending()) {
            return back()->with('error', 'لا يمكن سحب طلبٍ تمّت مراجعته.');
        }

        $visitRequest->update(['status' => ClientVisitRequest::STATUS_CANCELLED]);

        return back()->with('success', 'تم سحب طلب الزيارة.');
    }

    /**
     * إشعارُ **موظّف العميل المسند** لا كلّ الموظفين (طلب المستخدم
     * 2026-09-19). و`ClientPortalNotifier` يرجع إلى الرؤية بالصلاحيات إن
     * لم يكن للمندوب حسابُ دخول.
     */
    private function notifyStaff(ClientVisitRequest $visitRequest): void
    {
        $when = $visitRequest->preferred_date
            ? 'يفضّل '.$visitRequest->preferred_date->format('Y-m-d').' ('.$visitRequest->timeWindowLabel().')'
            : 'بلا يومٍ محدَّد ('.$visitRequest->timeWindowLabel().')';

        ClientPortalNotifier::notify(
            $visitRequest->client ?? $visitRequest->client_id,
            'client_visit_request',
            'طلب زيارة ميدانية من بوابة العميل',
            'طلب العميل '.($visitRequest->client?->trade_name ?? '#'.$visitRequest->client_id)
                .' زيارةً ميدانية — '.$visitRequest->reason.' — '.$when.' — بانتظار المراجعة.',
        );
    }
}

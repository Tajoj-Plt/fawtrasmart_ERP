<?php

namespace Modules\Client\Http\Controllers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientEmployeeNote;
use App\Models\ClientRelation;
use App\Models\ClientStageRequest;
use App\Models\ClientStatusRequest;
use App\Models\Competitor;
use App\Models\EmployeeClientVisit;
use App\Models\Invoice;
use App\Models\notifications;
use App\Models\Receipt;
use App\Models\Statuses;
use App\Models\Visit;
use App\Models\VisitCompetitorObservation;
use App\Support\OperationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * شاشة «إتمام الزيارة» — مساحة عمل الزيارة أثناء تنفيذها. شاشة قائمة
 * بذاتها بتصميمها الخاص — لا تُضمّن فيها شاشة العميل العامة
 * (`clients.show`) بأزرارها وتبويباتها الكثيرة، فتلك شاشةٌ لغرضٍ آخر بلغة
 * تصميمٍ مختلفة تماماً. من أراد ملفّ العميل الكامل فرابطٌ صريح في الرأس
 * يفتحه في تبويبٍ جديد.
 *
 * **الزيارة سلسلةُ خطواتٍ إلزاميّة، لا لوحةُ بطاقاتٍ يختار منها المندوب.**
 * كانت الشاشة تعرض كلَّ ما يمكن فعله دفعةً واحدة (ملاحظة، جرد عهد، رصد
 * منافس، مهمّة، مستند، نتيجة يدويّة) فيخرج المندوب بزيارةٍ ناقصةٍ بترتيبٍ
 * عشوائي. الآن يفتح كلُّ شيءٍ بترتيبه، ولا تُفتح خطوةٌ قبل التي تسبقها:
 *
 *   ١) **مسؤول العميل** — إن لم يكن للعميل مسؤولٌ مسجَّل فهذا أوّل إجراء،
 *      وبحفظه تعود الشاشة إلى الزيارة لتُكمل (`storeContact()`).
 *   ٢) **دوام المسؤول** — يُسجَّل مرّةً على المسؤول نفسه لا في كل ملاحظة
 *      (`storeContactShift()`)؛ تغييرُ دوامٍ مسجَّلٍ من قبل يحتاج صلاحية
 *      `clients_change_contact_shift`.
 *   ٣) **الملاحظة** — تظهر ملاحظات العميل وزرّ إضافتها بعد تمام الخطوتين
 *      أعلاه.
 *   ٤) **المستندات** — الفاتورة وسند القبض والتبديل والإشعار الدائن لا
 *      تُفتح إلا بعد تسجيل ملاحظة اليوم (`openDocument()` يختم رابط
 *      العودة، و`App\Http\Middleware\ReturnToVisitWorkspace` يُعيد
 *      المندوب إلى الزيارة بعد الحفظ).
 *   ٥) **الإنهاء** — بلا اختيار نتيجةٍ يدويّ: زيارةٌ فيها فاتورةٌ أو
 *      تحصيلٌ اليوم ناجحة، وما عداها سلبيّة.
 *
 * الملاحظات هنا هي `ClientRelation` الحقيقي — نفس ما تخزّنه شاشة
 * «إضافة ملاحظة» الحقيقية (`ClientController::addnotes()`، الفورم في
 * `appointments/note/add_note.blade.php`). `storeNote()` تستدعي تلك
 * الدالة نفسها بكامل منطقها (الإشعارات، النقاط، انتقال حالة العميل،
 * فحص مسافة GPS من إعدادات وحدات العملاء)، وتُعيد توجيه النتيجة إلى
 * هذه الصفحة بدل `clients.show` التي تقفز إليها دائماً.
 */
class VisitWorkspaceController extends Controller
{
    public const LOG_MODULE = 'ClientVisitWorkspace';

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * إشعارات الإدارة عن هذا العميل الموجَّهة إلى هذا المستخدم.
     *
     * **هذه الإشعارات كانت لا تصل مَن كُتبت له.** `type = 'client_popup'` هو
     * «إشعارٌ على عميل» يرسله المسؤول للمندوب ليقرأه وهو عنده («وانت عند
     * العميل كلمنى»، «بدله الضاعه»)، وكلُّ مستقبِليها في قاعدة البيانات
     * مندوبو مبيعات. ولم يكن يعرضها إلا سكربتٌ في `clients.show` ينادي
     * `notifications.client.popup` — وذاك المسار محروسٌ بـ
     * `permission:sales.notifications.view`، صلاحيةُ شاشة إشعارات المبيعات
     * الإدارية، ولا يملكها دور «مندوب مبيعات» إطلاقاً. فكان المندوب يفتح
     * ملفّ العميل، ويُطلَب الإشعار، ويُردّ الطلبُ 403، ولا يظهر شيء ولا رسالة.
     *
     * هنا تُقرأ من قاعدة البيانات مباشرةً في الخادم: لا مسار، ولا صلاحية
     * وسيطة — إشعارٌ عنوانُه `receiver_id = المستخدم نفسه` لا يحتاج صلاحيةً
     * ليقرأه صاحبُه.
     *
     * وقاعدة «المستقبِل وحده» مُبقاةٌ كما هي (نفس ما يفعله
     * `NotificationsController::getClientPopupNotifications()`): مشرفٌ يفتح
     * زيارة مندوبه لا يرى إشعاراتٍ ليست له. والمجدولةُ التي لم يحن وقتها
     * مستبعَدة بنفس شرطها هناك.
     *
     * @return \Illuminate\Support\Collection<int, notifications>
     */
    private function clientNotifications(int $clientId)
    {
        $query = notifications::query()
            ->with('user:id,name')
            ->where('client_id', $clientId)
            ->where('type', 'client_popup')
            ->where('receiver_id', Auth::id())
            ->where(function ($q) {
                $q->where('is_sent', 1)
                    ->orWhere(function ($scheduled) {
                        $scheduled->where('is_scheduled', 1)->where('scheduled_at', '<=', now());
                    });
            });

        // الردّ صفٌّ في نفس الجدول مربوطٌ بـ`parent_id` — والعمود قد لا يوجد
        // في تركيبٍ قديم، فيُسأل عنه لا يُفترض (notifications::supportsParentLink())
        if (notifications::supportsParentLink()) {
            $query->with(['replies' => fn ($r) => $r->select('id', 'parent_id', 'description', 'created_at')->oldest('id')]);
        }

        return $query
            // غير المقروء أوّلاً — هو المطلوب فعلُه الآن، والمقروء سياقٌ يُراجَع
            ->orderBy('read')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /**
     * الإشعار المقصود، مقيَّداً بصاحبه وبعميل هذه الزيارة.
     *
     * لا يكفي `findOrFail($id)`: المعرّف يصل من الشاشة، وإشعاراتُ الجدول
     * كلُّها في مجالٍ واحد — فبلا هذين الشرطين يقرأ المندوب إشعار غيره
     * أو يردّ عليه بتبديل رقمٍ في الطلب.
     */
    private function ownedClientNotification(Visit $visit, $notificationId): notifications
    {
        return notifications::where('id', $notificationId)
            ->where('client_id', $visit->client_id)
            ->where('type', 'client_popup')
            ->where('receiver_id', Auth::id())
            ->firstOrFail();
    }

    /**
     * «تم الاطّلاع» — يقفل الإشعار عن صاحبه.
     *
     * بلا `blockUntilStarted()` عمداً: قراءة توجيهٍ عن العميل تسبق الزيارة
     * ولا تتبعها — وقد يكون نصُّ الإشعار نفسه «لا تدخل عليه قبل أن تكلّمني».
     */
    public function markNotificationRead(Request $request, $visitId, $notificationId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        $notification = $this->ownedClientNotification($visit, $notificationId);
        $notification->update(['read' => 1]);

        OperationLog::record(
            'client_notification_read',
            self::LOG_MODULE,
            $visit,
            'اطّلاع على إشعار العميل «'.$notification->title.'» من مساحة عمل الزيارة'
        );

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->back()->with('success', 'تم تعليم الإشعار مقروءاً.');
    }

    /**
     * ردٌّ على إشعار العميل — يصل مُرسِلَه ويقفل الأصل.
     *
     * نفس ما تفعله `NotificationsController::replyClientToNotification()`
     * حرفياً (نوع `reply`، والمستقبِل هو `user_id` الأصلي، وربطُ `parent_id`
     * إن وُجد العمود، وقفلُ الأصل) — لأنّ شاشة الإشعارات الإدارية تقرأ الردود
     * بهذا الشكل بعينه. المكرَّر هو الحارس لا المنطق: ذاك المسار خلف
     * `permission:sales.notifications.view` التي لا يملكها المندوب.
     */
    public function replyToNotification(Request $request, $visitId, $notificationId)
    {
        $request->validate(['reply_message' => 'required|string|max:500']);

        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        $original = $this->ownedClientNotification($visit, $notificationId);
        $user = Auth::user();

        DB::transaction(function () use ($original, $request, $user) {
            notifications::create(array_merge([
                'user_id' => $user->id,
                'receiver_id' => $original->user_id,
                'title' => 'رد عميل: '.$original->title,
                'description' => $request->reply_message,
                'type' => 'reply',
                'client_id' => $original->client_id,
                'read' => 0,
                'is_sent' => true,
            ], notifications::supportsParentLink() ? ['parent_id' => $original->id] : []));

            $original->update(['read' => 1]);
        });

        OperationLog::record(
            'client_notification_replied',
            self::LOG_MODULE,
            $visit,
            'ردٌّ على إشعار العميل «'.$original->title.'» من مساحة عمل الزيارة'
        );

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->back()->with('success', 'تم إرسال ردّك.');
    }

    /**
     * بيانات «تغيير حالة العميل» لرأس الزيارة — الحالةُ ليست بيانَ زيارة، لكنّ
     * المندوب يكتشف في الميدان ما يوجب إيقاف العميل (محلٌّ أُغلق، امتناعٌ عن
     * السداد، نشاطٌ توقّف)، وكان عليه أن يخرج من الزيارة إلى ملفّ العميل ليطلبه
     * — فيضيع الطلب أو لا يُرفع أصلاً.
     *
     * المسار والحارس هنا هما نفسهما في ملفّ العميل بلا نسخةٍ ثانية من المنطق:
     * `clients.updateStatusClient` محروسٌ بـ`permission:clients_change_status`،
     * ويقرّر بنفسه أطلبٌ معلَّق أم تغييرٌ مباشر. ما هنا إظهارُ الزرّ لمن يملك
     * الصلاحية فقط — والقفلُ الحقيقي في الخادم كعادة هذه الشاشة.
     *
     * `$statusNeedsApproval` مطابقٌ حرفياً لشرط `updateStatusClient()`: من لا
     * يملك `clients_approve_status` والإعدادُ يشترط الاعتماد ⇐ طلبٌ يحتاج
     * مُعتمِداً؛ وما عداه تغييرٌ فوريّ. اختلافُ الشرطين كان يعني مودالاً يطلب
     * مُعتمِداً ثم لا يستعمله الخادم، أو العكس: نموذجاً بلا `manager_id` يردّه
     * الخادم برسالة «يرجى اختيار المسؤول».
     *
     * @return array<string, mixed>
     */
    private function statusChangeViewData(Client $client): array
    {
        if (! hasPermission('clients_change_status')) {
            return [
                'canChangeClientStatus' => false,
                'clientStatusOptions' => collect(),
                'clientStatusNeedsApproval' => false,
                'clientStatusApprovers' => collect(),
            ];
        }

        try {
            $canApprove = hasPermission('clients_approve_status');
        } catch (\Exception $e) {
            $canApprove = false;
        }

        $needsApproval = ! $canApprove && ClientSettingsHelper::clientStatusChangeRequiresApproval();

        return [
            'canChangeClientStatus' => true,
            // نشط/موقوف وحدهما — نفس `Statuses::assignable()` الذي تعرضه clients.show
            'clientStatusOptions' => Statuses::assignable()->ordered()->get(),
            'clientStatusNeedsApproval' => $needsApproval,
            // حاملو صلاحية الاعتماد أنفسهم، لا أصحاب الدور المسمّى manager
            'clientStatusApprovers' => $needsApproval ? ClientStatusRequest::approvers() : collect(),
        ];
    }

    /**
     * بيانات «تغيير وضع العميل» لرأس الزيارة — نفس فلسفة
     * `statusChangeViewData()` أعلاه بحرفيّتها: المندوب يكتشف في الميدان
     * تصنيفاً تجارياً جديداً للعميل (ضريبي، تحت المراجعة…) ولا سبب لإخراجه
     * من الزيارة إلى ملفّ العميل ليطلبه.
     *
     * الفارق عن الحالة: البوّابة هنا `clients_change_status` كذلك (نفس
     * صلاحية «لمس تصنيف العميل» — انظر تعليق الكتالوج)، والاعتمادُ **دائماً**
     * مطلوبٌ ممن لا يحمل `clients_approve_stage` بلا مفتاح إعدادٍ يعطّله،
     * فـ`$needsApproval` هنا لا تُشرَط بـ`ClientSettingsHelper` كما عند الحالة.
     *
     * @return array<string, mixed>
     */
    private function stageChangeViewData(Client $client): array
    {
        if (! hasPermission('clients_change_status')) {
            return [
                'canChangeClientStage' => false,
                'clientStageOptions' => collect(),
                'clientStageNeedsApproval' => false,
                'clientStageApprovers' => collect(),
            ];
        }

        try {
            $canApprove = hasPermission('clients_approve_stage');
        } catch (\Exception $e) {
            $canApprove = false;
        }

        $needsApproval = ! $canApprove;

        return [
            'canChangeClientStage' => true,
            'clientStageOptions' => Statuses::section('registration_stage')->ordered()->get(),
            'clientStageNeedsApproval' => $needsApproval,
            'clientStageApprovers' => $needsApproval ? ClientStageRequest::approvers() : collect(),
        ];
    }

    /**
     * المدخل الوحيد إلى مساحة عمل الزيارة: عميلٌ فقط، بلا رقم زيارة.
     *
     * كان الطريق يمرّ بـ`/clients_management/register-visit/{id}` — رابطٌ
     * وسيطٌ لا يعرض شيئاً، وظيفتُه إنشاء الزيارة ثمّ التحويل. ظهورُه في شريط
     * العنوان (وفي رسائل الخطأ منه) لا معنى له للمندوب: هو ذاهبٌ إلى الزيارة
     * لا إلى «تسجيلها». صار الرابط الظاهر هو الزيارة نفسها.
     *
     * تجد زيارة اليوم المفتوحة أو تُنشئها، ثمّ تُحوّل إلى الرابط القانوني
     * `/{client}/visits/{visit}/workspace` — فالتحديث والحفظ في المفضّلة
     * يعملان، ولا تُنشأ زيارةٌ جديدة مع كل تحديث للصفحة.
     */
    public function open($clientId)
    {
        $client = Client::findOrFail($clientId);

        // نفس فحص النطاق في `ClientController::registerVisit()`: صلاحية
        // المسار عامّة ولا تعرف أنّ هذا العميل بعينه ضمن نطاق المستخدم
        abort_unless(
            app(\App\Services\ClientAccessService::class)->canAccessClient(Auth::user(), $client->id),
            403,
            'لا يمكنك فتح زيارة لعميل غير مسموح لك بالوصول إليه.'
        );

        $visit = Visit::where('employee_id', Auth::id())
            ->where('client_id', $client->id)
            ->whereDate('visit_date', now()->toDateString())
            ->whereNull('departure_time')
            ->first();

        if (! $visit) {
            /* بلا موقعٍ للعميل تُنشأ الزيارة بإحداثيَّين فارغين ولا يُحبَس
               المندوب: `show()` أدناه تعرض بنفسها تنبيهاً واضحاً مع رابط
               إعادة المحاولة. نفس ما تفعله `registerVisit()`. */
            $location = $client->locations;

            $visit = Visit::create([
                'employee_id' => Auth::id(),
                'client_id' => $client->id,
                'visit_date' => now(),
                'visit_number' => next_document_number('visit'),
                'client_latitude' => optional($location)->latitude,
                'client_longitude' => optional($location)->longitude,
                'recording_method' => 'manual',
                'is_approved' => false,
                'stage' => Visit::STAGE_ARRIVED,
            ]);

            OperationLog::record(
                'visit_registered',
                self::LOG_MODULE,
                $visit,
                'فتح زيارة للعميل «'.$client->trade_name.'»'
            );
        } elseif (! $visit->isWorkspaceManaged()) {
            /* زيارةٌ سجّلها التتبّع الآلي ثمّ دخلها المندوب هنا: تُتبنّى
               فوراً، وإلا أغلقها الانصراف التلقائي بعد عشر دقائق وهو ما زال
               أمام زرّ «بدء الزيارة». من الآن لا يُنهيها إلا «إنهاء الزيارة». */
            $visit->update(['recording_method' => 'manual']);
        }

        return redirect()->route('clients.visitWorkspace', ['client' => $client->id, 'visit' => $visit->id]);
    }

    public function show($clientId, $visitId)
    {
        $visit = Visit::with(['client.customerTarget', 'client.status_client', 'employee', 'signedContact'])->findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        // لا بدءَ تلقائياً هنا: الزيارة تصل بمرحلة `STAGE_ARRIVED` بلا
        // `started_at`، والمؤقّت يبقى متوقّفاً حتى يضغط الموظف «بدء الزيارة»
        // من موقع العميل فعلياً — التحقّق الحقيقي في `start()` أدناه.

        // تُحسَب هنا فور الجلب لا داخل القالب: `LanguageSettings::apply()`
        // (مُركِّبٌ يعمل على كل View) قد يبدّل المنطقة الزمنية الافتراضية
        // للتطبيق أثناء تصيير الصفحة، وصبُّ Eloquent لعمودَي started_at/
        // paused_at يُعاد تفسيره من جديد في كل وصولٍ بحسب تلك المنطقة —
        // فاستدعاء $visit->elapsed_seconds من داخل Blade بعد أن يبدّلها
        // المُركِّب كان يُخرج رقماً مختلفاً تماماً (ساعات، لا ثوانٍ) عن
        // نفس الاستدعاء هنا قبل أن يبدأ أي تصيير. راجع Visit::getElapsedSecondsAttribute().
        $elapsedSeconds = $visit->elapsed_seconds;

        $client = $visit->client ?: Client::with(['customerTarget', 'status_client'])->findOrFail($clientId);

        $notes = ClientRelation::where('client_id', $client->id)
            ->with('employee')
            ->latest()
            ->limit(20)
            ->get();

        /*
            كل مسؤولي هذا العميل بدوامهم — لا `distinct('employee_id')`:
            ذاك العمود هو *موظّف النظام* الذي أضاف الصفّ لا المسؤول نفسه،
            فكان إسقاطُ التكرار عليه يحذف مسؤولين حقيقيّين أضافهم المندوب
            نفسُه ويُبقي واحداً منهم. والدوام مطلوبٌ هنا لأن الخطوة الثانية
            تُقاس عليه ولأن مودال الملاحظة يقرؤه للمسؤول المختار.
        */
        $clientEmployees = ClientEmployeeNote::where('client_id', $client->id)
            ->orderByDesc('is_primary')
            ->latest()
            ->get(['id', 'employee_name', 'employee_position', 'employee_phone', 'shift_start', 'shift_end', 'is_primary']);

        $competitors = VisitCompetitorObservation::where('visit_id', $visit->id)
            ->latest()
            ->get();

        $contact = $clientEmployees->first();

        $attachments = $competitors->whereNotNull('photo_path')->map(fn ($c) => [
            'url' => asset('assets/uploads/visit_competitors/'.$c->photo_path),
            'label' => $c->competitor_name,
            'at' => $c->created_at,
        ])
            ->sortByDesc('at')
            ->values();

        // موقع العميل المعروض هنا: موقع الزيارة نفسها أولاً (لقطة لحظة
        // تسجيلها في `registerVisit()`)، وإلا فآخر موقعٍ صالحٍ للعميل حالياً
        // (`Client::locations()` — `hasOne` مصفّاة بشرط إحداثياتٍ غير فارغة
        // ولا صفرية). بلا هذا الاحتياط: زيارةٌ أُنشئت قبل أن يُسجَّل موقع
        // العميل (أو عبر مسارٍ قديم لم يحفظ الإحداثيتين على الزيارة، مثل
        // `storeVisit()`) تبقى تعرض «لا يوجد موقع للعميل» إلى الأبد حتى
        // لو أضيف الموقع لاحقاً — بينما هو موجودٌ فعلاً في `locations`.
        $clientLatitude = $visit->client_latitude ?? optional($client->locations)->latitude;
        $clientLongitude = $visit->client_longitude ?? optional($client->locations)->longitude;

        // الزيارة الجارية نفسها تكفي عن شرط «يوم خط السير» — ولو فتحها مشرفٌ عن مندوبه
        $exchangeEligibility = \App\Support\ProductExchangeEligibility::forClient($client->id, ! $visit->isCompleted());

        return view('client::visit_workspace', [
            'client' => $client,
            'visit' => $visit,
            'notes' => $notes,
            'clientEmployees' => $clientEmployees,
            'competitors' => $competitors,
            'contact' => $contact,
            'attachments' => $attachments,
            'proximityMeters' => round(ClientSettingsHelper::visitProximityRadiusKmFor($visit->employee) * 1000),
            'proximityKm' => ClientSettingsHelper::visitProximityRadiusKmFor($visit->employee),
            'clientDue' => Account::ledger()->where('client_id', $client->id)->sum('balance'),
            'clientLatitude' => $clientLatitude,
            'clientLongitude' => $clientLongitude,
            'exchangeEligibility' => $exchangeEligibility,
            'elapsedSeconds' => $elapsedSeconds,
            'steps' => $this->stepState($visit),
            // مستندات هذه الزيارة بروابط طباعتها — تُسلَّم للعميل من هنا
            'visitDocuments' => $this->visitDocuments($visit),
            // تبويبات «الفواتير / سندات القبض / الإشعارات الدائنة / المرتجعات» — آخر 20 من كل نوع
            'clientDocuments' => $this->clientDocuments($visit),
            // رصد المنافسين في نافذة الملاحظة: إلزاميٌّ ما لم يُرصَد في هذه الزيارة بعد
            'competitorRequired' => self::competitorCaptureEnabled()
                && ! VisitCompetitorObservation::where('visit_id', $visit->id)->exists(),
            'canChangeShift' => hasPermission('clients_change_contact_shift'),
            'canCompleteVisit' => hasPermission('clients_visit_complete'),
            'canCancelVisit' => hasPermission('clients_visit_cancel'),
            // «طلب إيقاف العميل» من الميدان — انظر statusChangeViewData() أدناه
            ...$this->statusChangeViewData($client),
            // «طلب تغيير وضع العميل» من الميدان — انظر stageChangeViewData() أدناه
            ...$this->stageChangeViewData($client),
            // إشعارات الإدارة عن هذا العميل — انظر clientNotifications() أدناه
            'clientNotifications' => $this->clientNotifications($client->id),
            'canReplyToNotifications' => hasPermission('notifications_reply_to_notifications'),
            // الواجهة (JS: `WS_ANY_LOCATION`) تُعطِّل فحصَ الموقع كلَّه — GPS، تعطيل
            // زرّ «بدء الزيارة» حتى القرب، شارة الخروج عن النطاق — بصلاحية الموظّف
            // العامة أو بإعفاء هذا العميل تحديداً؛ لا الصلاحية وحدها كما كانت.
            'canVisitAnyLocation' => self::canVisitFromAnyLocation() || self::isClientExemptFromLocation($client),
            // خطوة «رصد المنافسين» بعد الملاحظة — ظهورُها وإلزامُها من مفتاحٍ واحد
            'showCompetitorCapture' => self::competitorCaptureEnabled(),
            'showNoteDepositCount' => ClientSettingsHelper::visitWorkspaceSectionVisible('note_deposit_count'),
            'showNotePhoto' => ClientSettingsHelper::visitWorkspaceSectionVisible('note_photo'),
            'noteMinPhotos' => ClientSettingsHelper::noteMinPhotos(),
            'uploadImageQuality' => ClientSettingsHelper::uploadImageQuality(),
            'uploadImageMaxPx' => ClientSettingsHelper::uploadImageMaxDimension(),
            'competitorOptions' => self::competitorCaptureEnabled()
                ? Competitor::active()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /**
     * هل قسم «رصد المنافسين» داخل ملاحظة الزيارة مفعَّل؟
     *
     * مفتاحٌ واحد يقرّر الظهور **والإلزام** معاً: مطفأً لا يُعرض القسم ولا
     * يُطلب. فصلُهما كان يصنع الحالة السيّئة — قسمٌ مخفيٌّ يرفض الخادمُ
     * الحفظَ بسببه.
     */
    public static function competitorCaptureEnabled(): bool
    {
        return ClientSettingsHelper::visitWorkspaceSectionVisible('show_competitor_capture');
    }

    /**
     * حالة خطوات الزيارة الإلزاميّة — مصدرٌ واحدٌ تقرؤه الشاشة ويحرس به
     * `complete()` نفسه، فلا يفترق ما يراه المندوب عمّا يقبله الخادم.
     *
     * @return array<string, bool|string>
     */
    private function stepState(Visit $visit): array
    {
        $clientId = (int) $visit->client_id;

        $contacts = ClientEmployeeNote::where('client_id', $clientId)->get(['shift_start', 'shift_end']);

        /* النافذة الزمنية لهذه الزيارة بعينها، لا اليوم كلّه.

           كان الشرط باليوم: ملاحظةٌ من الموظف لهذا العميل اليوم تكفي. ومع
           «سأعود لهذا العميل اليوم» صار لليوم زيارتان، فترث الزيارة المسائية
           ملاحظةَ الصباح وتُقفَل بلا أيّ عملٍ فيها — تمضي إلى التوقيع مباشرةً
           ولا تطلب شيئاً. كلُّ زيارةٍ تُثبِت نفسها الآن بما سُجّل **بعد
           بدئها**. */
        $since = $visit->started_at ?: now()->startOfDay();

        $hasContact = $contacts->isNotEmpty();
        $hasShift = $contacts->contains(fn ($c) => ! empty($c->shift_start) && ! empty($c->shift_end));
        $hasNote = $this->hasNoteSince($clientId, $since);
        $hasSale = $this->hasSaleOrCollectionSince($clientId, $since);

        /* رصد المنافسين خطوةٌ مستقلّة بعد الملاحظة — مودالُها يفتح بعدها،
           وهي ما يفتح المستندات. بالزيارة لا باليوم (`visit_id`)، ومطفأةً من
           الإعدادات تُعدّ منجزة فلا تحبس السلسلة. */
        $competitorStep = self::competitorCaptureEnabled();
        $hasCompetitors = ! $competitorStep
            || VisitCompetitorObservation::where('visit_id', $visit->id)->exists();

        $basics = $hasContact && $hasShift && $hasNote;

        return [
            'has_contact' => $hasContact,
            'has_shift' => $hasContact && $hasShift,
            'has_note' => $hasNote,
            'competitor_step' => $competitorStep,
            'has_competitors' => $hasCompetitors,
            // الترتيب: الملاحظة ← رصد المنافسين ← المستندات
            'docs_unlocked' => $basics && $hasCompetitors,
            'has_sale' => $hasSale,
            'can_complete' => $basics && $hasCompetitors,
            /* «سأعود لهذا العميل»: المسؤول ودوامه والملاحظة تكفي — الرصد
               والمستندات للزيارة التي يعود فيها، والعميل يبقى في خطّ سيره. */
            'can_return' => $basics,
            'outcome' => $hasSale ? 'success' : 'failed',
        ];
    }

    /**
     * هل سجّل الموظف ملاحظةً لهذا العميل **منذ بدء هذه الزيارة**؟ الخطوة
     * الثالثة من سلسلة الزيارة — وهي وحدها ما يفتح المستندات وما يسمح
     * بالإنهاء.
     *
     * الحدّ الأدنى `$since` لا `whereDate(today)`: `client_relations` بلا
     * عمود `visit_id`، فالنافذة الزمنية هي الرابط الوحيد بين الملاحظة
     * وزيارتها. ولولاها لأغلقت الزيارةُ الثانية في اليوم نفسه بملاحظة
     * الأولى.
     */
    private function hasNoteSince(int $clientId, \Illuminate\Support\Carbon $since): bool
    {
        return ClientRelation::where('client_id', $clientId)
            ->where('employee_id', Auth::id())
            ->where('created_at', '>=', $since)
            ->exists();
    }

    /**
     * هل في هذه الزيارة **بيعٌ أو تحصيل**؟ هذا وحده ما يفصل الزيارة الناجحة
     * عن السلبيّة — لا اختيارُ المندوب. الإشعار الدائن والتبديل مستندان
     * يفتحهما تسجيلُ الملاحظة كذلك، لكنّهما لا يُنتجان بيعاً ولا تحصيلاً
     * فلا يرفعان الزيارة إلى «ناجحة».
     *
     * ومحدودةٌ بنافذة الزيارة كذلك: فاتورةُ زيارة الصباح لا تجعل زيارة
     * المساء ناجحة — لكلٍّ نتيجتُها هي.
     */
    private function hasSaleOrCollectionSince(int $clientId, \Illuminate\Support\Carbon $since): bool
    {
        $employeeId = Auth::id();

        $hasInvoice = Invoice::where('client_id', $clientId)
            ->where('created_by', $employeeId)
            ->where('created_at', '>=', $since)
            ->exists();

        if ($hasInvoice) {
            return true;
        }

        $clientAccountId = Account::ledger()->where('client_id', $clientId)->value('id');

        return (bool) $clientAccountId && Receipt::where('account_id', $clientAccountId)
            ->where('created_by', $employeeId)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    /**
     * مستندات **هذه الزيارة** — فواتير وسندات قبض وإشعارات دائنة وتبديلات
     * أُنشئت لهذا العميل في نافذتها (من `started_at` حتى `departure_time`)
     * — بروابط طباعتها، ليُسلّمها المندوب للعميل وهو عنده بلا مغادرة الزيارة.
     *
     * منشئُ المستند صاحبُ الزيارة أو من يفتحها الآن (مشرفٌ أنشأ مستنداً من
     * داخلها).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function visitDocuments(Visit $visit): \Illuminate\Support\Collection
    {
        if (! $visit->started_at) {
            return collect();
        }

        $creators = array_values(array_unique([(int) $visit->employee_id, (int) Auth::id()]));
        $from = $visit->started_at;
        $to = $visit->departure_time;

        /* `$creatorColumn`: عمود من أنشأ المستند يختلف بالجدول —
           `payments_process` لا عمود `created_by` فيه إطلاقاً، ومن سجّل
           السند فيه في `employee_id` (وهو `users.id` لا `employees.id`). */
        return $this->documentRows($visit, function ($query, $creatorColumn = 'created_by') use ($creators, $from, $to) {
            $query->whereIn($creatorColumn, $creators)->where('created_at', '>=', $from);
            if ($to) {
                $query->where('created_at', '<=', $to);
            }

            return $query;
        });
    }

    /** آخر كم مستنداً من كل نوع في تبويبات مستندات العميل */
    private const CLIENT_DOCUMENTS_LIMIT = 20;

    /** حالة سداد الفاتورة — نفس خريطة تبويب «نظرة عامة» في ملف العميل */
    private const INVOICE_PAYMENT_STATUS = [
        1 => ['class' => 'bg-success', 'text' => 'مدفوعة بالكامل'],
        2 => ['class' => 'bg-info', 'text' => 'مدفوعة جزئياً'],
        3 => ['class' => 'bg-danger', 'text' => 'غير مدفوعة'],
        4 => ['class' => 'bg-secondary', 'text' => 'مستلمة'],
    ];

    /**
     * مستندات **العميل** — آخر `CLIENT_DOCUMENTS_LIMIT` من كل نوع أيّاً كان
     * منشئها، مجمَّعةً لتبويبات «الفواتير» و«سندات القبض» و«الإشعارات الدائنة»
     * و«المرتجعات»: يرى المندوب ما صدر للعميل وما سُدّد قبل أن يُصدر شيئاً،
     * بلا مغادرة الزيارة (`LockToOpenVisit` يعيده إليها لو فتح ملف العميل).
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, array<string, mixed>>>
     */
    private function clientDocuments(Visit $visit): \Illuminate\Support\Collection
    {
        return $this->documentRows($visit, fn ($query) => $query, self::CLIENT_DOCUMENTS_LIMIT)->groupBy('group');
    }

    /**
     * صفوف المستندات بروابط طباعتها — مصدرٌ واحد لبطاقة «مستندات هذه الزيارة»
     * ولتبويبات مستندات العميل. `$window` يضيّق كل استعلام (نافذة الزيارة، أو
     * لا شيء)، و`$limit` يقصّ كل نوعٍ على أحدثه. `group` مفتاح التبويب.
     *
     * أزرار الطباعة تتبع صلاحية راوت كلٍّ منها — زرٌّ يقود إلى 403 أسوأ من
     * غيابه؛ والراوت يبقى الحارس الفعليّ.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function documentRows(Visit $visit, callable $window, ?int $limit = null): \Illuminate\Support\Collection
    {
        $clientId = (int) $visit->client_id;
        $recent = fn ($query, $creatorColumn = 'created_by') => $window($query, $creatorColumn)
            ->latest('created_at')
            ->when($limit, fn ($q) => $q->limit($limit));

        $docs = collect();

        /* **مطبوعات النظام المدمجة وحدها** هنا — `builtin` في كل رابط.
           الزيارة تُسلِّم المستند للعميل وهو حاضر، على طابعة المندوب
           الحرارية، فالمطلوب مطبوعةٌ واحدةٌ معروفةُ الشكل يضغطها فتخرج:
           قالب النظام (`sales::invoices.print` وأخواته). وكانت البطاقة
           تعرض القوالب المعيَّنة افتراضيّاً في «قوالب المستندات» — تصاميمُ
           A4 للمكتب، تختلف من منشأةٍ لأخرى وقد تصير قائمةَ خياراتٍ أمام
           مندوبٍ واقفٍ عند العميل. شاشاتُ المستندات نفسُها تبقى على
           القالب المصمَّم كما هي؛ الاستثناء هذه البطاقة وحدها. */

        $canPrintInvoice = hasPermission('sales.invoices.print');
        $canPrintTax = hasPermission('sales.invoices.print_tax');
        // «إنشاء مرتجع» على كل فاتورة — بصلاحية راوت إنشاء المرتجع نفسها،
        // وعبر `openDocument('return')` ليعود المندوب إلى الزيارة بعد الحفظ
        $canCreateReturn = hasPermission('sales.returns.create');
        $recent(Invoice::where('client_id', $clientId)->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'returned')))
            ->get(['id', 'code', 'grand_total', 'payment_status', 'created_at'])
            ->each(function ($inv) use ($docs, $canPrintInvoice, $canPrintTax, $canCreateReturn, $visit) {
                $prints = [];
                if ($canPrintInvoice) {
                    $prints[] = ['label' => 'فاتورة', 'url' => route('invoices.print', [$inv->id, 'builtin'])];
                }
                if ($canPrintTax) {
                    $prints[] = ['label' => 'فاتورة ضريبية', 'url' => route('invoices.print_tax', [$inv->id, 'builtin'])];
                }

                $docs->push([
                    'group' => 'invoices',
                    'kind' => 'فاتورة', 'icon' => 'fa-file-invoice', 'number' => $inv->code, 'amount' => $inv->grand_total, 'at' => $inv->created_at,
                    'status' => self::INVOICE_PAYMENT_STATUS[(int) $inv->payment_status] ?? null,
                    'prints' => $prints,
                    'actions' => $canCreateReturn ? [[
                        'label' => 'إنشاء مرتجع', 'icon' => 'fa-undo',
                        'url' => route('clients.visits.openDocument', ['visit' => $visit->id, 'type' => 'return', 'invoice' => $inv->id]),
                    ]] : [],
                ]);
            });

        /* المرتجعات (`invoices.type = returned`) — تُنشأ من زرّ «إنشاء مرتجع»
           على الفاتورة وتعود إلى الزيارة، فتظهر بطباعتها الافتراضية. */
        $canPrintReturn = hasPermission('sales.returns.print');
        $recent(Invoice::where('client_id', $clientId)->where('type', 'returned'))
            ->get(['id', 'code', 'grand_total', 'created_at'])
            ->each(function ($ret) use ($docs, $canPrintReturn) {
                $docs->push([
                    'group' => 'returns',
                    'kind' => 'مرتجع مبيعات', 'icon' => 'fa-undo', 'number' => $ret->code, 'amount' => $ret->grand_total, 'at' => $ret->created_at,
                    'prints' => $canPrintReturn ? [['label' => 'مرتجع مبيعات', 'url' => route('ReturnIInvoices.print', [$ret->id, 'builtin'])]] : [],
                ]);
            });

        $clientAccountId = Account::ledger()->where('client_id', $clientId)->value('id');
        $canPrintReceipt = hasPermission('account.incomes.print');
        $recent(Receipt::where(function ($q) use ($clientId, $clientAccountId) {
            $q->where('client_id', $clientId);
            if ($clientAccountId) {
                $q->orWhere('account_id', $clientAccountId);
            }
        }))
            ->get(['id', 'code', 'amount', 'created_at'])
            ->each(function ($rec) use ($docs, $canPrintReceipt) {
                // بلا `?template=` فتعود `IncomesController::print()` لنسختيه المدمجتين
                $prints = $canPrintReceipt ? [
                    ['label' => 'سند قبض', 'hint' => 'A4', 'url' => route('incomes.print', [$rec->id, 'normal'])],
                    ['label' => 'سند قبض', 'hint' => 'حراري', 'url' => route('incomes.print', [$rec->id, 'thermal'])],
                ] : [];

                $docs->push([
                    'group' => 'receipts',
                    'kind' => 'سند قبض', 'icon' => 'fa-money-bill-wave', 'number' => $rec->code, 'amount' => $rec->amount, 'at' => $rec->created_at,
                    'prints' => $prints,
                ]);
            });

        /* «سند قبض» الثاني: دفعةُ العميل على فاتورته (`payments_process`) —
           يكتبها نموذجُ الفاتورة نفسُه وشاشةُ «مدفوعات العملاء»، وهي أكثرُ
           ما يُحصَّل في الزيارة. ولم تكن تظهر في البطاقة إطلاقاً: البطاقة
           تسأل `receipts` وحدَها، والجدولان لا يلتقيان — فيقبض المندوب على
           الفاتورة ثمّ لا يجد سنداً يسلّمه للعميل وهو واقفٌ عنده.

           والعميلُ يُبلَغ من عمود السند أو من فاتورته: آلافُ الصفوف
           `client_id` فيها فارغ والفاتورةُ وحدَها تحمله.

           والنوعان `client payments` و`sale` هما ما تعرضه شاشة «مدفوعات
           العملاء» نفسُها — وما عداهما في الجدول مدفوعاتُ موردين وتسوياتُ
           أرصدةٍ سابقة، ليست سنداتِ قبضٍ لهذا العميل. */
        $canPrintClientPayment = hasPermission('sales.payments.print');
        $recent(
            \App\Models\PaymentsProcess::whereIn('type', ['client payments', 'sale'])
                ->where(fn ($q) => $q->where('client_id', $clientId)
                    ->orWhereHas('invoice', fn ($i) => $i->where('client_id', $clientId)))
                /* سندُ القبض (`receipts`) يوزّع مبلغَه على الفواتير فيكتب صفَّ
                   `payments_process` لكل فاتورة و`reference_number` فيه رقمُ
                   السند نفسُه — فكان السندُ الواحد يظهر مرّتين: مرّةً من
                   `receipts` ومرّةً تخصيصاً. تُستبعد التخصيصات؛ فدفعاتُ نموذج
                   الفاتورة مرجعُها `INV-PAY-…` أو فارغ فتبقى. */
                ->whereNotExists(fn ($r) => $r->select(DB::raw(1))->from('receipts')
                    ->whereColumn('receipts.id', 'payments_process.reference_number')),
            'employee_id'
        )
            ->get(['id', 'reference_number', 'amount', 'created_at'])
            ->each(function ($pay) use ($docs, $canPrintClientPayment) {
                $docs->push([
                    'group' => 'receipts',
                    'kind' => 'سند قبض', 'icon' => 'fa-money-bill-wave',
                    'number' => $pay->reference_number ?: str_pad((string) $pay->id, 6, '0', STR_PAD_LEFT),
                    'amount' => $pay->amount, 'at' => $pay->created_at,
                    // `?builtin=1` يتخطّى القالب المصمَّم إلى مطبوعة النظام
                    'prints' => $canPrintClientPayment ? [
                        ['label' => 'سند قبض', 'hint' => 'A4', 'url' => route('paymentsClient.rereceipt', $pay->id).'?builtin=1'],
                        ['label' => 'سند قبض', 'hint' => 'حراري', 'url' => route('paymentsClient.rereceipt', $pay->id).'?builtin=1&type=thermal'],
                    ] : [],
                ]);
            });

        /* سند الصرف لعميل — `expenses` بلا عمود عميل؛ الرابط حسابُه الدفتريّ
           (`account_id`). لا نوعَ له في «قوالب المستندات» (`payment_voucher`
           يقرأ جدولاً آخر)، فطباعتاه المدمجتان: عادية وحرارية. */
        if ($clientAccountId) {
            $canPrintExpense = hasPermission('account.expenses.print');
            $recent(\App\Models\Expense::where('account_id', $clientAccountId))
                ->get(['id', 'code', 'amount', 'created_at'])
                ->each(function ($exp) use ($docs, $canPrintExpense) {
                    $docs->push([
                        'group' => 'expenses',
                        'kind' => 'سند صرف', 'icon' => 'fa-hand-holding-usd', 'number' => $exp->code, 'amount' => $exp->amount, 'at' => $exp->created_at,
                        'prints' => $canPrintExpense ? [
                            ['label' => 'سند صرف', 'hint' => 'A4', 'url' => route('expenses.print', [$exp->id, 'normal'])],
                            ['label' => 'سند صرف', 'hint' => 'حراري', 'url' => route('expenses.print', [$exp->id, 'thermal'])],
                        ] : [],
                    ]);
                });
        }

        $canPrintCredit = hasPermission('sales.credit_notes.print');
        $recent(\App\Models\CreditNotification::where('client_id', $clientId))
            ->get(['id', 'credit_number', 'grand_total', 'created_at'])
            ->each(function ($cn) use ($docs, $canPrintCredit) {
                // المسار بلا مقطعٍ للقالب — الطلب في `?builtin=1` (`CreditNotificationController::print()`)
                $prints = $canPrintCredit ? [['label' => 'إشعار دائن', 'url' => route('CreditNotes.print', $cn->id).'?builtin=1']] : [];

                $docs->push([
                    'group' => 'credit_notes',
                    'kind' => 'إشعار دائن', 'icon' => 'fa-file-invoice-dollar', 'number' => $cn->credit_number, 'amount' => $cn->grand_total, 'at' => $cn->created_at,
                    'prints' => $prints,
                ]);
            });

        // التبديل بلا نوعٍ في «قوالب المستندات» — مطبوعُه المدمج وحده
        $canPrintExchange = hasPermission('sales.exchanges.print');
        $recent(\App\Models\ProductExchange::where('client_id', $clientId))
            ->get(['id', 'code', 'created_at'])
            ->each(function ($ex) use ($docs, $canPrintExchange) {
                $docs->push([
                    'group' => 'exchanges',
                    'kind' => 'تبديل بضاعة', 'icon' => 'fa-exchange-alt', 'number' => $ex->code, 'amount' => null, 'at' => $ex->created_at,
                    'prints' => $canPrintExchange ? [['label' => 'تبديل بضاعة', 'url' => route('product-exchanges.print', $ex->id)]] : [],
                ]);
            });

        return $docs->sortByDesc('at')->values();
    }

    /**
     * بدء الزيارة فعلياً — لا يعمل المؤقّت قبل هذه الخطوة. تتطلّب موقع
     * الموظف الحالي وتتحقّق أنه ضمن نطاق موقع العميل، تماماً كما كان يُتحقَّق
     * في صفحة «تسجيل زيارة» القديمة — النطاق انتقل إلى هنا لأن الزيارة الآن
     * تُنشأ مباشرةً بلا تلك الصفحة الوسيطة.
     */
    public function start(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($visit->started_at) {
            return response()->json(['success' => true, 'started_at' => $visit->started_at, 'elapsed_seconds' => $visit->elapsed_seconds]);
        }

        $validated = $request->validate([
            'current_latitude' => [self::locationRule($visit), 'numeric', 'between:-90,90'],
            'current_longitude' => [self::locationRule($visit), 'numeric', 'between:-180,180'],
        ], [
            'current_latitude.required' => 'يجب تحديد موقعك الحالي لبدء الزيارة — فعّل GPS.',
            'current_longitude.required' => 'يجب تحديد موقعك الحالي لبدء الزيارة — فعّل GPS.',
        ]);

        if ($blocked = $this->outOfRangeMessage($visit, (float) ($validated['current_latitude'] ?? 0), (float) ($validated['current_longitude'] ?? 0), 'لبدء الزيارة')) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        // الموظفُ الميداني يبدأ الزيارة داخل دوامه فقط — بعد الحضور، قبل الانصراف، وخارج الاستراحة
        if ($blocked = app(\App\Services\Attendance\FieldWorkGuard::class)->visitStartBlock((int) $visit->employee_id)) {
            return response()->json(['success' => false, 'message' => $blocked, 'code' => 'OUTSIDE_DUTY'], 422);
        }

        try {
            $visit->update([
                'started_at' => now(),
                'arrival_time' => now(),
                'stage' => Visit::STAGE_IN_PROGRESS,
                'employee_latitude' => $validated['current_latitude'] ?? null,
                'employee_longitude' => $validated['current_longitude'] ?? null,
            ]);

            // صفّ خط السير (`employee_client_visits`) هو ما تعرضه
            // `clients.show` في تبويب الزيارات — والمؤقّت يعيش هنا في
            // `Visit`. بلا هذا الختم يبقى سجلّ العميل بلا وقت بدءٍ ولا
            // مدّة مهما طالت الزيارة.
            EmployeeClientVisit::markStartedFor(
                (int) $visit->employee_id,
                (int) $visit->client_id,
                $visit->visit_date
            );

            OperationLog::record(
                'visit_started',
                self::LOG_MODULE,
                $visit,
                'بدء زيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'»'
            );

            // إشعارات داخلية (للموظف وللمدراء) — نفس ما يصل في وضع
            // التسجيل التلقائي، حتى لا يبقى وصولٌ يدوي بلا إشعارٍ لأحد.
            app(\Modules\Client\Http\Controllers\VisitController::class)->sendVisitNotifications($visit, 'arrival');

            return response()->json(['success' => true, 'started_at' => $visit->started_at, 'elapsed_seconds' => $visit->elapsed_seconds]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'start_visit', $visit);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء بدء الزيارة.'], 500);
        }
    }

    /**
     * إيقاف/استئناف المؤقّت — بلا زرٍّ في الشاشة بعد اليوم: زرّا «إيقاف
     * مؤقت» و«استئناف» حُذفا، والمندوب لا يوقف مؤقّت زيارته بيده. الطريق
     * الوحيد إلى هنا هو الإيقاف التلقائي حين يخرج من نطاق موقع العميل
     * والاستئناف حين يعود (`wsHandleGeofence()` في القالب) — وهو ما يمنع
     * احتساب وقتٍ وهو بعيدٌ عن العميل.
     */
    public function togglePause(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($blocked = $this->blockUntilStarted($visit, $request)) {
            return $blocked;
        }

        try {
            if ($visit->isPaused()) {
                // بلا `true` صريحة تُعيد Carbon 3 فرقاً سالباً (paused_at
                // يسبق now() دائماً هنا) — فكانت مدّة الإيقاف الجديدة
                // تُطرَح من total_paused_seconds بدل أن تُضاف إليه في كل
                // استئناف، وقد تُنزله تحت الصفر
                $visit->update([
                    'total_paused_seconds' => $visit->total_paused_seconds + $visit->paused_at->diffInSeconds(now(), true),
                    'paused_at' => null,
                    'stage' => Visit::STAGE_IN_PROGRESS,
                ]);
            } else {
                $visit->update([
                    'paused_at' => now(),
                    'stage' => Visit::STAGE_PAUSED,
                ]);
            }

            OperationLog::record(
                $visit->isPaused() ? 'visit_paused' : 'visit_resumed',
                self::LOG_MODULE,
                $visit,
                ($visit->isPaused() ? 'إيقاف مؤقّت لزيارة العميل «' : 'استئناف زيارة العميل «').($visit->client->trade_name ?? $visit->client_id).'»'
            );

            return response()->json([
                'success' => true,
                'paused' => $visit->isPaused(),
                'elapsed_seconds' => $visit->elapsed_seconds,
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'toggle_pause_visit', $visit);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء تبديل حالة الإيقاف المؤقّت.'], 500);
        }
    }

    /**
     * ملاحظة الزيارة — **نافذةٌ واحدة بحفظٍ واحد**: المسؤول ودوامه والملاحظة
     * ورصد المنافسين معاً. كانت ثلاث نوافذ متتالية (الدوام، ثم الملاحظة، ثم
     * الرصد)، فطلب المستخدم أن «يسوي إضافة الملاحظة مع رصد المنافس وتعديل دوام
     * المسؤول».
     *
     * - **الدوام**: يُسجَّل هنا أوّل مرّة لمسؤولٍ بلا دوام، ويُعدَّل لمسؤولٍ
     *   دوامُه مسجَّل بصلاحية `clients_change_contact_shift` — الحارس نفسه في
     *   `storeContactShift()`. قيمةٌ لم تتغيّر لا تُعدّ تعديلاً. و`shift_start`/
     *   `shift_end` (إلزاميّان في `addnotes()`) يُملآن من الدوام المعتمد هنا لا
     *   من حقلٍ حرّ، فلا يُلتفّ على الصلاحية.
     * - **رصد المنافسين ليس هنا**: خطوةٌ تالية بمودالها المستقلّ
     *   (`storeCompetitorStep()`) — أُدمج هنا يوماً ثم أُعيد مستقلّاً بطلب المستخدم.
     * - **الدوام يُكتب بعد نجاح الملاحظة**: `addnotes()` تتحقّق بنفسها، ودوامٌ
     *   يُحفظ ثم تُرفض ملاحظتُه يغيّر سجلّ المسؤول بلا زيارة.
     */
    public function storeNote(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($blocked = $this->blockUntilStarted($visit, $request)) {
            return $blocked;
        }

        $workspaceUrl = route('clients.visitWorkspace', ['client' => $visit->client_id, 'visit' => $visit->id]);

        $request->merge(['client_id' => $visit->client_id]);

        $contact = ClientEmployeeNote::where('client_id', $visit->client_id)
            ->find($request->input('client_employee_id'));

        $newShift = null;
        if ($contact) {
            $submitted = trim((string) $request->input('shift', ''));

            if ($submitted !== '' && $submitted !== $contact->shift_range_value) {
                $newShift = ClientEmployeeNote::parseShiftRange($submitted);

                if ($newShift === null) {
                    return redirect($workspaceUrl)->with('error', 'فترة الدوام المختارة غير صالحة.');
                }

                if ($contact->hasShift() && ! hasPermission('clients_change_contact_shift')) {
                    return redirect($workspaceUrl)->with('error', 'دوام «'.$contact->employee_name.'» مسجَّل من قبل، وتغييره يحتاج صلاحية «تغيير دوام مسؤول العميل بعد تسجيله».');
                }
            } elseif (! $contact->hasShift()) {
                return redirect($workspaceUrl)->with('error', 'اختر دوام المسؤول «'.$contact->employee_name.'» — يُسجَّل مع هذه الملاحظة.');
            }

            [$shiftStart, $shiftEnd] = $newShift ?? [$contact->shift_start, $contact->shift_end];
            $request->merge(['shift_start' => $shiftStart, 'shift_end' => $shiftEnd]);
        }

        /* «عدد مستندات المنافسين» لا يُسأل عنه: رصد المنافسين خطوةٌ تالية
           بمودالها (`storeCompetitorStep()`) وهي من تكتب العدد الفعلي على
           هذه الملاحظة. الحقل ما زال إلزاميّاً في `addnotes()` — صفرٌ الآن. */
        $request->merge(['competitor_documents' => 0]);

        $response = app(ClientNoteController::class)->addnotes($request);

        if (! ($response instanceof RedirectResponse && session('success'))) {
            return $response;
        }

        if ($contact && $newShift) {
            $wasSet = $contact->hasShift();
            $contact->update(['shift_start' => $newShift[0], 'shift_end' => $newShift[1]]);

            OperationLog::record(
                $wasSet ? 'visit_contact_shift_changed' : 'visit_contact_shift_set',
                self::LOG_MODULE,
                $visit,
                ($wasSet ? 'تغيير دوام مسؤول العميل «' : 'تسجيل دوام مسؤول العميل «').$contact->employee_name.'» ('.$newShift[0].' — '.$newShift[1].') مع ملاحظة الزيارة'
            );
        }

        return redirect($workspaceUrl);
    }

    /** هل أرسل النموذج رصداً — منافساً مختاراً في صفّ، أو «لا يوجد منافس»؟ */
    private function hasCompetitorInput(Request $request): bool
    {
        return $request->boolean('no_competitors')
            || collect((array) $request->input('competitors', []))->contains(fn ($row) => ! empty($row['competitor_id'] ?? null));
    }

    /**
     * أوّلُ خطأٍ في رصد المنافسين، أو `null`.
     *
     * `$required`: لم يُرصَد في هذه الزيارة بعد — فصفٌّ أو «لا يوجد منافس»
     * واجب. وإلّا فالرصد اختياريٌّ (منافسٌ رآه المندوب في ملاحظةٍ لاحقة)، ويُتحقَّق
     * ممّا أُرسل فقط. «لا يوجد منافس» جوابٌ مشروع: يشهد أنّ المندوب نظر ولم يجد.
     */
    private function competitorValidationError(Request $request, bool $required): ?string
    {
        $noCompetitors = $request->boolean('no_competitors');

        // إذا اختار «لا يوجد منافس» — يجب صورة إثبات إلزامية
        if ($noCompetitors) {
            $validator = validator($request->all(), [
                'no_competitors_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.ClientSettingsHelper::maxUploadSizeKb()],
            ], [
                'no_competitors_photo.required' => 'صورة إثبات البحث إلزامية عند اختيار «لا يوجد منافس».',
                'no_competitors_photo.image' => 'ملف الصورة يجب أن يكون صورة فقط.',
                'no_competitors_photo.max' => 'حجم الصورة أكبر من المسموح.',
            ]);

            return $validator->fails() ? $validator->errors()->first() : null;
        }

        if (! $required && ! $this->hasCompetitorInput($request)) {
            return null;
        }

        $validator = validator($request->all(), [
            'competitors' => ['required', 'array', 'min:1'],
            'competitors.*.competitor_id' => [
                'required',
                Rule::exists('competitors', 'id')->where('is_active', true),
                'distinct',
            ],
            'competitors.*.asset_count' => ['required', 'integer', 'min:0'],
            // مرفقُ المنافس (صورة عهدته أو بضاعته) — إلزاميّ لكلّ صفٍّ فيه منافس.
            // «لا يوجد منافس» خرج من الدالة أعلاه، فلا يُطلب منه صورة.
            'competitors.*.photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.ClientSettingsHelper::maxUploadSizeKb()],
        ], [
            'competitors.*.photo.required' => 'صورة المنافس إلزامية — التقطها من زرّ الكاميرا في سطره.',
            'competitors.*.photo.image' => 'مرفق المنافس يجب أن يكون صورة.',
            'competitors.*.photo.max' => 'حجم صورة المنافس أكبر من المسموح.',
            'competitors.required' => 'اختر منافساً واحداً على الأقل وعدد قطع عهدته — أو أشِّر «لا يوجد منافس عند هذا العميل».',
            'competitors.*.competitor_id.required' => 'اختر المنافس في كل صفّ.',
            'competitors.*.competitor_id.exists' => 'أحد المنافسين المختارين غير موجودٍ أو معطَّل.',
            'competitors.*.competitor_id.distinct' => 'لا تختر المنافس نفسه مرّتين.',
            'competitors.*.asset_count.required' => 'اكتب عدد قطع عهدة كل منافس (0 إن لا عهدة له).',
        ]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    /**
     * يكتب رصد المنافسين مربوطاً بآخر ملاحظةٍ للمندوب في هذه الزيارة
     * (`client_relation_id`)، ويحدّث عددهم على الملاحظة (`competitor_documents`).
     * منافسٌ رُصد في هذه الزيارة من قبل لا يُكرَّر.
     */
    private function saveCompetitorObservations(Request $request, Visit $visit): void
    {
        $none = $request->boolean('no_competitors');

        $noteId = ClientRelation::where('client_id', $visit->client_id)
            ->where('employee_id', Auth::id())
            ->where('created_at', '>=', $visit->started_at)
            ->latest('id')
            ->value('id');

        /* الرصد يُحسب **للملاحظة** لا للزيارة: مندوبٌ ثانٍ (أو ملاحظةٌ ثانية)
           يرصد المنافس نفسه بعهدته وصورته هو — فالتكرار يُمنع داخل الملاحظة
           الواحدة فقط. بلا ملاحظةٍ للمستخدم في الزيارة: رصدُه هو فيها. */
        $noteScope = fn () => VisitCompetitorObservation::where('visit_id', $visit->id)
            ->when(
                $noteId,
                fn ($q) => $q->where('client_relation_id', $noteId),
                fn ($q) => $q->whereNull('client_relation_id')->where('employee_id', Auth::id())
            );

        DB::transaction(function () use ($request, $visit, $none, $noteId, $noteScope) {
            if ($none) {
                // «لا يوجد» مرّةً واحدة — لا يُكتب فوق رصدٍ سابقٍ في الملاحظة نفسها
                if ($noteScope()->exists()) {
                    return;
                }

                // حفظ صورة الإثبات عند اختيار «لا يوجد منافس»
                $photo = $request->file('no_competitors_photo');
                $photoPath = $photo ? $this->storeUploadedPhoto($photo, 'visit_competitors') : null;

                VisitCompetitorObservation::create([
                    'visit_id' => $visit->id,
                    'client_relation_id' => $noteId,
                    'client_id' => $visit->client_id,
                    'employee_id' => Auth::id(),
                    'competitor_id' => null,
                    'competitor_name' => 'لا يوجد منافس',
                    'asset_count' => 0,
                    'photo_path' => $photoPath,
                ]);

                return;
            }

            $rows = (array) $request->input('competitors', []);
            $names = Competitor::whereIn('id', array_column($rows, 'competitor_id'))->pluck('name', 'id');

            $already = $noteScope()
                ->whereNotNull('competitor_id')
                ->pluck('competitor_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($rows as $index => $row) {
                $competitorId = (int) ($row['competitor_id'] ?? 0);
                if ($competitorId === 0 || in_array($competitorId, $already, true)) {
                    continue;
                }

                // الملفّ من `$request->file()` لا من مصفوفة الإدخال — تلك لا تحمل الملفّات
                $photo = $request->file("competitors.$index.photo");

                VisitCompetitorObservation::create([
                    // نفس المجلّد الذي يقرؤه تبويب «المرفقات» في هذه الشاشة
                    'photo_path' => $photo ? $this->storeUploadedPhoto($photo, 'visit_competitors') : null,
                    'visit_id' => $visit->id,
                    'client_relation_id' => $noteId,
                    'client_id' => $visit->client_id,
                    'employee_id' => Auth::id(),
                    'competitor_id' => $competitorId,
                    // لقطةُ الاسم لحظتَه — إعادة تسميته لاحقاً لا تغيّر التقارير
                    'competitor_name' => $names[$competitorId] ?? '',
                    'asset_count' => (int) ($row['asset_count'] ?? 0),
                ]);
            }
        });

        if ($noteId) {
            ClientRelation::whereKey($noteId)->update([
                'competitor_documents' => VisitCompetitorObservation::where('client_relation_id', $noteId)
                    ->whereNotNull('competitor_id')
                    ->count(),
            ]);
        }

        OperationLog::record(
            'visit_competitors_observed',
            self::LOG_MODULE,
            $visit,
            $none
                ? 'رصد المنافسين للعميل #'.$visit->client_id.': لا يوجد منافس'
                : 'رصد '.count((array) $request->input('competitors', [])).' منافساً عند العميل #'.$visit->client_id
        );
    }

    /**
     * خطوة «رصد المنافسين» — بعد الملاحظة وقبل المستندات، بمودالها المستقلّ
     * (`#competitorStepModal`). منافس + عدد قطع عهدته + صورته الإلزامية، أو
     * «لا يوجد منافس عند هذا العميل» — جوابٌ مشروع يشهد أنّ المندوب نظر ولم يجد.
     * بحفظها تُفتح المستندات (`stepState()['docs_unlocked']`).
     */
    public function storeCompetitorStep(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($blocked = $this->blockUntilStarted($visit, $request)) {
            return $blocked;
        }

        $workspaceUrl = route('clients.visitWorkspace', ['client' => $visit->client_id, 'visit' => $visit->id]);

        if (! self::competitorCaptureEnabled()) {
            return redirect($workspaceUrl);
        }

        if (! $this->stepState($visit)['has_note']) {
            return redirect($workspaceUrl)->with('error', 'سجّل ملاحظة الزيارة أوّلاً — رصد المنافسين يأتي بعدها.');
        }

        if ($error = $this->competitorValidationError($request, true)) {
            return redirect($workspaceUrl)->with('error', $error);
        }

        try {
            $this->saveCompetitorObservations($request, $visit);

            return redirect($workspaceUrl)->with('success', 'تم رصد المنافسين — المستندات مفتوحة الآن.');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store_competitor_step', $visit);

            return redirect($workspaceUrl)->with('error', 'تعذّر حفظ رصد المنافسين.');
        }
    }

    /**
     * الخطوة الأولى: مسؤول العميل. «إذا لا يوجد للعميل موظف يُضاف له موظف،
     * وعند الحفظ يرجع في الزيارة ويكمل».
     *
     * تُنادى `ClientNoteController::storeEmployeeNote()` الحقيقية نفسها
     * (نفس ما تحفظه شاشة العميل) بدل نموذجٍ ومسارٍ ثانٍ لنفس الغرض — يبقى
     * سجلّ العمليات والنقاط وضغط الصورة كما هي في مكانٍ واحد. الدوام
     * يُسجَّل بعدها في خطوةٍ مستقلّة (`storeContactShift()`)، لا هنا:
     * السلسلة خطوتان لأن الطلب خطوتان.
     */
    public function storeContact(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($blocked = $this->blockUntilStarted($visit, $request)) {
            return $blocked;
        }

        return app(ClientNoteController::class)->storeEmployeeNote($request, $visit->client_id);
    }

    /**
     * الخطوة الثانية: دوام المسؤول — يُسجَّل على المسؤول نفسه فيُقرأ في كل
     * زيارةٍ تالية بدل أن يُعاد إدخاله في كل ملاحظة.
     *
     * تسجيلُه أوّل مرّة متاحٌ لكل من يفتح الشاشة (وإلا حُبست الزيارة كلّها
     * عند خطوةٍ لا يستطيع المندوب تجاوزها)، أمّا **تغييرُ** دوامٍ مسجَّلٍ من
     * قبل فيحتاج `clients_change_contact_shift`.
     */
    public function storeContactShift(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($blocked = $this->blockUntilStarted($visit, $request)) {
            return $blocked;
        }

        $validated = $request->validate([
            'client_employee_id' => [
                'required',
                Rule::exists('client_employee_notes', 'id')->where('client_id', $visit->client_id),
            ],
            // نفس قائمة `client::partials.shift-select` الواحدة: «HH:MM|HH:MM»
            'shift' => ['required', 'string', function ($attribute, $value, $fail) {
                if (ClientEmployeeNote::parseShiftRange($value) === null) {
                    $fail('فترة الدوام المختارة غير صالحة.');
                }
            }],
        ], [
            'client_employee_id.required' => 'اختر المسؤول الذي تسجّل دوامه.',
            'client_employee_id.exists' => 'هذا المسؤول لا يتبع هذا العميل.',
            'shift.required' => 'اختر فترة دوام المسؤول.',
        ]);

        $contact = ClientEmployeeNote::findOrFail($validated['client_employee_id']);

        if ($contact->hasShift() && ! hasPermission('clients_change_contact_shift')) {
            return redirect()->back()->with('error', 'دوام هذا المسؤول مسجَّل من قبل، وتغييره يحتاج صلاحية «تغيير دوام مسؤول العميل بعد تسجيله».');
        }

        [$start, $end] = ClientEmployeeNote::parseShiftRange($validated['shift']);

        try {
            $wasSet = $contact->hasShift();
            $contact->update(['shift_start' => $start, 'shift_end' => $end]);

            OperationLog::record(
                $wasSet ? 'visit_contact_shift_changed' : 'visit_contact_shift_set',
                self::LOG_MODULE,
                $visit,
                ($wasSet ? 'تغيير دوام مسؤول العميل «' : 'تسجيل دوام مسؤول العميل «').$contact->employee_name.'» ('.$start.' — '.$end.')'
            );

            return redirect()
                ->route('clients.visitWorkspace', ['client' => $visit->client_id, 'visit' => $visit->id])
                ->with('success', $wasSet ? 'تم تغيير دوام المسؤول.' : 'تم تسجيل دوام المسؤول — أكمل الزيارة.');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store_contact_shift', $visit);

            return redirect()->back()->with('error', 'حدث خطأ أثناء حفظ دوام المسؤول.');
        }
    }

    /**
     * فتح شاشة إنشاء مستندٍ من داخل الزيارة، بختم رابط العودة إليها في
     * الجلسة — `App\Http\Middleware\ReturnToVisitWorkspace` يقرؤه بعد
     * الحفظ فيُرجع المندوب إلى الزيارة بدل فهرس المستند.
     *
     * البوّابة هنا لا في الرابط وحده: أزرار المستندات في الشاشة مقفلةٌ حتى
     * تُسجَّل ملاحظة اليوم، وقفلُ الواجهة يزول بأداة مطوّرين.
     */
    /**
     * منافسٌ جديد يُضاف من داخل الزيارة — «إذا جاء منافس جديد».
     *
     * الكتالوج يُدار من «العملاء ← المنافسون»، لكنّ المندوب في الميدان يرى
     * منافساً ليس في القائمة وهو يكتب ملاحظته: إرسالُه ليطلب من الإدارة
     * إضافته يعني ملاحظةً تُحفظ بلا رصده، أو زيارةً تُقفل ناقصة. فيضيفه من
     * هنا ويختاره في السطر نفسه.
     *
     * بصلاحية **الملاحظات** لا بصلاحية كتالوج المنافسين: من يكتب ملاحظة
     * الزيارة يجب أن يستطيع تسمية منافسٍ رآه فيها. الحارس الأوّل كان
     * `clients_competitors_add` — صلاحيةُ شاشة الإدارة — فكان المندوب يرى
     * منافساً جديداً ولا سبيل له إلا ترك الرصد ناقصاً. شاشةُ الكتالوج تبقى
     * محروسةً بصلاحياتها الأربع.
     */
    public function storeQuickCompetitor(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($blocked = $this->blockUntilStarted($visit, $request)) {
            return $blocked;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'اكتب اسم المنافس.',
        ]);

        $name = trim($validated['name']);

        /* اسمٌ موجود: لا نُنشئ ثانياً ولا نرفض بلا فائدة.
           - مفعَّلٌ ⇒ نُعيده كما هو، فهو في القائمة أصلاً (المندوب لم يجده
             لأنّه كتب صيغةً أخرى).
           - معطَّلٌ ⇒ نرفض ولا نُعيد تفعيله: التعطيل قرارُ إدارةٍ لا يُنقض
             من شاشة زيارة. */
        $existing = Competitor::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing) {
            if (! $existing->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنافس «'.$existing->name.'» معطَّل في القائمة — راجع مدير النظام لتفعيله.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'existed' => true,
                'competitor' => ['id' => $existing->id, 'name' => $existing->name],
                'message' => 'المنافس «'.$existing->name.'» موجودٌ في القائمة — اخترناه لك.',
            ]);
        }

        try {
            $competitor = Competitor::create([
                'name' => $name,
                'is_active' => true,
                'notes' => 'أُضيف من زيارة العميل #'.$visit->client_id,
            ]);

            OperationLog::record(
                'competitor_added_from_visit',
                self::LOG_MODULE,
                $visit,
                'إضافة منافس «'.$competitor->name.'» من مساحة عمل الزيارة'
            );

            return response()->json([
                'success' => true,
                'existed' => false,
                'competitor' => ['id' => $competitor->id, 'name' => $competitor->name],
                'message' => 'أُضيف «'.$competitor->name.'» إلى قائمة المنافسين.',
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store_quick_competitor', $visit);

            return response()->json(['success' => false, 'message' => 'تعذّر إضافة المنافس.'], 500);
        }
    }

    public function openDocument(Request $request, $visitId, string $type)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($blocked = $this->blockUntilStarted($visit, $request)) {
            return $blocked;
        }

        $steps = $this->stepState($visit);
        if (! $steps['docs_unlocked']) {
            return redirect()->back()->with('error', 'سجّل مسؤول العميل ودوامه وملاحظة الزيارة'
                .($steps['competitor_step'] ? ' ورصد المنافسين' : '').' قبل فتح المستندات.');
        }

        $targets = [
            'invoice' => fn () => route('invoices.create', ['client_id' => $visit->client_id]),
            'receipt' => fn () => route('incomes.create', ['client_id' => $visit->client_id]),
            // سند صرف لعميل — يُفتح بنوع «لعميل» وحسابه مختاراً (`ExpensesController::create()`)
            'expense' => fn () => route('expenses.create', ['client_id' => $visit->client_id]),
            'credit-note' => fn () => route('CreditNotes.create', ['client_id' => $visit->client_id]),
            'exchange' => fn () => route('product-exchanges.create', ['client_id' => $visit->client_id]),
        ];

        /* مرتجعُ فاتورةٍ من «مستندات هذه الزيارة» (`?invoice=`): الفاتورة يجب
           أن تكون لعميل هذه الزيارة وغيرَ مرتجعٍ هي نفسها — رابطٌ مصنوعٌ
           بمعرّف فاتورةِ عميلٍ آخر لا يفتح مرتجعها من هنا. */
        if ($type === 'return') {
            $invoice = Invoice::where('client_id', $visit->client_id)
                ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'returned'))
                ->find($request->integer('invoice'));

            abort_unless($invoice, 404, 'الفاتورة ليست لعميل هذه الزيارة.');

            $targets['return'] = fn () => route('ReturnIInvoices.create', ['id' => $invoice->id]);
        }

        abort_unless(isset($targets[$type]), 404);

        $request->session()->put(
            \App\Http\Middleware\ReturnToVisitWorkspace::SESSION_KEY,
            route('clients.visitWorkspace', ['client' => $visit->client_id, 'visit' => $visit->id])
        );

        return redirect($targets[$type]());
    }

    public function complete(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        if ($blocked = $this->blockUntilStarted($visit, $request)) {
            return $blocked;
        }

        // بطاقة التوقيع قابلة للإخفاء من الإعدادات (visit_workspace_sections_settings.show_signature_section)
        // — إخفاؤها يُسقط الإلزام هنا أيضاً، لا الواجهة وحدها
        $signatureRequired = ClientSettingsHelper::visitWorkspaceSectionVisible('show_signature_section');

        /*
            لا `outcome` في الطلب: اختيار النتيجة اليدوي («تم بنجاح / تمت
            جزئياً / لم تتم») حُذف من الشاشة كلّها. النتيجة تُستنتج مما جرى
            فعلاً — فاتورةٌ أو تحصيلٌ اليوم ⇐ ناجحة، وإلا ⇐ سلبيّة — فلا
            يصف المندوب زيارته بنفسه.
        */
        $validated = $request->validate([
            'client_signature' => [$signatureRequired ? 'required' : 'nullable', 'string'],
            'employee_signature' => [$signatureRequired ? 'required' : 'nullable', 'string'],
            'outcome_notes' => 'nullable|string|max:2000',
            /* «العميل قال ارجع المغرب»: زيارةٌ ثانيةٌ لنفس العميل في اليوم
               نفسه. تُنهى هذه الزيارة بمدّتها هي، ويُفتح للمندوب صفُّ خطّ
               سيرٍ ثانٍ اليوم — فيبقى العميل ظاهراً في خريطته بدل أن يختفي
               كمن انتهى دوره. راجع `EmployeeClientVisit::finishedTodayClientIdsFor()`. */
            'return_later' => 'nullable|boolean',
            // الوقت إلزاميٌّ متى اختار العودة: «سأعود» بلا ساعةٍ لا تُجدوِل
            // شيئاً ولا تُذكِّر بشيء — صفٌّ مفتوحٌ بلا موعد
            'return_time' => 'required_if:return_later,1,true|nullable|date_format:H:i',
            'current_latitude' => [self::locationRule($visit), 'numeric', 'between:-90,90'],
            'current_longitude' => [self::locationRule($visit), 'numeric', 'between:-180,180'],
        ], [
            'current_latitude.required' => 'يجب تحديد موقعك الحالي لإنهاء الزيارة — فعّل GPS.',
            'current_longitude.required' => 'يجب تحديد موقعك الحالي لإنهاء الزيارة — فعّل GPS.',
            'return_time.required_if' => 'حدّد وقت العودة المتوقَّع لهذا العميل.',
        ]);

        // نفس بوّابة الخطوات التي تقرؤها الشاشة — قفلُ الأزرار هناك بصريٌّ
        // يزول بأداة مطوّرين، وهذه هي البوّابة الفعلية
        /* «سأعود لهذا العميل» يكفيه المسؤول ودوامه والملاحظة؛ الإنهاء التامّ
           يشترط رصد المنافسين كذلك. */
        $steps = $this->stepState($visit);
        $returnLaterRequested = $request->boolean('return_later');
        if (! ($returnLaterRequested ? $steps['can_return'] : $steps['can_complete'])) {
            $missing = ! $steps['has_contact']
                ? 'أضِف مسؤول العميل أولاً.'
                : (! $steps['has_shift'] ? 'سجّل دوام المسؤول أولاً.'
                    : (! $steps['has_note'] ? 'سجّل ملاحظة الزيارة أولاً.'
                        : 'سجّل رصد المنافسين أولاً — أو اختر «سأعود لهذا العميل اليوم».'));

            return redirect()->back()->with('error', 'لا يمكن إنهاء الزيارة قبل إتمام خطواتها: '.$missing);
        }

        if ($blocked = $this->outOfRangeMessage($visit, (float) ($validated['current_latitude'] ?? 0), (float) ($validated['current_longitude'] ?? 0), 'لإنهاء الزيارة')) {
            return redirect()->back()->with('error', $blocked);
        }

        try {
            $visit->update([
                'departure_time' => now(),
                'stage' => Visit::STAGE_COMPLETED,
                'outcome' => $steps['outcome'],
                'outcome_notes' => $validated['outcome_notes'] ?? $visit->outcome_notes,
                'client_signature_path' => $this->storeSignature($validated['client_signature'] ?? '', 'client'),
                'employee_signature_path' => $this->storeSignature($validated['employee_signature'] ?? '', 'employee'),
            ]);

            // المدّة المعروضة في سجلّ العميل هي المدّة الفعلية — بعد خصم
            // الإيقاف المؤقّت (`Visit::$elapsed_seconds`)، لا الفرق الخام
            // بين البدء والانصراف. تُقرأ **بعد** التحديث أعلاه كي تلتقط
            // `departure_time` الذي كُتب لتوّه بدل `now()`.
            EmployeeClientVisit::markCompletedFor(
                (int) $visit->employee_id,
                (int) $visit->client_id,
                $visit->visit_date,
                (int) round($visit->fresh()->elapsed_seconds / 60)
            );

            // نقاط اكتمال خطّ السير — كانت تُقيَّم بعد الملاحظة يوم كانت
            // الملاحظة تُكمل الزيارة؛ الإكمال صار هنا فالتقييم معه
            try {
                app(\App\Services\PointsService::class)->evaluateItineraryCompletion(
                    (int) $visit->employee_id,
                    now()->toDateString()
                );
            } catch (\Exception $e) {
                \Log::warning('فشل تقييم اكتمال خط السير بعد إنهاء الزيارة: '.$e->getMessage());
            }

            $returnLater = $request->boolean('return_later');
            if ($returnLater) {
                $this->scheduleReturnVisit($visit, $validated['return_time'] ?? null);
            }

            OperationLog::record(
                'visit_completed',
                self::LOG_MODULE,
                $visit,
                'إنهاء زيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'» — '
                    .($steps['outcome'] === 'success' ? 'ناجحة (فاتورة أو تحصيل)' : 'سلبيّة (بلا فاتورة ولا تحصيل)')
            );

            // إشعارات داخلية (للموظف وللمدراء) — نفس ما يصل في وضع
            // التسجيل التلقائي، حتى لا يبقى انصرافٌ يدوي بلا إشعارٍ لأحد.
            app(\Modules\Client\Http\Controllers\VisitController::class)->sendVisitNotifications($visit, 'departure');

            /* بعد الإنهاء يعود المندوب إلى **فهرس العملاء** لا إلى شاشة
               العميل: الزيارة انتهت، وشاشةُ العميل ليست محطّته التالية —
               محطّته العميل التالي في خطّ سيره. ومن عليه العودة إلى هذا
               العميل يدخل عليه بزيارةٍ جديدة من الفهرس، لا بتصفّح ملفّه. */
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'redirect' => route('clients.index')]);
            }

            $message = $steps['outcome'] === 'success'
                ? 'تم إنهاء الزيارة — زيارة ناجحة (سُجّلت فاتورة أو تحصيل).'
                : 'تم إنهاء الزيارة — زيارة سلبيّة (بلا فاتورة ولا تحصيل).';

            if ($returnLater) {
                $message .= ' وسُجّلت لك عودةٌ إليه اليوم — يبقى ظاهراً في خريطتك حتى تُنهي الزيارة الثانية.';
            } elseif (ClientSettingsHelper::hideFinishedVisitClients() && ! hasPermission('clients_view_all_clients')) {
                $message .= ClientSettingsHelper::allowReturnFinishedClientSameDay()
                    ? ' أُخفي العميل من قائمتك بقيّة اليوم — لإرجاعه لخط سيرك افتح «أُنهيت زياراتهم اليوم» أعلى القائمة.'
                    : ' أُخفي العميل من قائمتك بقيّة اليوم.';
            }

            return redirect()->route('clients.index')->with('success', $message);
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'complete_visit', $visit);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء إنهاء الزيارة.'], 500);
            }

            return redirect()->back()->with('error', 'حدث خطأ أثناء إنهاء الزيارة.');
        }
    }

    /**
     * صفُّ خطّ سيرٍ ثانٍ لليوم نفسه — «سأعود لاحقاً».
     *
     * صفٌّ جديد لا تحديثٌ للقائم: القائم مكتملٌ بمدّة الزيارة الأولى، وهو ما
     * يجب أن يبقى. والجدول بلا فهرسٍ فريد على (موظف، عميل، يوم) فالصفّان
     * يتعايشان — وكلٌّ منهما يحمل مدّة زيارته هو.
     *
     * وقتُ العودة في عمودٍ خاصٍّ به (`planned_time`) لا في `visit_date`:
     * النموذج يصبّ الأخير `date` لا `datetime`، فالجزء الزمني يُقصّ بصمت —
     * تُكتب 18:30 وتُقرأ 00:00.
     */
    private function scheduleReturnVisit(Visit $visit, ?string $returnTime): void
    {
        try {
            $this->createReturnItineraryRow((int) $visit->employee_id, (int) $visit->client_id, $returnTime);

            OperationLog::record(
                'visit_return_scheduled',
                self::LOG_MODULE,
                $visit,
                'جدولة عودة إلى العميل «'.($visit->client->trade_name ?? $visit->client_id).'»'
                    .($returnTime ? ' الساعة '.$returnTime : ' اليوم')
            );
        } catch (\Exception $e) {
            // الزيارة انتهت وحُفظت — فشلُ جدولة العودة لا يُبطلها
            OperationLog::failure($e, self::LOG_MODULE, 'schedule_return_visit', $visit);
        }
    }

    /**
     * صفُّ خطّ سيرٍ مفتوحٌ لليوم — ما يُرجع العميل ظاهراً للمندوب.
     *
     * `EmployeeClientVisit::finishedTodayClientIdsFor()` لا يُخفي عميلاً له
     * صفٌّ غير مكتملٍ اليوم، فهذا الصفّ وحده يكفي لإظهاره في الخريطة والفهرس
     * ولإعادة «تسجيل زيارة» بدل تحويل `clients.show` إلى الفهرس. مشتركٌ بين
     * «سأعود لهذا العميل اليوم» عند الإنهاء و«إرجاع لخط السير» بعده.
     */
    private function createReturnItineraryRow(int $employeeId, int $clientId, ?string $plannedTime): void
    {
        $now = now();
        $weekInfo = \App\Helpers\WeekHelper::getWeekAndYear($now);

        EmployeeClientVisit::create([
            'employee_id' => $employeeId,
            'client_id' => $clientId,
            'day_of_week' => strtolower(\App\Helpers\WeekHelper::getCurrentDayNameEn($now)),
            'year' => $weekInfo['year'],
            'week_number' => $weekInfo['week'],
            'visit_date' => $now->copy(),
            'planned_time' => $plannedTime,
            // `unactive` لا `active`: العودةُ لم تتمّ بعد، والعمودُ القديم تقرؤه
            // شاشاتٌ «مكتملة» — كان صفُّ العودة (وما أُجِّل منه ليومٍ آخر) يظهر مكتملاً
            'status' => 'unactive',
            'visit_status' => \App\Enums\VisitStatus::PLANNED,
        ]);
    }

    /**
     * «إرجاع لخط السير» — عميلٌ أنهى المندوب زيارته اليوم فأُخفي، ويريد العودة
     * إليه في اليوم نفسه دون أن يكون قد اختار «سأعود» عند الإنهاء.
     *
     * لا يُرجع إلا عميلاً انتهى دوره اليوم فعلاً عند هذا المستخدم: رقمٌ مصنوعٌ
     * لعميلٍ لم يزره يفتح له صفّ خطّ سيرٍ لم يُسنَد إليه. والإعداد يُفحص هنا لا
     * في الشاشة وحدها.
     */
    public function returnToItinerary(Request $request, $clientId): RedirectResponse
    {
        abort_unless(ClientSettingsHelper::allowReturnFinishedClientSameDay(), 403, 'إرجاع العميل لخط السير غير مفعَّل في الإعدادات.');

        $userId = (int) Auth::id();
        $client = Client::findOrFail($clientId);

        if (! EmployeeClientVisit::finishedTodayClientIdsFor($userId)->contains((int) $client->id)) {
            return redirect()->back()->with('error', 'هذا العميل ليس ممّن أنهيت زيارتهم اليوم.');
        }

        $this->createReturnItineraryRow($userId, (int) $client->id, null);

        OperationLog::record(
            'visit_client_returned_to_itinerary',
            self::LOG_MODULE,
            $client,
            'إرجاع العميل «'.$client->trade_name.'» لخط السير اليوم بعد إنهاء زيارته'
        );

        return redirect()->back()->with('success', 'أُرجع «'.$client->trade_name.'» لخط سيرك اليوم — يمكنك زيارته من جديد.');
    }

    public function cancel(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $this->authorizeVisitOwner($visit);

        /* الإلغاء كان الثغرة الأخيرة: يُقفل الزيارة ويكتب `departure_time`
           بلا أيّ فحص موقع — فتُلغى زيارةٌ من البيت بحجّة «المحل مغلق».
           صار يشترط الموقع كالإنهاء تماماً، والسببُ إلزاميّاً: إلغاءٌ بلا
           تفسيرٍ لا يبقى منه شيءٌ في التقرير. */
        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:255',
            'current_latitude' => [self::locationRule($visit), 'numeric', 'between:-90,90'],
            'current_longitude' => [self::locationRule($visit), 'numeric', 'between:-180,180'],
        ], [
            'cancel_reason.required' => 'اكتب سبب إلغاء الزيارة — هو كلّ ما يبقى منها في التقرير.',
            'current_latitude.required' => 'يجب تحديد موقعك الحالي لإلغاء الزيارة — فعّل GPS.',
            'current_longitude.required' => 'يجب تحديد موقعك الحالي لإلغاء الزيارة — فعّل GPS.',
        ]);

        if ($blocked = $this->outOfRangeMessage($visit, (float) ($validated['current_latitude'] ?? 0), (float) ($validated['current_longitude'] ?? 0), 'لإلغاء الزيارة')) {
            return redirect()->back()->with('error', $blocked);
        }

        try {
            $visit->update([
                'departure_time' => now(),
                'stage' => Visit::STAGE_CANCELLED,
                'cancel_reason' => $validated['cancel_reason'],
                // موقع المندوب لحظة الإلغاء — يبقى في التقرير شاهداً على المكان
                'employee_latitude' => $validated['current_latitude'] ?? null,
                'employee_longitude' => $validated['current_longitude'] ?? null,
            ]);

            OperationLog::record(
                'visit_cancelled',
                self::LOG_MODULE,
                $visit,
                'إلغاء زيارة العميل «'.($visit->client->trade_name ?? $visit->client_id).'»'
            );

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'redirect' => route('clients.index')]);
            }

            // الفهرس لا شاشة العميل — نفس ما ينتهي إليه `complete()`: الزيارة
            // انتهت (ولو بالإلغاء)، ومحطّةُ المندوب التالية عميلٌ آخر
            return redirect()->route('clients.index')->with('warning', 'تم إلغاء الزيارة.');
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'cancel_visit', $visit);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء إلغاء الزيارة.'], 500);
            }

            return redirect()->back()->with('error', 'حدث خطأ أثناء إلغاء الزيارة.');
        }
    }

    /**
     * صاحب الزيارة، أو من يملك صلاحية تتبّع زيارات العملاء (المدير/المشرف
     * عبر `clients_visits_track` — نفس الصلاحية التي تحرس `VisitController`
     * وشاشة «زيارات اليوم»)، أو دور admin. الاعتماد على اسم دور واحد فقط
     * كان يحجب حتى «المدير» عن فتح زيارة موظّفه.
     */
    private function authorizeVisitOwner(Visit $visit): void
    {
        $isOwner = (int) $visit->employee_id === (int) Auth::id();
        $canTrackVisits = hasPermission('clients_visits_track');
        $isAdmin = Auth::user()->hasRole('admin');

        abort_unless($isOwner || $canTrackVisits || $isAdmin, 403, 'لا يمكنك الوصول إلى زيارة لا تخصك.');
    }

    /**
     * لا إجراءَ في مساحة العمل قبل «بدء الزيارة».
     *
     * القفل في `visit_workspace.blade.php` بصريٌّ فقط (تعطيلُ عناصرَ
     * بـ JS) — يسقط بأوّل طلبٍ مصنوعٍ يدوياً أو بتبويبٍ بقي مفتوحاً من
     * جلسةٍ سابقة. فكلُّ مسارٍ يكتب شيئاً هنا يمرّ من هذه البوّابة:
     * `started_at` هو الدليل الوحيد أنّ الموظف كان فعلاً عند موقع العميل
     * (تتحقّق منه `start()` بالمسافة)، فأيّ ملاحظةٍ أو جردٍ أو نتيجةٍ قبله
     * بيانٌ بلا موقعٍ يسنده.
     *
     * `cancel()` مستثناةٌ عمداً: إلغاء زيارةٍ لم تبدأ هو المخرج الوحيد
     * لمن وصل الشاشة ثم تعذّر عليه الدخول على العميل.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|null
     */
    private function blockUntilStarted(Visit $visit, Request $request)
    {
        if ($visit->started_at) {
            return null;
        }

        $message = 'لم تبدأ الزيارة بعد — اضغط «بدء الزيارة» من موقع العميل قبل تنفيذ أي إجراء.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return redirect()->back()->with('error', $message);
    }

    /**
     * هل يُعفى المستخدم من فحص الموقع في بدء الزيارة وإنهائها وإلغائها؟
     *
     * `clients_visit_any_location` — على نمط `client_follow_up_add_notes_from_any_location`
     * التي تُعفي الملاحظة: الفحص يُسقط، والإحداثيات تصير اختياريّة (تُختم
     * مع الزيارة إن وصلت، ولا يُرفض الطلب إن غابت).
     */
    public static function canVisitFromAnyLocation(): bool
    {
        return hasPermission('clients_visit_any_location');
    }

    /**
     * إعفاءُ **العميل نفسه** من فحص الموقع — لا الموظّف: زرّ «استبعاد الموقع»
     * في ملفّ العميل (`ClientController::toggleExemptLocation()`، يحرسه
     * `clients_manage_exemptions`) يكتب `clients.exempt_visit_location`،
     * وكان هذا العمودُ يُقرأ فقط لفكّ قفل أزرار شاشة العميل
     * (`ClientController::show()`) — أيّ موظّفٍ يبدأ زيارة هذا العميل أو
     * ينهيها أو يلغيها أو يسجّل ملاحظتها كان لا يزال يُحبَس بفحص الموقع
     * رغم الاستبعاد. تُقرأ هنا الآن أيضاً، بنفس شرط تفعيل الميزة
     * (`exempt_location_enabled` في إعدادات وحدات العملاء).
     */
    public static function isClientExemptFromLocation(?Client $client): bool
    {
        return $client !== null
            && \App\Models\ClientModuleSetting::current()->exempt_location_enabled
            && $client->isExemptFromVisitLocation();
    }

    /** `required` لمن يُلزَم بالموقع، و`nullable` لمن يُعفى منه — بصلاحيته أو بإعفاء العميل. */
    private static function locationRule(?Visit $visit = null): string
    {
        $exempt = self::canVisitFromAnyLocation() || self::isClientExemptFromLocation($visit?->client);

        return $exempt ? 'nullable' : 'required';
    }

    /**
     * إحداثيات العميل التي تُقاس عليها المسافة: لقطةُ الزيارة أولاً، وإلا
     * فموقع العميل الحالي.
     *
     * السقوط إلى `Client::locations` ليس تزيّداً: زيارةٌ أُنشئت قبل أن
     * يُسجَّل موقع العميل تحمل إحداثيَّين فارغين إلى الأبد (راجع
     * `registerVisit()`/`open()`) — فلو اكتُفي بلقطتها لبقي فحصُ القرب
     * معطَّلاً عليها حتى بعد أن يُضاف الموقع.
     *
     * @return array{0: float, 1: float}|null
     */
    private function clientCoordinatesFor(Visit $visit): ?array
    {
        if ($visit->client_latitude && $visit->client_longitude) {
            return [(float) $visit->client_latitude, (float) $visit->client_longitude];
        }

        $location = optional($visit->client)->locations;

        if ($location && $location->latitude && $location->longitude) {
            return [(float) $location->latitude, (float) $location->longitude];
        }

        return null;
    }

    /**
     * «ما يعمل إنهاء زيارة من أي مكان»: يمنع أيّ إجراءٍ يقع خارج نطاق موقع
     * العميل.
     *
     * وحين **لا موقعَ للعميل إطلاقاً** يمنع كذلك، ولا يمرّ صامتاً: كان
     * الفحص كلُّه داخل `if ($visit->client_latitude && …)` — فعميلٌ بلا
     * موقعٍ مسجَّل تُبدأ زيارتُه وتُنهى من أيّ مكانٍ في العالم، وهي الثغرة
     * الوحيدة التي كانت تُفرغ الفحص من معناه. المخرج لمن علق: «إلغاء
     * الزيارة» — لا تشترط موقعاً عمداً.
     *
     * @return string|null رسالةُ المنع، أو null إن كان ضمن النطاق
     */
    private function outOfRangeMessage(Visit $visit, float $lat, float $lng, string $action): ?string
    {
        // صلاحية «من أي موقع» أو استبعاد العميل نفسه من الموقع — كلاهما يُسقط
        // الفحص كلَّه، ومنه منعُ العميل بلا موقع
        if (self::canVisitFromAnyLocation() || self::isClientExemptFromLocation($visit->client)) {
            return null;
        }

        $coordinates = $this->clientCoordinatesFor($visit);

        if ($coordinates === null) {
            return 'لا يوجد موقعٌ مسجَّل لهذا العميل، فلا يمكن التحقّق من وجودك عنده. '
                .'حدّث بيانات العميل بموقعه على الخريطة، أو ألغِ الزيارة.';
        }

        $distance = $this->calculateDistance($lat, $lng, $coordinates[0], $coordinates[1]);
        $maxDistance = ClientSettingsHelper::visitProximityRadiusKmFor($visit->employee);

        if ($distance > $maxDistance) {
            return sprintf(
                'يجب أن تكون ضمن نطاق %.1f كم من موقع العميل %s. المسافة الحالية: %.2f كم.',
                $maxDistance,
                $action,
                $distance
            );
        }

        return null;
    }

    /** نفس صيغة الحساب المستعملة في `ClientController::calculateDistance()` — النتيجة بالكيلومتر. */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (is_null($lat1) || is_null($lon1) || is_null($lat2) || is_null($lon2)) {
            return 0;
        }

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * asin(sqrt($a));

        return (6371000 * $c) / 1000;
    }

    /**
     * تُستعمل لصور المنافسين وحدها — تحفَظ مضغوطةً بنفس أسلوب مرفقات
     * الملاحظة (`ClientNoteController::compressAndSaveImage()`)، بعدما كانت
     * تُنقَل بحجمها الأصلي من كاميرا الجوّال مباشرةً (قد يتجاوز عدّة
     * ميغابايت لكل صورة منافس) فتُثقِل الصفحة عند عرضها لاحقاً.
     */
    private function storeUploadedPhoto($file, string $folder): string
    {
        $destination = public_path('assets/uploads/'.$folder);
        if (! file_exists($destination)) {
            @mkdir($destination, 0755, true);
        }

        if (in_array($file->getMimeType(), ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            $filename = time().'_'.Str::random(8).'.jpg';

            $originalSize = $file->getSize();
            $quality = 75;
            if ($originalSize > 10 * 1024 * 1024) {
                $quality = 40;
            } elseif ($originalSize > 5 * 1024 * 1024) {
                $quality = 50;
            } elseif ($originalSize > 2 * 1024 * 1024) {
                $quality = 60;
            }

            if ($this->compressAndSaveImage($file->getRealPath(), $destination.'/'.$filename, $quality)) {
                return $filename;
            }
        }

        $filename = time().'_'.Str::random(8).'.'.$file->getClientOriginalExtension();
        $file->move($destination, $filename);

        return $filename;
    }

    /** ضغط الصورة وتحجيمها وحفظها — نسخة `ClientNoteController::compressAndSaveImage()` بالحرف. */
    private function compressAndSaveImage($sourceFile, $destinationPath, $quality = 70)
    {
        try {
            $imageInfo = getimagesize($sourceFile);
            if (! $imageInfo) {
                return false;
            }

            $mime = $imageInfo['mime'];
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];

            $image = match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($sourceFile),
                'image/png' => imagecreatefrompng($sourceFile),
                'image/gif' => imagecreatefromgif($sourceFile),
                default => imagecreatefromjpeg($sourceFile),
            };

            $maxDimension = 1920;
            if ($originalWidth > $maxDimension || $originalHeight > $maxDimension) {
                $ratio = min($maxDimension / $originalWidth, $maxDimension / $originalHeight);
                $newWidth = intval($originalWidth * $ratio);
                $newHeight = intval($originalHeight * $ratio);

                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
                imagedestroy($image);
                $image = $resizedImage;
            }

            if (pathinfo($destinationPath, PATHINFO_EXTENSION) === 'png') {
                imagepng($image, $destinationPath, intval($quality / 10));
            } elseif (pathinfo($destinationPath, PATHINFO_EXTENSION) === 'gif') {
                imagegif($image, $destinationPath);
            } else {
                imagejpeg($image, $destinationPath, intval($quality));
            }

            imagedestroy($image);

            return true;
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'compressAndSaveImage');

            return false;
        }
    }

    private function storeSignature(string $dataUrl, string $who): ?string
    {
        if (! Str::startsWith($dataUrl, 'data:image')) {
            return null;
        }

        $destination = public_path('assets/uploads/visit_signatures');
        if (! file_exists($destination)) {
            @mkdir($destination, 0755, true);
        }

        [, $encoded] = explode(',', $dataUrl, 2) + [null, null];
        if (! $encoded) {
            return null;
        }

        $filename = 'sig_'.$who.'_'.time().'_'.Str::random(6).'.png';
        file_put_contents($destination.'/'.$filename, base64_decode($encoded));

        return $filename;
    }
}
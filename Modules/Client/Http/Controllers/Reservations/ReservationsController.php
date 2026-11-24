<?php

namespace Modules\Client\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Log as ActivityLog;
use App\Models\TaxSitting;
use App\Services\Sales\SalesDocumentMailer;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\LanguageSettings;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Http\Controllers\Reservations\Concerns\ScopesReservations;
use Modules\Client\Http\Requests\Reservations\StoreReservationRequest;
use Modules\Client\Http\Requests\Reservations\UpdateReservationRequest;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationService as BookableService;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Services\EmployeeShiftSchedule;
use Modules\Client\Reservations\Services\ReservationAppointmentSync;
use Modules\Client\Reservations\Services\ReservationAvailabilityService;
use Modules\Client\Reservations\Services\ReservationBookingService;
use Modules\Client\Reservations\Services\ReservationPricingService;
use Modules\Client\Reservations\Services\ReservationStatusTransitionService;
use Modules\Client\Reservations\Services\ServiceProductCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * شاشاتُ الحجز: القائمة والتصدير والتقويم والنموذج والعرض.
 *
 * ═══════════ ما لا يفعله هذا المتحكّم ═══════════
 *
 * **لا يكتب حجزاً بيده.** كلُّ إنشاءٍ وتعديلٍ يمرّ بـ
 * `ReservationBookingService` — لأنّ الكتابةَ تحتاج معاملةً وقفلاً وفحصَ
 * توفّرٍ وترقيماً وسجلّاً، ولأنّ الـAPI ونقطةَ البيع وأمرَ الكرون تكتب
 * الحجزَ نفسَه ولا تمرّ من هنا.
 *
 * ═══════════ ونداءاتُ AJAX محروسةٌ كالشاشات ═══════════
 *
 * `slots` و`serviceEmployees` و`quote` و`clients` كانت خارج كلّ قائمة
 * `only[]` — فيكفي تسجيلُ الدخول للبحث في عملاء المنشأة كلِّها بأسمائهم
 * وجوّالاتهم. صارت على صلاحيات الشاشات التي تناديها.
 */
class ReservationsController extends Controller
{
    use ScopesReservations;

    public function __construct(
        protected ReservationBookingService $booking,
        protected ReservationAvailabilityService $availability,
        protected ReservationPricingService $pricing,
        protected ReservationStatusTransitionService $transitions,
    ) {
        $this->middleware('permission:reservations.view', ['only' => ['index', 'clientHistory']]);
        $this->middleware('permission:reservations.calendar', ['only' => ['calendar', 'calendarFeed']]);
        // الإرسالُ بالبريد على صلاحية العرض: من يرى الحجزَ يرسله لصاحبه
        $this->middleware('permission:reservations.show', ['only' => ['show', 'print', 'sendEmail']]);
        $this->middleware('permission:reservations.create_invoice', ['only' => ['toInvoice']]);
        $this->middleware('permission:reservations.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:reservations.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:reservations.delete', ['only' => ['destroy']]);
        $this->middleware('permission:reservation_reports.export', ['only' => ['export']]);

        // نداءاتُ النماذج — نموذجُ الإنشاء والتعديل ومودالُ إعادة الجدولة
        $this->middleware('permission:reservations.create|reservations.edit|reservations.reschedule', ['only' => ['slots', 'serviceEmployees', 'quote', 'serviceProducts']]);
        // بحثُ العملاء — فلترُ القائمة ونموذجا الحجز
        $this->middleware('permission:reservations.view|reservations.create|reservations.edit', ['only' => ['clients']]);
    }

    // ════════════════════ القائمة ════════════════════

    public function index(Request $request)
    {
        $reservations = $this->applySort($this->applyFilters($this->scopedQuery(), $request), $request)
            /*
                التحميلُ المسبق يمنع N+1: القائمةُ تعرض اسمَ العميل والخدمة
                والموظّف والفرع لكل صفّ — أربعةَ استعلاماتٍ في الصفّ الواحد
                بلا هذا السطر.
            */
            ->with([
                'client:id,trade_name,first_name,last_name,code,phone,mobile',
                'employee:id,first_name,middle_name,nickname',
                'service:id,name,product_id', 'service.product:id,name',
                'branch:id,name',
                'product:id,name',
                // شارةُ «مفوتَر» من الفاتورة القائمة — لا من `invoice_id` وحده، فالفاتورةُ قد تُحذف
                'invoice:id,code',
            ])
            ->paginate(25)
            ->withQueryString();

        return view('client::reservations.index', array_merge($this->filterOptions(), [
            'reservations' => $reservations,
            'clients' => $this->recentClients(),
            'summary' => $this->summaryFor($request),
            'sortOptions' => $this->sortOptions(),
        ]));
    }

    /**
     * تصديرُ القائمة كما هي مفلترةً ومرتَّبة — CSV بـBOM ليقرأ Excel العربية.
     *
     * بثٌّ على دفعاتٍ (`lazy`) لا جلبٌ في الذاكرة: سنةٌ من الحجوزات قد
     * تبلغ عشرات الآلاف. والتحميلُ المسبق يسري على كلّ دفعة.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->applySort($this->applyFilters($this->scopedQuery(), $request), $request)
            ->orderBy('id')
            ->with([
                'client:id,trade_name,first_name,last_name,phone,mobile',
                'employee:id,first_name,middle_name,nickname',
                'service:id,name,product_id', 'service.product:id,name',
                'branch:id,name',
                'invoice:id,code',
                'product:id,name',
            ]);

        $filename = 'reservations-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('reservations.columns.code'), __('reservations.columns.client'), __('reservations.columns.phone'),
                __('reservations.columns.service'), __('reservations.columns.employee'), __('reservations.columns.branch'),
                __('reservations.columns.date'), __('reservations.columns.from'), __('reservations.columns.to'),
                __('reservations.columns.status'), __('reservations.columns.total'), __('reservations.columns.invoice'),
            ]);

            foreach ($query->lazy(500) as $r) {
                fputcsv($out, [
                    $r->code,
                    $r->client_name,
                    $r->client?->phone ?: $r->client?->mobile,
                    $r->service_name,
                    $r->employee?->full_name,
                    $r->branch?->name,
                    $r->reservation_date?->format('Y-m-d'),
                    substr((string) $r->start_time, 0, 5),
                    substr((string) $r->end_time, 0, 5),
                    $r->status->label(),
                    number_format((float) $r->total, 2, '.', ''),
                    $r->invoice?->code,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ════════════════════ التقويم ════════════════════

    /*
        أُعيد بناؤه (2026-09-29، طلب المستخدم «يحتاج مسح وبناء من جديد… نفس
        الصورة بالضبط»): لا FullCalendar. شبكةٌ مرسومةٌ في الصفحة بأربعة عروض —
        اليوم بأعمدة الموظفين (كلُّ عمودٍ موظّفٌ بورديته)، والأسبوع، والشهر،
        والأجندة — وبطاقاتُ أرقام، وقائمةُ حجوزات اليوم، وتقويمٌ مصغّر.
        المغذّي `calendarFeed` يردّ الأحداثَ وأعمدةَ اليوم معاً.
    */
    public function calendar(Request $request)
    {
        $views = ['timeGridDay' => 'day', 'timeGridWeek' => 'week', 'dayGridMonth' => 'month', 'listWeek' => 'agenda'];

        $allowedNodes = DataScope::selectableBranchIds();

        return view('client::reservations.calendar', [
            'services' => ServiceProductCatalog::options(),
            'statuses' => ReservationStatus::cases(),
            'employees' => $this->calendarEmployees(),
            // «تابع لـ» قائمةً واحدة بمسار كلّ جهةٍ ونوعها — مقصوصةً بنطاق المستخدم
            'nodeOptions' => \App\Support\AffiliationTree::pathOptions(
                \App\Models\Branch::withoutGlobalScopes()->get(['id', 'name', 'parent_id', 'node_type', 'is_territory'])
            )->filter(fn ($o) => $allowedNodes === null || in_array($o['id'], $allowedNodes, true))->values(),
            'defaultView' => $views[ReservationSetting::current()->calendar_default_view] ?? 'day',
        ]);
    }

    /**
     * مغذّي التقويم — أحداثُ المدى المعروض، وأعمدةُ الموظفين بورديتهم ليوم `start`.
     *
     * `?mode=counts` يردّ عددَ الحجوزات لكلّ يوم فقط — للتقويم المصغّر.
     */
    public function calendarFeed(Request $request): JsonResponse
    {
        $from = $request->date('start') ?? now()->startOfMonth();
        $to = $request->date('end') ?? now()->endOfMonth();

        if ($from->diffInDays($to) > 62) {
            $to = $from->copy()->addDays(62);
        }

        $query = $this->applyFilters($this->scopedQuery(), $request)
            ->whereDate('reservation_date', '>=', $from->toDateString())
            ->whereDate('reservation_date', '<=', $to->toDateString());

        if ($request->input('mode') === 'counts') {
            return response()->json([
                'counts' => $query->selectRaw('DATE(reservation_date) as d, COUNT(*) as c')
                    ->groupBy('d')->pluck('c', 'd'),
            ]);
        }

        $rows = $query
            ->with(['client:id,trade_name,first_name,last_name,code', 'product:id,name', 'service:id,name,product_id', 'service.product:id,name', 'employee:id,first_name,middle_name,nickname', 'branch:id,name'])
            ->orderBy('reservation_date')->orderBy('start_time')
            ->get();

        $events = $rows->map(fn (Reservation $r) => [
            'id' => $r->id,
            'code' => $r->code,
            'date' => $r->reservation_date?->format('Y-m-d'),
            'start' => substr((string) $r->start_time, 0, 5),
            'end' => substr((string) $r->end_time, 0, 5),
            'client' => (string) $r->client_name,
            'service' => ($r->product_id || $r->service_id) ? (string) $r->service_name : '',
            'employee_id' => $r->employee_id,
            'employee' => (string) ($r->employee?->full_name ?? ''),
            'branch' => (string) ($r->branch?->name ?? ''),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'color' => $r->status->color(),
            'url' => route('Reservations.show', $r->id),
        ])->values();

        return response()->json([
            'events' => $events,
            // أعمدةُ عرض اليوم: موظفو الحجز بورديتهم في ذلك اليوم + من له حجزٌ فيه
            'columns' => $request->boolean('columns')
                ? $this->dayColumns($from, $rows->filter(fn (Reservation $r) => $r->reservation_date?->isSameDay($from))->pluck('employee_id')->filter()->unique()->all(), $request->integer('employee_id') ?: null)
                : [],
        ]);
    }

    /**
     * موظفو التقويم: من يُحجز لهم (وردية + قيدُ الإعدادات)، مقصوصين بنطاق المستخدم.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Employee>
     */
    protected function calendarEmployees(): \Illuminate\Support\Collection
    {
        $visible = DataScope::employeeIds();

        return $this->availability->eligibleEmployees(null)
            ->when($visible !== null, fn ($c) => $c->whereIn('id', $visible))
            ->values();
    }

    /**
     * @param  array<int, int>  $withBookings  موظفون لهم حجزٌ في اليوم ولو خرجوا من القيد
     * @return array<int, array<string, mixed>>
     */
    protected function dayColumns(\Illuminate\Support\Carbon $day, array $withBookings, ?int $onlyEmployee): array
    {
        $employees = $this->calendarEmployees();

        $missing = array_diff($withBookings, $employees->pluck('id')->all());
        if ($missing !== []) {
            $employees = $employees->merge(\App\Models\Employee::query()->whereIn('id', $missing)->get());
        }

        if ($onlyEmployee) {
            $employees = $employees->where('id', $onlyEmployee);
        }

        $titles = \Illuminate\Support\Facades\DB::table('jop_titles')->pluck('name', 'id');
        $shifts = app(EmployeeShiftSchedule::class);

        return $employees->map(function ($e) use ($day, $titles, $shifts) {
            $shift = $shifts->describe((int) $e->id, $day);

            return [
                'id' => (int) $e->id,
                'name' => (string) $e->full_name,
                'title' => (string) ($titles[$e->job_title_id] ?? ''),
                'shift' => $shift['working'] && ! $shift['holiday'] ? ['start' => $shift['start'], 'end' => $shift['end']] : null,
            ];
        })->values()->all();
    }

    // ════════════════════ النموذج ════════════════════

    /**
     * `?client_id=` — من تبويب الحجوزات في شاشة العميل: العميلُ محدَّدٌ مقفل
     * ورابطُ «تغيير العميل» يفتح النموذجَ حرّاً، كنموذج المواعيد. كان الرابطُ
     * يفتح النموذجَ فارغاً فيبحث المستخدمُ عن العميل الذي جاء منه.
     *
     * والعميلُ يُقرأ من قائمة العملاء المرئيّين لا بـ`find`: معرّفٌ خارج
     * النطاق في الرابط لا يقفل النموذجَ على عميلٍ لا يحقّ للمستخدم حجزُه.
     */
    public function create(Request $request)
    {
        $lockedClient = $request->filled('client_id')
            ? $this->visibleClients()->whereKey($request->integer('client_id'))->first()
            : null;

        return view('client::reservations.create', $this->formData() + ['lockedClient' => $lockedClient]);
    }

    public function store(StoreReservationRequest $request)
    {
        try {
            $reservation = $this->booking->create($request->validated());
        } catch (ReservationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->failMessage($e, 'store'));
        }

        return redirect()
            ->route('Reservations.show', $reservation->id)
            ->with('success', __('reservations.messages.created', ['code' => $reservation->code]));
    }

    public function show($id)
    {
        $reservation = $this->findScoped($id, [
            'client', 'employee', 'product', 'service.product', 'service.employees:id', 'branch', 'invoice',
            'tax', 'creator', 'statusHistories.user',
        ]);

        /*
            سجلُّ النشاط — ما كتبه `OperationLog` على هذا الحجز: الإنشاءُ
            والتعديلُ بفرق القيم، والدفعاتُ والردُّ، وإعادةُ الجدولة بالموعدين.
            وهو غيرُ خطّ الحالات: ذاك انتقالاتٌ، وهذا كلُّ فعلٍ بمن فعله.
        */
        $activity = ActivityLog::query()
            ->where('type', 'Reservations')
            ->where('type_id', $reservation->id)
            ->with('user:id,name')
            ->latest('id')
            ->limit(50)
            ->get();

        // موظفو مودال إعادة الجدولة: المؤهَّلون للخدمة، مقصوصين بنطاق المستخدم
        $visible = DataScope::employeeIds();
        $rescheduleEmployees = $this->availability
            ->eligibleEmployees($reservation->bookingProfile(), $reservation->branch_id)
            ->when($visible !== null, fn ($c) => $c->whereIn('id', $visible));

        return view('client::reservations.show', [
            'reservation' => $reservation,
            // الانتقالاتُ الجائزةُ لهذا المستخدم وحدَها — فلا يُعرَض زرٌّ يرفضه الحفظ
            'transitions' => $this->transitions->availableTransitions($reservation),
            'activity' => $activity,
            'rescheduleEmployees' => $rescheduleEmployees,
            'sendLinks' => $this->sendLinks($reservation),
            'appointment' => app(ReservationAppointmentSync::class)->for($reservation),
        ]);
    }

    /**
     * «تحويل إلى فاتورة» — يفتح شاشة فاتورة المبيعات **معبّأةً** بالعميل
     * والموظّف والخدمة وسعرها وخصمها وضريبتها، فيحفظها المستخدمُ كأيّ
     * فاتورة: قيودُ المبيعات نفسُها وحسابُ العميل نفسُه، ولا حساباتٍ للحجز.
     *
     * والربطُ بالحجز يقع عند حفظ الفاتورة (`InvoicesController::store`
     * يقرأ `reservation_id`) — لا هنا: فتحُ الشاشة ثمّ تركُها لا يترك حجزاً
     * «مفوتَراً» بلا فاتورة.
     */
    public function toInvoice($id)
    {
        $reservation = $this->findScoped($id, ['invoice']);

        if ($reservation->isInvoiced()) {
            return redirect()->route('invoices.show', $reservation->invoice->id);
        }

        // القاعدةُ في النموذج (`canBeInvoiced`) — هي نفسُها التي تُظهر الزرَّ وتحرس شاشةَ الفاتورة
        $blocked = $reservation->invoiceBlockReason()
            ?? (\Illuminate\Support\Facades\Route::has('invoices.create') ? null : __('reservations.errors.sales_unavailable'));

        if ($blocked) {
            return back()->with('error', $blocked);
        }

        return redirect()->route('invoices.create', ['from_reservation' => $reservation->id]);
    }

    /** مطبوعةُ الحجز — صفحةٌ مستقلّة تُطبع، بلا قوائم النظام. */
    public function print($id)
    {
        $reservation = $this->findScoped($id, ['client', 'employee', 'product', 'service.product', 'branch', 'tax', 'invoice']);

        return view('client::reservations.print', ['reservation' => $reservation]);
    }

    /**
     * «إرسال بالبريد» — رسالةٌ حقيقية من خادم البريد، لا رابط `mailto:`.
     *
     * الإرسالُ `SalesDocumentMailer` نفسُه الذي ترسل به المبيعاتُ مستنداتها:
     * نصُّ الرسالة من «قوالب البريد» (`reservations.reservation`)، وتأكيدُ الحجز
     * مرفقٌ PDF، وسطرٌ في `email_logs` نجح الإرسالُ أم فشل. والمستلِمُ يُكتب
     * في النافذة — بريدُ العميل افتراضيُّه — فلا يلزم تعديلُ ملفّه لرسالةٍ واحدة.
     */
    public function sendEmail(Request $request, $id)
    {
        $reservation = $this->findScoped($id, ['client', 'employee', 'product', 'service.product', 'tax', 'invoice']);

        $request->validate([
            'email' => ['nullable', 'email'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $recipient = $request->filled('email') ? trim($request->input('email')) : $reservation->client?->email;

        $result = app(SalesDocumentMailer::class)->send(
            'reservation',
            $reservation->id,
            $recipient,
            [
                'client' => $reservation->client_name,
                'number' => (string) $reservation->code,
                'service' => $reservation->service_name ?: '—',
                'date' => LanguageSettings::formatDate($reservation->reservation_date ?? now()),
                'time' => substr((string) $reservation->start_time, 0, 5).' - '.substr((string) $reservation->end_time, 0, 5),
                'employee' => (string) ($reservation->employee?->full_name ?: '—'),
                'total' => CurrencySettings::format($reservation->total),
                'note' => (string) $request->input('note', ''),
            ],
            null,
            view('client::reservations.pdf', ['reservation' => $reservation])->render(),
            $reservation->client_id
        );

        if (! $result['ok']) {
            return back()->with('error', __('sales_documents.email.failed', ['reason' => $result['error']]));
        }

        OperationLog::record('emailed', 'Reservations', $reservation, "إرسال الحجز {$reservation->code} بالبريد إلى {$result['email']}");

        return back()->with('success', __('sales_documents.email.sent', ['email' => $result['email']]));
    }

    /**
     * «إرسال للعميل» — رسالةٌ جاهزة بالموعد عبر واتساب أو البريد، من جوّال
     * العميل وبريده في ملفّه. لا بوّابةَ رسائل هنا: الرابطُ يفتح تطبيقَ
     * المستخدم فيراجع النصَّ ويرسله بنفسه.
     *
     * @return array{whatsapp: ?string, email: ?string, text: string}
     */
    protected function sendLinks(Reservation $reservation): array
    {
        $text = __('reservations.send.message', [
            'client' => $reservation->client_name,
            'code' => $reservation->code,
            'service' => $reservation->service_name ?: '—',
            'date' => $reservation->reservation_date?->format('Y-m-d'),
            'time' => substr((string) $reservation->start_time, 0, 5),
            'employee' => $reservation->employee?->full_name ?: '—',
        ]);

        $phone = preg_replace('/\D+/', '', (string) ($reservation->client?->mobile ?: $reservation->client?->phone));
        $email = $reservation->client?->email;

        return [
            'whatsapp' => $phone ? 'https://wa.me/'.ltrim($phone, '0').'?text='.rawurlencode($text) : null,
            'email' => $email ? 'mailto:'.$email.'?subject='.rawurlencode(__('reservations.send.subject', ['code' => $reservation->code])).'&body='.rawurlencode($text) : null,
            'text' => $text,
        ];
    }

    public function edit($id)
    {
        $reservation = $this->findScoped($id, ['client', 'service', 'employee', 'invoice']);

        return view('client::reservations.edit', $this->formData($reservation->client_id) + ['reservation' => $reservation]);
    }

    public function update(UpdateReservationRequest $request, $id)
    {
        $reservation = $this->findScoped($id);

        try {
            $this->booking->update($reservation, $request->validated());
        } catch (ReservationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->failMessage($e, 'update', $reservation));
        }

        return redirect()
            ->route('Reservations.show', $reservation->id)
            ->with('success', __('reservations.messages.updated', ['code' => $reservation->code]));
    }

    public function destroy($id)
    {
        $reservation = $this->findScoped($id);

        try {
            $this->booking->delete($reservation);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->with('error', $this->failMessage($e, 'destroy', $reservation));
        }

        return redirect()->route('Reservations.index')->with('success', __('reservations.messages.deleted'));
    }

    // ════════════════════ نداءات AJAX ════════════════════

    /**
     * الفتراتُ الشاغرة ليومٍ وخدمةٍ وموظّف — من الحارس نفسِه الذي يفحص الحفظ.
     */
    public function slots(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
            'product_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'ignore_id' => ['nullable', 'integer'],
        ]);

        $service = $this->profileFromRequest($request);

        $slots = $this->availability->availableSlots(
            $request->input('date'),
            $service,
            $request->integer('employee_id') ?: null,
            $request->integer('branch_id') ?: current_branch_id(),
            $request->integer('ignore_id') ?: null
        );

        // ورديةُ الموظّف المختار في ذلك اليوم — النموذجُ يعرضها ويقصر خانةَ الوقت عليها
        $shift = $request->filled('employee_id')
            ? app(EmployeeShiftSchedule::class)->describe($request->integer('employee_id'), \Illuminate\Support\Carbon::parse($request->input('date')))
            : null;

        return response()->json(['slots' => $slots, 'shift' => $shift]);
    }

    /** الموظفون المؤهَّلون لخدمةٍ — تُحدَّث القائمةُ عند تبديل الخدمة. */
    public function serviceEmployees(Request $request): JsonResponse
    {
        $service = $this->profileFromRequest($request);

        $eligible = $this->availability->eligibleEmployees(
            $service,
            $request->integer('branch_id') ?: current_branch_id()
        );

        // ثمّ يُقصّ الناتجُ بنطاق المستخدم — الأهليّةُ شيءٌ والصلاحيةُ شيء
        $visible = DataScope::employeeIds();

        return response()->json([
            'employees' => $eligible
                ->when($visible !== null, fn ($c) => $c->whereIn('id', $visible))
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => (string) $e->full_name,
                ])->values(),
            'service' => $service ? [
                'product_id' => $service->product_id,
                'has_profile' => $service->exists,
                'duration' => (int) $service->duration,
                'price' => $service->effectivePrice(),
                'tax_id' => $service->effectiveTaxId(),
                'buffer_before' => (int) $service->buffer_before,
                'buffer_after' => (int) $service->buffer_after,
            ] : null,
        ]);
    }

    /**
     * تسعيرٌ حيٌّ للنموذج — الشاشةُ تعرض ما تحسبه **الخدمةُ** لا الجافاسكربت.
     */
    public function quote(Request $request): JsonResponse
    {
        $service = $this->profileFromRequest($request);

        // `tax_id` مُرسَلٌ فارغاً = «بلا ضريبة» صراحةً؛ وغيابُه = ضريبةُ الخدمة
        $money = $this->pricing->quoteForService(
            $service,
            $request->filled('unit_price') ? (float) $request->input('unit_price') : null,
            (float) $request->input('discount_value', 0),
            (string) $request->input('discount_type', 'amount'),
            $request->input('tax_id'),
            $request->has('tax_id')
        );

        return response()->json($money);
    }

    /**
     * بحثُ المنتجات الخدمية لـselect2 — مصدرُ الخدمة في الحجز.
     *
     * يعيد السعرَ من المنتج كما هو، والمدّةَ من ملفّ حجزه إن وُجد وإلّا من
     * الإعدادات — فيعرف المستخدمُ ما سيُحجَز قبل أن يختاره.
     */
    public function serviceProducts(Request $request): JsonResponse
    {
        $products = ServiceProductCatalog::options($request->input('q'), 30);
        $profiles = BookableService::whereIn('product_id', $products->pluck('id'))->get()->keyBy('product_id');
        $defaultDuration = (int) ReservationSetting::current()->default_duration;

        return response()->json([
            'results' => $products->map(fn ($p) => [
                'id' => $p->id,
                'text' => $p->name,
                'price' => (float) $p->sale_price,
                'duration' => (int) ($profiles[$p->id]->duration ?? $defaultDuration),
                'disabled' => isset($profiles[$p->id]) && ! $profiles[$p->id]->is_active,
            ])->values(),
        ]);
    }

    /**
     * بحثُ العملاء الحيّ لـselect2 — القائمةُ الكاملة تُجمّد المتصفّح.
     *
     * مقصوصٌ بنطاق رؤية العملاء كالقائمة الأولى — وإلّا كان البحثُ باباً
     * إلى أسماء عملاء المنشأة كلِّها وجوّالاتهم لمن نطاقُه عملاؤه وحدَهم.
     */
    public function clients(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        $clients = $this->visibleClients()
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->where('trade_name', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('mobile', 'like', "%{$term}%");
                });
            })
            ->limit(30)
            ->get();

        return response()->json([
            'results' => $clients->map(fn (Client $c) => [
                'id' => $c->id,
                'text' => trim(($c->display_name ?? '—').' — '.($c->code ?? '')),
                'phone' => $c->phone ?: $c->mobile,
            ])->values(),
        ]);
    }

    /** حجوزاتُ عميلٍ بعينه — للتبويب في شاشة العميل. */
    public function clientHistory($clientId)
    {
        $client = Client::findOrFail($clientId);

        $reservations = $this->scopedQuery()
            ->where('client_id', $client->id)
            ->with(['service:id,name,product_id', 'service.product:id,name', 'employee:id,first_name,middle_name,nickname'])
            ->latest('reservation_date')
            ->paginate(20);

        return view('client::reservations.client', compact('client', 'reservations'));
    }

    // ════════════════════ أدوات ════════════════════

    /**
     * ملفُّ حجز الخدمة من نداء AJAX — `product_id` أوّلاً، و`service_id` للقديم.
     *
     * لا يرمي: نداءُ التسعير أو الفترات لمنتجٍ ليس خدمياً يعود بلا خدمة،
     * والحفظُ هو الذي يرفض برسالته (`ServiceProductCatalog::resolve`).
     */
    protected function profileFromRequest(Request $request): ?BookableService
    {
        if ($request->filled('product_id')) {
            try {
                return BookableService::profileFor(ServiceProductCatalog::resolve($request->integer('product_id')));
            } catch (ReservationException $e) {
                return null;
            }
        }

        return $request->filled('service_id')
            ? BookableService::with('product')->find($request->integer('service_id'))
            : null;
    }

    /**
     * بياناتُ نموذجَي الإنشاء والتعديل — واحدةٌ لهما فلا يفترقان.
     *
     * @param  mixed  $keepClientId  عميلُ الحجز المفتوح — يبقى في القائمة ولو خرج من النطاق
     * @return array<string, mixed>
     */
    protected function formData($keepClientId = null): array
    {
        return array_merge($this->filterOptions(), [
            'clients' => $this->recentClients(50, $keepClientId),
            'taxes' => TaxSitting::orderBy('name')->get(),
            'settings' => ReservationSetting::current(),
            'nextCode' => peek_document_number('reservation'),
            // حالاتُ الإنشاء وحدَها — والتأكيدُ لمن يملك صلاحيتَه (القاعدةُ نفسُها في التحقّق)
            'statuses' => ReservationStatus::storableAtCreate(hasPermission('reservations.confirm')),
            // من له وردية في الموارد البشرية وحدَه — والقائمةُ تُحدَّث بالخدمة
            'employees' => $this->availability->eligibleEmployees(null)
                ->when(DataScope::employeeIds() !== null, fn ($c) => $c->whereIn('id', DataScope::employeeIds()))
                ->values(),
        ]);
    }

    /**
     * أرقامُ الترويسة — بالفلاتر نفسِها التي تحكم الجدول.
     *
     * و«المتبقّي» على غير المفوتَر وحدَه: بعد الفاتورة انتقلت الذمّةُ إلى
     * الفاتورة وحساب العميل، ويُتابَع تحصيلُها من دفعات المبيعات.
     *
     * @return array<string, mixed>
     */
    protected function summaryFor(Request $request): array
    {
        $base = fn () => $this->applyFilters($this->scopedQuery(), $request);

        return [
            'total' => $base()->count(),
            'today' => $base()->onDate(now())->count(),
            'upcoming' => $base()->upcoming()->count(),
            'completed' => $base()->where('status', ReservationStatus::COMPLETED->value)->count(),
            'cancelled' => $base()->where('status', ReservationStatus::CANCELLED->value)->count(),
            'no_show' => $base()->where('status', ReservationStatus::NO_SHOW->value)->count(),
        ];
    }
}

<?php

namespace Modules\Client\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Http\Controllers\Reservations\Concerns\ScopesReservations;

/**
 * لوحةُ الحجوزات — أرقامُ اليوم وما بعده، وتوزيعُ المدى المختار.
 *
 * ═══════════ كلُّ رقمٍ هنا يمرّ بالنطاق والفلاتر معاً ═══════════
 *
 * `scopedQuery()` تقصّ بنطاق المستخدم، و`applyFilters()` تقصّ بما اختاره
 * في الشريط. و«اليوم» و«القادمة» تمرّان بالفلاتر نفسها **عدا المدى**:
 * كانتا تتجاهلان الفلاترَ كلَّها، فمن اختار فرعاً رأى حجوزاتِ اليوم في
 * الفروع كلِّها فوق جدولٍ مقصورٍ على فرعه.
 *
 * ═══════════ ولماذا استعلاماتٌ مجمَّعة لا حلقةُ عدّ؟ ═══════════
 *
 * توزيعُ الحالات والفروع والخدمات والموظفين يُحسب بـ`groupBy` في القاعدة:
 * لوحةٌ على فرعٍ بعشرة آلاف حجز كانت ستُحمّل عشرةَ آلاف نموذج لتعرض
 * عشرة أرقام.
 */
class ReservationDashboardController extends Controller
{
    use ScopesReservations;

    public function __construct()
    {
        $this->middleware('permission:reservations.dashboard');
    }

    public function index(Request $request)
    {
        // المدى الافتراضي: الشهرُ الجاري — لا «كلّ الزمن» الذي يمسح الجدول
        $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : now()->startOfMonth();
        $to = $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : now()->endOfMonth();

        $request->merge(['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

        $scoped = fn () => $this->applyFilters($this->scopedQuery(), $request);
        $undated = fn () => $this->applyFilters($this->scopedQuery(), $request, false);

        $relations = [
            'client:id,trade_name,first_name,last_name,phone,mobile',
            'product:id,name', 'service:id,name,product_id',
            'employee:id,first_name,middle_name,nickname',
        ];

        // ── توزيعُ الحالات: استعلامٌ واحد لا عشرة ──
        // لا `SUM(paid_amount)`: لا مالَ في الحجز — المالُ على فاتورة المبيعات
        $byStatus = $scoped()
            ->selectRaw('status, COUNT(*) as c, SUM(total) as t')
            ->groupBy('status')
            ->get()
            // `status` مصبوبٌ إلى التعداد — والتعدادُ لا يتحوّل إلى نصّ ضمنياً
            ->keyBy(fn ($row) => $row->status instanceof ReservationStatus ? $row->status->value : (string) $row->status);

        $countOf = fn (ReservationStatus ...$statuses) => (int) collect($statuses)
            ->sum(fn (ReservationStatus $s) => (int) ($byStatus[$s->value]->c ?? 0));

        /*
            «قيمةُ الحجوزات» على الشاغل للمواعيد وحدَه — الحيُّ والمكتمل.
            الملغى والمرفوض ومن لم يحضر ليست قيمةً يُنتظر تحصيلُها.
        */
        $value = (float) collect(ReservationStatus::occupyingValues())
            ->sum(fn (string $status) => (float) ($byStatus[$status]->t ?? 0));

        return view('client::reservations.dashboard', array_merge($this->filterOptions(), [
            'from' => $from,
            'to' => $to,

            'cards' => [
                'today' => $undated()->onDate(now())->occupying()->count(),
                'upcoming' => $undated()->upcoming()->count(),
                'total' => (int) $byStatus->sum('c'),
                'pending' => $countOf(ReservationStatus::PENDING, ReservationStatus::DRAFT),
                'confirmed' => $countOf(ReservationStatus::CONFIRMED),
                'in_service' => $countOf(ReservationStatus::CHECKED_IN, ReservationStatus::IN_PROGRESS),
                'completed' => $countOf(ReservationStatus::COMPLETED),
                'cancelled' => $countOf(ReservationStatus::CANCELLED, ReservationStatus::REJECTED),
                'no_show' => $countOf(ReservationStatus::NO_SHOW),

                'value' => $value,
                /*
                    الإيرادُ على المكتمل وحدَه — نظيرُ قاعدة الاعتراف بالإيراد:
                    ما لم تُؤدَّ خدمتُه ليس إيراداً بعد.
                */
                'revenue' => (float) ($byStatus[ReservationStatus::COMPLETED->value]->t ?? 0),
                // لا «محصَّل» ولا «متبقٍّ»: المالُ على فاتورة المبيعات وتقاريرُه هناك
            ],

            'byStatus' => collect(ReservationStatus::cases())->map(fn (ReservationStatus $s) => [
                'status' => $s,
                'count' => (int) ($byStatus[$s->value]->c ?? 0),
            ]),

            // ── الأقرب موعداً: ما يحتاجه موظّفُ الاستقبال أوّلَ ما يفتح ──
            'todayList' => $undated()
                ->onDate(now())->occupying()
                ->with($relations)
                ->orderBy('start_time')
                ->limit(20)->get(),

            'upcomingList' => $undated()
                ->upcoming()
                ->whereDate('reservation_date', '>', now()->toDateString())
                ->with($relations)
                ->orderBy('reservation_date')->orderBy('start_time')
                ->limit(10)->get(),

            // ── التوزيع: مجمَّعاً في القاعدة ──
            'byBranch' => $scoped()
                ->selectRaw('branch_id, COUNT(*) as c, SUM(total) as t')
                ->whereNotNull('branch_id')
                ->groupBy('branch_id')->orderByDesc('c')->limit(10)
                ->with('branch:id,name,node_type,is_territory')->get(),

            // الخدمةُ = المنتجُ الخدمي
            'topServices' => $scoped()
                ->selectRaw('product_id, COUNT(*) as c, SUM(total) as t')
                ->whereNotNull('product_id')
                ->groupBy('product_id')->orderByDesc('c')->limit(10)
                ->with('product:id,name')->get(),

            'topEmployees' => $scoped()
                ->selectRaw('employee_id, COUNT(*) as c, SUM(total) as t')
                ->whereNotNull('employee_id')
                ->groupBy('employee_id')->orderByDesc('c')->limit(10)
                ->with('employee:id,first_name,middle_name,nickname')->get(),
        ]));
    }
}

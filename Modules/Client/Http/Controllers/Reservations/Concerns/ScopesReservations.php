<?php

namespace Modules\Client\Http\Controllers\Reservations\Concerns;

use App\Models\Client;
use App\Models\Employee;
use App\Services\Clients\ClientVisibilityScope;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Exceptions\ReservationException;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Services\ServiceProductCatalog;
use Throwable;

/**
 * نطاقُ الرؤية والفلاتر — مشتركةً بين كلّ شاشات الحجوزات.
 *
 * ═══════════ محورا الحراسة ═══════════
 *
 * حراسةُ المسار بـ`permission:reservations.view` تجيب «هل يفتح الشاشة؟»
 * ولا تجيب «على أيّ صفوفٍ منها؟». والثاني هو ما يفعله
 * {@see \App\Support\DataScope}:
 *
 *   `data_scope_all`     بلا قيد.
 *   `data_scope_region`  فرعُه وما تحته.
 *   `data_scope_branch`  فرعُه وحدَه.
 *   `data_scope_team`    هو ومن يتبعه إدارياً.
 *   `data_scope_own`     حجوزاتُه هو.
 *
 * ═══════════ والقوائمُ تُقصّ كما يُقصّ الاستعلام ═══════════
 *
 * لا يكفي تقييدُ الصفوف وتركُ منتقي الموظفين كاملاً: من رأى اسمَ زميله
 * في القائمة وضغطه خرج بشاشةٍ فارغة بلا تفسير — وعَلِم من القائمة وحدَها
 * ما لا يحقّ له. فكلُّ قائمةٍ هنا تمرّ على النطاق نفسِه.
 *
 * ═══════════ والحجزُ الواحد يُحرَس عند فتحه ═══════════
 *
 * `findScoped()` تُنادى في كلّ شاشةٍ تأخذ حجزاً بمعرّفه. و`findOrFail`
 * وحدَها ثغرةُ IDOR: القائمةُ مقيَّدة والرابطُ المباشر مفتوح.
 */
trait ScopesReservations
{
    /**
     * استعلامُ الحجوزات مقيَّداً بنطاق المستخدم.
     */
    protected function scopedQuery(): Builder
    {
        /*
            القصُّ نفسُه في النموذج (`Reservation::scopeVisibleToUser`) —
            فتقرأ منه شاشةُ فاتورة المبيعات أيضاً حين تُفتح من الحجز، ولا
            تفترق القائمةُ عمّا يُفتح بالرابط المباشر.
        */
        return Reservation::query()->visibleToUser();
    }

    /**
     * حجزٌ بمعرّفه — **بعد التحقّق من نطاق المستخدم**، وإلّا 404.
     */
    protected function findScoped($id, array $with = []): Reservation
    {
        return $this->scopedQuery()->with($with)->findOrFail($id);
    }

    /**
     * يرمي إن كان هذا الحجز خارج نطاق المستخدم.
     */
    protected function authorizeReservation(Reservation $reservation): void
    {
        if (! $this->scopedQuery()->whereKey($reservation->getKey())->exists()) {
            throw ReservationException::because(__('reservations.errors.out_of_scope'));
        }
    }

    /**
     * تطبيقُ فلاتر الشاشة على استعلام — الفلاترُ نفسُها في القائمة
     * والتقويم واللوحة والتقارير وسجلّ المدفوعات، فلا تفترق نتائجُها.
     *
     * @param  bool  $withDates  اللوحةُ تحسب «اليوم» و«القادمة» بالفلاتر نفسها
     *                           عدا المدى الزمني — فهما مدىً بحدّ ذاتهما.
     */
    protected function applyFilters(Builder $query, Request $request, bool $withDates = true): Builder
    {
        return $query
            /*
                البحثُ السريع: رقمُ الحجز، واسمُ العميل بأشكاله، ورقمُه،
                وجوّاله — خانةٌ واحدة تكفي موظّفَ الاستقبال والعميلُ على
                الهاتف.
            */
            ->when(filled($term = trim((string) $request->input('q'))), function ($q) use ($term) {
                $like = '%'.$term.'%';

                $q->where(function ($w) use ($like) {
                    $w->where('code', 'like', $like)
                        ->orWhereHas('client', function ($c) use ($like) {
                            $c->where('trade_name', 'like', $like)
                                ->orWhere('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('code', 'like', $like)
                                ->orWhere('phone', 'like', $like)
                                ->orWhere('mobile', 'like', $like);
                        });
                });
            })
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            // الخدمةُ منتجٌ خدمي — و`service_id` يبقى مقبولاً لروابط قديمة
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            /*
                «تابع لـ» — الشجرةُ نفسُها التي تستعملها التقارير. `forBranch()`
                تشمل العقدةَ وما تحتها، و`exact_node` تقصرها على العقدة وحدها.
            */
            ->when($request->filled('tree_node'), function ($q) use ($request) {
                $node = (int) preg_replace('/^[bg]_/', '', (string) $request->input('tree_node'));

                if ($node <= 0) {
                    return $q;
                }

                return $request->boolean('exact_node')
                    ? $q->where('branch_id', $node)
                    : $q->forBranch($node);
            })
            // الاسمُ القديم يبقى مقبولاً: روابطٌ محفوظةٌ وتقاريرُ مطبوعة تحمله
            ->when($request->filled('branch_id') && ! $request->filled('tree_node'),
                fn ($q) => $q->forBranch($request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->whereIn('status', (array) $request->input('status')))
            ->when($withDates && $request->filled('date_from'), fn ($q) => $q->whereDate('reservation_date', '>=', $request->date('date_from')))
            ->when($withDates && $request->filled('date_to'), fn ($q) => $q->whereDate('reservation_date', '<=', $request->date('date_to')))
            ->when($request->filled('code'), fn ($q) => $q->where('code', 'like', '%'.$request->input('code').'%'))
            /*
                «مفوتَر» من الفاتورة القائمة لا من `invoice_id` وحده — فاتورةٌ
                حُذفت لا تُبقي الحجزَ مفوتَراً. ولا فلاترَ سدادٍ هنا: لا مالَ
                في الحجز، والتحصيلُ يُتابَع من المبيعات.
            */
            ->when($request->input('invoiced') === '1', fn ($q) => $q->whereHas('invoice'))
            ->when($request->input('invoiced') === '0', fn ($q) => $q->whereDoesntHave('invoice'))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', $request->input('currency')));
    }

    /**
     * ترتيبُ القائمة — من قائمةٍ مغلقة لا من اسم عمودٍ قادمٍ من الرابط.
     *
     * تمريرُ `sort` كما هو إلى `orderBy` يفتح الترتيبَ على أيّ عمود في
     * الجدول، ويُسقط الصفحةَ بخطأ قاعدةٍ عند اسمٍ غير موجود.
     */
    protected function applySort(Builder $query, Request $request): Builder
    {
        return match ($request->input('sort')) {
            'date_asc' => $query->orderBy('reservation_date')->orderBy('start_time'),
            'total_desc' => $query->orderByDesc('total'),
            'created_desc' => $query->latest('id'),
            default => $query->orderByDesc('reservation_date')->orderByDesc('start_time'),
        };
    }

    /** @return array<string, string> خياراتُ الترتيب للقالب */
    protected function sortOptions(): array
    {
        return [
            'date_desc' => __('reservations.sort.date_desc'),
            'date_asc' => __('reservations.sort.date_asc'),
            'total_desc' => __('reservations.sort.total_desc'),
            'created_desc' => __('reservations.sort.created_desc'),
        ];
    }

    /**
     * بياناتُ الفلاتر للقوالب — كلُّها مقصوصةٌ بالنطاق.
     *
     * @return array<string, mixed>
     */
    protected function filterOptions(): array
    {
        return [
            'employees' => $this->scopedEmployees(),
            // الخدماتُ = المنتجاتُ الخدمية المفعَّلة، لا كتالوجاً ثانياً
            'services' => ServiceProductCatalog::options(),
            'statuses' => ReservationStatus::cases(),

            /*
                عقدُ شجرة «تابع لـ» — مقصوصةً بنطاق المستخدم، بالقصّ نفسِه
                الذي تستعمله تقاريرُ المبيعات والمشتريات والعملاء.
            */
            'treeNodes' => DataScope::filterNodes(AffiliationTree::nodes()),
        ];
    }

    /**
     * الموظفون المرئيّون لهذا المستخدم.
     *
     * @return Collection<int, Employee>
     */
    protected function scopedEmployees(): Collection
    {
        $ids = DataScope::employeeIds();

        return Employee::query()
            // `null` تعني «بلا قيد» — والمصفوفةُ الفارغة تعني «لا أحد»
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('first_name')
            ->get();
    }

    /**
     * عملاءُ قائمةِ الاختيار — محدودةً، والبحثُ الحيّ يكمّل.
     *
     * ثمانيةُ آلاف عميلٍ في `<select>` واحد يجمّد المتصفّح. فالقائمةُ تحمل
     * الأحدثَ فقط، و`select2` بـAJAX يجلب البقيّة عند الكتابة.
     *
     * **ومقصوصةٌ بنطاق رؤية العملاء** — القصُّ نفسُه الذي يحكم مُنتقي العميل
     * في الفاتورة وعرض السعر ({@see ClientVisibilityScope}): من لا يرى
     * عميلاً في «إدارة العملاء» لا يراه هنا. و`$keep` عميلُ الحجز المفتوح
     * يبقى خياراً ولو ضاق النطاق بعد حفظه.
     */
    protected function recentClients(int $limit = 50, $keep = null): Collection
    {
        return $this->visibleClients($keep)->latest('id')->limit($limit)->get();
    }

    /**
     * استعلامُ العملاء مقصوصاً بنطاق المستخدم — للقائمة وللبحث الحيّ وللتحقّق.
     *
     * @param  mixed  $keep  عميلٌ لا يسقط من القائمة (عميلُ الحجز المفتوح)
     */
    protected function visibleClients($keep = null): \Illuminate\Database\Eloquent\Builder
    {
        return ClientVisibilityScope::applyDocumentSelectScope(Client::query(), null, null, $keep);
    }

    /**
     * استثناءٌ غيرُ متوقَّع: يُكتب في سجلّ الأخطاء ويعود للمستخدم مرجعٌ قصير.
     *
     * فلا يرى `SQLSTATE` ولا مسارَ ملفّ ولا اسمَ جدول — وهي معلوماتٌ تفيد
     * المهاجمَ ولا تفيده. والمرجعُ يطابق ما في سجلّ الأخطاء فيُبحث به.
     */
    protected function failMessage(Throwable $e, string $action, ?Reservation $reservation = null): string
    {
        OperationLog::failure($e, 'Reservations', $action, $reservation);

        return __('reservations.messages.unexpected_error', [
            'ref' => 'ERR-'.strtoupper(substr(md5($e->getMessage().$e->getLine()), 0, 8)),
        ]);
    }
}

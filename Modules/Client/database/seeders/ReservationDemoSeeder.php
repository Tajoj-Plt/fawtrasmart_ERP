<?php

namespace Modules\Client\Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Client\Reservations\Enums\ReservationStatus as Status;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationService;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Models\ReservationStatusHistory;
use Modules\Client\Reservations\Services\ReservationPricingService;

/**
 * بياناتٌ تجريبية لوحدة الحجوزات — ليُرى كلُّ شيءٍ يعمل قبل الحجز الحقيقي.
 *
 *   php artisan db:seed --class="Modules\Client\Database\Seeders\ReservationDemoSeeder" --force
 *
 * أو زرُّ «بيانات تجريبية للحجوزات» في «تحديث النظام».
 *
 * ═══════════ ما يُنشئه ═══════════
 *
 *   • خمسُ خدمات = منتجاتٌ من نوع «منتج خدمي» (لا جدولَ خدمات)، بفئة «خدمات الحجز»،
 *     ولكلٍّ ملفُّ حجزٍ بمدّته ووقتَي التحضير والتنظيف وموظفين مؤهَّلين.
 *   • دوامٌ أسبوعي على كلّ جهةٍ جذرية لا دوامَ لها — فيرثه كلُّ ما تحتها.
 *   • عطلةٌ قادمة واحدة.
 *   • نحو ثلاثين حجزاً من أسبوعين مضيا إلى أسبوعين قادمين، بكلّ الحالات، على
 *     عملائك وموظفيك الموجودين، ومع خطّ حالات كلٍّ منها.
 *
 * ═══════════ وما لا يفعله ═══════════
 *
 *   • **لا يحذف ولا يعدّل شيئاً قائماً**: الدوامُ المضبوط يبقى، والمنتجُ الموجود
 *     باسمه يُستعمل كما هو، والإعدادات لا تُمسّ.
 *   • **لا يُطلق أحداث الحجز**: كلُّها ترسل إشعاراً للعميل، والعملاءُ هنا حقيقيون
 *     — فالحجوزاتُ تُكتب مباشرةً بلا `event()`.
 *   • **لا قبضَ ولا قيد ولا فاتورة**: المالُ يمرّ بالحسابات المحاسبية، وحجزٌ تجريبيٌّ
 *     لا يُكتب له قيدٌ في دفاترك. المدفوعُ صفر والمتبقّي الإجمالي.
 *   • لا معرّفاتٍ مكتوبة: الفئةُ والفرعُ والعملاءُ والموظفون يُقرؤون من الجداول.
 *
 * ═══════════ ويُعاد تشغيلُه بأمان ═══════════
 *
 * كلُّ عنصرٍ يُفحص قبل إنشائه، والحجوزاتُ تحمل الوسم `MARKER` في ملاحظاتها —
 * فإن وُجد حجزٌ بالوسم لا يُضاف غيرُه. وبالوسم نفسِه تُعرف في القائمة وتُلغى إن أُريد.
 */
class ReservationDemoSeeder extends Seeder
{
    public const MARKER = '[بيانات تجريبية]';

    public const CATEGORY = 'خدمات الحجز';

    /** الخدمات — الاسمُ والسعرُ والمدّةُ ووقتا التحضير والتنظيف (دقائق). */
    protected const SERVICES = [
        ['name' => 'قص شعر رجالي', 'price' => 50, 'duration' => 30, 'before' => 0, 'after' => 5],
        ['name' => 'تنظيف بشرة عميق', 'price' => 150, 'duration' => 60, 'before' => 5, 'after' => 10],
        ['name' => 'جلسة مساج استرخاء', 'price' => 200, 'duration' => 60, 'before' => 0, 'after' => 15],
        ['name' => 'استشارة تجميل', 'price' => 100, 'duration' => 30, 'before' => 0, 'after' => 0],
        ['name' => 'صبغة شعر', 'price' => 180, 'duration' => 90, 'before' => 0, 'after' => 10],
    ];

    /** بداياتُ المواعيد في اليوم — متباعدةٌ بما يسع أطولَ خدمةٍ ووقتَ تنظيفها. */
    protected const SLOTS = ['09:00', '11:00', '16:00', '18:30'];

    protected const SATURDAY_SLOTS = ['10:00', '12:30', '15:00'];

    protected array $report = [];

    public function run(): void
    {
        $creatorId = User::query()->orderBy('id')->value('id');

        DB::transaction(function () use ($creatorId) {
            // لا دوامَ ولا عطلَ هنا: الدوامُ من ورديات الموارد البشرية وعطلُها
            $services = $this->seedServices($creatorId);
            $this->seedReservations($services, $creatorId);
        });

        foreach ($this->report as $line) {
            $this->command?->info($line);
        }
    }

    // ════════════════════ الخدمات ════════════════════

    /**
     * @return Collection<int, ReservationService> ملفّاتُ الحجز، ومع كلٍّ منتجُه
     */
    protected function seedServices(?int $creatorId): Collection
    {
        $category = Category::query()->where('name', self::CATEGORY)->first();

        if (! $category) {
            $category = new Category;
            $category->name = self::CATEGORY;
            $category->is_active = 1;
            $category->save();
        }

        // الضريبةُ من إعدادات الحجوزات — لا نسبةَ مكتوبةً هنا
        $taxId = ReservationSetting::current()->default_tax_id;

        $employees = Employee::query()->orderBy('id')->limit(6)->pluck('id')->values();

        $created = 0;
        $profiles = collect();

        foreach (self::SERVICES as $index => $definition) {
            $product = Product::query()
                ->where('type', ProductType::SERVICES->value)
                ->where('name', $definition['name'])
                ->first();

            if (! $product) {
                $product = new Product;
                $product->name = $definition['name'];
                $product->description = self::MARKER;
                $product->category_id = $category->id;
                $product->serial_number = next_document_number('products');
                $product->type = ProductType::SERVICES->value;
                $product->status = ProductStatus::ACTIVE->value;
                $product->sale_price = $definition['price'];
                $product->purchase_price = 0;
                $product->tax1 = $taxId;
                $product->created_by = $creatorId;
                $product->save();
                $created++;
            }

            $profile = ReservationService::query()->firstOrNew(['product_id' => $product->id]);

            if (! $profile->exists) {
                $profile->fill([
                    'duration' => $definition['duration'],
                    'buffer_before' => $definition['before'],
                    'buffer_after' => $definition['after'],
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_by' => $creatorId,
                ])->save();

                // ثلاثةُ موظفين مؤهَّلين لكل خدمة، بالتناوب على الموجودين
                $qualified = $employees->isEmpty()
                    ? []
                    : collect(range(0, 2))->map(fn ($k) => $employees[($index + $k) % $employees->count()])->unique()->all();
                $profile->employees()->sync($qualified);
            }

            $profile->setRelation('product', $product);
            $profiles->push($profile->load('employees:id,branch_id'));
        }

        $this->report[] = "الخدمات: {$created} جديدة، و".(count(self::SERVICES) - $created).' موجودة من قبل.';

        return $profiles;
    }

    // ════════════════════ الحجوزات ════════════════════

    /**
     * @param  Collection<int, ReservationService>  $services
     */
    protected function seedReservations(Collection $services, ?int $creatorId): void
    {
        if (Reservation::query()->where('notes', 'like', self::MARKER.'%')->exists()) {
            $this->report[] = 'الحجوزات التجريبية موجودة من قبل — لم يُضف غيرُها.';

            return;
        }

        $clients = Client::query()->orderBy('id')->limit(20)->pluck('id')->values();

        if ($clients->isEmpty() || $services->isEmpty()) {
            $this->report[] = 'لا عملاء أو لا خدمات — تُخطّيت الحجوزات.';

            return;
        }

        $mainBranchId = Branch::withoutGlobalScopes()->where('is_main', 1)->value('id')
            ?? Branch::withoutGlobalScopes()->whereNull('parent_id')->orderBy('id')->value('id');

        $pricing = app(ReservationPricingService::class);

        // حالاتٌ بحسب الزمن — ماضٍ مكتملٌ غالباً، واليومُ جارٍ، والقادمُ ينتظر
        $past = [Status::COMPLETED, Status::COMPLETED, Status::NO_SHOW, Status::COMPLETED, Status::CANCELLED, Status::COMPLETED];
        $today = [Status::CONFIRMED, Status::CHECKED_IN, Status::IN_PROGRESS, Status::PENDING];
        $future = [Status::PENDING, Status::CONFIRMED, Status::CONFIRMED, Status::DRAFT];

        $counter = 0;
        $created = 0;

        foreach (range(-14, 13) as $offset) {
            $day = now()->startOfDay()->addDays($offset);

            if ($day->dayOfWeek === Carbon::FRIDAY) {
                continue;
            }

            $slots = $day->dayOfWeek === Carbon::SATURDAY ? self::SATURDAY_SLOTS : self::SLOTS;
            $perDay = $offset === 0 ? 4 : ($offset % 2 === 0 ? 2 : 1);

            foreach (array_slice($slots, 0, $perDay) as $slotIndex => $time) {
                $profile = $services[$counter % $services->count()];
                $employeeId = $profile->employees->isNotEmpty()
                    ? $profile->employees[$counter % $profile->employees->count()]->id
                    : null;

                $pool = $offset < 0 ? $past : ($offset === 0 ? $today : $future);
                $status = $pool[($offset === 0 ? $slotIndex : $counter) % count($pool)];

                $start = $day->copy()->setTimeFromTimeString($time);
                $end = $start->copy()->addMinutes((int) $profile->duration);

                $counter++;

                if ($employeeId && $this->employeeBusy($employeeId, $start, $end)) {
                    continue;
                }

                $this->createReservation($profile, $pricing, [
                    'branch_id' => $profile->employees->firstWhere('id', $employeeId)?->branch_id ?: $mainBranchId,
                    'client_id' => $clients[$counter % $clients->count()],
                    'employee_id' => $employeeId,
                    'start' => $start,
                    'end' => $end,
                    'status' => $status,
                    'creator' => $creatorId,
                ]);

                $created++;
            }
        }

        $this->report[] = "الحجوزات: {$created} حجزاً من ".now()->subDays(14)->toDateString().' إلى '.now()->addDays(13)->toDateString().'.';
    }

    /** حجزٌ قائمٌ يشغل الموظّفَ في الفترة نفسها — حجزٌ حقيقيٌّ لا يُزاحَم. */
    protected function employeeBusy(int $employeeId, Carbon $start, Carbon $end): bool
    {
        return Reservation::query()
            ->where('employee_id', $employeeId)
            ->whereDate('reservation_date', $start->toDateString())
            ->whereIn('status', Status::occupyingValues())
            ->where('start_time', '<', $end->format('H:i:s'))
            ->where('end_time', '>', $start->format('H:i:s'))
            ->exists();
    }

    /**
     * @param  array{branch_id: ?int, client_id: int, employee_id: ?int, start: Carbon, end: Carbon, status: Status, creator: ?int}  $data
     */
    protected function createReservation(ReservationService $profile, ReservationPricingService $pricing, array $data): void
    {
        $money = $pricing->quoteForService($profile);
        $start = $data['start'];
        $end = $data['end'];
        $status = $data['status'];

        // أُنشئ قبل موعده بيومين إلى خمسة — كما يحدث فعلاً
        $createdAt = $start->copy()->subDays(2 + ($start->day % 4))->setTime(10, 15);

        $reservation = new Reservation([
            'code' => next_document_number('reservation'),
            'branch_id' => $data['branch_id'],
            'client_id' => $data['client_id'],
            'employee_id' => $data['employee_id'],
            'service_id' => $profile->id,
            'product_id' => $profile->product_id,
            'reservation_date' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'blocked_start' => $start->copy()->subMinutes((int) $profile->buffer_before)->format('H:i'),
            'blocked_end' => $end->copy()->addMinutes((int) $profile->buffer_after)->format('H:i'),
            'duration' => (int) $profile->duration,
            'status' => $status,
            'tax_id' => $money['tax_id'],
            'unit_price' => $money['unit_price'],
            'subtotal' => $money['subtotal'],
            'discount_value' => 0,
            'discount_type' => 'amount',
            'discount_amount' => $money['discount_amount'],
            'tax_amount' => $money['tax_amount'],
            'total' => $money['total'],
            'paid_amount' => 0,
            'remaining_amount' => $money['total'],
            'notes' => self::MARKER.' '.$profile->product?->name,
            'source' => 'back_office',
            'created_by' => $data['creator'],
        ] + $this->statusStamps($status, $start, $end, $data['creator']));

        $reservation->created_at = $createdAt;
        $reservation->updated_at = $createdAt;
        $reservation->save();

        $this->writeTimeline($reservation, $status, $createdAt, $start, $end, $data['creator']);
    }

    /** طوابعُ الحالة — متى أُكّد وحضر وبدأ واكتمل أو أُلغي. */
    protected function statusStamps(Status $status, Carbon $start, Carbon $end, ?int $userId): array
    {
        $reached = fn (Status ...$states) => in_array($status, $states, true);

        return array_filter([
            'confirmed_at' => $reached(Status::CONFIRMED, Status::CHECKED_IN, Status::IN_PROGRESS, Status::COMPLETED, Status::NO_SHOW)
                ? $start->copy()->subDay() : null,
            'checked_in_at' => $reached(Status::CHECKED_IN, Status::IN_PROGRESS, Status::COMPLETED) ? $start->copy()->subMinutes(5) : null,
            'started_at' => $reached(Status::IN_PROGRESS, Status::COMPLETED) ? $start : null,
            'completed_at' => $reached(Status::COMPLETED) ? $end : null,
            'no_show_at' => $reached(Status::NO_SHOW) ? $start->copy()->addMinutes(30) : null,
            'cancelled_at' => $reached(Status::CANCELLED) ? $start->copy()->subDay() : null,
            'cancelled_by' => $reached(Status::CANCELLED) ? $userId : null,
            'cancellation_reason' => $reached(Status::CANCELLED) ? 'اعتذر العميل لظرف طارئ — '.self::MARKER : null,
        ], fn ($value) => $value !== null);
    }

    /** خطُّ الحالات — كلُّ انتقالٍ مرّ به الحجز حتى حالته، بتوقيته. */
    protected function writeTimeline(Reservation $reservation, Status $status, Carbon $createdAt, Carbon $start, Carbon $end, ?int $userId): void
    {
        $steps = match ($status) {
            Status::DRAFT => [[null, Status::DRAFT, $createdAt]],
            Status::PENDING => [[null, Status::PENDING, $createdAt]],
            Status::CONFIRMED => [[null, Status::PENDING, $createdAt], [Status::PENDING, Status::CONFIRMED, $start->copy()->subDay()]],
            Status::CHECKED_IN => [[null, Status::PENDING, $createdAt], [Status::PENDING, Status::CONFIRMED, $start->copy()->subDay()], [Status::CONFIRMED, Status::CHECKED_IN, $start->copy()->subMinutes(5)]],
            Status::IN_PROGRESS => [[null, Status::PENDING, $createdAt], [Status::PENDING, Status::CONFIRMED, $start->copy()->subDay()], [Status::CONFIRMED, Status::CHECKED_IN, $start->copy()->subMinutes(5)], [Status::CHECKED_IN, Status::IN_PROGRESS, $start]],
            Status::COMPLETED => [[null, Status::PENDING, $createdAt], [Status::PENDING, Status::CONFIRMED, $start->copy()->subDay()], [Status::CONFIRMED, Status::CHECKED_IN, $start->copy()->subMinutes(5)], [Status::CHECKED_IN, Status::IN_PROGRESS, $start], [Status::IN_PROGRESS, Status::COMPLETED, $end]],
            Status::NO_SHOW => [[null, Status::PENDING, $createdAt], [Status::PENDING, Status::CONFIRMED, $start->copy()->subDay()], [Status::CONFIRMED, Status::NO_SHOW, $start->copy()->addMinutes(30)]],
            Status::CANCELLED => [[null, Status::PENDING, $createdAt], [Status::PENDING, Status::CANCELLED, $start->copy()->subDay()]],
            default => [[null, $status, $createdAt]],
        };

        foreach ($steps as $i => [$from, $to, $at]) {
            $history = new ReservationStatusHistory([
                'reservation_id' => $reservation->id,
                'from_status' => $from?->value,
                'to_status' => $to->value,
                'note' => $i === 0 ? __('reservations.timeline.created') : self::MARKER,
                'user_id' => $userId,
            ]);
            $history->created_at = $at;
            $history->updated_at = $at;
            $history->save();
        }
    }
}

<?php

namespace Modules\Client\Reservations\Services;

use App\Models\Client;
use App\Support\DataScope;
use Illuminate\Support\Facades\Route;
use Modules\Client\Reservations\Models\ReservationSetting;

/**
 * هل النظامُ جاهزٌ للحجز؟ — فحصٌ حيٌّ من البيانات الفعلية، لا قائمةُ نصائح.
 *
 * أسبابُ الرفض الشائعة كلُّها إعدادٌ ناقص: لا موظّفَ له وردية في الموارد
 * البشرية، أو لا خدمات، أو لا عملاء. فهذا الفحصُ يقول **ما الناقص الآن**
 * ويضع زرَّ إصلاحه بجانبه.
 *
 *   blocker ← الحجزُ نفسُه يُرفض بدونه.
 *   tip     ← يعمل بدونه، لكنّه ينقص تجربةً متوقَّعة.
 *
 * ولا فحصَ للحسابات ولا للخزائن: لا مالَ في الحجز — المالُ على فاتورة
 * المبيعات، وجاهزيتُها جاهزيةُ المبيعات.
 */
class ReservationReadiness
{
    public const BLOCKER = 'blocker';

    public const TIP = 'tip';

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $checks = null;

    public function __construct(protected EmployeeShiftSchedule $shifts) {}

    /**
     * @return array<int, array{key: string, level: string, ok: bool, title: string, detail: string, fix_url: ?string}>
     */
    public function checks(): array
    {
        if ($this->checks !== null) {
            return $this->checks;
        }

        $settings = ReservationSetting::current();
        $line = fn (string $key, string $state, array $replace = []) => __("reservations.start.checks.$key.$state", $replace);

        // رابطُ الإصلاح لا يُعرض لمن لا يفتحه — زرٌّ يردّ بـ403 أسوأ من غيابه
        $link = fn (string $route, ?string $permission = null) => Route::has($route) && ($permission === null || hasPermission($permission))
            ? route($route)
            : null;

        $canCreate = hasPermission('reservations.create');
        $shifted = $this->shifts->shiftedEmployeesCount(DataScope::employeeIds());
        $services = ServiceProductCatalog::query()->count();
        $hasClients = Client::query()->exists();

        return $this->checks = [
            $this->item('permission', self::BLOCKER, $canCreate, $line('permission', 'title'),
                $line('permission', $canCreate ? 'ok' : 'no'), null),

            $this->item('shifts', self::BLOCKER, $shifted > 0, $line('shifts', 'title'),
                $line('shifts', $shifted > 0 ? 'ok' : 'no', ['n' => $shifted]), $link('shift_management.index')),

            $this->item('services', self::BLOCKER, $services > 0, $line('services', 'title'),
                $line('services', $services > 0 ? 'ok' : 'no', ['n' => $services]), $link('products.create')),

            $this->item('clients', $settings->require_client ? self::BLOCKER : self::TIP, $hasClients, $line('clients', 'title'),
                $line('clients', $hasClients ? 'ok' : ($settings->require_client ? 'no' : 'no_tip')), $link('clients.create')),

            $this->item('invoice', self::TIP, hasPermission('reservations.create_invoice'), $line('invoice', 'title'),
                $line('invoice', hasPermission('reservations.create_invoice') ? 'ok' : 'no'), null),
        ];
    }

    /**
     * الناقصُ الذي يمنع الحجزَ نفسَه — لشريط التنبيه فوق «حجز جديد» واللوحة.
     *
     * @return array<int, array<string, mixed>>
     */
    public function blockers(): array
    {
        return array_values(array_filter(
            $this->checks(),
            fn ($check) => $check['level'] === self::BLOCKER && ! $check['ok']
        ));
    }

    /** @return array<string, mixed> */
    protected function item(string $key, string $level, bool $ok, string $title, string $detail, ?string $fixUrl): array
    {
        return [
            'key' => $key,
            'level' => $level,
            'ok' => $ok,
            'title' => $title,
            'detail' => $detail,
            'fix_url' => $ok ? null : $fixUrl,
        ];
    }
}

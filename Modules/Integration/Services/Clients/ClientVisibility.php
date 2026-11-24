<?php

namespace Modules\Integration\Services\Clients;

use App\Models\EmployeeClientVisit;
use App\Models\HiddenClient;
use App\Models\User;
use App\Services\ClientAccessService;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * «أيّ العملاء أرى» — نفس سلسلة شاشة «إدارة العملاء» في الموقع
 * (`ClientController::index()`) منسوخةً بترتيبها، على استعلامٍ لا على
 * قائمة معرّفات (آلاف العملاء لا تُسحب إلى الذاكرة):
 *
 *   ١. `clients_view_all_clients` ⇒ الكلّ، محصوراً بشجرة فرع المستخدم الحالي
 *      (`users.branch_id` — نفس فرع الترويسة في الموقع؛ الرئيسيّ = بلا حصر).
 *   ٢. وإلا سلسلة الصلاحيات المشتركة ({@see ClientAccessService::applyPermissionCascade()}):
 *      خطّ السير اليومي ← مجموعات الموظفين ← عملاؤه المُسندون.
 *   ٣. وإلا: الموظف ⇒ خطّ سير اليوم، ومن له فرع ⇒ شجرة فرعه، وغيرهما لا شيء.
 *
 * ثم ما تستبعده الشاشة نفسها: من أنهى المندوب زيارتهم اليوم (لمن لا يرى
 * الكلّ)، والعملاء المُخفَون من خريطته.
 */
class ClientVisibility
{
    public function __construct(private readonly ClientAccessService $access) {}

    /**
     * يقيّد الاستعلام بما يراه المستخدم — `false` حين لا يرى شيئاً (وقد
     * قُيّد الاستعلام إلى الفراغ فعلاً).
     */
    public function apply(Builder $query, User $user): bool
    {
        $branchScope = $this->access->resolveUserBranchTreeScope($user);

        if (hasPermission('clients_view_all_clients')) {
            $this->access->applyClientBranchTreeScope($query, $branchScope);

            return true;
        }

        $context = $this->access->todayItineraryContext();
        $matched = $this->access->applyPermissionCascade($query, $user, $context['dayNameEn'], $context['year'], $context['week']);

        if ($matched !== null) {
            return $matched;
        }

        if ($user->isEmployee()) {
            $ids = $this->access->todayItineraryClientIds($user->id, $context['dayNameEn'], $context['year'], $context['week']);

            if ($ids->isEmpty()) {
                $query->whereRaw('1 = 0');

                return false;
            }

            $query->whereIn('id', $ids);

            return true;
        }

        if ($user->branch_id) {
            $this->access->applyClientBranchTreeScope($query, $branchScope);

            return true;
        }

        $query->whereRaw('1 = 0');

        return false;
    }

    /** استبعادات قائمة العمل اليومية — نفس الفهرس. */
    public function excludeHidden(Builder $query, User $user, bool $forceHideFinished = false): void
    {
        if ($forceHideFinished || ! hasPermission('clients_view_all_clients')) {
            $finished = EmployeeClientVisit::hiddenFinishedClientIdsFor((int) $user->id);

            if ($finished->isNotEmpty()) {
                $query->whereNotIn('id', $finished);
            }
        }

        $hidden = HiddenClient::getHiddenClientsForUser($user->id);

        if (! empty($hidden)) {
            $query->whereNotIn('id', $hidden);
        }
    }

    /**
     * فلتر «الفرع» من التطبيق — عقدةٌ من شجرة الفروع مع كل ما تحتها.
     * الفرع خارج ما يحقّ له اختياره ({@see DataScope::selectableBranchIds()}) يُرفض
     * صراحةً لا يُتجاهَل بصمت.
     */
    public function applyBranchFilter(Builder $query, ?int $branchId, bool $withDescendants = true): void
    {
        if (! $branchId) {
            return;
        }

        $allowed = DataScope::selectableBranchIds();

        if ($allowed !== null && ! in_array($branchId, array_map('intval', $allowed), true)) {
            throw new AccessDeniedHttpException('الفرع المختار خارج نطاق صلاحيتك.');
        }

        $query->inTreeNode($branchId, $withDescendants);
    }

    public function canAccess(User $user, int $clientId): bool
    {
        return $this->access->canAccessClient($user, $clientId);
    }
}

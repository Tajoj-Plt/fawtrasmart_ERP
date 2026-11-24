<?php

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\Visit;
use App\Services\ClientAccessService;
use App\Support\HeaderBranchScope;
use Illuminate\Support\Collection;

/**
 * العملاء المسموح لمستخدمٍ برؤيتهم في قوائم اختيار العميل (سيلكت الفاتورة
 * وشاشات الإنشاء المشابهة في المبيعات) — بنفس تسلسل صلاحيات العرض الذي
 * تقرؤه «إدارة العملاء»، لا بإعداد «تقييد شاشات الإنشاء بخط السير» العام
 * الذي كان يُطبَّق على كل موظف بصرف النظر عن صلاحياته.
 *
 * والتسلسلُ **يُستدعى** من {@see \App\Services\ClientAccessService} ولا
 * يُحاكى هنا: نسختان منه افترقتا فعلاً في الترتيب وفي الانتقال عند الفراغ،
 * فرأى المندوبُ في مُنتقي الفاتورة مئاتِ عملاءَ لا تعرضهم له شاشةُ العملاء.
 * انظر {@see resolveEligibleClientIds()}.
 */
class ClientVisibilityScope
{
    /**
     * ══════════ قائمةُ العميل في مستندٍ يُنشأ ══════════
     *
     * مُنتقي العميل في شاشات الإنشاء يخضع لمحورين لا محورٍ واحد:
     *
     *   ١. **الصلاحية** — مَن يحقّ له أن يرى هذا العميل أصلاً
     *      ({@see eligibleClientIds()}): كلُّ العملاء، أو مجموعاتُ موظّفه،
     *      أو خطُّ سيره اليوم، أو عملاؤه هو.
     *   ٢. **المكان** — الفرعُ المختار في الشريط العلويّ. مَن بدّل الشريط
     *      إلى «فرع المدينة» يعمل في فرع المدينة، فقائمةُ عملائه يجب أن
     *      تكون عملاءَ ذلك الفرع وما تحته لا عملاءَ المنشأة كلِّها.
     *
     * والمحوران يتقاطعان ولا يحلّ أحدهما محلّ الآخر: صلاحيةُ «كلّ العملاء»
     * لا تُلغي فرعَ الشريط، وفرعُ الشريط لا يُري أحداً عميلاً لا يحقّ له.
     * (هذه هي قاعدةُ محورَي النطاق نفسُها المتَّبعة في التقارير.)
     *
     * ── ومتى تُعرَض القائمةُ كاملةً ──
     *
     * حين لا يكون في الشريط فرعٌ أصلاً («كلّ الفروع»)، أو حين يكون
     * المختارُ هو **الفرع الرئيسي** — والقاعدةُ وشرحُها في
     * {@see \App\Support\HeaderBranchScope}، وتشترك فيها قوائمُ المورِّدين
     * أيضاً فلا تُكتب هنا نسخةٌ ثانية.
     *
     * ── والمطابقة ──
     *
     * عبر {@see \App\Models\Client::scopeInTreeNode()} — الموضعُ الوحيد
     * الذي يعرف كيف يتعلّق العميلُ بعقدة: `tree_node_id` أوّلاً، ثمّ
     * `branch_id` و`neighborhoods.region_id` للصفوف التي سبقت العمود.
     * ومطابقةُ `clients.branch_id` وحدَها تُسقط عملاءَ المناطق والمنافذ —
     * ثمانيةٌ من ٦٥٧ في منطقة الرياض وحدها.
     *
     * ── وعميلُ المستند المفتوح ──
     *
     * شاشةُ **التعديل** تسأل سؤالاً زائداً: مستندٌ محفوظٌ لعميلٍ خارج نطاق
     * مَن يعدّله اليوم (أُسنِد العميلُ لغيره، أو بُدّل فرعُ الشريط) يفتح
     * على مُنتقٍ لا يحوي عميلَه، فيُعرض فارغاً — ثمّ يحفظ المستخدمُ فيكتب
     * `client_id` فارغاً أو يُنسب المستندُ إلى أوّل اسمٍ في القائمة. لذلك
     * يُمرَّر `$keepClientIds`: العميلُ المحفوظ يبقى خياراً مهما ضاق
     * النطاق. وهو استثناءٌ لا ثغرة — المستندُ معروضٌ له أصلاً، وصلاحيةُ
     * تعديله محروسةٌ قبل هذه الدالّة.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  استعلامُ العملاء
     * @param  mixed  $user  المستخدم — الافتراضي الحاليّ
     * @param  int|null  $branchId  عقدةٌ صريحة بدل فرع الشريط
     * @param  mixed  $keepClientIds  عميلٌ (أو عملاء) لا يسقطون من القائمة
     */
    public static function applyDocumentSelectScope($query, $user = null, ?int $branchId = null, $keepClientIds = null)
    {
        $user = $user ?: auth()->user();

        $keep = static::normalizeKeptIds($keepClientIds);

        // ── المحور الأوّل: الصلاحية ──
        $eligible = static::eligibleClientIds($user);

        if ($eligible !== null) {
            $query->whereIn('id', $eligible->map(fn ($id) => (int) $id)->merge($keep)->unique()->values());
        }

        /* ── المحور الثاني: فرع الشريط العلويّ ──
           ولا يُطبَّق على **محور الأشخاص**: حين تكون القائمةُ أصلاً «عملاءُ
           خطّ سيري» أو «عملاءُ مجموعاتي» أو «عملائي المُسنَدون»، فالعميلُ
           منسوبٌ إليّ لا إلى مكان — وقصُّه على فرعِ الشريط لا يزيد إلّا
           إخفاءَ عملائي أنا.

           وهو ما وقع فعلاً: مشرفٌ خطُّ سير فريقه عميلان، أحدُهما بلا
           تبعيّةٍ إطلاقاً (`tree_node_id` و`branch_id` فارغان) والآخرُ في
           منطقةٍ غير منطقته — فيفتح شاشة الفاتورة على مُنتقي عملاءَ
           **فارغ**، ولا يستطيع أن يكتب فاتورةً لأحد.

           و«إدارة العملاء» لا تفعل هذا أصلاً: تُطبّق شجرةَ الفرع على من
           يملك `clients_view_all_clients` وحدَه
           (`ClientController::index()`). فهذا يُلحق المُنتقي بالفهرس بدل
           أن يفترقا — وهو نصُّ ما وُجد له هذا الصنف. */
        /* و«محورُ الأشخاص» هو بعينه أن يكون النطاقُ مقيَّداً بقائمة:
           `eligibleClientIds()` تردّ `null` لمن يملك `clients_view_all_clients`
           — وهو وحدَه محورُ المكان — وتردّ قائمةً لمن نطاقُه خطُّ سيره أو
           مجموعاتُه أو عملاؤه المُسنَدون، وتلك نسبةٌ إلى شخصٍ لا إلى مكان.

           (كان السطرُ `$this->peopleAxis ?? false` — و`$this` في دالّةٍ
           ساكنة يرمي `Using $this when not in object context`، فتسقط
           شاشاتُ الإنشاء كلُّها بـ500. والخاصّيةُ لا وجود لها أصلاً.) */
        $nodeId = $eligible !== null ? null : HeaderBranchScope::nodeId($branchId);

        if ($nodeId) {
            $query->where(function ($scoped) use ($nodeId, $keep) {
                $scoped->inTreeNode($nodeId);

                if ($keep) {
                    $scoped->orWhereIn('id', $keep);
                }
            });
        }

        return $query;
    }

    /**
     * معرّفاتُ العملاء المستثنَين — واحداً كان أو قائمة.
     *
     * `0` و`null` تُطرحان: تمريرُ `client_id` فارغٍ من مستندٍ ناقص لا يجوز
     * أن يُترجَم إلى `orWhereIn('id', [0])` يلغي شرطَ الفرع كلَّه.
     *
     * @return array<int, int>
     */
    private static function normalizeKeptIds($ids): array
    {
        if ($ids === null) {
            return [];
        }

        return collect(is_array($ids) || $ids instanceof Collection ? $ids : [$ids])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * عملاءُ مُنتقي المستند جاهزين — الشكلُ الذي تحتاجه شاشاتُ الإنشاء.
     *
     * `$with` لأنّ بعض الشاشات تعرض رصيدَ العميل تحت المُنتقي، فتحتاج
     * `account` محمَّلةً مسبقاً.
     *
     * @param  array<int|string, mixed>  $with
     * @param  mixed  $keepClientIds  عميلُ المستند المفتوح — لا يسقط من القائمة
     */
    public static function documentSelectClients($user = null, array $with = [], ?int $branchId = null, $keepClientIds = null)
    {
        $query = Client::query();

        if ($with) {
            $query->with($with);
        }

        return static::applyDocumentSelectScope($query, $user, $branchId, $keepClientIds)->get();
    }

    /**
     * @return Collection<int, int>|null معرّفات العملاء المسموحين، أو null لعدم وجود قيد (كل العملاء)
     */
    public static function eligibleClientIds($user): ?Collection
    {
        $ids = static::resolveEligibleClientIds($user);

        // عميلُ الزيارة الجارية يُضاف دائماً — راجع Visit::openWorkspaceClientIdsFor()
        if ($ids !== null && $user) {
            $ids = $ids->map(fn ($id) => (int) $id)
                ->merge(Visit::openWorkspaceClientIdsFor((int) $user->id))
                ->unique()
                ->values();
        }

        return $ids;
    }

    /**
     * ══════════ تسلسلٌ واحد، لا نسختان ══════════
     *
     * كان هنا تسلسلٌ ثانٍ مكتوبٌ بيده يخالف تسلسلَ «إدارة العملاء» في
     * أمرين، فيفترق ما تراه الشاشتان لنفس المستخدم:
     *
     *   • **الترتيب** — كان يبدأ بالمجموعات، والفهرس يبدأ بخطّ السير.
     *   • **الانتقالُ عند الفراغ** — كان نطاقٌ فارغ يُسقِط المستخدمَ إلى
     *     الصلاحية التالية. والفهرس لا ينتقل: أوّلُ صلاحيةٍ يملكها هي
     *     نطاقُه وحدَها ولو كان فارغاً.
     *
     * والفرقُ ليس نظرياً: مندوبٌ يملك «خط السير اليومي» و«عملاؤه هو» معاً
     * — وهو حال المندوبين كلِّهم في هذه المنشأة — ويفتح يوماً بلا خطّ سير،
     * كان يجد «إدارة العملاء» فارغةً (صحيح) وسيلكت الفاتورة وعرض السعر
     * والإشعار والمرتجع ممتلئاً بمئات عملائه المسندين (خطأ).
     *
     * فالتسلسلُ الآن من {@see ClientAccessService::applyPermissionCascade()}
     * نفسِها التي يستدعيها الفهرس — لا نسخةٌ تُحاكيها. وعلّةُ النسخة
     * القديمة المشروحة فيها (مَن يملك المجموعاتِ الفارغةَ وخطَّ السير معاً
     * يُحبَس عند الأولى) يحلّها ترتيبُ الفهرس نفسُه: خطُّ السير أوّلاً.
     */
    private static function resolveEligibleClientIds($user): ?Collection
    {
        $access = app(ClientAccessService::class);

        /* «كلّ العملاء» — بحدود شجرة فرع المستخدم كما في الفهرس تماماً:
           مديرُ فرعٍ يملك الصلاحية لا يرى في «إدارة العملاء» إلّا فرعَه وما
           تحته، فلا يصحّ أن يراهم كلَّهم في مُنتقي المستند. والفرعُ الرئيسيّ
           (أو بلا فرع) يعني المنشأة كلَّها — و`null` هنا «بلا قيد» فلا
           تُستخرَج ٣٨٥٢ معرّفاً لتقول «الكلّ». */
        if (hasPermission('clients_view_all_clients')) {
            return static::branchTreeClientIds($access, $user);
        }

        $context = $access->todayItineraryContext();
        $query = Client::query();

        $matched = $access->applyPermissionCascade(
            $query, $user, $context['dayNameEn'], $context['year'], $context['week']
        );

        /* `true` نطاقٌ غير فارغ، و`false` صلاحيةٌ مملوكةٌ نطاقُها فارغ
           (وقد طُبِّق `1 = 0` على الاستعلام) — وكلتاهما نتيجةٌ نهائية. */
        if ($matched !== null) {
            return $query->pluck('id');
        }

        // لا يملك أيّاً من الثلاث: احتياطُ الفهرس نفسُه — خطُّ سيره، ثمّ فرعُه
        if ($user->isEmployee()) {
            return $access->todayItineraryClientIds(
                $user->id, $context['dayNameEn'], $context['year'], $context['week']
            );
        }

        return static::branchTreeClientIds($access, $user);
    }

    /**
     * عملاءُ شجرة فرع المستخدم — أو `null` بلا قيد.
     *
     * عبر {@see ClientAccessService::applyClientBranchTreeScope()} لا
     * بمطابقةٍ مكتوبةٍ هنا: تلك تبدأ بالتبعيّة الصريحة (`tree_node_id`)
     * وتنزل إلى الأعمدة القديمة لمن لا تبعيّة له، والنسخةُ التي كانت هنا
     * تطابق `branch_id` والحيَّ معاً فتضمّ عملاءَ لا يضمّهم الفهرس.
     */
    private static function branchTreeClientIds(ClientAccessService $access, $user): ?Collection
    {
        $scopeIds = $access->resolveUserBranchTreeScope($user);

        if ($scopeIds === null) {
            return null;
        }

        $query = Client::query();
        $access->applyClientBranchTreeScope($query, $scopeIds);

        return $query->pluck('id');
    }
}

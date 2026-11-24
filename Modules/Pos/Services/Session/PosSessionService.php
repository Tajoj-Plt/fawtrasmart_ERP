<?php

namespace Modules\Pos\Services\Session;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\CashierDevice;
use App\Models\PosSession;
use App\Models\PosSessionDetail;
use App\Models\shifts_pos;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Pos\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosSettings;
use Modules\Pos\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Pos\Models\PosAuditLog;
use Modules\Pos\Services\Accounting\PosJournalPoster;

/**
 * دورةُ حياة جلسة نقطة البيع: فتحٌ ← بيعٌ ← إقفالٌ ← **تأكيد**.
 *
 * ═════════════════════ الخطوةُ الرابعة التي لم تكن ═════════════════════
 *
 * `points_sale_confirm_close_sessions_own` و`..._all` صلاحيتان معرَّفتان في
 * كتالوج الصلاحيات منذ البداية، ووصفُهما في الكتالوج يقول بوضوح: «الكاشير
 * يُغلق، والمشرف يؤكّد. هذا أصل الضبط النقدي في نقاط البيع».
 *
 * **ولم يكن لهما تنفيذٌ إطلاقاً**: لا عمود يحمل من أكّد ولا متى، ولا حالة
 * «بانتظار التأكيد»، ولا دالّة تُستدعى. فالكاشير يعدّ درجَه ويكتب الرقم
 * ويُقفل — ويعتمد عهدته بنفسه. وهو بالضبط ما وُضعت الصلاحيتان لمنعه.
 *
 * ═════════════════════ وفرقُ الصندوق بلا قيد ═════════════════════
 *
 * كان الفرقُ يُكتب في `pos_session_details` بنوعٍ `cash_adjustment` — صفٌّ
 * في جدولٍ تشغيلي، **بلا أيّ أثرٍ في دفتر الأستاذ**. فخزينةُ الكاشير تُقفل
 * على رصيدٍ والدفاترُ تقول رصيداً آخر، والفرقُ يتراكم شهراً بعد شهر بلا
 * حسابٍ يحمله — حتى يظهر عند الجرد النقدي دفعةً واحدة.
 *
 * وهنا يُقيَّد: الفائضُ إيرادٌ عارض والعجزُ مصروف، بحسابين منفصلين يضبطهما
 * المحاسب (`pos_cash_over` و`pos_cash_short`).
 */
class PosSessionService
{
    use ResolvesAccountingAccounts;
    use ResolvesPosSettings;
    use RespectsAccountingPeriods;
    use UsesDocumentCurrency;

    public function __construct(private CashDrawerService $drawer) {}

    /* ═══════════════════════ الفتح ═══════════════════════ */

    /**
     * الجهازُ لمن أُسنِد إليه — لا لغيره.
     *
     * ═══════════════ ما كان ═══════════════
     *
     * الفحصُ الوحيد كان `assertDeviceAccess()`: «أيبلغ هذا المستخدمُ هذا
     * الجهاز؟» — وهي تسمح بأجهزة المرؤوسين وبالأجهزة غير المُسنَدة. فمديرٌ
     * يفتح جلسةً على جهاز مرؤوسه، والجلسةُ تُنسب إليه هو (`user_id` =
     * المستخدمُ الحالي) بينما مخزونُ البيع وخزينتُه يُشتقّان من موظّف
     * الجهاز عبر `PosCustody`.
     *
     * فينشقُّ المستندُ عن نفسه: الجلسةُ باسم زيد، والبضاعةُ تُخصم من مستودع
     * عمرو، والنقدُ يدخل خزينةَ عمرو. ثم يُغلق زيدٌ الجلسةَ فيُحاسَب على
     * عهدةٍ ليست عهدتَه.
     *
     * ═══════════════ والقاعدة ═══════════════
     *
     * **الموظّفُ المرتبط بالكاشير هو نفسُه الذي يفتح الجلسة.** فلا يبقى
     * انفصالٌ بين من تُنسب إليه الجلسة ومن يُخصم من مستودعه.
     *
     * وجهازٌ بلا مسؤولٍ يُرفض صراحةً لا يُقبل بالسكوت: `PosCustody` لن تجد
     * له مستودعاً ولا خزينة، فالجلسةُ تُفتح ثم يقف البيعُ عند أوّل صنف —
     * والرفضُ هنا يقول السببَ في وقته.
     */
    private function assertDeviceBelongsToOpener(CashierDevice $device, $user): void
    {
        if (! $device->user_id) {
            throw new \RuntimeException(sprintf(
                'الجهاز «%s» غير مرتبط بموظف. اربطه بموظف من شاشة الأجهزة قبل فتح جلسة عليه.',
                $device->device_name
            ));
        }

        if ((int) $device->user_id === (int) $user->id) {
            return;
        }

        $owner = User::find($device->user_id);

        throw new \RuntimeException(sprintf(
            'الجهاز «%s» مرتبط بالموظف «%s» — وهو وحده من يفتح عليه جلسة. '
            .'لفتح جلسةٍ لموظفٍ آخر أعِد إسنادَ الجهاز إليه من شاشة الأجهزة.',
            $device->device_name,
            $owner?->name ?? '#'.$device->user_id
        ));
    }

    /**
     * فتحُ جلسة.
     *
     * @param  array{device_id: int, shift_id: int, opening_balance: float}  $data
     *
     * @throws \RuntimeException
     */
    public function open(array $data): PosSession
    {
        $user = auth()->user();

        $device = CashierDevice::findOrFail($data['device_id']);

        $this->assertDeviceBelongsToOpener($device, $user);

        /*
            الفرعُ يُشتقّ من الجهاز **مرّةً واحدة، لحظةَ الفتح**، ثم يُختَم
            في الجلسة.

            وكان يُستنتج عند كل قراءة بقفزتين (جلسة ← جهاز ← فرع)، فنقلُ
            الجهاز بين الفروع لاحقاً يُعيد كتابة تاريخ الجلسات القديمة:
            جلسةُ الشهر الماضي تظهر فجأةً في فرعٍ لم تُفتح فيه.
        */
        $branchId = $device->branch_id
            ?? $user->employee?->branch_id
            ?? $user->branch_id;

        if (! $this->posEnabledForBranch($branchId)) {
            throw new \RuntimeException('نقطةُ البيع غير مفعَّلة لهذا الفرع.');
        }

        if ($block = $this->accountingPeriodBlock(now())) {
            throw new \RuntimeException($block);
        }

        $this->assertNoConflictingSession($user->id, $data['device_id']);
        $this->assertShiftWindow($data['shift_id']);

        return DB::transaction(function () use ($data, $user) {
            $session = PosSession::create([
                'session_number' => PosSession::generateSessionNumber(),
                'user_id' => $user->id,
                'device_id' => $data['device_id'],
                'shift_id' => $data['shift_id'],
                // `branch_id` و`accounting_period_id` و`currency` تُختَم في
                // [PosSession] عبر سماتها — والفرعُ يُشتقّ من الجهاز هناك،
                // فلا يُمرَّر من هنا ولا من أي طريقٍ آخر يفتح جلسة
                'opening_balance' => $data['opening_balance'],
                'started_at' => now(),
                'status' => PosSession::STATUS_ACTIVE,
            ]);

            if ((float) $data['opening_balance'] > 0) {
                PosSessionDetail::create([
                    'session_id' => $session->id,
                    'transaction_type' => 'opening_balance',
                    'amount' => $data['opening_balance'],
                    'payment_method' => 'cash',
                    'cash_amount' => $data['opening_balance'],
                    'card_amount' => 0,
                    'description' => 'الرصيد الافتتاحي',
                    'transaction_time' => now(),
                ]);
            }

            return $session->fresh();
        });
    }

    /**
     * موانعُ الفتح — اثنان لا ثلاثة.
     *
     * ═══════════════ ما سقط: القيدُ على الوردية ═══════════════
     *
     * كان:
     *
     *     PosSession::active()->where('shift_id', $shiftId)->exists()
     *     ⇒ «الوردية المحددة لديها جلسة نشطة للمستخدم: فلان»
     *
     * أي **جلسةٌ نشطةٌ واحدة لكل ورديةٍ في المنشأة كلِّها**. وهو يناقض ما
     * الورديةُ عليه: «الوردية الصباحية» مدًى زمنيّ يعمل فيه عشرةُ كاشيرين
     * في خمسة فروع — لا مقعدٌ يجلس عليه واحد.
     *
     * والأثرُ أنّ أوّلَ من يفتح جلسته صباحاً يُقفل الوردية على الباقين،
     * فيضطرّ الثاني إلى اختيار ورديةٍ ليست ورديتَه لينفُذ. فينهار ما بُنيت
     * عليه الورديةُ أصلاً: تقريرُ مبيعات الورديات، وفحصُ `windowCheck()`
     * على وقت الدوام، وقياسُ الالتزام.
     *
     * ═══════════════ وما بقي ═══════════════
     *
     *   • جلسةٌ نشطةٌ واحدة **للمستخدم** — عهدتُه واحدة لا تتجزّأ.
     *   • وجلسةٌ نشطةٌ واحدة **للجهاز** — الدرجُ الفعليُّ واحد.
     *
     * وهما ما يحفظ لكلِّ جلسةٍ بياناتِها: الفاتورةُ تحمل `session_id`،
     * وحركةُ الدرج كذلك، والرصيدُ المتوقَّع يُحسب من جلسته وحدها. فعشرُ
     * جلساتٍ على وردية واحدة عشرُ عُهَدٍ منفصلة، تُقفَل كلٌّ منها على حدة
     * ويُراجَع فرقُها وحده.
     *
     * @throws \RuntimeException
     */
    private function assertNoConflictingSession(int $userId, int $deviceId): void
    {
        /*
            «مفتوحة» لا «نشطة»: المعلَّقةُ عهدةٌ لم تُسلَّم بعد — انظر
            `PosSession::scopeOpen()`. وكان الفحصُ بـ`active()` فمن علّق
            جلستَه فتح ثانيةً وبقيت الأولى معلَّقةً لا تُحصى.
        */
        $own = PosSession::open()->forUser($userId)->first();

        if ($own) {
            throw new \RuntimeException(sprintf(
                'لديك جلسة %s بالفعل (%s). يجب إقفالها قبل فتح جلسة جديدة.',
                $own->status === PosSession::STATUS_SUSPENDED ? 'معلَّقة' : 'نشطة',
                $own->session_number
            ));
        }

        $deviceSession = PosSession::open()->forDevice($deviceId)->first();

        if ($deviceSession) {
            throw new \RuntimeException(sprintf(
                'الجهاز المحدد عليه جلسة %s للمستخدم «%s» (%s) — يُقفلها صاحبُها أوّلاً.',
                $deviceSession->status === PosSession::STATUS_SUSPENDED ? 'معلَّقة' : 'نشطة',
                $deviceSession->user->name ?? '—',
                $deviceSession->session_number
            ));
        }
    }

    /**
     * قواعدُ الوردية — تُفرَض لا تُسجَّل.
     *
     * ═══════════════ ما كان ═══════════════
     *
     * `warnIfOutsideShift()`: تقيس الوقتَ ثم **تكتب سطراً في اللوغ وتمضي**.
     * لا منعَ ولا إخبارَ للمستخدم — فمن يفتح جلسةً في الثالثة فجراً على
     * ورديةٍ صباحية يمرّ كما لو كان في موعده، ولا يعلم أحدٌ إلّا من قرأ
     * اللوغ. و`days_of_week` لم تكن تُقرأ في تلك الدالّة إطلاقاً.
     *
     * ═══════════════ والحزمُ متفاوت ═══════════════
     *
     *   • الوردية المعطَّلة واليوم غير المسموح: منعٌ **دائم** — من عطّل
     *     ورديةً أو حدّد أيّامَها قصد المنعَ صراحةً.
     *
     *   • نافذةُ الوقت: منعٌ افتراضيّ يُتاح إرخاؤه بـ`enforce_shift_window`،
     *     لأن `grace_minutes` نفسَها اعترافٌ بأن الحدَّ ليّن. ومع الإرخاء
     *     يبقى السطرُ في اللوغ — فالمخالفةُ تُسجَّل وإن لم تُمنع.
     *
     * @throws \RuntimeException
     */
    private function assertShiftWindow(int $shiftId): void
    {
        $reason = $this->shiftWindowBlock(shifts_pos::find($shiftId));

        if ($reason !== null) {
            throw new \RuntimeException($reason);
        }
    }

    /**
     * أهذه الورديةُ خارجَ مداها الآن؟ — السببُ نصّاً، أو `null` إن جاز.
     *
     * ═══════════════ لماذا دالّةٌ عامّة ═══════════════
     *
     * كان الفحصُ خاصّاً بلحظة **فتح** الجلسة وحدها. فمن فتح جلستَه في
     * موعدها ثم بقي عليها إلى الفجر يبيع بلا مانع — والقاعدةُ التي تمنع
     * الفتحَ خارج الوقت تمنع البيعَ خارجَه من باب أولى: الورديةُ مدًى
     * زمنيّ لا بوّابةُ دخول.
     *
     * وتُقرأ الآن من ثلاثة مواضع: فتحُ الجلسة، وشاشةُ الكاشير، وقائمةُ
     * الورديات في نموذج الفتح — فلا يُعرض خيارٌ سيُرفض.
     *
     * ═══════════════ ما يُمنع دائماً وما يُرخى ═══════════════
     *
     *   • ورديةٌ معطَّلة، أو يومٌ لا تعمل فيه ⇒ **منعٌ قاطع**. هذان قرارٌ
     *     صريحٌ من المسؤول لا تقديرُ وقت.
     *   • خارجَ ساعات الدوام (بعد المهلة) ⇒ يُمنع افتراضاً، ويُرخى
     *     بإعداد `enforce_shift_window` — وحينها يُكتب في السجلّ ويمضي.
     */
    public function shiftWindowBlock(?shifts_pos $shift): ?string
    {
        if (! $shift) {
            return null;
        }

        $check = $shift->windowCheck();

        if ($check['ok']) {
            return null;
        }

        $days = array_map('intval', (array) ($shift->days_of_week ?? []));

        $hardBlock = ! $shift->is_active
            || ($days && ! in_array((int) Carbon::now()->dayOfWeek, $days, true));

        if ($hardBlock || $this->posSettingEnabled('enforce_shift_window', 'enforce_shift_window', true)) {
            return $check['reason'];
        }

        Log::info('عملٌ في نقطة بيع خارج وقت الوردية', [
            'shift_id' => $shift->id,
            'shift' => $shift->name,
            'reason' => $check['reason'],
            'user_id' => auth()->id(),
        ]);

        return null;
    }

    /**
     * أتجوز مواصلةُ العمل على هذه الجلسة الآن؟
     *
     * جلسةٌ فُتحت في موعدها لا تبقى مشروعةً إلى الأبد. وجوابُها نصُّ السبب
     * أو `null` — ليعرضه المتحكّمُ كما هو بدل أن يخترع رسالةً أخرى.
     */
    public function sessionWindowBlock(?PosSession $session): ?string
    {
        if (! $session || ! $session->shift_id) {
            return null;
        }

        return $this->shiftWindowBlock($session->shift);
    }

    /* ═══════════════════════ الإحصاءات ═══════════════════════ */

    /**
     * تحديثُ إحصائيات الجلسة.
     *
     * كانت هذه الدالّة **مكرَّرةً حرفياً** في متحكّمَين
     * (`SalesStartController::updateSessionStatistics` و
     * `SessionsController::updateSessionStatistics`) — نسختان تتباعدان بأول
     * تعديلٍ يُطبَّق على إحداهما.
     */
    public function refreshStatistics(PosSession $session): PosSession
    {
        /*
            ═══════ الرصيدُ الافتتاحي كان يُحتسب مرّتين ═══════

            كانت الصيغة:

                SUM(cash_amount) as total_cash

            **على كل الصفوف** — وفتحُ الجلسة يُنشئ صفّاً بنوع
            `opening_balance` وبـ`cash_amount` يساوي الرصيدَ الافتتاحي.
            فيدخل الافتتاحُ في `total_cash`.

            ثم يُحسب المتوقَّع بـ`opening_balance + total_cash - total_returns`
            — فيُجمع الافتتاحُ مرّةً بعمودِه ومرّةً داخل `total_cash`.

            جلسةٌ تفتح بـ٥٠٠ وتبيع بـ٠ كان متوقَّعُها ١٬٠٠٠ لا ٥٠٠. والفرقُ
            عند الإقفال يُظهر عجزاً بمقدار الرصيد الافتتاحي **في كل جلسة**.

            والحصرُ هنا على `sale`: المرتجعاتُ تُطرح بعمودها
            (`total_returns`)، وصفوفُ الإقفال والتسوية والافتتاح ليست
            مبيعاتٍ فلا مكان لها في `total_cash`.

            وعدُّ المعاملات كذلك: كان يعدّ صفوف الافتتاح والإقفال معاملاتٍ،
            فجلسةٌ بلا بيعٍ واحد تُظهر «معاملتان».
        */
        /*
            صفوفُ الإلغاء (`void`) تحمل مبالغَ **سالبة** بقصد.

            فدخولُها في المجاميع يُلغي أثرَ الفاتورة الملغاة بلا حذفِ
            سطرها — والجلسةُ سجلٌّ زمني لما جرى على الدرج، فحذفُ سطرٍ منها
            يُخفي أنّ بيعاً وقع ثم أُلغي.

            وهي لا تُعدّ «معاملة»: البيعُ الملغى لم يقع، فعدُّه يُضخّم
            «عدد المعاملات» ويُفسد متوسّطَ الفاتورة.
        */
        $stats = $session->details()
            ->selectRaw("
                SUM(CASE WHEN transaction_type IN ('sale','return') THEN 1 ELSE 0 END) as transaction_count,
                SUM(CASE WHEN transaction_type IN ('sale','void') THEN amount ELSE 0 END) as total_sales,
                SUM(CASE WHEN transaction_type = 'return' THEN amount ELSE 0 END) as total_returns,
                SUM(CASE WHEN transaction_type IN ('sale','void') THEN cash_amount ELSE 0 END) as total_cash,
                SUM(CASE WHEN transaction_type IN ('sale','void') THEN card_amount ELSE 0 END) as total_card
            ")
            ->first();

        $session->update([
            'total_transactions' => $stats->transaction_count ?? 0,
            'total_sales' => $stats->total_sales ?? 0,
            'total_returns' => $stats->total_returns ?? 0,
            'total_cash' => $stats->total_cash ?? 0,
            'total_card' => $stats->total_card ?? 0,
        ]);

        return $session->fresh();
    }

    /* ═══════════════════════ الإقفال ═══════════════════════ */

    /**
     * إقفالُ الجلسة.
     *
     * تنتهي إلى `pending_review` لا إلى `closed`: العهدةُ لا تُعتمد إلّا
     * بتأكيد مشرف. وتصير `closed` مباشرةً فقط حين لا يكون في المنشأة من
     * يؤكّد (لا أحدَ يملك صلاحية التأكيد) — وإلّا عُلّقت الجلسات إلى الأبد.
     *
     * @param  array{actual_closing_balance: float, closing_notes?: string|null}  $data
     */
    public function close(PosSession $session, array $data): PosSession
    {
        if (! $session->canBeClosed()) {
            throw new \RuntimeException('هذه الجلسة مُقفلةٌ أصلاً، فلا يمكن إقفالها مرّةً أخرى.');
        }

        if ($block = $this->accountingPeriodBlock(now())) {
            throw new \RuntimeException($block);
        }

        return DB::transaction(function () use ($session, $data) {
            $this->refreshStatistics($session);
            $session->refresh();

            // المتوقَّعُ يُجمَّد هنا — يشمل حركاتِ الدرج التي لم تكن تُحسب
            $expected = $session->computeExpectedCash();
            $actual = round((float) $data['actual_closing_balance'], 2);
            $difference = round($actual - $expected, 2);

            $needsConfirmation = $this->confirmationIsRequired();

            $session->update([
                'status' => $needsConfirmation
                    ? PosSession::STATUS_PENDING_REVIEW
                    : PosSession::STATUS_CLOSED,
                'ended_at' => now(),
                'expected_cash' => $expected,
                'closing_balance' => $expected,
                'actual_closing_balance' => $actual,
                'difference' => $difference,
                'closing_notes' => $data['closing_notes'] ?? null,
                'closed_by' => auth()->id(),
            ]);

            PosSessionDetail::create([
                'session_id' => $session->id,
                'transaction_type' => 'closing_balance',
                'amount' => $actual,
                'payment_method' => 'cash',
                'cash_amount' => $actual,
                'card_amount' => 0,
                'description' => 'الرصيد الختامي الفعلي',
                'transaction_time' => now(),
            ]);

            if (abs($difference) > 0.01) {
                PosSessionDetail::create([
                    'session_id' => $session->id,
                    'transaction_type' => 'cash_adjustment',
                    'amount' => abs($difference),
                    'payment_method' => 'cash',
                    'cash_amount' => $difference,
                    'card_amount' => 0,
                    'description' => $difference > 0 ? 'زيادة في الصندوق' : 'نقص في الصندوق',
                    'transaction_time' => now(),
                ]);

                $this->postVarianceEntry($session, $difference);

                PosAuditLog::record(PosAuditLog::ACTION_SESSION_CLOSE_VARIANCE, [
                    'session_id' => $session->id,
                    'device_id' => $session->device_id,
                    'branch_id' => $session->branch_id,
                    'amount' => $difference,
                    'currency' => $session->currency,
                    'reason' => $data['closing_notes'] ?? null,
                    'before' => ['expected' => $expected],
                    'after' => ['actual' => $actual],
                    'accounting_period_id' => $session->accounting_period_id,
                ]);
            }

            return $session->fresh();
        });
    }

    /**
     * أيلزم تأكيدٌ بعد الإقفال؟
     *
     * يلزم إن كان في المنشأة **دورٌ واحد على الأقل** يملك صلاحية التأكيد.
     * وإلّا فلا: منشأةٌ بمالكٍ واحد يبيع بنفسه لا معنى لأن تنتظر جلساتُها
     * تأكيداً لن يأتي.
     */
    private function confirmationIsRequired(): bool
    {
        try {
            return DB::table('permissions')
                ->whereIn('name', [
                    'points_sale_confirm_close_sessions_all',
                    'points_sale_confirm_close_sessions_own',
                ])
                ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * قيدُ فرق الصندوق.
     *
     * الفائضُ (`+`): مدينٌ خزينةُ الكاشير، دائنٌ حسابُ الفائض.
     * العجزُ  (`-`): مدينٌ حسابُ العجز، دائنٌ خزينةُ الكاشير.
     *
     * ولا يُقيَّد شيءٌ إن لم يُربط الحسابُ في الشاشة — ويُكتب ذلك في اللوغ
     * بدل أن يُرحَّل الفرقُ إلى حسابٍ مخمَّن.
     */
    private function postVarianceEntry(PosSession $session, float $difference): void
    {
        $isOver = $difference > 0;
        $account = $isOver ? $this->cashOverAccount() : $this->cashShortAccount();

        if (! $account) {
            Log::warning('فرقُ صندوقٍ بلا قيد — حسابُ الفرق غير مربوط في «الحسابات المحاسبية»', [
                'session_id' => $session->id,
                'difference' => $difference,
                'key' => $isOver ? 'pos_cash_over' : 'pos_cash_short',
            ]);

            return;
        }

        $drawerAccount = $this->treasuryAccountFor($session->user?->employee_id);

        $amount = abs($difference);

        $poster = PosJournalPoster::make([
            'reference_number' => 'VAR-'.$session->session_number,
            'date' => now(),
            'description' => ($isOver ? 'فائض' : 'عجز').' درج الكاشير — جلسة '.$session->session_number,
            'currency' => $this->documentCurrency($session),
            'branch_id' => $session->branch_id,
            'accounting_period_id' => $session->accounting_period_id,
            'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
            'linking_type' => LinkingType::SINGLE,
        ]);

        if ($isOver) {
            $poster->debit($drawerAccount, $amount, 'زيادة نقدية في الدرج')
                ->credit($account, $amount, 'إيراد فائض صندوق');
        } else {
            $poster->debit($account, $amount, 'مصروف عجز صندوق')
                ->credit($drawerAccount, $amount, 'نقص نقدي في الدرج');
        }

        $entry = $poster->post();

        if ($entry) {
            $session->update(['variance_journal_entry_id' => $entry->id]);
        }
    }

    /* ═══════════════════════ التأكيد ═══════════════════════ */

    /**
     * تأكيدُ الإقفال — الخطوةُ التي تُغلق العهدة فعلاً.
     */
    public function confirm(PosSession $session, ?string $notes = null): PosSession
    {
        if (! $session->needsConfirmation()) {
            throw new \RuntimeException('هذه الجلسة ليست بانتظار التأكيد.');
        }

        return DB::transaction(function () use ($session, $notes) {
            $session->update([
                'status' => PosSession::STATUS_CLOSED,
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'confirmation_notes' => $notes,
            ]);

            PosAuditLog::record(PosAuditLog::ACTION_SESSION_CONFIRM, [
                'session_id' => $session->id,
                'device_id' => $session->device_id,
                'branch_id' => $session->branch_id,
                'approved_by' => auth()->id(),
                'amount' => $session->difference,
                'currency' => $session->currency,
                'reason' => $notes,
                'accounting_period_id' => $session->accounting_period_id,
            ]);

            return $session->fresh();
        });
    }

    /* ═══════════════════════ ملخّصٌ للشاشة ═══════════════════════ */

    /**
     * ملخّصُ الإقفال — ما تعرضه شاشةُ الإغلاق قبل أن يكتب الكاشير رقمه.
     *
     * @return array<string, mixed>
     */
    public function closingSummary(PosSession $session): array
    {
        $this->refreshStatistics($session);
        $session->refresh();

        $drawer = $this->drawer->summary($session);

        return [
            'opening_balance' => (float) $session->opening_balance,
            'total_sales' => (float) $session->total_sales,
            'total_cash' => (float) $session->total_cash,
            'total_card' => (float) $session->total_card,
            'total_returns' => (float) $session->total_returns,
            'total_transactions' => (int) $session->total_transactions,
            'drawer' => $drawer,
            'expected_cash' => $session->computeExpectedCash(),
            'currency' => $this->documentCurrency($session),
            'symbol' => $this->currencySymbol($session),
            'needs_confirmation' => $this->confirmationIsRequired(),
        ];
    }
}

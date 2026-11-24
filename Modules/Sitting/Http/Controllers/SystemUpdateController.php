<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckSystemUpdates;
use App\Services\Maintenance\SystemUpdateRegistry;
use App\Services\Maintenance\SystemUpdateService;
use App\Support\OperationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * شاشة «تحديث النظام» — تشغيل الترحيلات وزراعة البيانات الجديدة.
 *
 * الصلاحيات مفصولة عن `settings_edit_general_settings` عمداً، للسبب نفسه
 * الذي فُصلت لأجله صلاحيات النسخ الاحتياطي: من يعدّل شعار المنشأة لا يجوز
 * أن يشغّل ترحيلاً على قاعدة البيانات.
 *
 * التشغيل نفسه محصور في `SystemUpdateService`، والمسموح تشغيله محصور في
 * `SystemUpdateRegistry` — هذا المتحكّم لا يبني اسم أمرٍ ولا يمرّر خياراً.
 */
class SystemUpdateController extends Controller
{
    /**
     * ⚠ الشاشة مفتوحة مؤقّتاً لكل مستخدمٍ مسجَّل الدخول — بطلب صاحب النظام
     * (2026-08-26) بعد أن تعذّر الوصول إليها بالصلاحيات.
     *
     * الصلاحيات الثلاث ما زالت موجودة في القاعدة وفي الكتالوج وفي
     * `SystemMaintenancePermissionsSeeder` — لم يُحذف منها شيء. لإعادة
     * الإقفال: أزل التعليق عن الأسطر الثلاثة أدناه، ثم أعد `@permission`
     * في ثلاثة مواضع وُسمت بالوسم نفسه:
     *   - `App\Services\Settings\SettingsNavigation` (بند تحديث النظام)
     *   - `resources/views/layouts/sidebar.blade.php`
     *   - `Modules/Sitting/resources/views/system_update/index.blade.php`
     */
    public function __construct(protected SystemUpdateService $updates)
    {
        // [إقفال-تحديث-النظام] $this->middleware('permission:settings.system_update.view')->only('index');
        // [إقفال-تحديث-النظام] $this->middleware('permission:settings.system_update.migrate')->only('migrate');
        // [إقفال-تحديث-النظام] $this->middleware('permission:settings.system_update.seed')->only('seed');
        //
        // مهامُّ البيانات (`seed`، `runAll`) أُعيد إقفالُها في 2026-10-06 بفحصٍ
        // داخل الدالّتَين (`requireSeedPermission`) لا بوسيط: الشاشةُ والترحيلات
        // تبقيان مفتوحتَين كما طُلب، وما عداهما خلف صلاحيته.
    }

    public function index()
    {
        return view('sitting::system_update.index', [
            // الزائر (وهو لا يصل هنا إلا وترحيلٌ معلَّق) يرى الشاشة ويشغّل
            // الترحيلات وحدها — أزرارُ البيانات تُخفى عنه
            'guest' => auth()->guest(),
            'migrations' => $this->updates->migrationStatus(),
            // ما ستفعله الترحيلات المعلّقة بالبيانات — يُعرَض قبل الزرّ لا بعده
            'safety' => $this->updates->safetyReport(),
            'groups' => SystemUpdateRegistry::groups(),
            'tasks' => SystemUpdateRegistry::tasks(),
            'guards' => $this->updates->guardCounts(),
        ]);
    }

    /** تشغيل الترحيلات المعلّقة. */
    public function migrate(Request $request)
    {
        $before = $this->updates->migrationStatus();

        if ($before['pending'] === []) {
            return back()->with('success', 'لا ترحيلات معلّقة — قاعدة البيانات محدَّثة.');
        }

        $result = $this->updates->runMigrations(
            ...$this->safetyOptions($request)
        );

        // قبل أي فرع: وإلا بقي شريط «يوجد تحديث» ظاهراً بعد نجاح الترحيل حتى
        // تنتهي مدّة الكاش، فيظنّه المستخدم لم يُنفَّذ فيعيده.
        //
        // وموضعُه هنا لا بعد فرع «أُوقف» عمداً: الوقوف عند ترحيلٍ يحذف يقع
        // **بعد** أن يكون ما قبله قد نُفِّذ فعلاً، فالكاش قديمٌ في هذه الحال
        // أيضاً — وهو تحديداً ما يجعل الشاشة تعرض عدداً معلَّقاً لا يتناقص
        $this->updates->forgetPendingCache();
        $after = $this->updates->migrationStatus();
        $applied = count($before['pending']) - count($after['pending']);

        /*
            أُوقف الحارس التنفيذ عند ترحيلٍ يحذف بيانات.

            ليس فشلاً ولا يُسجَّل عطلاً — هذا الحارسُ يعمل كما صُمِّم. وما قبله
            نُفِّذ ونجح، فيُذكر عددُه: «لم يُنفَّذ شيء» على تحديثٍ نفّذ اثني عشر
            ترحيلاً رسالةٌ كاذبة تدفع إلى إعادة الضغط.
        */
        if (! empty($result['blocked'])) {
            return back()
                ->with('warning', $applied > 0
                    ? "نُفِّذ {$applied} ترحيلاً بأمان، ثم أُوقف التنفيذ عند ترحيلٍ يحذف بيانات — لم يُشغَّل، وراجع المخرَج."
                    : 'لم يُنفَّذ شيء — أوّلُ ترحيلٍ معلَّق يحذف بيانات، وهذه الشاشة لا تحذف. راجع المخرَج.')
                ->with('update_output', $result['output']);
        }

        if (! $result['ok']) {
            OperationLog::failure(
                new \RuntimeException($result['output']),
                'SystemUpdate',
                'migrate'
            );

            return back()
                ->with('error', "فشل الترحيل. نُفِّذ {$applied} من ".count($before['pending']).' — راجع المخرَج.')
                ->with('update_output', $result['output']);
        }

        OperationLog::record('updated', 'SystemUpdate', null, "تشغيل الترحيلات: {$applied} ترحيلاً.");

        // الترحيلات وحدها هي ما يحجز المستخدم على هذه الشاشة، فنجاحُها هو
        // اللحظة الطبيعية لإعادته إلى ما كان يعمل عليه
        return $this->afterSuccess(
            "تمّ تنفيذ {$applied} ترحيلاً.",
            $result['output']
        );
    }

    /** تشغيل مهمّة تحديث بيانات من السجلّ. */
    public function seed(Request $request)
    {
        if ($response = $this->requireSeedPermission()) {
            return $response;
        }

        $data = $request->validate([
            'task' => ['required', 'string', 'max:64'],
        ]);

        $task = SystemUpdateRegistry::find($data['task']);

        // مفتاحٌ خارج السجلّ لا يصل من الشاشة — وصولُه يعني طلباً معدَّلاً
        if ($task === null) {
            return back()->with('error', 'مهمّة غير معروفة.');
        }

        $result = $this->updates->runTask($task['key']);

        if (! $result['ok']) {
            OperationLog::failure(
                new \RuntimeException($result['output']),
                'SystemUpdate',
                'seed:'.$task['key']
            );

            return back()
                ->with('error', 'فشل تشغيل «'.$task['label'].'».')
                ->with('update_output', $result['output']);
        }

        OperationLog::record('updated', 'SystemUpdate', null, 'تحديث بيانات: '.$task['label'].'.');

        return back()
            ->with('success', 'تمّ تشغيل «'.$task['label'].'».')
            ->with('update_output', $result['output']);
    }

    /**
     * التحديث الشامل — الترحيلات ثم كل مهمّة بياناتٍ آمنة التكرار.
     *
     * الزرّ الذي يُغني عن أربعين نقرة بعد كل تحديث. ما يُستثنى منه ولماذا
     * مشروحٌ في `SystemUpdateService::runAll()`، والشاشة تعرض ما استُثني
     * صراحةً كي لا يظنّ المستخدم أنه شُغِّل.
     */
    public function runAll(Request $request)
    {
        if ($response = $this->requireSeedPermission()) {
            return $response;
        }

        $result = $this->updates->runAll(...$this->safetyOptions($request));

        // أُوقف عند ترحيلٍ يحذف — ولم تُزرَع البيانات لأن البنية لم تكتمل
        if (! empty($result['blocked'])) {
            $this->updates->forgetPendingCache();

            return back()
                ->with('warning', 'أُوقف التحديث عند ترحيلٍ يحذف بيانات — لم يُشغَّل، '
                    .'ولم تُزرَع بيانات التحديث لأن بنية القاعدة لم تكتمل. راجع المخرَج.')
                ->with('update_output', $result['steps'][0]['output'] ?? '');
        }

        $done = collect($result['steps'])->where('state', 'ok')->count();
        $failed = collect($result['steps'])->where('state', 'failed')->count();

        // المخرَج يُعرَض كاملاً خطوةً خطوة: «فشل التحديث» بلا بيان أيِّ
        // خطوةٍ سقطت لا يُصلَح به شيء
        $output = collect($result['steps'])
            ->map(fn ($step) => match ($step['state']) {
                'ok' => '✔ '.$step['label'],
                'failed' => '✖ '.$step['label'],
                default => '— '.$step['label'].' (متروكة)',
            }."\n".$this->indent($step['output']))
            ->implode("\n\n");

        if ($failed > 0) {
            OperationLog::failure(
                new \RuntimeException($output),
                'SystemUpdate',
                'run-all'
            );

            return back()
                ->with('error', "التحديث الشامل: نجحت {$done} خطوة وسقطت {$failed} — راجع المخرَج.")
                ->with('update_output', $output);
        }

        OperationLog::record('updated', 'SystemUpdate', null, "تحديث شامل: {$done} خطوة.");

        return $this->afterSuccess(
            "تمّ التحديث الشامل — {$done} خطوة نُفِّذت.",
            $output
        );
    }

    /**
     * خيارُ الأمان الوحيد القادم من الشاشة: أخذُ نسخةٍ قبل التشغيل.
     *
     * **ولا خيارَ للحذف.** كان هنا `confirm_destructive` يسمح بتنفيذ ترحيلٍ
     * يحذف عند تعليم خانة، فأُزيل بقرار صاحب النظام: هذه الشاشة لا تحذف شيئاً
     * بحال، والحارسُ يقف عند أوّل ترحيلٍ يحذف مهما أُرسل في الطلب. فلا تُقرأ
     * الخانة هنا، ولا وجودَ لها في القالب — ولو أُرسلت يدوياً لم تُقرأ.
     *
     * **والنسخة** `boolean(..., true)`: غيابُها يعني «خُذها»، وهو معاكسُ ما
     * تفعله `boolean()` عادةً — عمداً. فالخانة معلَّمةٌ في الشاشة، وطلبٌ يصل بلا
     * هذا المفتاح — متصفّحٌ قديم، أو نموذجٌ أُرسل من مكانٍ آخر — يجب أن يُنتج
     * نسخةً زائدة لا تحديثاً بلا طريق عودة. وإزالتُها فعلٌ صريح يقوم به من أخذ
     * نسخته بنفسه.
     *
     * والزائر لا يزيلها بحال: `AllowPendingUpdateAccess` يفتح هذه الشاشة لمن لم
     * يسجّل دخوله ما دام ترحيلٌ معلَّقاً، ومن لا نعرف من هو لا يُمنح حقَّ إسقاط
     * النسخة الاحتياطية عن قاعدة بياناتٍ ليست له.
     *
     * @return array{0: bool, 1: int|null}
     */
    private function safetyOptions(Request $request): array
    {
        return [
            auth()->guest() ? true : $request->boolean('backup_first', true),
            auth()->id(),
        ];
    }

    /**
     * وجهةُ ما بعد النجاح: الصفحة التي قُطع المستخدم عندها إن وُجدت.
     *
     * `CheckSystemUpdates` يحفظ عنوانها حين يقذفه إلى هنا. فمن كان يحرّر
     * فاتورةً حين نزل التحديث يعود إليها بضغطةٍ واحدة، لا يبحث عن طريقه
     * بيده من شاشةٍ لا تخصّه.
     *
     * ولا يُعاد إلّا إذا **لم يبقَ ترحيلٌ معلَّق**: العودة قبل اكتمال التحديث
     * تصطدم بالحارس نفسه فتُعيده إلى هنا — حلقةٌ يظنّها المستخدم عطلاً. وحين
     * يبقى شيءٌ معلَّق تبقى الوجهةُ محفوظةً في الجلسة للمحاولة التالية.
     */
    private function afterSuccess(string $message, string $output): RedirectResponse
    {
        $stillPending = $this->updates->pendingMigrationCount() > 0;

        if ($stillPending) {
            return back()
                ->with('success', $message)
                ->with('update_output', $output);
        }

        $intended = session()->pull(CheckSystemUpdates::INTENDED_KEY);

        if (! $intended) {
            return back()
                ->with('success', $message)
                ->with('update_output', $output);
        }

        return redirect()->to($intended)
            ->with('success', $message.' وعُدتَ إلى الصفحة التي كنت فيها.');
    }

    /**
     * «متابعة الآن» — تأجيل التحويل الإجباري إلى هذه الشاشة.
     *
     * مخرجُ من لا يملك قرار الترحيل: موظّفٌ يحتاج أن يُصدر فاتورة الآن لا
     * يجوز أن يُحتجَز في شاشةٍ لا يستطيع تجاوزها حتى يحضر المسؤول. والمهلة
     * قصيرة والشريط يبقى ظاهراً — تأجيلٌ لا إلغاء.
     */
    public function snooze(Request $request)
    {
        // والزائر لا يؤجّل: التأجيل مخرجُ من له عملٌ يتابعه، ولا عمل لمن لم
        // يدخل بعد — ولولا هذا لصار زرّاً يفتح شاشة الدخول رغم التحديث
        if ($response = $this->requireLogin()) {
            return $response;
        }

        $request->session()->put(
            CheckSystemUpdates::SNOOZE_KEY,
            time() + (CheckSystemUpdates::SNOOZE_MINUTES * 60)
        );

        return redirect()->route('dashboard')->with(
            'warning',
            'أُجِّل التحديث '.CheckSystemUpdates::SNOOZE_MINUTES.' دقيقة. '
            .'الترحيلات ما زالت معلّقة، وقد تظهر أخطاءٌ في الشاشات الجديدة حتى تُنفَّذ.'
        );
    }

    /**
     * مهامّ البيانات لمن سجّل دخوله وحده.
     *
     * الشاشة مفتوحةٌ للزائر ما دام ترحيلٌ معلَّقاً (`AllowPendingUpdateAccess`)،
     * لكنّ المفتوح له هو **الترحيلات** لا زراعةُ البيانات: الترحيل بنيويٌّ إلى
     * الأمام، وقد وقّعه مطوّر التحديث؛ أمّا اختيارُ تشغيل سيدرٍ فقرارُ مسؤولٍ
     * يعرف نظامه. والفحص هنا لا في المسار: المسار نفسه يجب أن يبقى مفتوحاً
     * ليعرض الشاشة.
     */
    private function requireLogin(): ?RedirectResponse
    {
        if (auth()->check()) {
            return null;
        }

        return back()->with(
            'error',
            'تشغيل مهامّ البيانات يتطلّب تسجيل الدخول. شغّل «الترحيلات» أوّلاً، '
            .'ثم ادخل بحسابك لإكمال بقيّة التحديث.'
        );
    }

    /**
     * مهامُّ البيانات («تشغيل» لكل مهمّة، و«التحديث الشامل») خلف صلاحيتها.
     *
     * الشاشةُ نفسُها والترحيلاتُ تبقيان مفتوحتَين عمداً: ترحيلٌ معلَّق يحوّل
     * كلَّ مستخدمٍ إليها قبل أيّ صفحة، فإقفالُها يقفل النظام. أمّا مهامُّ
     * البيانات فليست طريقاً إجبارياً لأحد — وفيها ما يعيد كتابةَ القوالب
     * ويمنح الصلاحيات — وكانت مفتوحةً لكل من سجّل دخوله، مندوباً كان أو
     * مالكاً. صارت لمن يحمل `settings.system_update.seed` وحدَه.
     */
    private function requireSeedPermission(): ?RedirectResponse
    {
        if ($response = $this->requireLogin()) {
            return $response;
        }

        if (hasPermission('settings.system_update.seed')) {
            return null;
        }

        return back()->with(
            'error',
            'تشغيل مهامّ البيانات يحتاج صلاحية «زراعة بيانات التحديث». '
            .'تشغيلُ الترحيلات متاحٌ لك، وبقيّةُ التحديث يكمله من يملك الصلاحية.'
        );
    }

    /** إزاحة مخرَج خطوةٍ سطراً سطراً — ليُقرأ الشامل كقائمةٍ لا ككتلة. */
    private function indent(string $output): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];

        return implode("\n", array_map(fn ($line) => '    '.$line, $lines));
    }
}

<?php

namespace Modules\Client\Http\Controllers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Support\ClientPortalPermissions;
use App\Support\OperationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * «معاينة كعميل» — يدخل الموظّفُ بحساب العميل ليرى بوابته بعينه.
 *
 * ── لماذا انتحالٌ لا محاكاة؟ ──
 *
 * السؤال «ماذا يرى العميل؟» لا تجيبه صفحةٌ تعرض شاشات البوابة للموظّف
 * بصلاحياته هو: الشاشات مُصفّاةٌ على `auth()->user()->client_id`،
 * و`ClientAccessMiddleware` يقرّر ما يُسمح به من صلاحيات البوابة.
 * فمعاينةٌ لا تدخل بحساب العميل تعرض شيئاً غير الذي يراه — وهي أسوأ من
 * لا معاينة، لأنها تُطمئن على وضعٍ غير الواقع.
 *
 * ── الرجوع مضمون ──
 *
 * مُعرّف الأصل يُحفَظ في الجلسة (`self::SESSION_KEY`)، ومسار «العودة
 * لحسابي» مستثنىً في `ClientAccessMiddleware` — فالحصر لا يبتلع الطريقَ
 * الوحيدَ للخروج منه. وشريطُ التنبيه ظاهرٌ في كل صفحات البوابة
 * (`resources/views/client.blade.php`) فلا يُنسى أحدٌ داخل الجلسة.
 *
 * ── حدود مقصودة ──
 *
 *   • لا تداخل: من هو في معاينةٍ لا يبدأ معاينةً أخرى؛ يعود أوّلاً.
 *   • حصرٌ مفروض: تُعامَل جلسةُ المعاينة معاملةَ حساب عميلٍ مهما كان دور
 *     ذلك الحساب — راجع `ClientAccessMiddleware`. فلو كان حساب العميل
 *     مُسنَداً بالخطأ إلى دورٍ إداري، لم تصر المعاينةُ باباً خلفياً.
 *   • كلُّ بدءٍ وعودةٍ في سجلّ النشاط باسم الموظّف الأصلي لا العميل.
 */
class ClientPortalPreviewController extends Controller
{
    /** مُعرّف المستخدم الأصلي أثناء المعاينة. */
    public const SESSION_KEY = 'client_portal_preview.impersonator_id';

    /** اسم العميل المعروض في الشريط — لتفادي استعلامٍ في كل صفحة. */
    public const SESSION_LABEL = 'client_portal_preview.client_name';

    private const LOG_MODULE = 'ClientPortalPreview';

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:clients_portal_preview', ['only' => ['start']]);
        // `stop` بلا صلاحية عمداً: صاحبُ الجلسة أثناء المعاينة هو **العميل**
        // لا الموظّف، وصلاحياتُه صلاحياتُ عميل. فاشتراطُ صلاحيةٍ إدارية على
        // زرّ العودة يحبس الموظّفَ في الجلسة التي فتحها بنفسه. الحمايةُ في
        // الجلسة: لا عودةَ إلّا لمن فيها مُعرّفُ أصلٍ محفوظ.
    }

    /** بدء المعاينة: الدخول بحساب العميل والانتقال إلى بوابته. */
    public function start(Request $request, $clientId): RedirectResponse
    {
        if ($request->session()->has(self::SESSION_KEY)) {
            return back()->with('error', 'أنت في معاينةٍ بالفعل. عُد إلى حسابك أوّلاً.');
        }

        $client = Client::findOrFail($clientId);
        $portalUser = User::where('client_id', $client->id)->first();

        if (! $portalUser) {
            return back()->with('error', 'لا يوجد حساب دخول مرتبط بهذا العميل — أنشئ له مستخدماً أوّلاً.');
        }

        if (! ClientPortalPermissions::portalEnabled()) {
            return back()->with('error', 'بوابة العملاء مغلقة من «صلاحيات بوابة العميل».');
        }

        $landing = ClientPortalPermissions::landingRoute();

        if ($landing === null) {
            return back()->with('error', 'لا توجد صفحةٌ مفعّلة في البوابة — فعّل صلاحيةً واحدة على الأقل.');
        }

        // بلا دورٍ مختار لا يحصر الوسيطُ أحداً، فتُظهر المعاينة النظام كلَّه
        // بدل بوابة العميل — وهذا عكسُ المقصود. تُمنع لا لتُشدَّد، بل لئلّا
        // تكذب على من يسأل «ماذا يرى عميلي؟»
        if (ClientSettingsHelper::clientPortalRoleId() === null) {
            return back()->with('error', 'اختر «دور العميل» من «صلاحيات بوابة العميل» قبل المعاينة.');
        }

        OperationLog::record(
            'portal_preview_started',
            self::LOG_MODULE,
            $client,
            'بدء معاينة بوابة العميل «'.$client->trade_name.'» بحساب «'.$portalUser->name.'»'
        );

        $originalId = Auth::id();

        Auth::login($portalUser);

        // بعد `login()` لا قبله: `Auth::login()` تُعيد توليد مُعرّف الجلسة
        // فيضيع ما كُتب قبلها في بعض التهيئات
        $request->session()->put(self::SESSION_KEY, $originalId);
        $request->session()->put(self::SESSION_LABEL, $client->trade_name);

        return redirect()->route($landing)
            ->with('success', 'أنت الآن تعاين البوابة بحساب «'.$client->trade_name.'».');
    }

    /** إنهاء المعاينة والعودة إلى الحساب الأصلي. */
    public function stop(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull(self::SESSION_KEY);
        $clientName = $request->session()->pull(self::SESSION_LABEL);

        if (! $originalId) {
            return redirect()->route('dashboard');
        }

        $original = User::find($originalId);

        // الحساب الأصلي حُذف أو عُطِّل أثناء المعاينة — إنهاء الجلسة أسلمُ
        // من إبقاء الموظّف داخل حساب عميلٍ بلا طريق عودة
        if (! $original) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'تعذّرت العودة إلى حسابك الأصلي. سجّل دخولك من جديد.');
        }

        Auth::login($original);

        OperationLog::record(
            'portal_preview_stopped',
            self::LOG_MODULE,
            null,
            'إنهاء معاينة بوابة العميل'.($clientName ? ' «'.$clientName.'»' : '')
        );

        return redirect()->route('dashboard')
            ->with('success', 'عُدتَ إلى حسابك.');
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * «إذا ما انتهى من الزيارة ما أبغاه ينتقل إلى عميلٍ أو أيّ صفحة إلا إذا
 * سوّى إنهاء الزيارة بعد ما يخلّص إجراءاتها».
 *
 * مندوبٌ **بدأ** زيارةً اليوم ولم يُنهها ولم يُلغها: أيُّ انتقالٍ إلى صفحةٍ
 * أخرى يُعيده إلى مساحة عمل تلك الزيارة برسالة. المخرجان الوحيدان
 * «إنهاء الزيارة» و«إلغاء الزيارة» — كلاهما من الشاشة نفسها.
 *
 * ── لماذا بعد «بدء الزيارة» لا عند فتح الشاشة؟ ──
 * الإلغاء يشترط أن يكون عند موقع العميل. مندوبٌ فتح زيارةً بالخطأ وهو بعيد
 * كان سيُحبس بلا مخرج. قبل البدء الزيارةُ لم تبدأ، فيخرج منها بحرّية.
 *
 * ── لماذا اليوم وحده؟ ──
 * زيارةٌ بدأت أمس وبقيت مفتوحة لا تُنهى إلا من موقع العميل. قفلُ اليوم بها
 * يحبس المندوب عن يوم عمله كلّه بسبب زيارةٍ نسيها.
 *
 * ── ما يمرّ دون فحص ──
 *  - كلُّ ما ليس `GET`: الحفظ من شاشات المستندات، والإنهاء والإلغاء، والخروج.
 *    هذه طلباتٌ تصدر من صفحاتٍ مسموحة أصلاً.
 *  - طلبات AJAX/JSON: شاشة الفاتورة وحدها تنادي عشرات المسارات (بحث
 *    المنتجات، الأسعار، رصيد العميل)، والإشعارات تُستطلع في الرأس.
 *    القفل على **الانتقال** بين الصفحات لا على نبض الصفحة الحالية.
 *
 * ── ما يُسمح به من الصفحات (بالاسم لا بالمسار) ──
 * مسارات النظام تحت بادئة اللغة (`/ar/...`)، فالمطابقة بالعنوان تنكسر.
 * الأسماء لا تتغيّر بتغيّر اللغة.
 *  - مساحة عمل هذه الزيارة نفسها وكلُّ مساراتها.
 *  - شاشات إنشاء المستندات **متى فُتحت من الزيارة** (ختم
 *    `ReturnToVisitWorkspace` في الجلسة) وطباعتُها.
 *  - مخارج الوسطاء الآخرين: شاشة التحديث، وتبرير الزيارات، والخروج. كلٌّ
 *    منها يحوّل إلى شاشته، ولو حجبها هذا دار الطلب بينهما بلا نهاية.
 */
class LockToOpenVisit
{
    /** شاشات إنشاء المستندات التي تفتحها `VisitWorkspaceController::openDocument()`. */
    private const DOCUMENT_CREATE_ROUTES = [
        'invoices.create',
        'incomes.create',
        'expenses.create',
        'CreditNotes.create',
        'ReturnIInvoices.create',
        'product-exchanges.create',
    ];

    /** طباعة «مستندات هذه الزيارة» — يُسلَّم المستند للعميل وهو عنده. */
    private const DOCUMENT_PRINT_ROUTES = [
        'invoices.print',
        'invoices.print_tax',
        'incomes.print',
        'expenses.print',
        'CreditNotes.print',
        'ReturnIInvoices.print',
        'product-exchanges.print',
    ];

    /** مخارجُ وسطاءٍ آخرين — حجبُها يصنع حلقة تحويلٍ لا نهاية لها. */
    private const ESCAPE_ROUTES = [
        'logout',
        'login',
        'SystemUpdate.*',
        'incomplete.visits.justification',
        'incomplete.visits.submit',
        'employee.visit-justifications.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->expectsJson()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $visit = Visit::query()
            ->with('client:id,trade_name')
            ->where('employee_id', $user->id)
            ->whereDate('visit_date', now()->toDateString())
            ->whereNotNull('started_at')
            ->whereNull('departure_time')
            ->where(fn ($q) => $q->whereNull('stage')->orWhere('stage', '!=', Visit::STAGE_CANCELLED))
            ->latest('id')
            ->first(['id', 'client_id', 'stage']);

        if (! $visit || $this->isAllowed($request, $visit)) {
            return $next($request);
        }

        $clientName = $visit->client->trade_name ?? 'العميل';

        return redirect()
            ->route('clients.visitWorkspace', ['client' => $visit->client_id, 'visit' => $visit->id])
            ->with('error', 'لديك زيارة مفتوحة لـ«'.$clientName.'» — أكمل إجراءاتها ثم «إنهاء الزيارة» (أو ألغِها) قبل الانتقال إلى صفحة أخرى.');
    }

    private function isAllowed(Request $request, Visit $visit): bool
    {
        $route = $request->route();
        $name = (string) $route?->getName();

        if ($name === '') {
            return false;
        }

        foreach (self::ESCAPE_ROUTES as $pattern) {
            if ($name === $pattern || fnmatch($pattern, $name)) {
                return true;
            }
        }

        // مساحة عمل هذه الزيارة بعينها — زيارةٌ أخرى أو عميلٌ آخر يُعيده إليها
        if ($name === 'clients.visitWorkspace') {
            return (int) $route->parameter('visit') === (int) $visit->id;
        }

        if ($name === 'clients.openVisitWorkspace') {
            return (int) $route->parameter('client') === (int) $visit->client_id;
        }

        if (str_starts_with($name, 'clients.visits.')) {
            return (int) $route->parameter('visit') === (int) $visit->id;
        }

        // شاشة المستند مفتوحةٌ من الزيارة: `openDocument()` يختم رابط العودة
        if (in_array($name, self::DOCUMENT_CREATE_ROUTES, true)) {
            return $request->hasSession()
                && $request->session()->has(ReturnToVisitWorkspace::SESSION_KEY);
        }

        return in_array($name, self::DOCUMENT_PRINT_ROUTES, true);
    }
}

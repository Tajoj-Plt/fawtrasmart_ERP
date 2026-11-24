<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * «وعند عمل أيٍّ منهم وحفظ يرجع يكمل الزيارة».
 *
 * المندوب داخل زيارةٍ مفتوحة يفتح فاتورةً أو سند قبضٍ أو إشعاراً دائناً أو
 * تبديل بضاعة من مساحة عمل الزيارة، فيرميه المتحكّم بعد الحفظ إلى فهرس
 * ذاك المستند — وهو خارج الزيارة تماماً، فيبقى عليه أن يجد طريق العودة
 * إليها بنفسه ليُنهيها.
 *
 * البديل — تعديلُ أربعة متحكّمات في وحدتين (Sales وAccount) لتعرف بوجود
 * زيارةٍ مفتوحة — يُقحم مفهوم الزيارة في شاشاتٍ لا شأن لها به. فهذا
 * الوسيط يفعلها من الخارج بدلها:
 *
 *   • `VisitWorkspaceController::openDocument()` يختم في الجلسة رابطَ
 *     العودة قبل أن يحوّل المندوب إلى شاشة إنشاء المستند.
 *   • هنا: إن كان الطلب **حفظاً ناجحاً** لأحد مسارات المستندات الأربعة
 *     المسمّاة أدناه (بالاسم لا بالمسار — المسارات مُلحقةٌ ببادئة اللغة
 *     وتتبدّل)، يُستبدل هدفُ إعادة التوجيه برابط الزيارة، ويُمحى الختم.
 *
 * لا يمسّ الوسيط شيئاً في غياب الختم — وهو الغالب في كل طلب.
 */
class ReturnToVisitWorkspace
{
    public const SESSION_KEY = 'visit_workspace_return_url';

    /** مسارات الحفظ التي تُرجع المندوب إلى زيارته — بالاسم لا بالمسار. */
    private const DOCUMENT_STORE_ROUTES = [
        'invoices.store',
        'incomes.store',
        'expenses.store',
        'CreditNotes.store',
        'ReturnIInvoices.store',
        'product-exchanges.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasSession() || ! $request->session()->has(self::SESSION_KEY)) {
            return $response;
        }

        if (! in_array($request->route()?->getName(), self::DOCUMENT_STORE_ROUTES, true)) {
            return $response;
        }

        /*
            حفظٌ بـAJAX: التبديل وسند القبض يُرسَلان بـ`fetch` ويردّ الخادم
            JSON فيه `redirect` تتبعه الصفحة (`window.location = data.redirect`).
            كان الوسيط يكتفي بإعادة التوجيه العادية فيتخطّاهما، فيُرمى المندوب
            إلى صفحة المستند بدل زيارته. هنا يُستبدل `redirect` نفسه — ونجاحٌ
            صريح (`success: true`) شرطٌ كما في الفرع العادي.
        */
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (! is_array($data) || ($data['success'] ?? false) !== true || ! $response->isSuccessful()) {
                return $response;
            }

            $data['redirect'] = $request->session()->pull(self::SESSION_KEY);

            return $response->setData($data);
        }

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        /*
            حفظٌ فاشل يعود بـ`redirect()->back()` مع الأخطاء — إرجاعُه إلى
            الزيارة يبتلع رسالة الخطأ ويضيّع ما كتبه المندوب. لا نتدخّل إلا
            حين يُعلن المتحكّم النجاح صراحةً.
        */
        if (! $request->session()->has('success')) {
            return $response;
        }

        $target = $request->session()->pull(self::SESSION_KEY);

        return $response->setTargetUrl($target);
    }
}

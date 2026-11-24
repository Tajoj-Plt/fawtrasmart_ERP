<?php

namespace Modules\Integration\Http\Controllers\Api\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

/**
 * جسرُ واجهة الجوال إلى كنترولرات الويب.
 *
 * **مبدأ التنفيذ: تفويضٌ، لا إعادة كتابة.** كلّ عملية كتابة في الجوال
 * تنادي دالّة الويب المقابلة بحذافيرها — نفس التحقّق، نفس الترقيم
 * المتسلسل، نفس الحسابات المحاسبية من الإعدادات، نفس الضرائب، نفس حارس
 * الفترة المحاسبية، نفس النطاق. هذا الـTrait لا يعرف كيف يُنشأ مستند؛
 * يعرف فقط كيف يُترجم جوابَ الويب إلى JSON موحَّد.
 */
trait DelegatesWebActions
{
    /**
     * يُطبِّع جواب دالّة كتابةٍ من الويب إلى JSON.
     *
     * بعض دوال الويب تفحص `wantsJson()` فتردّ JSON مباشرةً (يمرّ كما هو)،
     * وبعضها يردّ توجيهاً دائماً — نجاحاً (`->with('success', …)`) أو فشلاً
     * ابتلعته الدالّة (`back()->with('error', …)` أو `->withErrors([…])`).
     * فتُقرأ رايةُ النجاح/الفشل التي تكتبها في الجلسة، بلا لمس شيفرتها.
     * استثناءٌ يُرمى (تحقّقٌ فشل، 403 نطاق) يمرّ كما هو.
     */
    protected function delegateWebAction(callable $call, string $successMessage): JsonResponse
    {
        session()->forget(['error', 'success', 'errors']);

        $result = $call();

        if ($result instanceof JsonResponse) {
            return $result;
        }

        /** @var ViewErrorBag|null $errorBag */
        $errorBag = session('errors');
        if ($errorBag instanceof ViewErrorBag && $errorBag->any()) {
            return response()->json([
                'success' => false,
                'message' => $errorBag->first(),
                'errors' => $errorBag->getBags()['default']->messages(),
            ], 422);
        }

        if ($error = session('error')) {
            // بعض دوال الويب تضع الاستثناء نفسه في الجلسة لا نصَّه
            $message = $error instanceof \Throwable ? $error->getMessage() : (string) $error;

            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return response()->json([
            'success' => true,
            'message' => session('success') ?? $successMessage,
        ]);
    }

    /**
     * دوال القوائم في الويب تردّ جدول HTML بدل البيانات متى رأت الطلب
     * «AJAX» — بمعامل `is_ajax` أو بترويسة `X-Requested-With` التي ترسلها
     * بعض مكتبات HTTP في الجوال تلقائياً. فيُنزع الاثنان.
     */
    protected function forceDataListResponse(Request $request): void
    {
        $request->query->remove('is_ajax');
        $request->request->remove('is_ajax');
        $request->headers->remove('X-Requested-With');
    }

    /** بيانات شاشة ويب — ما تمرّره دالّة الويب إلى قالبها، مصدرٌ واحد لا تجميعٌ موازٍ. */
    protected function viewData($view): array
    {
        return $view instanceof View ? $view->getData() : [];
    }

    /**
     * طلبٌ مُصادَقٌ عليه من نوع FormRequest الذي تنتظره دالّة الويب —
     * يُبنى من الطلب الحالي ويُتحقَّق منه بقواعده نفسها (فشلُه 422 JSON).
     *
     * @template T of \Illuminate\Foundation\Http\FormRequest
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function webFormRequest(string $class)
    {
        return app($class);
    }
}

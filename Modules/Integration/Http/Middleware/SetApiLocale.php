<?php

namespace Modules\Integration\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لغة واجهة الجوال — العربية ما لم يطلب التطبيق الإنجليزية صراحةً
 * بترويسة `Accept-Language: en`.
 *
 * الويب يأخذ لغته من بادئة العنوان (`/ar/...`)، وواجهة الجوال بلا بادئة،
 * و`config/app.php` يثبّت `locale` على `en` — فكانت كل رسالةٍ مترجمة
 * (قفل الفترة المحاسبية، رسائل التحقّق الافتراضية…) تصل بالإنجليزية.
 *
 * ومعها أسماء الحقول العربية الخاصة بالواجهة (`resources/lang/ar/validation_attributes.php`
 * في الوحدة): تُضاف لهذا الطلب وحده كي لا تغيّر معنى حقلٍ بالاسم نفسه في
 * شاشات الموقع.
 */
class SetApiLocale
{
    private const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED) ?: 'ar';
        app()->setLocale($locale);

        if ($locale === 'ar') {
            $this->addAttributeNames();
        }

        return $next($request);
    }

    private function addAttributeNames(): void
    {
        $translator = app('translator');

        // يُحمَّل ملف التحقّق العام أوّلاً: الإضافة إلى مجموعةٍ لم تُحمَّل
        // تُعلِّمها «محمَّلة» فتضيع بقية رسائلها
        $translator->get('validation.required', [], 'ar');

        $names = (array) require module_path('Integration', 'resources/lang/ar/validation_attributes.php');
        $lines = [];

        foreach ($names as $field => $label) {
            $lines['validation.attributes.'.$field] = $label;
        }

        $translator->addLines($lines, 'ar');
    }
}

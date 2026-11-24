<?php

namespace Modules\Client\Http\Controllers\Concerns;

use App\Support\ClientPortalPermissions;

/**
 * حارسُ شاشات بوابة العميل — طبقةٌ ثانيةٌ فوق `ClientAccessMiddleware`.
 *
 * لماذا طبقتان؟ الوسيط يحصر **العميل** فيما هو مسموحٌ له؛ وهذا يمنع
 * **غير العميل** من شاشةٍ لا تعنيه. فمديرٌ يفتح شاشةَ بوابةٍ حسابُه بلا
 * `client_id`، فتصير كلُّ استعلامات الشاشة `where('client_id', null)` —
 * صفحةٌ فارغةٌ بلا تفسير.
 *
 * كان هذا الحارسُ دالّةً خاصّةً في `ClientSettingController` وحده؛ استُخرج
 * هنا حين صارت للبوابة شاشاتٌ في متحكّماتٍ أخرى (الحجز وإشعارات السداد)،
 * فلا تُنسخ قاعدةُ الحصر نسخةً ثانيةً تفترق عن الأولى.
 */
trait GuardsClientPortal
{
    /**
     * @return array{0: ?\Illuminate\Http\RedirectResponse, 1: ?int} [التحويل إن وجب، مُعرّف العميل]
     */
    protected function guardPortal(string $permissionKey): array
    {
        $clientId = auth()->user()?->client_id;

        if (! $clientId) {
            return [
                redirect()->route('dashboard')
                    ->with('error', 'هذه الشاشة لحسابات العملاء وحدها — حسابك غير مرتبط بسجلّ عميل.'),
                null,
            ];
        }

        if (! ClientPortalPermissions::enabled($permissionKey)) {
            return [
                redirect()->route('login')
                    ->with('error', 'هذه الصفحة غير متاحة في البوابة حالياً. راجع إدارة النظام.'),
                null,
            ];
        }

        return [null, (int) $clientId];
    }
}

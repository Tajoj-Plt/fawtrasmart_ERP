<?php

namespace Modules\OnlineStore\Http\Controllers\Api\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * معرّفُ جلسة الزائر لسلته ومشاهداته.
 *
 * يأتي من ترويسة `X-Session-ID` التي تُرسلها الواجهة. كان يُستعمل كما وصل:
 * ترويسةٌ طويلة أو بمحارف غريبة تتجاوز عمود `session_id` فتسقط السلة بـ500؛
 * وغيابُها يقع على `session()->getId()` وواجهة الـAPI بلا جلسة أصلاً.
 * هنا: محارفُ آمنة وطولٌ محدود، وإلا معرّفٌ عشوائيّ للطلب.
 */
trait ResolvesStoreSession
{
    protected function storeSessionId(Request $request): string
    {
        $header = $request->header('X-Session-ID');

        if (is_string($header)) {
            $clean = substr(preg_replace('/[^A-Za-z0-9_\-.:]/', '', $header) ?? '', 0, 100);

            if (strlen($clean) >= 8) {
                return $clean;
            }
        }

        if ($request->hasSession()) {
            try {
                return substr($request->session()->getId(), 0, 100);
            } catch (\Throwable $e) {
                // لا جلسة صالحة — تُستعمل البصمة أدناه
            }
        }

        // بلا معرّف من الواجهة: معرّفٌ عشوائيّ لهذا الطلب وحده — بصمةٌ ثابتة من العنوان/المتصفّح كانت ستجمع كلَّ زوّار
        // خادمٍ وسيط (واجهة المتجر) في سلةٍ واحدة. الواجهة ترسل X-Session-ID فلا يصل أحدٌ إلى هنا عملياً
        return (string) Str::uuid();
    }
}

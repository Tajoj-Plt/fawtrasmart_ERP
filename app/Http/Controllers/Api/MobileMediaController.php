<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * استقبال صور تطبيق الجوال — خادم التطبيق (Node) مستضافٌ منفصلاً عن النظام، فلا
 * يكتب على قرصه. يضغط الصورة هناك ثم يرسلها إلى هنا لتُحفظ تحت `public/` في
 * **مجلّدات الويب نفسها** (صورة العميل، صور الملاحظات، المنافسون، التوقيعات،
 * مسؤولو العميل، الصورة الشخصية، مرفق الإذن المخزني) فتظهر في شاشات الموقع بلا فرق.
 *
 * ── الحماية ──
 * لا مستخدم هنا: الطلب من خادمٍ إلى خادم بمفتاحٍ سرّيٍّ مشترك
 * (`MOBILE_MEDIA_TOKEN` في الطرفين) يُقارَن بـ`hash_equals`. غيابُ المفتاح في
 * `.env` يُطفئ النقطة كلّها (503) — لا بابَ مفتوحاً افتراضاً.
 * والمجلّد من قائمةٍ مغلقة، واسم الملف حروفٌ آمنة بامتدادِ صورة، والمحتوى صورةٌ
 * حقيقية، ولا يُكتب فوق ملفٍّ موجود. وPDF مقبولٌ في مجلّد المستندات وحده.
 */
class MobileMediaController extends Controller
{
    /** المجلّدات التي يكتب فيها التطبيق — لا غيرها */
    public const FOLDERS = [
        'assets/uploads',
        'assets/uploads/notes',
        'assets/uploads/visit_competitors',
        'assets/uploads/visit_signatures',
        'uploads/client_notes',
        'uploads/avatars',
        'assets/uploads/warehouse',
        // صورة تبديل البضاعة — `ProductExchangeController::processAndSaveImage()`
        'uploads/product_exchanges',
    ];

    /**
     * مجلّدات المستندات — مرفق الإذن المخزني صورةٌ أو PDF (5 ميغابايت)
     * كقاعدة `WarehousePermitsRequest::attachments` في الويب.
     */
    public const DOCUMENT_FOLDERS = ['assets/uploads/warehouse'];

    private const FILENAME = '/^[A-Za-z0-9][A-Za-z0-9_\-]{0,150}\.(jpg|jpeg|png|gif|webp)$/';

    private const DOCUMENT_FILENAME = '/^[A-Za-z0-9][A-Za-z0-9_\-]{0,150}\.(jpg|jpeg|png|pdf)$/';

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $document = in_array($request->input('folder'), self::DOCUMENT_FOLDERS, true);

        $data = $request->validate([
            'folder' => ['required', 'string', 'in:'.implode(',', self::FOLDERS)],
            'filename' => ['required', 'string', 'regex:'.($document ? self::DOCUMENT_FILENAME : self::FILENAME)],
            'file' => $document
                ? ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120']
                : ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:25600'],
        ]);

        $directory = public_path($data['folder']);
        File::ensureDirectoryExists($directory);

        if (File::exists($directory.DIRECTORY_SEPARATOR.$data['filename'])) {
            return response()->json(['success' => false, 'message' => 'يوجد ملفٌّ بهذا الاسم.', 'code' => 'FILE_EXISTS'], 409);
        }

        $request->file('file')->move($directory, $data['filename']);

        return response()->json(['success' => true, 'data' => ['path' => $data['folder'].'/'.$data['filename']]], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $document = in_array($request->input('folder'), self::DOCUMENT_FOLDERS, true);

        $data = $request->validate([
            'folder' => ['required', 'string', 'in:'.implode(',', self::FOLDERS)],
            'filename' => ['required', 'string', 'regex:'.($document ? self::DOCUMENT_FILENAME : self::FILENAME)],
        ]);

        $path = public_path($data['folder']).DIRECTORY_SEPARATOR.basename($data['filename']);
        $deleted = File::exists($path) && File::delete($path);

        return response()->json(['success' => true, 'data' => ['deleted' => $deleted]]);
    }

    private function guard(Request $request): ?JsonResponse
    {
        $token = (string) config('services.mobile_media.token');

        if ($token === '') {
            return response()->json(['success' => false, 'message' => 'استقبال صور التطبيق غير مفعَّل على النظام.', 'code' => 'MEDIA_DISABLED'], 503);
        }

        if (! hash_equals($token, (string) $request->header('X-Media-Token'))) {
            return response()->json(['success' => false, 'message' => 'غير مصرّح.', 'code' => 'UNAUTHORIZED'], 401);
        }

        return null;
    }
}

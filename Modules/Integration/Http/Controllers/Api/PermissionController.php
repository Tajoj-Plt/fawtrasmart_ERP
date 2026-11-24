<?php

namespace Modules\Integration\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Services\AppPermissions;

/**
 * صلاحيات المستخدم الحالية — يعيد التطبيق قراءتها متى غيّر المدير
 * الصلاحيات أثناء الجلسة، بلا تسجيل خروج. نفسها تصل أيضاً مع الدخول
 * والملف الشخصي تحت `access`.
 */
class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AppPermissions::forUser($request->user()),
        ]);
    }
}

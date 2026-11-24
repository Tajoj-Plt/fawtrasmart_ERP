<?php

namespace Modules\Integration\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Integration\Http\Requests\ChangePasswordRequest;
use Modules\Integration\Http\Requests\UpdateProfileRequest;
use Modules\Integration\Http\Resources\EmployeeProfileResource;

/**
 * الملف الشخصي للموظف في تطبيق الجوال — عرضٌ وتعديل.
 *
 * التعديل مقصور على حقول التواصل والعنوان وجهة الطوارئ؛ حقول التوظيف
 * (المسمّى، القسم، الراتب، الحالة…) تُدار من شاشات HR فقط.
 */
class ProfileController extends Controller
{
    /**
     * جلب الملف الشخصي (بيانات الحساب + سجل الموظف).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['employee.job_title', 'employee.department', 'employee.branch']);

        return response()->json([
            'success' => true,
            'data' => new EmployeeProfileResource($user),
        ]);
    }

    /**
     * تعديل الملف الشخصي.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        $user->fill($request->safe()->only(['name', 'phone']))->save();

        $employee->fill($request->safe()->only([
            'mobile_number',
            'phone_number',
            'personal_email',
            'language',
            'nickname',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relation',
            'current_address_1',
            'current_address_2',
            'city',
            'region',
            'postal_code',
        ]))->save();

        $user->load(['employee.job_title', 'employee.department', 'employee.branch']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح.',
            'data' => new EmployeeProfileResource($user),
        ]);
    }

    /**
     * رفع/تحديث الصورة الشخصية.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->forceFill(['avatar' => $path])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصورة الشخصية بنجاح.',
            'data' => [
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    /**
     * تغيير كلمة المرور (للمستخدم المسجَّل) — يُبطل رموز الأجهزة الأخرى
     * ويُبقي الجهاز الحالي متصلاً.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill(['password' => Hash::make($request->password)])->save();

        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح.',
        ]);
    }
}

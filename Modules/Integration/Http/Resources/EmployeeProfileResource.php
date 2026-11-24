<?php

namespace Modules\Integration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Integration\Services\AppPermissions;

/**
 * الملف الشخصي للموظف كما يراه تطبيق الجوال.
 *
 * المورد يلفّ App\Models\User مع علاقة employee محمَّلة.
 */
class EmployeeProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'avatar_url' => $this->avatar_url,
                'email_verified' => $this->email_verified_at !== null,
                'branch_id' => $this->branch_id,
                'last_login_at' => $this->last_login_at?->toDateTimeString(),
            ],
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'nickname' => $employee->nickname,
                'full_name' => trim($employee->first_name.' '.$employee->middle_name),
                'photo_url' => $employee->employee_photo
                    ? asset('storage/'.ltrim($employee->employee_photo, '/'))
                    : null,
                'email' => $employee->email,
                'personal_email' => $employee->personal_email,
                'mobile_number' => $employee->mobile_number,
                'phone_number' => $employee->phone_number,
                'language' => $employee->language,
                'status' => $employee->status,
                'employee_type' => $employee->employee_type,
                'hire_date' => $employee->hire_date?->toDateString(),
                'date_of_birth' => $employee->date_of_birth?->toDateString(),
                'gender' => $employee->gender,
                'country' => $employee->country,
                'job_title' => $employee->job_title?->name,
                'department' => $employee->department?->name,
                'branch' => $employee->branch?->name,
                'emergency_contact' => [
                    'name' => $employee->emergency_contact_name,
                    'phone' => $employee->emergency_contact_phone,
                    'relation' => $employee->emergency_contact_relation,
                ],
                'address' => [
                    'line_1' => $employee->current_address_1,
                    'line_2' => $employee->current_address_2,
                    'city' => $employee->city,
                    'region' => $employee->region,
                    'postal_code' => $employee->postal_code,
                ],
            ] : null,
            // صلاحيات التطبيق ونطاق البيانات — لإخفاء ما لا يملكه المستخدم
            'access' => AppPermissions::forUser($this->resource),
        ];
    }
}

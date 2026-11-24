<?php

namespace Modules\Integration\Services\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Models\ClientEmployeeNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PointsService;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «مسؤولو العميل» — موظفو المحلّ الذين يتعامل معهم المندوب، بدوامهم.
 * نفس `ClientNoteController::storeEmployeeNote()/getEmployeeNotes()` في الموقع،
 * يستعمله ملفّ العميل وخطوة الزيارة الأولى معاً.
 *
 * الدوام قيمةٌ واحدة: `full` (دوام كامل) أو `HH:00|HH:00` (من|إلى).
 */
class ContactService
{
    public static function shiftRule(): array
    {
        return ['required', 'string', function ($attribute, $value, $fail) {
            if (ClientEmployeeNote::parseShiftRange($value) === null) {
                $fail('فترة الدوام غير صالحة — أرسل full أو من|إلى بصيغة HH:00|HH:00.');
            }
        }];
    }

    public static function create(Request $request, int $clientId, User $user, string $logModule): ClientEmployeeNote
    {
        \App\Rules\SaudiPhone::normalizeRequest($request, ['employee_phone']);

        $data = $request->validate([
            'employee_name' => 'required|string|max:255',
            'employee_position' => 'nullable|string|max:100',
            'employee_phone' => ['required', 'string', 'max:20', new \App\Rules\SaudiPhone],
            'shift' => self::shiftRule(),
            'is_primary' => 'nullable|boolean',
            'invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')->where('client_id', $clientId)],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:'.ClientSettingsHelper::maxUploadSizeKb(),
        ], [
            'employee_name.required' => 'اسم المسؤول إلزامي.',
            'employee_phone.required' => 'رقم جوال المسؤول إلزامي.',
            'shift.required' => 'دوام المسؤول إلزامي — اختر فترة.',
            'invoice_id.exists' => 'الفاتورة المختارة ليست لهذا العميل.',
        ]);

        [$start, $end] = ClientEmployeeNote::parseShiftRange($data['shift']);

        $values = [
            'client_id' => $clientId,
            'employee_id' => $user->id,
            'employee_name' => $data['employee_name'],
            'employee_position' => $data['employee_position'] ?? 'موظف',
            'employee_phone' => $data['employee_phone'],
            'shift_start' => $start,
            'shift_end' => $end,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'invoice_id' => $data['invoice_id'] ?? null,
        ];

        if ($image = $request->file('image')) {
            $values['image'] = MediaStore::CONTACTS.'/'.MediaStore::storePhoto($image, MediaStore::CONTACTS);
        }

        $contact = ClientEmployeeNote::create($values);

        OperationLog::record('employee_note_added', $logModule, $clientId,
            'إضافة مسؤول العميل «'.$contact->employee_name.'» للعميل #'.$clientId.' — من تطبيق الجوال');

        try {
            app(PointsService::class)->processAction('note_created', [
                'employee_id' => $user->id,
                'client_id' => $clientId,
                'description' => 'تسجيل ملاحظة موظف عميل',
                'reference_type' => get_class($contact),
                'reference_id' => $contact->id,
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, $logModule, 'points_on_contact');
        }

        return $contact;
    }

    public static function present(ClientEmployeeNote $contact, array $invoiceCodes = []): array
    {
        return [
            'id' => (int) $contact->id,
            'employee_name' => $contact->employee_name,
            'employee_position' => $contact->employee_position,
            'employee_phone' => $contact->employee_phone,
            'is_primary' => (bool) $contact->is_primary,
            'shift' => $contact->shift_range_value ?: null,
            'shift_start' => $contact->shift_start,
            'shift_end' => $contact->shift_end,
            'shift_label' => $contact->shift_label,
            'image_url' => $contact->image ? asset($contact->image) : null,
            'invoice_id' => $contact->invoice_id ? (int) $contact->invoice_id : null,
            'invoice_code' => $contact->invoice_id ? ($invoiceCodes[$contact->invoice_id] ?? null) : null,
            'created_by' => $contact->relationLoaded('employee') ? $contact->employee?->name : null,
            'created_at' => $contact->created_at?->toDateTimeString(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function listFor(int $clientId): array
    {
        $rows = ClientEmployeeNote::where('client_id', $clientId)->with('employee:id,name')
            ->orderByDesc('is_primary')->latest()->get();
        $codes = Invoice::whereIn('id', $rows->pluck('invoice_id')->filter())->pluck('code', 'id')->all();

        return $rows->map(fn ($c) => self::present($c, $codes))->values()->all();
    }
}

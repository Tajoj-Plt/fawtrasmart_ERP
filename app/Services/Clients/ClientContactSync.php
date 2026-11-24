<?php

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\ClientEmployeeNote;
use App\Rules\SaudiPhone;

/**
 * اسمُ العميل وجوّالُه وجهاتُ اتصاله في نموذج الإضافة/التعديل تنزل مسؤولين في
 * «موظفو العميل» (`client_employee_notes`) — الجدولِ الذي تقرؤه مساحةُ عمل
 * الزيارة وملاحظاتُ المتابعة.
 *
 * كان النموذج يحفظها في `clients` و`contacts` وحدهما، فيعيد المندوبُ إدخالَ
 * الاسم والجوّال نفسيهما مسؤولاً عند أوّل زيارة (٧٢١ صفّاً محلياً جوّالُها
 * جوّالُ العميل حرفياً).
 *
 * **لا تكرار:** الرقمُ هو الهويّة — رقمٌ موجودٌ عند العميل لا يُضاف ثانيةً،
 * وصفٌّ أضافه المندوبُ بيده لا يُمسّ اسمُه ولا دوامُه.
 * **الدوام:** «دوام كامل» — النموذج لا يسأل عنه، ومسؤولٌ بلا دوامٍ يوقف خطوات
 * الزيارة. يُغيَّر بعدها من ملفّ العميل.
 */
class ClientContactSync
{
    public const POSITION_OWNER = 'صاحب الحساب';

    public const POSITION_CONTACT = 'جهة اتصال';

    /**
     * @param  array<int, array<string, mixed>>  $contacts  صفوف «قائمة الاتصال» كما أُرسلت
     * @param  array<string, mixed>|null  $previous  قيمُ العميل قبل التعديل (`getOriginal()`)
     */
    public static function sync(Client $client, array $contacts = [], ?array $previous = null, ?int $userId = null): void
    {
        $userId ??= auth()->id();

        [$name, $phone] = self::personOf($client->getAttributes());
        [$oldName, $oldPhone] = $previous ? self::personOf($previous) : [null, null];

        if ($phone !== null) {
            // تعديلُ جوّال العميل أو اسمه يتبعه صفُّه الذي نزل منه، لا صفٌّ جديد بجانبه
            $moved = $oldPhone !== null && $oldPhone !== $phone
                ? self::findByPhone($client->id, $oldPhone, self::POSITION_OWNER)
                : null;

            if ($moved && ! self::findByPhone($client->id, $phone)) {
                $moved->update(['employee_phone' => $phone, 'employee_name' => $name]);
            } else {
                $owner = self::findByPhone($client->id, $phone, self::POSITION_OWNER);

                if ($owner && $oldName !== null && $owner->employee_name === $oldName && $name !== $oldName) {
                    $owner->update(['employee_name' => $name]);
                }

                self::add($client, $name, $phone, self::POSITION_OWNER, $userId);
            }
        }

        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $contactName = trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? ''));
            $contactPhone = self::phone($contact['mobile'] ?? null) ?? self::phone($contact['phone'] ?? null);

            if ($contactName !== '' && $contactPhone !== null) {
                self::add($client, $contactName, $contactPhone, self::POSITION_CONTACT, $userId);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: string, 1: ?string}
     */
    private static function personOf(array $attributes): array
    {
        $name = trim(($attributes['first_name'] ?? '').' '.($attributes['last_name'] ?? ''));

        return [
            $name !== '' ? $name : trim((string) ($attributes['trade_name'] ?? '')),
            self::phone($attributes['mobile'] ?? null) ?? self::phone($attributes['phone'] ?? null),
        ];
    }

    /** الرقمُ مطبَّعاً، أو null إن لم يكن رقماً صالحاً — بياناتٌ قديمة فيها «,» و«11». */
    private static function phone(mixed $value): ?string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return SaudiPhone::isValid($value) ? SaudiPhone::normalize($value) : null;
    }

    private static function findByPhone(int $clientId, string $phone, ?string $position = null): ?ClientEmployeeNote
    {
        return ClientEmployeeNote::where('client_id', $clientId)
            ->when($position, fn ($q) => $q->where('employee_position', $position))
            ->get()
            ->first(fn ($row) => SaudiPhone::normalize($row->employee_phone) === $phone);
    }

    private static function add(Client $client, string $name, string $phone, string $position, ?int $userId): void
    {
        if ($name === '' || self::findByPhone($client->id, $phone)) {
            return;
        }

        ClientEmployeeNote::create([
            'client_id' => $client->id,
            'employee_id' => $userId,
            'employee_name' => $name,
            'employee_position' => $position,
            'employee_phone' => $phone,
            'shift_start' => ClientEmployeeNote::FULL_TIME_START,
            'shift_end' => ClientEmployeeNote::FULL_TIME_END,
            // مسؤولٌ أساسيٌّ واحد: صاحبُ الحساب ما لم يُحدَّد غيرُه قبله
            'is_primary' => $position === self::POSITION_OWNER
                && ! ClientEmployeeNote::where('client_id', $client->id)->where('is_primary', true)->exists(),
        ]);
    }
}

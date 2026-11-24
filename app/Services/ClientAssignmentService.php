<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use Illuminate\Support\Facades\DB;

class ClientAssignmentService
{
    /**
     * تعيين جميع العملاء إلى الموظفين بناءً على المجموعات
     *
     * @param array $assignmentData - مصفوفة تحتوي على بيانات التعيين
     *                                Format: [
     *                                    'group_id' => int,
     *                                    'employee_id' => int
     *                                ]
     * @return array - يحتوي على نتائج العملية
     */
    public function assignClientsToEmployeesByGroup($assignmentData)
    {
        $result = [
            'success' => false,
            'message' => '',
            'assigned_count' => 0,
            'failed_count' => 0,
            'details' => []
        ];

        try {
            DB::beginTransaction();

            foreach ($assignmentData as $assignment) {
                $groupId = $assignment['group_id'] ?? null;
                $employeeId = $assignment['employee_id'] ?? null;

                if (!$groupId || !$employeeId) {
                    $result['failed_count']++;
                    $result['details'][] = [
                        'status' => 'error',
                        'group_id' => $groupId,
                        'employee_id' => $employeeId,
                        'message' => 'بيانات غير كاملة'
                    ];
                    continue;
                }

                // التحقق من وجود الموظف
                $employee = Employee::find($employeeId);
                if (!$employee) {
                    $result['failed_count']++;
                    $result['details'][] = [
                        'status' => 'error',
                        'group_id' => $groupId,
                        'employee_id' => $employeeId,
                        'message' => 'الموظف غير موجود'
                    ];
                    continue;
                }

                // جلب جميع العملاء في المجموعة
                $clients = $this->getClientsInGroup($groupId);

                if ($clients->isEmpty()) {
                    $result['details'][] = [
                        'status' => 'warning',
                        'group_id' => $groupId,
                        'employee_id' => $employeeId,
                        'message' => 'لا توجد عملاء في هذه المجموعة'
                    ];
                    continue;
                }

                // تعيين جميع العملاء للموظف
                foreach ($clients as $client) {
                    try {
                        $this->assignClientToEmployee($client->id, $employeeId);
                        $result['assigned_count']++;
                    } catch (\Exception $e) {
                        $result['failed_count']++;
                        $result['details'][] = [
                            'status' => 'error',
                            'client_id' => $client->id,
                            'client_name' => $client->trade_name,
                            'employee_id' => $employeeId,
                            'message' => 'فشل في التعيين: ' . $e->getMessage()
                        ];
                    }
                }
            }

            DB::commit();

            $result['success'] = true;
            $result['message'] = "تم تعيين {$result['assigned_count']} عميل بنجاح";

            if ($result['failed_count'] > 0) {
                $result['message'] .= " و فشل تعيين {$result['failed_count']} عميل";
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $result['success'] = false;
            $result['message'] = 'حدث خطأ: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * تعيين عميل واحد إلى موظف
     */
    private function assignClientToEmployee($clientId, $employeeId)
    {
        $client = Client::find($clientId);

        if (!$client) {
            throw new \Exception('العميل غير موجود');
        }

        // استخدام belongsToMany للتعيين
        if (!$client->employees()->wherePivot('employee_id', $employeeId)->exists()) {
            $client->employees()->attach($employeeId);
        }
    }

    /**
     * جلب جميع العملاء في مجموعة معينة — بـ«تابع لـ» العميل وما تحت المجموعة.
     *
     * كانت عبر حيّ العميل (`neighborhoods.region_id`) وحدَه، فعميلٌ نُسب إلى
     * منطقةٍ من شاشته بلا صفّ حيّ لا يُسنَد أبداً، وعميلُ منفذٍ تحت المنطقة يسقط.
     */
    private function getClientsInGroup($groupId)
    {
        return Client::query()->inTreeNode((int) $groupId)->get();
    }

    // ═══════════════════ الإسناد والنقل بقواعد الإعدادات ═══════════════════

    /** يُضاف الموظف بجانب من هم على العميل */
    public const MODE_ADD = 'add';

    /** يحلّ الموظف محلّ كل من على العميل — «نقل» العملاء إليه */
    public const MODE_REPLACE = 'replace';

    /**
     * إسناد عملاء إلى موظف — الموضعُ الوحيد الذي يكتب `client_employee` من
     * شاشة التعيين الجماعي.
     *
     * كان الإسنادُ `attach` أعمى: يُضيف الموظف ولو كان «تعدّد الموظفين» مطفأً
     * في الإعدادات، ولا يستطيع أن **ينقل** عملاءَ من موظفٍ إلى آخر — فمن أراد
     * إعادة توزيع منطقة وجد عملاءَها بموظفَين بعد العملية بدل موظفٍ واحد.
     *
     *   • `add`     ← يُضاف لمن ليس عليه، ويُتخطّى من يمنعه السقف أو منعُ التعدّد.
     *   • `replace` ← يُنزَع كلُّ موظفٍ آخر عن العميل ويبقى هذا وحده.
     *
     * صفوفُ الجدول تُقرأ دفعةً واحدة وتُكتب دفعةً واحدة: منطقةٌ بألف عميل
     * كانت ألفَي استعلام.
     *
     * @param  array<int, int>  $clientIds
     * @return array{success: bool, message: string, assigned_count: int, already_count: int, replaced_count: int, skipped_count: int, released_employee_ids: array<int, int>}
     */
    public function assignToEmployee(array $clientIds, int $employeeId, string $mode = self::MODE_ADD): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'assigned_count' => 0,
            'already_count' => 0,
            'replaced_count' => 0,
            'skipped_count' => 0,
            'released_employee_ids' => [],
        ];

        $employee = Employee::find($employeeId);
        if (! $employee) {
            $result['message'] = 'الموظف غير موجود';

            return $result;
        }

        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));
        if (empty($clientIds)) {
            $result['message'] = 'لا يوجد عملاء تابعون لما اخترته';

            return $result;
        }

        $allowMultiple = \App\Helpers\ClientSettingsHelper::clientEmployeesAllowMultiple();
        $max = \App\Helpers\ClientSettingsHelper::clientEmployeesMax();

        try {
            DB::transaction(function () use ($clientIds, $employeeId, $mode, $allowMultiple, $max, &$result) {
                $now = now();
                $released = [];

                foreach (array_chunk($clientIds, 500) as $chunk) {
                    /* الموظفون الموجودون فعلاً وحدَهم: في الجدول صفوفٌ تشير إلى
                       معرّفاتٍ لا موظف لها (من حين كتب نموذجُ الإضافة معرّفاتِ
                       مستخدمين هنا — انظر ClientController::create) فلا تظهر في
                       أي شاشة؛ لو عُدّت لتخطّى «منعُ التعدّد» عميلاً لا موظف له. */
                    $current = DB::table('client_employee')
                        ->join('employees', 'employees.id', '=', 'client_employee.employee_id')
                        ->whereNull('employees.deleted_at')
                        ->whereIn('client_employee.client_id', $chunk)
                        ->get(['client_employee.client_id', 'client_employee.employee_id'])
                        ->groupBy('client_id')
                        ->map(fn ($rows) => $rows->pluck('employee_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

                    $insert = [];
                    $detachFrom = [];

                    foreach ($chunk as $clientId) {
                        $onClient = $current->get($clientId, []);
                        $has = in_array($employeeId, $onClient, true);
                        $others = array_values(array_diff($onClient, [$employeeId]));

                        if ($mode === self::MODE_REPLACE) {
                            if ($others) {
                                $detachFrom[] = $clientId;
                                $released = array_merge($released, $others);
                                $result['replaced_count']++;
                            }

                            if ($has) {
                                if (! $others) {
                                    $result['already_count']++;
                                }
                            } else {
                                $insert[] = ['client_id' => $clientId, 'employee_id' => $employeeId, 'created_at' => $now, 'updated_at' => $now];
                                $result['assigned_count']++;
                            }

                            continue;
                        }

                        if ($has) {
                            $result['already_count']++;

                            continue;
                        }

                        $after = count($onClient) + 1;
                        if ((! $allowMultiple && $after > 1) || ($max !== null && $after > $max)) {
                            $result['skipped_count']++;

                            continue;
                        }

                        $insert[] = ['client_id' => $clientId, 'employee_id' => $employeeId, 'created_at' => $now, 'updated_at' => $now];
                        $result['assigned_count']++;
                    }

                    if ($detachFrom) {
                        DB::table('client_employee')
                            ->whereIn('client_id', $detachFrom)
                            ->where('employee_id', '!=', $employeeId)
                            ->delete();
                    }

                    foreach (array_chunk($insert, 500) as $rows) {
                        DB::table('client_employee')->insert($rows);
                    }
                }

                $result['released_employee_ids'] = array_values(array_unique($released));
            });
        } catch (\Throwable $e) {
            $result['message'] = 'حدث خطأ: '.$e->getMessage();

            return $result;
        }

        $result['success'] = ($result['assigned_count'] + $result['replaced_count'] + $result['already_count']) > 0;
        $result['message'] = $this->summarize($result, trim((string) $employee->full_name), $allowMultiple, $max);

        return $result;
    }

    /**
     * نقل عملاءَ من موظفٍ إلى آخر — تعديلُ الإسناد القائم لا إضافةُ آخر.
     *
     * يُعدَّل الصفُّ نفسُه (`employee_id`) لا يُحذف ويُنشأ غيرُه، فيبقى ما عليه
     * من «رئيسي» ونوعِ العلاقة. ومن كان الموظفُ الجديد عليه أصلاً يُنزَع عنه
     * القديمُ وحدَه كي لا يتكرّر الصفّ.
     *
     * @param  array<int, int>  $clientIds
     * @return array{success: bool, message: string, moved_count: int}
     */
    public function transferBetweenEmployees(array $clientIds, int $fromEmployeeId, int $toEmployeeId): array
    {
        $result = ['success' => false, 'message' => '', 'moved_count' => 0];

        if ($fromEmployeeId === $toEmployeeId) {
            $result['message'] = 'اختر موظفاً غير الموظف الحالي';

            return $result;
        }

        $to = Employee::find($toEmployeeId);
        if (! $to) {
            $result['message'] = 'الموظف غير موجود';

            return $result;
        }

        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));

        try {
            DB::transaction(function () use ($clientIds, $fromEmployeeId, $toEmployeeId, &$result) {
                foreach (array_chunk($clientIds, 500) as $chunk) {
                    $onFrom = DB::table('client_employee')
                        ->whereIn('client_id', $chunk)
                        ->where('employee_id', $fromEmployeeId)
                        ->pluck('client_id')->map(fn ($id) => (int) $id)->unique()->all();

                    if (! $onFrom) {
                        continue;
                    }

                    $alreadyOnTo = DB::table('client_employee')
                        ->whereIn('client_id', $onFrom)
                        ->where('employee_id', $toEmployeeId)
                        ->pluck('client_id')->map(fn ($id) => (int) $id)->unique()->all();

                    $rewrite = array_values(array_diff($onFrom, $alreadyOnTo));

                    if ($rewrite) {
                        DB::table('client_employee')
                            ->whereIn('client_id', $rewrite)
                            ->where('employee_id', $fromEmployeeId)
                            ->update(['employee_id' => $toEmployeeId, 'updated_at' => now()]);
                    }

                    if ($alreadyOnTo) {
                        DB::table('client_employee')
                            ->whereIn('client_id', $alreadyOnTo)
                            ->where('employee_id', $fromEmployeeId)
                            ->delete();
                    }

                    $result['moved_count'] += count($onFrom);
                }
            });
        } catch (\Throwable $e) {
            $result['message'] = 'حدث خطأ: '.$e->getMessage();

            return $result;
        }

        $result['success'] = $result['moved_count'] > 0;
        $result['message'] = $result['success']
            ? "تم نقل {$result['moved_count']} عميل إلى الموظف ".trim((string) $to->full_name)
            : 'لا يوجد عملاء على الموظف الحالي لنقلهم';

        return $result;
    }

    /** رسالةٌ تقول ما جرى فعلاً — لا «تم بنجاح» وقد تُخطّي نصفُ العملاء. */
    private function summarize(array $r, string $employeeName, bool $allowMultiple, ?int $max): string
    {
        $parts = [];

        if ($r['assigned_count']) {
            $parts[] = "أُسند {$r['assigned_count']} عميل إلى {$employeeName}";
        }
        if ($r['replaced_count']) {
            $parts[] = "ونُقل منهم {$r['replaced_count']} من موظفيهم السابقين";
        }
        if ($r['already_count']) {
            $parts[] = "{$r['already_count']} عميل كان مسنداً إليه أصلاً";
        }
        if ($r['skipped_count']) {
            $why = ! $allowMultiple
                ? 'لأن لهم موظفاً آخر والإعدادات لا تسمح بأكثر من موظف — استعمل «نقل» بدل «إضافة»'
                : "لأنهم بلغوا الحدّ الأقصى ({$max}) من الموظفين";
            $parts[] = "وتُخطّي {$r['skipped_count']} عميل {$why}";
        }

        return $parts ? implode('، ', $parts) : 'لم يتغيّر شيء';
    }

    /**
     * تعيين جميع العملاء في مجموعة إلى موظف واحد
     */
    public function assignGroupToEmployee($groupId, $employeeId)
    {
        $result = [
            'success' => false,
            'message' => '',
            'assigned_count' => 0
        ];

        try {
            // التحقق من وجود الموظف
            $employee = Employee::find($employeeId);
            if (!$employee) {
                $result['message'] = 'الموظف غير موجود';
                return $result;
            }

            // جلب جميع العملاء في المجموعة
            $clients = $this->getClientsInGroup($groupId);

            if ($clients->isEmpty()) {
                $result['message'] = 'لا توجد عملاء في هذه المجموعة';
                return $result;
            }

            DB::beginTransaction();

            foreach ($clients as $client) {
                $this->assignClientToEmployee($client->id, $employeeId);
                $result['assigned_count']++;
            }

            DB::commit();

            $result['success'] = true;
            $employeeName = $employee->first_name . ' ' . ($employee->middle_name ?? '') . ' ' . ($employee->last_name ?? '');
            $result['message'] = "تم تعيين {$result['assigned_count']} عميل بنجاح إلى الموظف " . trim($employeeName);

        } catch (\Exception $e) {
            DB::rollBack();
            $result['message'] = 'حدث خطأ: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * تعيين مجموعة عملاء محدَّدة بمعرّفاتهم مباشرةً إلى موظف — تُستعمَل من
     * التعيين بواسطة شجرة الفروع (فرع + كل أبنائه)، بلا اشتراط أن يكون
     * العميل مربوطاً بحيٍّ داخل «منطقة» كما في `getClientsInGroup()`.
     */
    public function assignClientsByIds(array $clientIds, int $employeeId): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'assigned_count' => 0,
        ];

        $employee = Employee::find($employeeId);
        if (! $employee) {
            $result['message'] = 'الموظف غير موجود';

            return $result;
        }

        if (empty($clientIds)) {
            $result['message'] = 'لا يوجد عملاء ضمن هذا النطاق';

            return $result;
        }

        try {
            DB::beginTransaction();

            foreach ($clientIds as $clientId) {
                $this->assignClientToEmployee($clientId, $employeeId);
                $result['assigned_count']++;
            }

            DB::commit();

            $result['success'] = true;
            $employeeName = trim($employee->first_name.' '.($employee->middle_name ?? '').' '.($employee->last_name ?? ''));
            $result['message'] = "تم تعيين {$result['assigned_count']} عميل بنجاح إلى الموظف ".$employeeName;
        } catch (\Exception $e) {
            DB::rollBack();
            $result['message'] = 'حدث خطأ: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * تعيين عميل إلى مجموعة موظفين في مجموعة معينة
     */
    public function assignGroupToEmployeesByRegion($groupId, $employeeIds)
    {
        $result = [
            'success' => false,
            'message' => '',
            'assigned_count' => 0,
            'failed_count' => 0
        ];

        try {
            // جلب جميع العملاء في المجموعة
            $clients = $this->getClientsInGroup($groupId);

            if ($clients->isEmpty()) {
                $result['message'] = 'لا توجد عملاء في هذه المجموعة';
                return $result;
            }

            DB::beginTransaction();

            foreach ($employeeIds as $employeeId) {
                // التحقق من وجود الموظف
                if (!Employee::find($employeeId)) {
                    $result['failed_count']++;
                    continue;
                }

                foreach ($clients as $client) {
                    try {
                        $this->assignClientToEmployee($client->id, $employeeId);
                        $result['assigned_count']++;
                    } catch (\Exception $e) {
                        $result['failed_count']++;
                    }
                }
            }

            DB::commit();

            $result['success'] = true;
            $result['message'] = "تم تعيين {$result['assigned_count']} عميل بنجاح";

        } catch (\Exception $e) {
            DB::rollBack();
            $result['message'] = 'حدث خطأ: ' . $e->getMessage();
        }

        return $result;
    }
}

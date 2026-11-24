<?php

namespace App\Services;

use App\Constants\Banks;
use App\Models\PayrollBankFile;
use App\Models\PayrollRun;
use App\Support\CurrencySettings;
use App\Support\PayrollSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * بناء ملف تحويل بنكي من عمليات الصرف المصروفة.
 *
 * الصفّ يدخل الملف إن وُجد آيبان صالح وصافٍ > 0. بلا آيبان يُستبعد ويُذكر السبب.
 * النسب والصيغة إعدادات — ليست مواصفة وزارة أو بنك معيّن.
 */
class PayrollBankFileService
{
    /**
     * @param  Collection<int, PayrollRun>  $runs
     * @return array{included: list<array<string, mixed>>, skipped: list<array<string, mixed>>, total: float}
     */
    public function preview(Collection $runs): array
    {
        $included = [];
        $skipped = [];
        $total = 0.0;

        if (! $this->tablesReady()) {
            return compact('included', 'skipped', 'total');
        }

        $currency = CurrencySettings::code();

        foreach ($runs as $run) {
            $employee = $run->employee;
            $name = $employee?->full_name ?: ('#'.$run->employee_id);
            $amount = round((float) ($run->net_salary ?? 0), 2);

            if ($amount < 0.01) {
                $skipped[] = $this->skip($run, $name, 'صافي الراتب صفر');

                continue;
            }

            if (! $employee) {
                $skipped[] = $this->skip($run, $name, 'الموظف غير موجود');

                continue;
            }

            $iban = Banks::normalize($employee->iban ?? null);
            $reason = $this->ibanRejectReason($iban);
            if ($reason) {
                $skipped[] = $this->skip($run, $name, $reason);

                continue;
            }

            $row = [
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'employee_code' => (string) ($employee->employee_code ?? ''),
                'employee_name' => $name,
                'iban' => $iban,
                'bank_name' => (string) ($employee->bank_name ?: (Banks::name(Banks::bankCodeFromIban($iban)) ?? '')),
                'account_holder' => (string) ($employee->bank_account_holder ?: $name),
                'amount' => $amount,
                'currency' => $run->slip?->currency ?: $currency,
                'period_from' => optional($run->period_from)->toDateString(),
                'period_to' => optional($run->period_to)->toDateString(),
            ];
            $included[] = $row;
            $total = round($total + $amount, 2);
        }

        return compact('included', 'skipped', 'total');
    }

    /**
     * @param  Collection<int, PayrollRun>  $runs
     */
    public function generate(Collection $runs, $periodFrom, $periodTo, ?int $userId = null): ?PayrollBankFile
    {
        if (! $this->tablesReady()) {
            return null;
        }

        $preview = $this->preview($runs);
        if ($preview['included'] === []) {
            return null;
        }

        $settings = $this->settings();
        $from = $periodFrom instanceof \DateTimeInterface
            ? $periodFrom->format('Y-m-d')
            : (string) $periodFrom;
        $to = $periodTo instanceof \DateTimeInterface
            ? $periodTo->format('Y-m-d')
            : (string) $periodTo;

        $filename = sprintf(
            'payroll_bank_%s_%s.%s',
            $from,
            $to,
            $settings['format']
        );

        return PayrollBankFile::create([
            'period_from' => $from,
            'period_to' => $to,
            'format' => $settings['format'],
            'bank_code' => $settings['bank_code'],
            'filename' => $filename,
            'row_count' => count($preview['included']),
            'skipped_count' => count($preview['skipped']),
            'total_amount' => $preview['total'],
            'payload' => [
                'included' => $preview['included'],
                'skipped' => $preview['skipped'],
            ],
            'generated_by' => $userId,
            'generated_at' => now(),
        ]);
    }

    /** محتوى CSV مع BOM ليُفتح في إكسل بالعربية. */
    public function toCsv(PayrollBankFile $file): string
    {
        $handle = fopen('php://temp', 'r+');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, $this->headings());
        foreach ($file->includedRows() as $row) {
            fputcsv($handle, $this->csvValues($row));
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'رقم الموظف',
            'اسم الموظف',
            'الآيبان',
            'البنك',
            'اسم المستفيد',
            'المبلغ',
            'العملة',
            'من',
            'إلى',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function csvValues(array $row): array
    {
        return [
            $row['employee_code'] ?? '',
            $row['employee_name'] ?? '',
            $row['iban'] ?? '',
            $row['bank_name'] ?? '',
            $row['account_holder'] ?? '',
            $row['amount'] ?? 0,
            $row['currency'] ?? '',
            $row['period_from'] ?? '',
            $row['period_to'] ?? '',
        ];
    }

    /** @return array{format: string, bank_code: string|null} */
    public function settings(): array
    {
        $row = PayrollSettings::row();

        $format = PayrollBankFile::FORMAT_CSV;
        $code = null;
        if ($row) {
            if (Schema::hasColumn('payroll_settings', 'bank_export_format')) {
                $format = $row->bank_export_format ?: $format;
            }
            if (Schema::hasColumn('payroll_settings', 'bank_export_bank_code')) {
                $code = $row->bank_export_bank_code ?: null;
            }
        }
        if (! in_array($format, PayrollBankFile::formats(), true)) {
            $format = PayrollBankFile::FORMAT_CSV;
        }

        return [
            'format' => $format,
            'bank_code' => $code,
        ];
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('payroll_bank_files')
            && Schema::hasTable('payroll_runs')
            && Schema::hasTable('employees')
            && Schema::hasColumn('employees', 'iban');
    }

    public function ibanRejectReason(string $iban): ?string
    {
        if ($iban === '') {
            return 'لا آيبان';
        }

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return 'آيبان غير صالح';
        }

        $spec = Banks::ibanSpec(substr($iban, 0, 2));
        if ($spec && strlen($iban) !== $spec['length']) {
            return 'آيبان غير مكتمل';
        }

        return null;
    }

    /**
     * @return array{payroll_run_id: int, employee_id: int|null, employee_name: string, reason: string}
     */
    private function skip(PayrollRun $run, string $name, string $reason): array
    {
        return [
            'payroll_run_id' => $run->id,
            'employee_id' => $run->employee_id,
            'employee_name' => $name,
            'reason' => $reason,
        ];
    }
}

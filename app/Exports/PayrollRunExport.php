<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * ملفُّ Excel لمسير رواتب: ورقةُ القسائم وورقةُ الموظفين.
 *
 * البياناتُ تمرَّر من المتحكّم كما جمعها لشاشة العرض — لا استعلامَ هنا —
 * فيطابق الملفُّ ما يراه المستخدم في المسير صفّاً بصفّ.
 */
class PayrollRunExport implements WithMultipleSheets
{
    public function __construct(
        protected $payroll,
        protected Collection $slips,
        protected Collection $members
    ) {}

    public function sheets(): array
    {
        return [
            new PayrollSlipsExport($this->slips, $this->payroll),
            new PayrollMembersSheet($this->members, $this->slips),
        ];
    }
}

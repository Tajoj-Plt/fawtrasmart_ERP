<?php

namespace Modules\HR\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Modules\HR\Support\HrReportExporter;
use Symfony\Component\HttpFoundation\Response;

/**
 * إن طُلب `export=xlsx|pdf` يُنزَّل الملف من نفس بيانات الشاشة، وإلا null
 * فيُكمَل `return view`. صلاحية المسار هي صلاحية التصدير — لا مفتاح جديد.
 */
trait ExportsHrReports
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int|string, mixed>>  $rows
     */
    protected function exportIfRequested(
        Request $request,
        string $title,
        array $headings,
        iterable|callable $rows,
        string $slug,
    ): ?Response {
        $format = (string) $request->input('export');

        if ($format === '') {
            return null;
        }

        $resolved = is_callable($rows) ? $rows() : $rows;

        return HrReportExporter::download($format, $title, $headings, $resolved, $slug);
    }
}

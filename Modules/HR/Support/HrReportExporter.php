<?php

namespace Modules\HR\Support;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HR\Exports\HrReportTableExport;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

/**
 * تصديرٌ موحَّد لتقارير الموارد البشرية — Excel من الخادم وPDF من جدولٍ واحد.
 *
 * التصدير من الجدول المعروض بجافاسكربت يصدّر الصفحة لا الفلتر، ويُسقط
 * صلاحية المسار. هنا الملف يُبنى من نفس استعلام الشاشة بعد النطاق.
 */
class HrReportExporter
{
    /** @var array<int, string> */
    public const FORMATS = ['xlsx', 'pdf'];

    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int|string, mixed>>  $rows
     */
    public static function download(
        string $format,
        string $title,
        array $headings,
        iterable $rows,
        string $slug,
    ): ?Response {
        if (! in_array($format, self::FORMATS, true)) {
            return null;
        }

        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = array_values($row);
        }

        $filename = Str::slug($slug, '_').'_'.now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return Excel::download(
                new HrReportTableExport($title, $headings, $normalized),
                $filename.'.xlsx'
            );
        }

        return self::downloadPdf($title, $headings, $normalized, $filename);
    }

    /** حدود وتظليل الجدول — نفس ألوان قوالب PDF المخزون (`#d8d6de` الحدّ، `#f3f2f7` ترويسة الجدول). */
    private const TH_STYLE = 'border:1px solid #d8d6de;background-color:#f3f2f7;font-weight:bold;padding:4px;text-align:center;font-size:9px;';

    private const TD_STYLE = 'border:1px solid #d8d6de;padding:4px;text-align:center;font-size:8px;';

    /** تظليل الصفوف الزوجية — نفس `tr.alt` في قوالب المخزون. */
    private const TD_ALT_BG = 'background-color:#fafafc;';

    /**
     * PDF عبر TCPDF لا DomPDF: DomPDF لا يصل الحروف العربية ولا يقلب
     * اتجاهها (نفس القصور الموثَّق في Sales\InvoicesController وفي
     * DataExportService::writePdf())، فتخرج الصفحة حروفاً مبعثرة.
     * TCPDF مع `setRTL(true)` يصل الحروف ويقلب الاتجاه أصلاً. الخط
     * `aealarabiya` تحديداً (لا `dejavusans`) لأنه يملك رمز ربطة «الله»
     * المُشكَّلة (U+FDF2) التي يستبدلها TCPDF تلقائياً في كل اسمٍ يحوي
     * «الله» (عبدالله، نور الله...) — دون هذا الخط تظهر مربّعاً فارغاً.
     *
     * الترويسة والجدول بنفس تصميم قوالب PDF المخزون
     * (`stock::storehouse.pdf.*`): اسم المنشأة عبر `print-org-head`،
     * جدولٌ محدَّد الحدود بترويسة مظلَّلة وصفوف متناوبة التظليل، لا
     * الجدول العاري السابق. الأنماط مضمَّنة على كل عنصر — لا `<style>`
     * عامّة — لأن الكتابة على شرائح (أدناه) تمرّر كل شريحة إلى `writeHTML`
     * في نداءٍ مستقل فلا تُضمَن استمرارية صنفٍ مُعرَّف في نداءٍ سابق.
     *
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    private static function downloadPdf(string $title, array $headings, array $rows, string $filename): Response
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->setRTL(true);
        $pdf->SetCreator('Fawtra');
        $pdf->SetTitle($title);
        $pdf->AddPage();

        $pdf->SetFont('aealarabiya', 'B', 12);
        $pdf->writeHTML(
            view('hr::reports.exports.pdf_head', ['title' => $title])->render(),
            true, false, true, false, ''
        );

        $pdf->SetFont('aealarabiya', '', 8);

        $header = '<tr>'.implode('', array_map(
            fn ($heading) => '<th style="'.self::TH_STYLE.'">'.e($heading).'</th>',
            $headings
        )).'</tr>';

        $table = fn (string $body) => '<table style="width:100%;border-collapse:collapse;margin-bottom:6px;">'
            .'<thead>'.$header.'</thead><tbody>'.$body.'</tbody></table>';

        if ($rows === []) {
            $pdf->writeHTML(
                $table('<tr><td style="'.self::TD_STYLE.'" colspan="'.max(count($headings), 1).'">لا بيانات</td></tr>'),
                true, false, true, false, ''
            );
        }

        $buffer = [];
        $rowIndex = 0;

        // الكتابة على شرائح: `writeHTML` لجدول من آلاف الصفوف دفعةً واحدة
        // يلتهم الذاكرة (نفس التحفّظ في DataExportService::writePdf()).
        $flush = function () use ($pdf, $table, &$buffer) {
            if ($buffer === []) {
                return;
            }

            $pdf->writeHTML($table(implode('', $buffer)), true, false, true, false, '');

            $buffer = [];
        };

        foreach ($rows as $row) {
            $cellStyle = self::TD_STYLE.($rowIndex % 2 === 1 ? self::TD_ALT_BG : '');

            $buffer[] = '<tr>'.implode('', array_map(
                fn ($cell) => '<td style="'.$cellStyle.'">'.e((string) $cell).'</td>',
                $row
            )).'</tr>';
            $rowIndex++;

            if (count($buffer) >= 200) {
                $flush();
            }
        }

        $flush();

        $pdf->writeHTML(
            '<div style="margin-top:4px;padding-top:4px;border-top:1px solid #d8d6de;'
            .'text-align:center;font-size:8px;color:#6e6b7b;">عدد الصفوف: '.count($rows).'</div>',
            true, false, true, false, ''
        );

        return response($pdf->Output($filename.'.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }
}

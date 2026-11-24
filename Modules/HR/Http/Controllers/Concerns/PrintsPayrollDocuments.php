<?php

namespace Modules\HR\Http\Controllers\Concerns;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Illuminate\Support\Collection;

/**
 * طباعةُ قسائم الرواتب ومسيراتها بقوالب «قوالب المستندات» وحدها.
 *
 * كانت في الوحدة ستّةُ ملفّات بلايد مثبّتة (`salary_slip/payslip/*`) لا
 * يستدعيها مسار، وزرُّ «طباعة الكشف» في شاشة المسير يطبع الشاشةَ نفسَها
 * بـ`window.print()`. فلا القالبُ الذي يصمّمه المستخدم يُطبَع، ولا ترويسةُ
 * المنشأة تظهر. صارت الطباعةُ كما في المبيعات
 * (`RendersSalesDocumentTemplates`): القالبُ المطلوب باسمه أو الافتراضيُّ
 * لنوعه، ثمّ `DocumentPrintPage::wrap()` صفحةً كاملة.
 *
 * ولا احتياطَ مدمجاً هنا: لا قالبَ فعّالاً ⇒ رسالةٌ تدلّ على شاشة القوالب،
 * لا مطبوعةٌ ثابتةٌ تُخالف ما صمّمه المستخدم.
 */
trait PrintsPayrollDocuments
{
    /** @return Collection<int, DocumentTemplate> */
    protected function payrollTemplates(string $documentType): Collection
    {
        return PrintSettings::templatesFor($documentType);
    }

    /**
     * @param  int|string|null  $template  معرّف قالبٍ بعينه، أو لا شيء فالافتراضيّ
     * @param  array<int, int>|null  $employeeIds  الموظفون الظاهرون للمستخدم — `null` بلا قيد
     * @param  int|null  $payrollRunId  لقسيمة الراتب: الصرفُ المطبوع بعينه (لا آخرُ صرف)
     */
    protected function renderPayrollTemplate(string $documentType, $documentId, $template = null, ?array $employeeIds = null, ?int $payrollRunId = null): ?string
    {
        $model = is_numeric($template)
            ? DocumentTemplate::where('id', (int) $template)->where('document_type', $documentType)->where('is_active', true)->first()
            : PrintSettings::defaultTemplateFor($documentType);

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)
                ->withEmployeeScope($employeeIds)
                ->withPayrollRun($payrollRunId)
                ->getDocumentData($documentType, $documentId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.PayrollPrint', 'render_template', is_numeric($documentId) ? (int) $documentId : null);

            return null;
        }
    }

    protected function printResponse(string $html, string $title, ?string $backUrl = null)
    {
        return response(DocumentPrintPage::wrap($html, $title, $backUrl, request()->boolean('autoprint')));
    }
}

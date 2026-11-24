<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Services\Sales\SalesDocumentMailer;
use App\Support\CurrencySettings;
use App\Support\LanguageSettings;
use Illuminate\Http\Request;

/**
 * زرُّ «إرسال بالبريد» في شاشات مستندات المبيعات — منطقُه مرّةً واحدة.
 *
 * ستّ شاشاتٍ تحتاج الشيء نفسَه: مستلِمٌ افتراضيُّه بريدُ العميل ويمكن
 * تغييرُه، وقالبُ طباعةٍ مختارٌ من قائمة النوع، وسطرُ ملاحظةٍ حرّ، ثم
 * نتيجةٌ تعود flash. الفروقُ بينها ثلاثةٌ فقط — النوع، والرقم،
 * والإجمالي — وهي ما يمرّره المتحكّم.
 *
 * @see \App\Services\Sales\SalesDocumentMailer الإرسالُ نفسُه
 */
trait SendsSalesDocumentEmails
{
    /**
     * نتيجةُ آخر إرسال.
     *
     * الردُّ الذي تعيده `mailSalesDocument()` هو `back()->with(...)` —
     * ورسالتُه تُومَض على الاستجابة ولا تدخل الجلسة بعد، فقراءةُ
     * `session('success')` بعده تعود فارغةً دائماً. من أراد أن يكتب سجلّ
     * عمليةٍ عند النجاح وحده يقرأ هذه.
     *
     * @var array{ok: bool, error: string|null, email: string|null}|null
     */
    protected $lastDocumentMailResult = null;

    /** هل نجح آخرُ إرسال؟ */
    protected function documentMailSucceeded(): bool
    {
        return (bool) ($this->lastDocumentMailResult['ok'] ?? false);
    }

    /**
     * يرسل المستند ويعيد ردّاً جاهزاً للشاشة.
     *
     * @param  string|array<int, string>  $documentType  نوعُه في «قوالب المستندات» — أو الأنواعُ المسموح للطلب أن يختار منها
     * @param  array{number: string, client: string, email: string|null, total: mixed, date: mixed, link: string, client_id: int|null}  $document
     * @param  string|null  $fallbackHtml  قالبُ الوحدة المدمج حين لا قالبَ مصمَّم
     * @param  string|null  $dataType  مصدرُ بيانات المستند حين يخالف نوعَ القالب
     */
    protected function mailSalesDocument(
        Request $request,
        $documentType,
        $documentId,
        array $document,
        ?string $fallbackHtml = null,
        ?string $dataType = null
    ) {
        /* النوعُ يقرّره المتحكّم. وحين يمرّر أكثرَ من واحد (شاشةُ الفاتورة
           تخدم العاديةَ والضريبية) يختار الطلبُ **من بينها وحدها**: النوعُ
           يحدّد الجدولَ الذي تقرأ منه `DocumentTemplateDataService`، فقبولُه
           مفتوحاً من المتصفّح يجعل المعرّفَ نفسَه يقرأ مستنداً من نوعٍ آخر. */
        $allowedTypes = (array) $documentType;
        $documentType = $request->filled('document_type') && in_array($request->input('document_type'), $allowedTypes, true)
            ? $request->input('document_type')
            : $allowedTypes[0];

        $request->validate([
            'email' => ['nullable', 'email'],
            'template' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $recipient = $request->filled('email') ? trim($request->input('email')) : ($document['email'] ?? null);

        $result = app(SalesDocumentMailer::class)->send(
            $documentType,
            $documentId,
            $recipient,
            [
                'client' => $document['client'] ?? '',
                'number' => (string) ($document['number'] ?? $documentId),
                'date' => LanguageSettings::formatDate($document['date'] ?? now()),
                'total' => isset($document['total']) ? CurrencySettings::format($document['total']) : '',
                'link' => $document['link'] ?? '',
                'note' => (string) $request->input('note', ''),
            ],
            $request->input('template'),
            $fallbackHtml,
            $document['client_id'] ?? null,
            $dataType
        );

        $this->lastDocumentMailResult = $result;

        if (! $result['ok']) {
            return back()->with('error', __('sales_documents.email.failed', ['reason' => $result['error']]));
        }

        return back()->with('success', __('sales_documents.email.sent', ['email' => $result['email']]));
    }
}

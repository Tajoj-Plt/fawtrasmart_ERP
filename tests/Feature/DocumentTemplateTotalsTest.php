<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use Database\Seeders\DocumentTemplateSeeder;
use Tests\TestCase;

/**
 * أسطر الإجماليات في مطبوعات المستندات مشروطة: لا يُطبع سطرٌ بصفر.
 *
 * الشرط مكتوب في نصّ كل قالبٍ مزروع (`@if(totals.shipping > 0)` …)، وهو
 * موضعٌ يسهل أن يُنسى عند إضافة قالبٍ جديد أو تعديل قائم. وسقوطه لا يظهر
 * في أي فحصٍ آخر: القالب يُصيَّر بلا خطأ، ويخرج المطبوع بسطر «الشحن:
 * 0.00» لا يلاحظه أحد إلا العميل.
 *
 * والفخّ الذي وقع فيه القالب الحراري قبل ذلك: `@if(totals.discount)`
 * وحده لا يكفي — القيمة نصٌّ مُنسَّق («0.00»)، وكل نصٍّ غير «0» و«»
 * صادقٌ في PHP. فالمقارنة `> 0` هي المقصودة.
 *
 * لا يمسّ هذا الاختبار قاعدة البيانات: القوالب تُبنى من المزروعة في
 * الذاكرة، والبيانات من `getDemoData()`.
 */
class DocumentTemplateTotalsTest extends TestCase
{
    /** الصنف الذي يحمله سطرُ كل مبلغٍ مشروط في القوالب المزروعة */
    private const ROW_MARKERS = [
        'discount-row' => 'discount',
        'shipping-row' => 'shipping',
        'tax-row' => 'tax',
    ];

    public function test_no_totals_row_is_printed_with_a_zero_amount(): void
    {
        $zeros = ['discount' => '0.00', 'shipping' => '0.00', 'tax' => '0.00', 'paid' => '0.00'];

        foreach ($this->renderEveryPreset($zeros) as [$name, $html]) {
            foreach (array_keys(self::ROW_MARKERS) as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $this->body($html),
                    "القالب «{$name}» يطبع سطر «{$marker}» وقيمته صفر."
                );
            }
        }
    }

    public function test_every_totals_row_is_printed_when_it_has_an_amount(): void
    {
        $amounts = ['discount' => '110.00', 'shipping' => '55.00', 'tax' => '1078.50', 'paid' => '3000.00'];

        foreach ($this->renderEveryPreset($amounts) as [$name, $html, $content]) {
            foreach (self::ROW_MARKERS as $marker => $key) {
                // لا يعرض كل تصميمٍ كل سطر — المقصود: ما صمَّمه يطبعه
                if (! str_contains($content, $marker)) {
                    continue;
                }

                $this->assertStringContainsString(
                    $marker,
                    $this->body($html),
                    "القالب «{$name}» لا يطبع سطر «{$key}» وله قيمة."
                );
            }
        }
    }

    public function test_a_template_renders_even_when_its_document_type_has_no_detailed_totals(): void
    {
        // السندات وقيد اليومية وكشف الرواتب لا تملأ كل مفاتيح `totals`،
        // والشرط على مفتاح غائب يرمي `Undefined array key`
        foreach (['receipt_voucher', 'payment_voucher', 'journal_entry', 'salary_slip', 'contract'] as $type) {
            foreach ($this->renderEveryPreset([], $type) as [$name, $html]) {
                $this->assertStringNotContainsString(
                    'تعذّر عرض القالب',
                    $html,
                    "القالب «{$name}» سقط عند التصيير."
                );
            }
        }
    }

    /**
     * يصيّر كل قالبٍ مزروع (أو قوالب نوعٍ واحد) بإجمالياتٍ مُعدَّلة.
     *
     * @param  array<string, string>  $totals
     * @return iterable<int, array{0: string, 1: string, 2: string}>
     */
    private function renderEveryPreset(array $totals, ?string $onlyType = null): iterable
    {
        $seeder = new DocumentTemplateSeeder();
        $renderer = new DocumentTemplateRenderer();
        $data = new DocumentTemplateDataService();

        foreach (DocumentTemplateSeeder::types() as $type => $info) {
            if ($onlyType !== null && $type !== $onlyType) {
                continue;
            }

            foreach ($seeder->getPresetsForGroup($info['group'], $info) as $preset) {
                $template = new DocumentTemplate($seeder->attributesFor($preset));

                $payload = $data->getDemoData($type);
                $payload['totals'] = array_merge($payload['totals'], $totals);

                yield [$preset['name'], $renderer->render($template, $payload), (string) $preset['content']];
            }
        }
    }

    /**
     * المطبوعة بلا ورقة أنماطها.
     *
     * المصيّر يحقن `.tax-row { display:none }` و`.discount-row { … }` في
     * `<style>` حين يُطفأ «إظهار الضريبة» أو «إظهار الخصم» — فالبحث عن
     * الصنف في المطبوعة كاملةً يجده هناك ولو لم يُطبع سطرٌ واحد.
     */
    private function body(string $html): string
    {
        $end = strrpos($html, '</style>');

        return $end === false ? $html : substr($html, $end);
    }
}

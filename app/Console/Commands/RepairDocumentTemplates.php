<?php

namespace App\Console\Commands;

use App\Models\DocumentTemplate;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Console\Command;

/**
 * يفحص قوالب المستندات المخزَّنة ويعيد المعطوب منها إلى أصله.
 *
 * **لماذا؟** كان مُحوِّل المحرّر المرئي يشترط ترتيباً بعينه لسمات الوسم،
 * والمحرّر يعيد ترتيبها — فتُحفظ شريحة الحقل وسماً خاماً بدل رمزه. تراكم
 * ذلك على بعض القوالب حتى تداخل المستند في نفسه. المُحوِّل أُصلح، لكنّ ما
 * حُفظ قبل الإصلاح باقٍ في قاعدة البيانات: هذا الأمر يجده ويعيده.
 *
 * الفحص وحده هو التصرّف الافتراضي — الإصلاح يُطلب صراحةً بـ `--fix`.
 */
class RepairDocumentTemplates extends Command
{
    protected $signature = 'document-templates:repair
                            {--fix : يعيد القوالب الجاهزة المعطوبة إلى نصّها المزروع}';

    protected $description = 'فحص قوالب المستندات وإصلاح ما أفسده مُحوِّل المحرّر المرئي';

    public function handle(DocumentTemplateSeeder $seeder): int
    {
        $broken = [];

        foreach (DocumentTemplate::orderBy('id')->get() as $template) {
            $reasons = $this->diagnose((string) $template->content);

            if ($reasons !== []) {
                $broken[] = [$template, $reasons];
            }
        }

        if ($broken === []) {
            $this->info('لا توجد قوالب معطوبة.');

            return self::SUCCESS;
        }

        $this->warn('قوالب معطوبة: '.count($broken));

        $rows = [];
        $repaired = 0;
        $skipped = [];

        foreach ($broken as [$template, $reasons]) {
            $preset = $seeder->presetFor((string) $template->document_type, (string) $template->name);
            $restorable = $template->template_type === 'ready' && $preset !== null;

            $action = match (true) {
                ! $restorable => 'قالب مخصّص — يُصلَح بفتحه وحفظه',
                ! $this->option('fix') => 'قابل للاستعادة (شغّل مع ‎--fix‎)',
                default => 'استُعيد',
            };

            if ($restorable && $this->option('fix')) {
                // الاسم والنوع مفتاح المطابقة، فلا يُكتبان — والباقي يعود كما زُرع
                $template->update($seeder->attributesFor($preset));
                $repaired++;
            } elseif (! $restorable) {
                $skipped[] = $template->id;
            }

            $rows[] = [$template->id, $template->name, implode('، ', $reasons), $action];
        }

        $this->table(['#', 'الاسم', 'العطل', 'الإجراء'], $rows);

        if ($this->option('fix')) {
            $this->info("استُعيد {$repaired} قالباً.");
        }

        if ($skipped !== []) {
            $this->warn('قوالب مخصّصة لا نسخة أصلية لها: '.implode(', ', $skipped)
                .' — افتحها في المحرّر واحفظها، فالمُحوِّل المُصلَح يعيد شرائحها إلى رموز.');
        }

        return self::SUCCESS;
    }

    /**
     * أعطال المحتوى المخزَّن، إن وُجدت.
     *
     * @return array<int, string>
     */
    private function diagnose(string $content): array
    {
        $reasons = [];

        // شريحة حقل بقيت وسماً بدل أن تعود رمزاً — أثر المُحوِّل المكسور
        if (preg_match('/<span[^>]*\bdata-field\s*=/i', $content)) {
            $reasons[] = 'شرائح حقول لم تُحوَّل';
        }

        if ($this->maxLoopDepth($content) > 1) {
            $reasons[] = 'حلقات أصناف متداخلة';
        }

        if (substr_count($content, '{{#items}}') !== substr_count($content, '{{/items}}')) {
            $reasons[] = 'حلقة أصناف غير متوازنة';
        }

        if (preg_match_all('/@if\s*\(/', $content) !== preg_match_all('/@endif\b/', $content)) {
            $reasons[] = 'شرط غير متوازن';
        }

        return $reasons;
    }

    private function maxLoopDepth(string $content): int
    {
        preg_match_all('/\{\{#items\}\}|\{\{\/items\}\}/', $content, $matches);

        $depth = 0;
        $max = 0;

        foreach ($matches[0] as $token) {
            $depth += $token === '{{#items}}' ? 1 : -1;
            $max = max($max, $depth);
        }

        return $max;
    }
}

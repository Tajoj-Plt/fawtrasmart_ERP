<?php

namespace App\Console\Commands;

use App\Models\DocumentTemplate;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Console\Command;

/**
 * يُنزل نصَّ القوالب الجاهزة المزروعة على ما هو مخزَّن في قاعدة البيانات.
 *
 * **لماذا لا يكفي إعادة زراعة الجدول؟** `DocumentTemplateSeeder` يكتب
 * السجلّ كاملاً بـ`updateOrCreate` — فيعيد معه `is_default` و`is_active`
 * و`settings` إلى قيم المصنع. ومن غيَّر القالبَ الافتراضي لنوع مستند من
 * شاشة «قوالب المستندات»، أو أطفأ «إظهار الضريبة» في قالبٍ بعينه، يجد
 * اختياره قد ضاع. وهذا الأمر لا يمسّ إلا `content` و`styles`.
 *
 * ولا يمسّ إلا `template_type = 'ready'`: القالب الذي صمّمه المستخدم
 * نصُّه هو عملُه، ولا نسخة «أصلية» له تُنزَّل عليه.
 *
 * يُشغَّل بعد أي تعديلٍ على نصوص القوالب في `DocumentTemplateSeeder` —
 * مثل جعل أسطر الإجماليات مشروطة وإضافة سطر الشحن.
 */
class RefreshDocumentTemplates extends Command
{
    protected $signature = 'document-templates:refresh
                            {--type= : نوع مستند واحد بدل كل الأنواع}
                            {--sync-key=* : مفتاح ظهور يُعاد من المزروعة (show_barcode…)}
                            {--create-missing : ينشئ القوالب المزروعة التي لا صفَّ لها بعد}
                            {--unscope-branch : يجعل القوالب الجاهزة عامّةً لكل الفروع}
                            {--skip-content : لا يمسّ نصّ القوالب القائمة — للمهامّ الإضافية وحدها}
                            {--adopt-unified : يجعل «A4 التصميم الموحّد» القالبَ الافتراضيَّ لكل نوع}
                            {--adopt-hr : يجعل «تصميم الموارد البشرية» الافتراضيَّ للعقد والقسيمة والمسير والسلفة والعمولة}
                            {--dry-run : يعرض ما سيتغيّر ولا يكتب شيئاً}';

    protected $description = 'تحديث نصّ القوالب الجاهزة من المزروع، مع حفظ الافتراضي والإعدادات';

    /**
     * ما يجوز لـ`--sync-key` أن يُعيده — مفاتيح الظهور وحدها.
     *
     * وليس `primary_color` ولا `font_size` ولا `direction`: تلك اختيارات
     * تصميمٍ يملكها المستخدم، وإعادتها من المزروعة تمحو عمله بلا أن يطلب.
     *
     * @var array<int, string>
     */
    private const SYNCABLE_KEYS = [
        'show_logo', 'show_qr', 'show_barcode',
        'show_customer', 'show_tax', 'show_discount',
    ];

    public function handle(DocumentTemplateSeeder $seeder): int
    {
        $onlyType = $this->option('type');
        $dry = (bool) $this->option('dry-run');
        $syncKeys = array_values(array_unique((array) $this->option('sync-key')));

        /*
            `--skip-content`: يُنشئ الناقص أو يُلغي حصر الفرع، ولا يمسّ نصّاً قائماً.

            **لماذا وُجدت هذه الراية؟** لأن إنزال النصّ كان يقع في كل استدعاءٍ
            للأمر مهما كانت رايته — فـ`--create-missing` و`--unscope-branch`،
            وهما موسومتان `additive` في `SystemUpdateRegistry` أي تعملان في زرّ
            «التحديث الشامل»، كانتا تُعيدان مع عملهما نصَّ كلِّ قالبٍ جاهزٍ يخالف
            المزروعة. فمن حرّر قالبه من شاشة «قوالب المستندات» يجده قد عاد إلى
            نصّ المصنع بعد تحديثٍ لم يَعِده بذلك: الأولى اسمُها «إنشاء الناقص»
            والثانية «إلغاء حصر الفرع»، وليس في الاسمين إعادةُ كتابة.

            والافتراض بلا الراية على حاله — لا يتغيّر شيء لمن يستدعي الأمر من
            سطر الأوامر قاصداً إنزال النصّ، وهو أصلُ وجود الأمر.
        */
        $skipContent = (bool) $this->option('skip-content');

        $unknownKeys = array_diff($syncKeys, self::SYNCABLE_KEYS);

        if ($unknownKeys !== []) {
            $this->error('مفاتيح غير مسموح بمزامنتها: '.implode('، ', $unknownKeys));
            $this->line('المسموح: '.implode('، ', self::SYNCABLE_KEYS));

            return self::FAILURE;
        }

        $templates = DocumentTemplate::where('template_type', 'ready')
            ->when($onlyType, fn ($query) => $query->where('document_type', $onlyType))
            ->orderBy('document_type')
            ->orderBy('id')
            ->get();

        /*
            «لا قوالب جاهزة» تُنهي الأمر — إلا حين يكون المطلوب إنشاءها.

            كان الخروج هنا غيرَ مشروط، والأمرُ في أصله يُنزل النصَّ على صفوفٍ
            قائمة فلا معنى له بلا صفوف. لكنّ `--create-missing` أُضيفت بعدُ،
            وهي تعمل على ما **ليس** في الجدول — فكان الخروجُ يمنعها في الحال
            الوحيدة التي وُجدت لأجلها: تركيبٌ جديد بجدولِ قوالبَ فارغ، فيقول
            الأمرُ «لا قوالب جاهزة مطابقة» وينصرف دون أن يُنشئ واحداً.
            و`--unscope-branch` مثلها.
        */
        $wantsCreate = (bool) $this->option('create-missing');
        $wantsUnscope = (bool) $this->option('unscope-branch');
        $wantsAdopt = (bool) $this->option('adopt-unified');
        $wantsAdoptHr = (bool) $this->option('adopt-hr');

        if ($templates->isEmpty() && ! $wantsCreate && ! $wantsUnscope && ! $wantsAdopt && ! $wantsAdoptHr) {
            $this->warn('لا قوالب جاهزة مطابقة.');

            return self::SUCCESS;
        }

        $rows = [];
        $changed = 0;
        $unknown = 0;

        foreach ($skipContent ? [] : $templates as $template) {
            $preset = $seeder->presetFor((string) $template->document_type, (string) $template->name);

            if ($preset === null) {
                // قالبٌ باسمٍ لم يعد في المزروعة — أُعيدت تسميته أو حُذف من الشفرة
                $unknown++;

                continue;
            }

            $settings = (array) ($template->settings ?? []);
            $synced = $settings;

            foreach ($syncKeys as $key) {
                if (array_key_exists($key, $preset['settings'] ?? [])) {
                    $synced[$key] = $preset['settings'][$key];
                }
            }

            $differs = $template->content !== $preset['content']
                || (string) $template->styles !== (string) $preset['styles']
                || $synced !== $settings;

            if (! $differs) {
                continue;
            }

            $changed++;
            $rows[] = [$template->id, $template->document_type, $template->name];

            if (! $dry) {
                $template->update([
                    'content' => $preset['content'],
                    'styles' => $preset['styles'],
                    'settings' => $synced,
                ]);
            }
        }

        $created = $wantsCreate ? $this->createMissing($seeder, $onlyType, $dry) : 0;
        $unscoped = $wantsUnscope ? $this->unscopeBranch($onlyType, $dry) : 0;
        $adopted = $wantsAdopt ? $this->adoptUnified($onlyType, $dry) : 0;
        $adoptedHr = $wantsAdoptHr ? $this->adoptHr($onlyType, $dry) : 0;

        if ($wantsAdoptHr) {
            $this->info($dry
                ? "أنواعٌ سيصير «تصميم الموارد البشرية» افتراضيَّها: {$adoptedHr}."
                : "صار «تصميم الموارد البشرية» القالبَ الافتراضيَّ في {$adoptedHr} نوعاً.");
        }

        if ($wantsAdopt) {
            $this->info($dry
                ? "أنواعٌ سيصير «التصميم الموحّد» افتراضيَّها: {$adopted}."
                : "صار «التصميم الموحّد» القالبَ الافتراضيَّ في {$adopted} نوعاً.");
        }

        if ($rows !== []) {
            $this->table(['#', 'النوع', 'الاسم'], $rows);
        }

        if ($created > 0) {
            $this->info($dry
                ? "قوالب ستُنشأ: {$created}."
                : "أُنشئ {$created} قالباً جديداً.");
        }

        if ($unscoped > 0) {
            $this->info($dry
                ? "قوالب ستُحرَّر من قيد الفرع: {$unscoped}."
                : "حُرِّر {$unscoped} قالباً من قيد الفرع — صارت تظهر لكل الفروع.");
        }

        if ($skipContent) {
            // يُقال صراحةً: صمتٌ هنا يُقرأ «لم يتغيّر نصٌّ لأن لا فرق»، والحقيقة
            // أن النصَّ لم يُفحَص أصلاً — وبينهما فرقٌ لمن يقرأ المخرَج
            $this->line("نصوص القوالب لم تُمَسّ ({$templates->count()} قالباً جاهزاً) — `--skip-content`.");
        } else {
            $this->info($dry
                ? "قوالب ستتحدَّث: {$changed} (من {$templates->count()})."
                : "حُدِّث {$changed} قالباً (من {$templates->count()}).");
        }

        if ($unknown > 0) {
            $this->warn("قوالب باسمٍ لا مقابل له في المزروعة، تُركت كما هي: {$unknown}.");
        }

        return self::SUCCESS;
    }

    /**
     * إنشاءُ القوالب المزروعة التي لا صفَّ لها في القاعدة بعد.
     *
     * الأمرُ في أصله **لا يُنشئ شيئاً**: يُنزل النصَّ على صفوفٍ قائمة. وإذا
     * أُضيفت مزروعةٌ جديدة إلى `DocumentTemplateSeeder` لم يكن لبلوغها إلا
     * `db:seed` — وهو يكتب الصفوف كاملةً بـ`updateOrCreate` فيُعيد
     * `is_default` و`is_active` و`settings` في المئتين القائمة إلى قيم
     * المصنع، فيضيع القالبُ الافتراضي الذي اختاره المستخدم لكلّ نوع.
     *
     * فالإنشاءُ هنا بالاسم والنوع، ولا يلمس صفّاً موجوداً بحال — ولا يجعل
     * الجديدَ افتراضياً ولو قال ذلك في المزروعة: الافتراضيُّ اختيارُ
     * المستخدم من شاشة «قوالب المستندات»، ولا يُنتزع منه بأمرٍ في الطرفية.
     */
    private function createMissing(DocumentTemplateSeeder $seeder, ?string $onlyType, bool $dry): int
    {
        $created = 0;
        $rows = [];

        foreach (DocumentTemplateSeeder::types() as $docType => $info) {
            if ($onlyType && $docType !== $onlyType) {
                continue;
            }

            foreach ($seeder->presetsFor($docType, $info) as $preset) {
                $exists = DocumentTemplate::where('document_type', $docType)
                    ->where('name', $preset['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $created++;
                $rows[] = [$docType, $preset['name']];

                if (! $dry) {
                    /* `withoutEvents`: خطّاف `creating` في `DocumentTemplate`
                       يختم `branch_id` بفرع المستخدم متى كان فارغاً
                       (`$model->branch_id ?? Auth::user()->branch_id`).
                       والقالبُ المزروع **عامٌّ لا يملكه فرع** — كذا يقول
                       `attributesFor()` صراحةً — لكنّ الأمر يُشغَّل من شاشة
                       «تحديث النظام» بجلسةِ مستخدمٍ له فرع، فيخرج القالبُ
                       مختوماً بفرعه ولا يراه أحدٌ سواه. وهذا بعينه ما وقع
                       للمئتين المزروعة سابقاً: كلُّها على فرعٍ واحد.
                       انظر `PrintSettings::templatesFor()`. */
                    DocumentTemplate::withoutEvents(function () use ($preset, $docType, $seeder) {
                        DocumentTemplate::create(array_merge(
                            ['name' => $preset['name'], 'document_type' => $docType],
                            $seeder->attributesFor($preset),
                            ['is_default' => false]
                        ));
                    });
                }
            }
        }

        if ($rows !== []) {
            $this->table(['النوع', 'قالبٌ جديد'], $rows);
        }

        return $created;
    }

    /**
     * «A4 التصميم الموحّد» افتراضيٌّ لكلّ نوعٍ له هذا القالب.
     *
     * **يغيّر اختيارَ المستخدم عمداً** — ولذلك رايةٌ مستقلّة لا تدخل
     * «التحديث الشامل»: `--create-missing` يُنشئ القوالب ولا يجعلها افتراضية.
     * وافتراضيٌّ واحد لكلّ نوع: `PrintSettings::defaultTemplateFor()` تأخذ أوّلَ
     * افتراضيّ، فبقاءُ القديم افتراضياً معه يجعل الطبعَ رهنَ ترتيب الأسماء.
     * ونوعٌ لم يُنشأ قالبُه الموحّد بعد يُترك كما هو.
     */
    private function adoptUnified(?string $onlyType, bool $dry): int
    {
        $suffix = ' - A4 '.DocumentTemplateSeeder::UNIFIED_SUFFIX;

        $targets = DocumentTemplate::where('template_type', 'ready')
            ->where('paper_size', 'A4')
            ->where('name', 'like', '%'.$suffix)
            ->when($onlyType, fn ($q) => $q->where('document_type', $onlyType))
            ->get();

        $adopted = 0;

        foreach ($targets->groupBy('document_type') as $docType => $group) {
            /* أنواعُ الموارد البشرية لها «تصميم الموارد البشرية» — لا يُعاد
               «الموحّد» افتراضيَّها متى وُجد صفُّه (`--adopt-hr` يتولّاها). */
            if ($this->hrTarget((string) $docType) !== null) {
                continue;
            }

            $target = $group->first();

            $alreadyAlone = $target->is_default
                && ! DocumentTemplate::where('document_type', $docType)
                    ->where('id', '!=', $target->id)
                    ->where('is_default', true)
                    ->exists();

            if ($alreadyAlone) {
                continue;
            }

            $adopted++;

            if (! $dry) {
                DocumentTemplate::where('document_type', $docType)
                    ->where('id', '!=', $target->id)
                    ->update(['is_default' => false]);

                DocumentTemplate::whereKey($target->id)->update(['is_default' => true, 'is_active' => true]);
            }
        }

        return $adopted;
    }

    /** قالبُ «تصميم الموارد البشرية» A4 لنوعٍ — أو `null` إن لم يُنشأ */
    private function hrTarget(string $docType): ?DocumentTemplate
    {
        if (! in_array($docType, DocumentTemplateSeeder::HR_TYPES, true)) {
            return null;
        }

        return DocumentTemplate::where('template_type', 'ready')
            ->where('document_type', $docType)
            ->where('paper_size', 'A4')
            ->where('name', 'like', '% - '.DocumentTemplateSeeder::HR_SUFFIX.' A4%')
            ->orderBy('id')
            ->first();
    }

    /**
     * «تصميم الموارد البشرية» افتراضيٌّ لأنواع الموارد البشرية وحدها.
     *
     * لا يمسّ نوعاً افتراضيُّه قالبٌ صمّمه المستخدم (`template_type` غيرُ
     * `ready`): ذلك اختيارٌ مقصود. ولا يغيّر نصَّ قالبٍ ولا إعداداته — الافتراضَ وحده.
     */
    private function adoptHr(?string $onlyType, bool $dry): int
    {
        $adopted = 0;
        $rows = [];

        foreach (DocumentTemplateSeeder::HR_TYPES as $docType) {
            if ($onlyType && $docType !== $onlyType) {
                continue;
            }

            $target = $this->hrTarget($docType);

            if ($target === null) {
                $rows[] = [$docType, '—', 'لم يُنشأ بعد (--create-missing)'];

                continue;
            }

            $custom = DocumentTemplate::where('document_type', $docType)
                ->where('is_default', true)
                ->where('template_type', '!=', 'ready')
                ->first();

            if ($custom) {
                $rows[] = [$docType, $custom->name, 'تُرك: الافتراضيُّ قالبٌ صمّمه المستخدم'];

                continue;
            }

            $alreadyAlone = $target->is_default
                && ! DocumentTemplate::where('document_type', $docType)
                    ->where('id', '!=', $target->id)
                    ->where('is_default', true)
                    ->exists();

            if ($alreadyAlone) {
                $rows[] = [$docType, $target->name, 'افتراضيٌّ أصلاً'];

                continue;
            }

            $adopted++;
            $rows[] = [$docType, $target->name, $dry ? 'سيصير افتراضياً' : 'صار افتراضياً'];

            if (! $dry) {
                DocumentTemplate::where('document_type', $docType)
                    ->where('id', '!=', $target->id)
                    ->update(['is_default' => false]);

                DocumentTemplate::whereKey($target->id)->update(['is_default' => true, 'is_active' => true]);
            }
        }

        if ($rows !== []) {
            $this->table(['النوع', 'القالب', 'الحال'], $rows);
        }

        return $adopted;
    }

    /**
     * تحرير القوالب الجاهزة من قيد الفرع.
     *
     * **العطب:** `PrintSettings::templatesFor()` لا تُظهر إلّا قالباً
     * `branch_id` فارغٌ أو مساوٍ لفرع المستخدم. وخطّاف `creating` في
     * `DocumentTemplate` يختم فرعَ من زرعها. فإن زُرعت القوالبُ مرّةً من
     * شاشةٍ بجلسةِ مستخدمٍ في فرع، خرجت كلُّها مختومةً بذلك الفرع — فتَخلو
     * شاشةُ «قوالب المستندات» عند كلّ من سواه، ويعود الطبعُ إلى القالب
     * المدمج بلا أن يفهم أحدٌ لماذا.
     *
     * والقوالب `ready` عامّةٌ بنصّ `DocumentTemplateSeeder::attributesFor()`
     * (`branch_id => null`)، فهذا ردٌّ إلى الأصل لا توسيعُ صلاحية. وقالبُ
     * المستخدم المصمَّم (`template_type` غيرُ `ready`) لا يُمَسّ: قد يكون
     * صمّمه لفرعه عمداً.
     */
    private function unscopeBranch(?string $onlyType, bool $dry): int
    {
        $query = DocumentTemplate::where('template_type', 'ready')
            ->whereNotNull('branch_id')
            ->when($onlyType, fn ($q) => $q->where('document_type', $onlyType));

        $count = (clone $query)->count();

        if ($count > 0 && ! $dry) {
            // تحديثٌ بالاستعلام: خطّاف `updating` لا يُعيد الختم، لكنّ
            // المرورَ صفّاً صفّاً على مئتي قالبٍ استعلامان لكلٍّ بلا داعٍ
            $query->update(['branch_id' => null]);
        }

        return $count;
    }
}

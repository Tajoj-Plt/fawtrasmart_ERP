<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

/**
 * تصدير بيانات مختارة إلى CSV أو Excel أو أرشيف مضغوط.
 *
 * هذا ليس نسخاً احتياطياً وإن جاور شاشته: النسخة الاحتياطية أرشيف SQL
 * يُستَرجَع منه النظام، والتصدير ملفّ بيانات يُقرأ في برنامج جداول. الخلط
 * بينهما خطر عملي — من يظنّ ملف Excel نسخةً احتياطية يكتشف عند العطل أنه
 * لا يملك ما يعيد به النظام. لذلك يخرج التصدير من هنا لا من `BackupService`
 * ولا يُسجَّل صفّاً في `backups`.
 *
 * كل اسم يصل من الواجهة — جدولاً كان أو عموداً — يُطابَق على ما هو موجود
 * فعلاً عبر `BackupTableRegistry` قبل أن يقترب من جملة SQL. القيم وحدها
 * تُمرَّر معاملاتٍ مربوطة.
 */
class DataExportService
{
    public const FORMATS = [
        'csv' => ['label' => 'CSV', 'icon' => 'file-text', 'hint' => 'يُفتَح في Excel وGoogle Sheets — الأخفّ والأسرع. جدول واحد = ملف، وأكثر = أرشيف مضغوط.'],
        'xlsx' => ['label' => 'Excel (xlsx)', 'icon' => 'grid', 'hint' => 'مصنّف واحد بورقة لكل جدول. الأثقل — لا يصلح لملايين الصفوف.'],
        'pdf' => ['label' => 'PDF للطباعة', 'icon' => 'printer', 'hint' => 'للقراءة والطباعة والإرسال لا للاستيراد. يناسب أعمدة قليلة — حدّه '.self::PDF_MAX_ROWS.' صفّ لكل جدول.'],
        'zip' => ['label' => 'أرشيف مضغوط (ZIP)', 'icon' => 'archive', 'hint' => 'ملفّ CSV لكل جدول داخل أرشيف واحد.'],
    ];

    /**
     * معاملات الترشيح المسموحة.
     *
     * قائمة مغلقة: المعامل يدخل جملة SQL نصّاً ولا يُربَط كمعامل.
     */
    public const OPERATORS = [
        '=' => ['label' => 'يساوي', 'value' => true],
        '!=' => ['label' => 'لا يساوي', 'value' => true],
        '>' => ['label' => 'أكبر من', 'value' => true],
        '>=' => ['label' => 'أكبر من أو يساوي', 'value' => true],
        '<' => ['label' => 'أصغر من', 'value' => true],
        '<=' => ['label' => 'أصغر من أو يساوي', 'value' => true],
        'like' => ['label' => 'يحتوي', 'value' => true],
        'not like' => ['label' => 'لا يحتوي', 'value' => true],
        'in' => ['label' => 'ضمن قائمة (بفواصل)', 'value' => true],
        'is null' => ['label' => 'فارغ', 'value' => false],
        'is not null' => ['label' => 'غير فارغ', 'value' => false],
    ];

    /**
     * الصيغ والمعاملات بتسمياتها المترجَمة.
     *
     * الثابتان أعلاه يبقيان مرجع المفاتيح المسموحة — أي الحاجز الأمني —
     * وقيمهما العربية احتياط. ما تعرضه الشاشة يأتي من هاتين الدالّتين.
     */
    public static function formats(): array
    {
        $out = [];

        foreach (self::FORMATS as $key => $meta) {
            $out[$key] = [
                'label' => self::tr('formats.'.$key, $meta['label']),
                'icon' => $meta['icon'],
                'hint' => self::tr('formats.'.$key.'_hint', $meta['hint'], ['max' => self::PDF_MAX_ROWS]),
            ];
        }

        return $out;
    }

    public static function operators(): array
    {
        // مفاتيح المعاملات رموز (`>=`, `is null`) لا تصلح مفاتيحَ ترجمة
        $keys = [
            '=' => 'eq', '!=' => 'neq', '>' => 'gt', '>=' => 'gte',
            '<' => 'lt', '<=' => 'lte', 'like' => 'like', 'not like' => 'not_like',
            'in' => 'in', 'is null' => 'is_null', 'is not null' => 'is_not_null',
        ];

        $out = [];

        foreach (self::OPERATORS as $operator => $meta) {
            $out[$operator] = [
                'label' => self::tr('operators.'.($keys[$operator] ?? $operator), $meta['label']),
                'value' => $meta['value'],
            ];
        }

        return $out;
    }

    protected static function tr(string $key, string $fallback, array $replace = []): string
    {
        $full = 'settings.export.'.$key;
        $translated = __($full, $replace);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    /** سقف الصفوف لكل جدول — يمنع تصديراً يُفرغ الذاكرة أو يعلّق الطلب. */
    public const MAX_ROWS = 200000;

    /**
     * سقف أضيق لـ PDF.
     *
     * ملفّ PDF يُصفّ صفحاتٍ ويضمّن خطّاً ويرسم كل خليّة، فتكلفته لكل صفّ
     * أضعاف تكلفة سطر في CSV. وهو للقراءة والطباعة أصلاً — لا معنى لمستند
     * من ألفَي صفحة، ومحاولةُ توليده تعلّق الطلب وتُفرغ الذاكرة.
     */
    public const PDF_MAX_ROWS = 5000;

    /** حجم الدفعة عند القراءة — الصفوف تُكتَب أولاً بأول لا تُجمَع في الذاكرة. */
    private const CHUNK = 2000;

    public function __construct(private BackupTableRegistry $registry) {}

    /**
     * تنفيذ التصدير وإرجاع مسار الملفّ المؤقّت الناتج.
     *
     * @param  array<int, array{table:string,columns?:array<int,string>,filters?:array<int,array<string,mixed>>,limit?:int}>  $selections
     * @return array{path:string,name:string,rows:int,tables:int}
     *
     * @throws \RuntimeException
     */
    public function export(array $selections, string $format, ?int $userId = null): array
    {
        $format = array_key_exists($format, self::FORMATS) ? $format : 'csv';
        $plans = $this->plan($selections);

        if ($plans === []) {
            throw new \RuntimeException(__('settings.export.errors.no_table'));
        }

        // سقف PDF يُطبَّق هنا لا في `plan()`: الخطة لا تعرف الصيغة، والحدّ
        // الذي كتبه المستخدم صالح لبقية الصيغ كما هو
        if ($format === 'pdf') {
            $plans = array_map(function (array $plan) {
                $plan['limit'] = min($plan['limit'], self::PDF_MAX_ROWS);

                return $plan;
            }, $plans);
        }

        // تنزيل يُلغى في منتصفه يمنع تنفيذ تنظيف ما بعد الإرسال، فيبقى
        // مجلَّده. كنسٌ للقديم عند كل تصدير أرخص من مهمة مجدولة لأجله
        $this->sweepStaleWorkDirs();

        $workDir = storage_path('app/export-temp/'.bin2hex(random_bytes(8)));

        if (! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new \RuntimeException(__('settings.export.errors.temp_dir'));
        }

        try {
            $result = match ($format) {
                'xlsx' => $this->writeWorkbook($plans, $workDir),
                'pdf' => $this->writePdf($plans, $workDir),
                'zip' => $this->writeArchive($plans, $workDir),
                // جدول واحد بصيغة CSV يخرج ملفّاً مباشراً؛ أكثر من جدول لا
                // يمكن جمعه في ملفّ CSV واحد فيُضغط
                default => count($plans) === 1
                    ? $this->writeSingleCsv($plans[0], $workDir)
                    : $this->writeArchive($plans, $workDir),
            };

            // تصدير بيانات خام حدث يستحقّ الأثر: من صدّر، وماذا، وكم صفّاً
            Log::info('تصدير بيانات', [
                'user_id' => $userId,
                'format' => $format,
                'tables' => array_column($plans, 'table'),
                'rows' => $result['rows'],
            ]);

            return $result + ['tables' => count($plans)];
        } catch (\Throwable $e) {
            $this->removeDirectory($workDir);

            throw $e;
        }
    }

    /**
     * تحويل اختيار الواجهة إلى خطة منقّاة: أسماء موجودة فعلاً ومعاملات مسموحة.
     *
     * @param  array<int, array<string, mixed>>  $selections
     * @return array<int, array{table:string,columns:array<int,string>,filters:array<int,array<string,mixed>>,limit:int}>
     */
    public function plan(array $selections): array
    {
        $known = $this->registry->names();
        $plans = [];

        foreach ($selections as $selection) {
            // الواجهة ترسل مفتاحاً معتَّماً لا اسم جدول. عدم قبول اسم
            // الجدول مباشرةً هو ما يجعل التعتيم حقيقياً لا تجميلياً
            $table = ExportCatalog::tableForKey(
                is_string($selection['key'] ?? null) ? trim($selection['key']) : null
            );

            // شرطان لا واحد: مفتاح صالح، والجدول موجود في القاعدة فعلاً
            if ($table === null || ! in_array($table, $known, true)) {
                continue;
            }

            $columns = $this->allowedColumns($table, $selection['columns'] ?? null);

            if ($columns === []) {
                continue;
            }

            $plans[] = [
                'table' => $table,
                'columns' => $columns,
                'labels' => $this->labelsFor($table, $columns),
                'filters' => $this->cleanFilters($table, $selection['filters'] ?? []),
                'limit' => max(1, min(self::MAX_ROWS, (int) ($selection['limit'] ?? self::MAX_ROWS))),
            ];
        }

        return $plans;
    }

    /**
     * أعمدة الكيان المسموح تصديرها.
     *
     * التقاطع ثلاثيّ: ما هو موجود في الجدول فعلاً، وما له اسم عربي في
     * الفهرس، وما اختاره المستخدم. عمود بلا اسم عربي لا يخرج — وهذا ما
     * يمنع تسرّب بنية القاعدة إلى ترويسة الملفّ.
     *
     * @param  array<int, mixed>|null  $requested
     * @return array<int, string>
     */
    protected function allowedColumns(string $table, ?array $requested): array
    {
        $exportable = array_values(array_intersect(
            $this->registry->columnNames($table),
            array_keys(ExportCatalog::columnsFor($table))
        ));

        if (empty($requested)) {
            return $exportable; // بلا اختيار = كل المسموح
        }

        return array_values(array_filter(
            array_map(fn ($column) => is_string($column) ? trim($column) : null, $requested),
            fn ($column) => $column !== null && in_array($column, $exportable, true)
        ));
    }

    /**
     * ترويسة الملفّ بالعربية.
     *
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    protected function labelsFor(string $table, array $columns): array
    {
        return array_map(
            fn ($column) => ExportCatalog::columnLabel($table, $column) ?? $column,
            $columns
        );
    }

    /**
     * تنقية شروط الترشيح.
     *
     * @param  array<int, array<string, mixed>>  $filters
     * @return array<int, array{column:string,operator:string,value:mixed}>
     */
    protected function cleanFilters(string $table, array $filters): array
    {
        // الترشيح محصور بالأعمدة المفهرَسة أيضاً: شرطٌ على عمود مخفيّ
        // يكشف وجوده وقيمه بالاستنتاج من النتائج
        $columns = $this->allowedColumns($table, null);
        $clean = [];

        foreach ($filters as $filter) {
            $column = is_string($filter['column'] ?? null) ? trim($filter['column']) : '';
            $operator = is_string($filter['operator'] ?? null) ? strtolower(trim($filter['operator'])) : '';

            if (! in_array($column, $columns, true) || ! array_key_exists($operator, self::OPERATORS)) {
                continue;
            }

            $needsValue = self::OPERATORS[$operator]['value'];
            $value = $filter['value'] ?? null;

            if ($needsValue && ($value === null || $value === '')) {
                continue;
            }

            $clean[] = [
                'column' => $column,
                'operator' => $operator,
                'value' => $needsValue ? $value : null,
            ];
        }

        return $clean;
    }

    /**
     * المرور على صفوف الخطة دفعةً دفعة مع احترام حدّ الصفوف.
     *
     * لا تُستعمل `chunk()`: هي تفرض `limit`/`offset` خاصّين بها على
     * الاستعلام، فتمحو الحدّ الذي وضعه المستخدم بلا إشعار — «صدّر 100 صفّ»
     * تُخرج الجدول كلّه. الترقيم هنا يدويّ ويأخذ في كل دفعة أقلَّ من
     * (حجم الدفعة، ما تبقّى من الحدّ).
     *
     * @param  array{table:string,columns:array<int,string>,filters:array<int,array<string,mixed>>,limit:int}  $plan
     * @param  callable(array<string,mixed>):void  $callback
     * @return int عدد الصفوف التي مُرَّ عليها
     */
    protected function eachRow(array $plan, callable $callback): int
    {
        $seen = 0;
        $offset = 0;

        while ($seen < $plan['limit']) {
            $take = min(self::CHUNK, $plan['limit'] - $seen);

            // ترتيب ثابت شرطٌ لصحّة الترقيم بالإزاحة، وإلا تكرّرت صفوف
            // وسقطت أخرى بين الدفعات
            $rows = $this->query($plan)
                ->orderBy($plan['columns'][0])
                ->offset($offset)
                ->limit($take)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $callback((array) $row);
                $seen++;
            }

            if ($rows->count() < $take) {
                break; // انتهت الصفوف قبل بلوغ الحدّ
            }

            $offset += $take;
        }

        return $seen;
    }

    /**
     * بناء الاستعلام — الأسماء منقّاة سلفاً والقيم مربوطة.
     *
     * بلا `limit`: يضعه `eachRow` لكل دفعة على حدة.
     *
     * @param  array{table:string,columns:array<int,string>,filters:array<int,array<string,mixed>>,limit:int}  $plan
     */
    protected function query(array $plan): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($plan['table'])->select($plan['columns']);

        foreach ($plan['filters'] as $filter) {
            match ($filter['operator']) {
                'is null' => $query->whereNull($filter['column']),
                'is not null' => $query->whereNotNull($filter['column']),
                'in' => $query->whereIn($filter['column'], array_values(array_filter(
                    array_map('trim', explode(',', (string) $filter['value'])),
                    fn ($part) => $part !== ''
                ))),
                'like', 'not like' => $query->where(
                    $filter['column'],
                    $filter['operator'],
                    '%'.$filter['value'].'%'
                ),
                default => $query->where($filter['column'], $filter['operator'], $filter['value']),
            };
        }

        return $query->limit($plan['limit']);
    }

    // ==================== الكتّاب ====================

    /**
     * @param  array{table:string,columns:array<int,string>,filters:array<int,array<string,mixed>>,limit:int}  $plan
     * @return array{path:string,name:string,rows:int}
     */
    protected function writeSingleCsv(array $plan, string $workDir): array
    {
        $name = ExportCatalog::labelFor($plan['table']).'-'.date('Y-m-d-His').'.csv';
        $path = $workDir.DIRECTORY_SEPARATOR.$name;
        $rows = $this->streamCsv($plan, $path);

        return ['path' => $path, 'name' => $name, 'rows' => $rows];
    }

    /**
     * @param  array<int, array<string, mixed>>  $plans
     * @return array{path:string,name:string,rows:int}
     */
    protected function writeArchive(array $plans, string $workDir): array
    {
        $name = 'export-'.date('Y-m-d-His').'.zip';
        $path = $workDir.DIRECTORY_SEPARATOR.$name;

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException(__('settings.export.errors.zip_create'));
        }

        $total = 0;
        $files = [];

        foreach ($plans as $plan) {
            $csvPath = $workDir.DIRECTORY_SEPARATOR.ExportCatalog::labelFor($plan['table']).'.csv';
            $total += $this->streamCsv($plan, $csvPath);
            $files[] = $csvPath;
            $zip->addFile($csvPath, ExportCatalog::labelFor($plan['table']).'.csv');
        }

        // `close()` هو ما يكتب الأرشيف فعلاً — الملفات المصدرية يجب أن
        // تبقى موجودة حتى تنتهي، فلا تُحذف قبلها
        if (! $zip->close()) {
            throw new \RuntimeException(__('settings.export.errors.zip_close'));
        }

        foreach ($files as $file) {
            @unlink($file);
        }

        return ['path' => $path, 'name' => $name, 'rows' => $total];
    }

    /**
     * مصنّف Excel بورقة لكل جدول.
     *
     * @param  array<int, array<string, mixed>>  $plans
     * @return array{path:string,name:string,rows:int}
     */
    protected function writeWorkbook(array $plans, string $workDir): array
    {
        $name = 'export-'.date('Y-m-d-His').'.xlsx';
        $path = $workDir.DIRECTORY_SEPARATOR.$name;

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $total = 0;
        $usedTitles = [];

        foreach ($plans as $plan) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->sheetTitle(ExportCatalog::labelFor($plan['table']), $usedTitles));
            $sheet->setRightToLeft(true);

            $sheet->fromArray($plan['labels'], null, 'A1');
            $rowNumber = 2;
            $buffer = [];

            $total += $this->eachRow($plan, function (array $record) use ($sheet, $plan, &$rowNumber, &$buffer) {
                $buffer[] = array_map(
                    fn ($column) => $this->stringify($record[$column] ?? null),
                    $plan['columns']
                );

                // الكتابة على دفعات لا صفّاً صفّاً: `fromArray` لكل صفّ
                // تُبطئ المصنّف الكبير بشدّة
                if (count($buffer) >= self::CHUNK) {
                    $sheet->fromArray($buffer, null, 'A'.$rowNumber, true);
                    $rowNumber += count($buffer);
                    $buffer = [];
                }
            });

            if ($buffer !== []) {
                $sheet->fromArray($buffer, null, 'A'.$rowNumber, true);
            }

            foreach (range(1, count($plan['columns'])) as $index) {
                $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
            }
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return ['path' => $path, 'name' => $name, 'rows' => $total];
    }

    /**
     * مستند PDF للطباعة — قسم لكل جدول.
     *
     * TCPDF لا dompdf: الأخير مثبَّت في المشروع لكنه لا يضمّن خطّاً عربياً
     * ولا يصل الحروف، فينتج صفحة فارغة من العربية (جُرِّب: 1.3 ك.ب بلا
     * `FontFile` وبخطّ `Times-Roman` الذي لا حرف عربياً فيه). أمّا TCPDF
     * فيضمّن `dejavusans` ويصل الحروف ويقلب الاتجاه أصلاً.
     *
     * @param  array<int, array<string, mixed>>  $plans
     * @return array{path:string,name:string,rows:int}
     */
    protected function writePdf(array $plans, string $workDir): array
    {
        $name = 'export-'.date('Y-m-d-His').'.pdf';
        $path = $workDir.DIRECTORY_SEPARATOR.$name;

        // عرضية دائماً: الجداول أعرض من الصفحة الطولية بكثير.
        // التذييل الافتراضي للحزمة يطبع سطر «Powered by TCPDF» على كل
        // صفحة — يُستبدَل برقم صفحة وتاريخ، وهما ما يفيد في ورقة مطبوعة
        $pdf = new class('L', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF
        {
            public string $stamp = '';

            /**
             * إسكات رابط «Powered by TCPDF» الذي تضيفه الحزمة في آخر المستند.
             *
             * الخاصيّة محميّة فلا سبيل إلى إطفائها من خارج الصنف — وهذا
             * سبب وجود هذا الصنف الفرعي إلى جانب التذييل المخصّص.
             */
            public function hideVendorLink(): void
            {
                $this->tcpdflink = false;
            }

            public function Footer()
            {
                $this->SetY(-10);
                $this->SetFont('dejavusans', '', 7);
                $paging = __('settings.export.pdf_page', [
                    'page' => $this->getAliasNumPage(),
                    'total' => $this->getAliasNbPages(),
                ]);

                $this->Cell(0, 6, $paging.'   •   '.$this->stamp, 0, 0, 'C');
            }
        };

        $pdf->stamp = __('settings.export.pdf_stamp', ['date' => date('Y-m-d H:i')]);
        $pdf->hideVendorLink();
        $pdf->setRTL(true);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(true);
        $pdf->setFooterFont(['dejavusans', '', 7]);
        $pdf->SetMargins(8, 10, 8);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetCreator(__('settings.export.pdf_creator'));
        $pdf->SetTitle(__('settings.export.pdf_title'));

        $total = 0;

        foreach ($plans as $planIndex => $plan) {
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->writeHTML(
                '<div>'.e(ExportCatalog::labelFor($plan['table'])).'</div>',
                true, false, true, false, ''
            );

            $pdf->SetFont('dejavusans', '', 7);

            $header = '<tr>'.implode('', array_map(
                fn ($label) => '<th align="center"><b>'.e($label).'</b></th>',
                $plan['labels']
            )).'</tr>';

            $buffer = [];
            $tableRows = 0;

            $flush = function () use ($pdf, $header, &$buffer, &$tableRows) {
                if ($buffer === []) {
                    return;
                }

                // ترويسة مكرَّرة مع كل شريحة: الجدول يمتدّ صفحات، وقارئ
                // الصفحة الخامسة يحتاج أسماء الأعمدة كما يحتاجها قارئ الأولى
                $pdf->writeHTML(
                    '<table border="0.4" cellpadding="2"><thead>'.$header.'</thead><tbody>'
                    .implode('', $buffer).'</tbody></table>',
                    true, false, true, false, ''
                );

                $buffer = [];
                $tableRows = 0;
            };

            $total += $this->eachRow($plan, function (array $record) use ($plan, &$buffer, &$tableRows, $flush) {
                $buffer[] = '<tr>'.implode('', array_map(
                    fn ($column) => '<td>'.e(mb_substr($this->stringify($record[$column] ?? null), 0, 120)).'</td>',
                    $plan['columns']
                )).'</tr>';

                // الكتابة على شرائح: `writeHTML` لجدول من آلاف الصفوف دفعةً
                // واحدة تلتهم الذاكرة
                if (++$tableRows >= 200) {
                    $flush();
                }
            });

            $flush();

            unset($planIndex);
        }

        $pdf->Output($path, 'F');

        return ['path' => $path, 'name' => $name, 'rows' => $total];
    }

    /**
     * كتابة جدول واحد إلى ملفّ CSV بالدفعات.
     *
     * @param  array{table:string,columns:array<int,string>,filters:array<int,array<string,mixed>>,limit:int}  $plan
     * @return int عدد الصفوف المكتوبة
     */
    protected function streamCsv(array $plan, string $path): int
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException(__('settings.export.errors.temp_write', ['file' => basename($path)]));
        }

        // علامة ترتيب البايتات: بدونها يقرأ Excel على ويندوز الملفَّ
        // بترميز النظام فتظهر العربية حروفاً مبعثرة
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $plan['labels']);

        try {
            $rows = $this->eachRow($plan, function (array $record) use ($handle, $plan) {
                fputcsv($handle, array_map(
                    fn ($column) => $this->stringify($record[$column] ?? null),
                    $plan['columns']
                ));
            });
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    // ==================== أدوات ====================

    /**
     * تحويل قيمة إلى نصّ صالح لخليّة.
     *
     * الأعمدة الثنائية لا تُكتَب: بايتاتها تُفسد الملفّ ولا معنى لها في
     * جدول بيانات.
     */
    protected function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = (string) $value;

        return preg_match('//u', $value) ? $value : __('settings.export.binary_data');
    }

    /**
     * عنوان ورقة صالح: Excel يمنع بعض المحارف ويحدّ الطول بـ31، ويرفض التكرار.
     *
     * @param  array<int, string>  $used
     */
    protected function sheetTitle(string $table, array &$used): string
    {
        $title = mb_substr((string) preg_replace('/[\\\\\/\?\*\[\]:]/u', '_', $table), 0, 31);
        $base = $title;
        $suffix = 1;

        while (in_array($title, $used, true)) {
            $tail = '~'.(++$suffix);
            $title = mb_substr($base, 0, 31 - mb_strlen($tail)).$tail;
        }

        $used[] = $title;

        return $title;
    }

    /**
     * حذف مجلَّدات التصدير المؤقّتة المتروكة.
     *
     * ساعة كاملة قبل الحذف: تصدير جدول ضخم قد يستغرق دقائق طويلة، وحذف
     * مجلَّد تصدير جارٍ يُفسد الملفّ تحت يد صاحبه.
     */
    protected function sweepStaleWorkDirs(int $olderThanSeconds = 3600): void
    {
        try {
            foreach (glob(storage_path('app/export-temp/*'), GLOB_ONLYDIR) ?: [] as $dir) {
                if (time() - (int) @filemtime($dir) > $olderThanSeconds) {
                    $this->removeDirectory($dir);
                }
            }
        } catch (\Throwable $e) {
            // التنظيف خدمة جانبية — فشلُه لا يمنع التصدير
            Log::warning('تعذّر كنس مجلَّدات التصدير المؤقّتة', ['error' => $e->getMessage()]);
        }
    }

    /** حذف المجلَّد المؤقّت بعد إرسال الملفّ. */
    public function removeDirectory(string $dir): void
    {
        try {
            if (! is_dir($dir)) {
                return;
            }

            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                is_dir($file) ? $this->removeDirectory($file) : @unlink($file);
            }

            @rmdir($dir);
        } catch (\Throwable $e) {
            Log::warning('تعذّر حذف مجلَّد التصدير المؤقّت', ['dir' => $dir, 'error' => $e->getMessage()]);
        }
    }
}

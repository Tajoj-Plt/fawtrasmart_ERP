<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Backup\BackupTableRegistry;
use App\Services\Backup\DataExportService;
use App\Services\Backup\ExportCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * تصدير بيانات مختارة إلى CSV أو Excel أو ZIP.
 *
 * صلاحية مستقلّة `settings.backup.export`: التصدير استخراج بيانات خام —
 * أرقام العملاء وأرصدتهم ورواتب الموظفين — فهو أقرب إلى تنزيل نسخة
 * احتياطية منه إلى عرض تقرير، ولا يجوز أن يملكه من يملك مجرّد الاطّلاع.
 */
class DataExportController extends Controller
{
    public function __construct(
        protected BackupTableRegistry $registry,
        protected DataExportService $exporter,
    ) {
        $this->middleware('permission:settings.backup.export');
    }

    public function index()
    {
        return view('sitting::backup.export', [
            'groups' => ExportCatalog::groupedAvailable(),
            'formats' => DataExportService::formats(),
            'operators' => DataExportService::operators(),
            'maxRows' => DataExportService::MAX_ROWS,
        ]);
    }

    /**
     * أعمدة جدول واحد — تُطلَب من الواجهة عند اختيار الجدول.
     *
     * تُحمَّل عند الطلب لا مع الصفحة: القاعدة فيها مئات الجداول، وتصيير
     * أعمدتها كلّها مقدَّماً يعني صفحة بآلاف الحقول المخفيّة.
     */
    public function columns(string $key)
    {
        // المفتاح وحده مقبول. غير المفهرَس لا وجود له من منظور هذه الشاشة —
        // ولا يُفرَّق في الردّ بين «غير مسموح» و«غير موجود»، فالتفريق نفسه
        // يكشف ما في القاعدة
        $table = ExportCatalog::tableForKey($key);

        if ($table === null) {
            return response()->json(['message' => __('settings.export.entity_unavailable')], 404);
        }

        $labels = ExportCatalog::columnsFor($table);
        $columns = [];

        foreach ($this->registry->columns($table) as $column) {
            if (! isset($labels[$column['name']])) {
                continue; // بلا اسم عربي = لا يُعرَض ولا يُصدَّر
            }

            $columns[] = [
                'name' => $column['name'],
                'label' => $labels[$column['name']],
                'kind' => $column['kind'],
            ];
        }

        if ($columns === []) {
            return response()->json(['message' => __('settings.export.no_fields')], 404);
        }

        $meta = collect($this->registry->all())->firstWhere('name', $table);

        return response()->json([
            // اسم الجدول لا يخرج في الردّ — المفتاح يكفي الواجهة
            'key' => $key,
            'label' => ExportCatalog::labelFor($table),
            'rows' => $meta['rows'] ?? 0,
            'columns' => $columns,
        ]);
    }

    /**
     * تنفيذ التصدير وإرسال الملفّ.
     */
    public function export(Request $request)
    {
        $data = $request->validate([
            'format' => ['required', 'in:'.implode(',', array_keys(DataExportService::FORMATS))],
            // كان الحدّ خمسين والقاعدة فيها ثلاثمئة وثلاثون جدولاً، فـ«تصدير
            // كامل قاعدة البيانات» يفشل بالتحقّق قبل أن يبدأ
            'selections' => ['required', 'array', 'min:1', 'max:400'],
            'selections.*.key' => ['required', 'string', 'max:64'],
            'selections.*.columns' => ['array'],
            'selections.*.columns.*' => ['string'],
            'selections.*.limit' => ['nullable', 'integer', 'min:1', 'max:'.DataExportService::MAX_ROWS],
            'selections.*.filters' => ['array', 'max:10'],
            'selections.*.filters.*.column' => ['nullable', 'string'],
            'selections.*.filters.*.operator' => ['nullable', 'string'],
            'selections.*.filters.*.value' => ['nullable', 'string', 'max:500'],
        ], [
            'selections.required' => __('settings.export.errors.selections_required'),
            'selections.max' => __('settings.export.errors.selections_max'),
            // `in` غير مترجَم في ملفّ التحقّق العربي
            'format.in' => __('settings.export.errors.format_in'),
            'format.required' => __('settings.export.errors.format_required'),
            'selections.*.limit.max' => __('settings.export.errors.limit_max', ['max' => number_format(DataExportService::MAX_ROWS)]),
        ]);

        // الأسماء تُطابَق على الموجود فعلاً داخل الخدمة — التحقّق هنا
        // للشكل لا للمحتوى
        try {
            @set_time_limit(1800);

            $result = $this->exporter->export($data['selections'], $data['format'], auth()->id());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('فشل تصدير البيانات', [
                'user_id' => auth()->id(),
                'format' => $data['format'],
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', __('settings.export.file_failed', ['error' => $e->getMessage()]));
        }

        if ($result['rows'] === 0) {
            $this->exporter->removeDirectory(dirname($result['path']));

            return back()->withInput()->with('error', __('settings.export.no_rows'));
        }

        // `deleteFileAfterSend` يحذف الملفّ وحده ويترك مجلَّده. التنظيف
        // مؤجَّل إلى ما بعد إرسال الاستجابة عبر `terminating` — حذفه قبلها
        // يسحب الملفّ من تحت التنزيل
        $directory = dirname($result['path']);

        app()->terminating(fn () => $this->exporter->removeDirectory($directory));

        return response()->download($result['path'], $result['name'])->deleteFileAfterSend(true);
    }
}

<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Database\DatabaseSizeInspector;
use Illuminate\Http\Request;

/**
 * شاشة أحجام جداول قاعدة البيانات.
 *
 * قراءة محضة — لا تعرض زرّ حذف ولا تفريغ. معرفة أين تذهب المساحة شيء،
 * وحذف جدول من شاشة إعدادات شيء آخر لا رجعة فيه.
 */
class DatabaseSizeController extends Controller
{
    private DatabaseSizeInspector $inspector;

    public function __construct(DatabaseSizeInspector $inspector)
    {
        $this->middleware('permission:settings_edit_general_settings');
        $this->inspector = $inspector;
    }

    public function index(Request $request)
    {
        $tables = $this->inspector->tables();
        $summary = $this->inspector->summary($tables);

        // البحث يُطبَّق بعد حساب الإجماليات: «إجمالي القاعدة» يجب أن يبقى
        // إجمالي القاعدة لا مجموع ما طابق البحث
        if ($request->filled('search')) {
            $needle = mb_strtolower(trim($request->get('search')));
            $tables = array_values(array_filter(
                $tables,
                fn ($table) => str_contains(mb_strtolower($table['name']), $needle)
            ));
        }

        if ($request->get('sort') === 'name') {
            usort($tables, fn ($a, $b) => strcmp($a['name'], $b['name']));
        } elseif ($request->get('sort') === 'rows') {
            usort($tables, fn ($a, $b) => $b['rows'] <=> $a['rows']);
        } elseif ($request->get('sort') === 'free') {
            usort($tables, fn ($a, $b) => $b['free_bytes'] <=> $a['free_bytes']);
        }

        return view('sitting::database.sizes', [
            'tables' => $tables,
            'summary' => $summary,
            'matched' => count($tables),
            'sort' => $request->get('sort', 'size'),
            'search' => $request->get('search', ''),
        ]);
    }
}

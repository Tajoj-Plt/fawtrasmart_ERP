<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Maintenance\CacheClearService;
use App\Support\OperationLog;
use Illuminate\Http\RedirectResponse;

/**
 * شاشة «مسح الذاكرة المؤقتة».
 *
 * الصلاحية مفصولة عن `settings_edit_general_settings` كسائر شاشات الصيانة:
 * المسح لا يُتلف بياناتٍ، لكنه يُبطئ النظام لحظاتٍ على كل مستخدميه معاً —
 * فهو قرارُ مسؤولِ نظام لا قرارُ من يعدّل شعار المنشأة.
 *
 * ولا يبني هذا المتحكّم اسم أمرٍ ولا يمرّر خياراً: يأخذ مفتاحاً من المسار
 * ويُسلّمه إلى `CacheClearService` وحدها لتطابقه على قائمتها البيضاء.
 */
class CacheController extends Controller
{
    public function __construct(protected CacheClearService $caches)
    {
        $this->middleware('permission:settings.cache.view')->only('index');
        $this->middleware('permission:settings.cache.clear')->only(['clear', 'clearAll']);
    }

    public function index()
    {
        return view('sitting::cache.index', [
            'targets' => CacheClearService::targets(),
            'status' => $this->caches->status(),
        ]);
    }

    /** مسح نوعٍ واحد. */
    public function clear(string $key): RedirectResponse
    {
        if (! CacheClearService::has($key)) {
            return back()->with('error', 'نوع ذاكرةٍ غير معروف.');
        }

        $target = CacheClearService::targets()[$key];
        $result = $this->caches->clear($key);

        if (! $result['ok']) {
            OperationLog::failure(
                new \RuntimeException($result['output']),
                'CacheClear',
                $key
            );

            return back()
                ->with('error', 'تعذّر مسح «'.$target['label'].'».')
                ->with('cache_output', $result['output']);
        }

        OperationLog::record('clear', 'CacheClear', null, 'مسح '.$target['label']);

        return back()
            ->with('success', 'تمّ مسح «'.$target['label'].'».')
            ->with('cache_output', $result['output']);
    }

    /** مسح كل الأنواع — الزرّ المقصود بعد رفع تحديثٍ إلى الخادم. */
    public function clearAll(): RedirectResponse
    {
        $result = $this->caches->clearAll();

        $report = collect($result['steps'])
            ->map(fn ($step) => ($step['state'] === 'ok' ? '✔' : '✖').' '.$step['label'].' — '.$step['output'])
            ->implode("\n");

        OperationLog::record(
            'clear',
            'CacheClear',
            null,
            'مسح الذاكرة المؤقتة كاملةً ('.count($result['steps']).' نوع)'
        );

        if (! $result['ok']) {
            return back()
                ->with('error', 'اكتمل المسح مع فشل بعض الخطوات — التفصيل في المخرَج أدناه.')
                ->with('cache_output', $report);
        }

        return back()
            ->with('success', 'تمّ مسح الذاكرة المؤقتة كاملةً.')
            ->with('cache_output', $report);
    }
}

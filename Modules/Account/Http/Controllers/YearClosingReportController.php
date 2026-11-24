<?php

namespace Modules\Account\Http\Controllers;

use App\Constants\planFeatures\AccountPlanFeatures;
use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Services\Accounting\YearEndClosingService;
use App\Services\Fawtra\SubscriptionGate;
use App\Support\AccountingPeriodFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * تقرير الإقفال السنوي في التقارير المالية.
 *
 * التقريرُ نفسُه الذي تعرضه شاشةُ الفترات المحاسبية
 * (`AccountingPeriods.years.closing_report`) — المصدرُ واحد
 * (`YearEndClosingService::report()`) — لكن بين التقارير المالية وبتصديرها
 * المخصَّص (`exportToExcelProfessional` / `printTable`)، ولمن يقرأ التقارير
 * ولا يملك إدارة الفترات.
 */
class YearClosingReportController extends Controller
{
    public function __construct(private YearEndClosingService $yearEnd) {}

    /**
     * رابطُ هذا التقرير لسنةٍ بعينها — أو `null` إن لم يبلغه المستخدم.
     *
     * شاشةُ الفترات المحاسبية تحيل إليه بدل نسختها بتصميم الإعدادات: تقريرٌ
     * واحدٌ بتصميم التقارير المالية. ولا تحيل إلا من يفتحه فعلاً — صلاحيتُه،
     * وباقةٌ تشمل التقارير المالية (`plan:` على مجموعة المسارات) — وإلا
     * بقيت نسختُها احتياطاً.
     */
    public static function urlFor(FiscalYear $year): ?string
    {
        if (! Route::has('GeneralAccountReports.yearClosing') || ! hasPermission('account.reports.year_closing')) {
            return null;
        }

        if (! app(SubscriptionGate::class)->allowsAny([AccountPlanFeatures::ACCOUNT_REPORTS])) {
            return null;
        }

        return route('GeneralAccountReports.yearClosing', ['fiscal_year_id' => $year->id]);
    }

    public function index(Request $request)
    {
        $years = FiscalYear::orderByDesc('start_date')->get();

        /*
            السنةُ المطلوبة، وإلا آخرُ سنةٍ مقفلة — التقريرُ يُطلب غالباً بعد
            الإقفال — وإلا الجارية، وإلا أحدثُ سنة.
        */
        $year = $request->filled('fiscal_year_id')
            ? $years->firstWhere('id', (int) $request->input('fiscal_year_id'))
            : null;

        $year ??= $years->first(fn (FiscalYear $y) => ! $y->isOpen())
            ?? AccountingPeriodFilter::currentYear()
            ?? $years->first();

        return view('account::report.accountGeneral.year_closing', [
            'years' => $years,
            'year' => $year,
            'report' => $year ? $this->yearEnd->report($year) : null,
            'readiness' => $year && $year->isOpen() ? $this->yearEnd->readiness($year) : null,
        ]);
    }
}

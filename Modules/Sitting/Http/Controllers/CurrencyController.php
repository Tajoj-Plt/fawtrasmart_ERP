<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountSetting;
use App\Models\Client;
use App\Models\SystemCurrency;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Sitting\Http\Requests\StoreCurrencyRequest;
use Modules\Sitting\Http\Requests\UpdateCurrencyRequest;
use Modules\Sitting\Repositories\CurrencyRepository;
use Modules\Sitting\Services\CurrencyService;

class CurrencyController extends Controller
{
    protected $currencyService;

    protected $currencyRepository;

    public function __construct(CurrencyService $currencyService, CurrencyRepository $currencyRepository)
    {
        /*
            الشاشةُ كانت بلا فحص صلاحية: أيُّ مستخدمٍ مسجَّل الدخول يبدّل العملةَ
            الافتراضية للنظام أو يعطّل عملةً برابطٍ مباشر. الحارسُ نفسُه الذي
            تُخفى به من القائمة الجانبية.
        */
        $this->middleware('permission:settings_edit_general_settings');

        $this->currencyService = $currencyService;
        $this->currencyRepository = $currencyRepository;
    }

    public function index(Request $request)
    {
        $account_setting = AccountSetting::where('user_id', auth()->id())->first();
        $client = Client::where('user_id', auth()->id())->first();

        $query = SystemCurrency::query();

        // تطبيق فلاتر البحث من خلال الـ GET Request
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('country', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->get('status'));
        }

        if ($request->filled('source')) {
            $query->where('rate_source', $request->get('source'));
        }

        // جلب البيانات مع الترقيم
        $system_currencies = $query->orderBy('sort_order', 'asc')->paginate(\Modules\Sitting\Support\PageSize::get(12));

        $stats = [
            'total' => SystemCurrency::count(),
            'active' => SystemCurrency::where('is_active', true)->count(),
            'manual' => SystemCurrency::where('rate_source', 'manual')->count(),
        ];

        // قفل العملة الافتراضية: يُحسب مرّة للصفحة كلّها لا لكل صف،
        // فالقاعدة تخصّ الافتراضية الحالية وحدها لا كل عملة في الجدول.
        $defaultLocked = $this->currencyService->defaultIsLocked();
        $defaultUsage = $defaultLocked ? $this->currencyService->defaultUsageSummary() : '';

        return view('sitting::sittingAccount.currency', compact(
            'system_currencies', 'account_setting', 'client', 'stats', 'defaultLocked', 'defaultUsage'
        ));
    }

    public function store(StoreCurrencyRequest $request)
    {
        try {
            $this->currencyService->store($request->validated());

            return redirect()->back()->with('success', __('settings.messages.currency_added'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Currency', 'store');

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function edit($id)
    {
        $currency = $this->currencyRepository->find($id);

        return response()->json($currency);
    }

    public function update(UpdateCurrencyRequest $request, $id)
    {
        try {
            $this->currencyService->update($id, $request->validated());

            return redirect()->back()->with('success', __('settings.messages.currency_saved'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Currency', 'update');

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->currencyService->delete($id);

            return redirect()->back()->with('success', __('settings.messages.currency_deleted'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Currency', 'destroy');

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function toggleActive(Request $request)
    {
        try {
            Log::info('Currency toggleActive hit', ['id' => $request->id, 'all' => $request->all()]);
            $this->currencyService->toggleActive($request->id);
            Log::info('Currency toggleActive success');

            return redirect()->back()->with('success', __('settings.messages.currency_state_changed'));
        } catch (\Exception $e) {
            Log::error('Currency toggleActive error', ['msg' => $e->getMessage()]);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function storeAccountCurrency(Request $request)
    {
        try {
            $request->validate([
                'currency' => 'required|string',
            ]);

            // الحارس قبل أي كتابة: تبديل الافتراضية بعد تسجيل الحركات
            // يُعيد قراءة أرقام قديمة بعملة لم تُحرَّر بها.
            $this->currencyService->assertDefaultChangeable($request->currency);

            /* الرمزُ يجب أن يكون عملةً معرَّفةً ومفعَّلة. كان أيُّ نصٍّ يُقبل:
               تُنزَع الافتراضيةُ عن الجميع ثمّ لا يطابق التحديثُ شيئاً، فيبقى
               النظامُ بلا عملةٍ افتراضية. */
            $currency = SystemCurrency::where('code', $request->currency)->first();

            if (! $currency || ! $currency->is_active) {
                return redirect()->back()->with('error', __('settings.messages.currency_not_usable'));
            }

            // كلُّها أو لا شيء: نزعُ الافتراضية ومنحُها لا ينفصلان
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $currency) {
                $AccountSetting = AccountSetting::firstOrCreate(['user_id' => auth()->id()]);
                $AccountSetting->currency = $request->currency;
                $AccountSetting->save();

                $Client = Client::where('user_id', $AccountSetting->user_id)->first();
                if ($Client) {
                    $Client->currency = $request->currency;
                    $Client->save();
                }

                SystemCurrency::query()->update(['is_default' => false]);
                $currency->update(['is_default' => true]);
            });

            $this->currencyRepository->clearCache();

            return redirect()->back()->with('success', __('settings.messages.currency_settings_saved'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Currency', 'storeAccountCurrency');

            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}

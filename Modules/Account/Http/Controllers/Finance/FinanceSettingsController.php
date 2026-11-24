<?php

namespace Modules\Account\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpensesCategory;
use App\Models\ReceiptCategory;
use App\Support\OperationLog;
use Illuminate\Http\Request;

class FinanceSettingsController extends Controller
{
    /**
     * صلاحيات إعدادات الحسابات — الكتالوج الجديد account.settings.*.
     * لم يكن لهذا المتحكّم أي حماية صلاحياتٍ إطلاقاً من قبل.
     */
    public function __construct()
    {
        $this->middleware('permission.role:account.settings.view', ['only' => ['index']]);
        $this->middleware('permission.role:account.settings.expense_categories', [
            'only' => ['expenses_category', 'expenses_category_store', 'expenses_category_update', 'expenses_category_delete'],
        ]);
        $this->middleware('permission.role:account.settings.receipt_categories', [
            'only' => ['receipt_category', 'receipt_category_store', 'receipt_category_update', 'receipt_category_delete'],
        ]);
    }

    public function index()
    {
        return view('account::finance.finance_settings.index');
    }

    public function expenses_category()
    {
        $expenses_categories = ExpensesCategory::orderBy('id', 'DESC')->get();

        // «حسابات التصنيف»: ما يراه في سند الصرف العام من لا يملك «إظهار كل الحسابات»
        $expenseAccounts = app(ExpensesController::class)->getExpenseLeafAccounts()
            ->map(fn ($a) => ['id' => (int) $a->id, 'text' => trim(($a->code ? $a->code.' - ' : '').$a->name)])
            ->values();
        $categoryAccountMap = ExpensesCategory::linkedAccountMap();

        return view('account::finance.finance_settings.expenses_category', compact('expenses_categories', 'expenseAccounts', 'categoryAccountMap'));

    }

    public function expenses_category_store(Request $request)
    {
        // الاسمُ فريد — تصنيفان باسمٍ واحد لا يُفرَّق بينهما في منتقي سند الصرف ولا في التقارير
        $request->validate([
            'name' => 'required|string|max:255|unique:expenses_categories,name',
        ], ['name.unique' => 'يوجد تصنيف مصروفات بهذا الاسم.']);

        $data = new ExpensesCategory;

        if ($request->has('status')) {
            $data->status = 1;
        }
        $data->name = $request->name;
        $data->description = $request->description;
        $data->save();

        $this->syncClientDefault($data, $request->boolean('is_client_default'));
        $this->syncLinkedAccounts($data, $request);

        $Log = OperationLog::record(
            'recorded',
            'product_log',
            $data->id, // ID النشاط المرتبط
            'تم اضافة تصنيف  منصرفات **'.$request->name.'**'
        );

        return redirect()->back()->with(['success' => 'تمت إضافة تصنيف المصروفات بنجاح']);
    }

    public function expenses_category_update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:expenses_categories,name,'.(int) $id,
        ], ['name.unique' => 'يوجد تصنيف مصروفات بهذا الاسم.']);

        $data = ExpensesCategory::findOrFail($id);
        $oldName = $data->name;
        if ($request->has('status')) {
            $data->status = 1;
        } else {
            $data->status = 0;
        }
        $data->name = $request->name;

        $data->description = $request->description;
        $data->update();

        $this->syncClientDefault($data, $request->boolean('is_client_default'));
        $this->syncLinkedAccounts($data, $request);

        OperationLog::record(
            'recorded',
            'finance_log',
            $data->id, // ID النشاط المرتبط
            'تم تعديل  تصنيف المنصرفات من **'.$oldName.'** إلى **'.$request->name.'**'
        );

        return redirect()->back()->with(['success' => 'تم تحديث تصنيف المصروفات بنجاح']);
    }

    /**
     * «التصنيف الافتراضي لسند صرف العميل» — تصنيفٌ واحد فقط: تأشيرُ واحدٍ
     * يرفع العلامة عن غيره. يقرؤه `ExpensesCategory::clientDefaultId()`.
     * بلا العمود (ترحيلٌ لم يُشغَّل) لا يُكتب شيء.
     */
    private function syncClientDefault(ExpensesCategory $category, bool $isDefault): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('expenses_categories', 'is_client_default')) {
            return;
        }

        if ($isDefault) {
            ExpensesCategory::where('id', '!=', $category->id)->where('is_client_default', true)->update(['is_client_default' => false]);
        }

        if ((bool) $category->is_client_default !== $isDefault) {
            $category->forceFill(['is_client_default' => $isDefault])->save();
        }
    }

    /**
     * «حسابات التصنيف» — تُستبدل بما أُرسل (قائمةٌ فارغة تفكّ الربط كلَّه)،
     * ولا يُقبل إلا حسابُ مصروفٍ طرفيّ. بلا الجدول (ترحيلٌ لم يُشغَّل) لا يُكتب شيء.
     */
    private function syncLinkedAccounts(ExpensesCategory $category, Request $request): void
    {
        if (ExpensesCategory::linkedAccountMap() === null) {
            return;
        }

        $allowed = app(ExpensesController::class)->getExpenseLeafAccounts()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ids = array_values(array_intersect(array_map('intval', (array) $request->input('account_ids', [])), $allowed));

        $category->linkedAccounts()->sync($ids);
    }

    public function expenses_category_delete($id)
    {
        $category = ExpensesCategory::find($id);

        // نقرةٌ ثانية أو صفحةٌ قديمة — لا صفحة ٤٠٤ عارية
        if (! $category) {
            return redirect()->route('finance_settings.expenses_category')->with('error', 'هذا التصنيف غير موجود — ربما حُذف مسبقاً.');
        }

        /* كان يُحذف ولو حمله ٦٣٢ سنداً — فتبقى السنداتُ بلا تصنيف وتسقط من
           تقارير المصروفات حسب التصنيف. والمستعمَلُ يُعطَّل (الحالة) لا يُحذف. */
        $used = \App\Models\Expense::where('expenses_category_id', $category->id)->count();
        if ($used > 0) {
            return redirect()->back()->with('error', 'لا يمكن حذف التصنيف «'.$category->name.'» — مستعمَل في '.$used.' سند صرف. عطِّله من التعديل بدل حذفه.');
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('expenses_categories', 'is_client_default') && $category->is_client_default) {
            return redirect()->back()->with('error', 'لا يمكن حذف التصنيف «'.$category->name.'» — هو التصنيف الافتراضي لسند صرف العميل. اختر تصنيفاً افتراضياً غيره أولاً.');
        }

        OperationLog::record(
            'recorded',
            'finance_log',
            $category->id, // ID النشاط المرتبط
            'تم حذف تصنيف  منصرفات **'.$category->name.'**'
        );
        if (ExpensesCategory::linkedAccountMap() !== null) {
            $category->linkedAccounts()->detach();
        }
        $category->delete();

        return redirect()->back()->with('success', 'تم حذف تصنيف المصروفات بنجاح');
    }

    public function receipt_category()
    {
        $receipt_categories = ReceiptCategory::orderBy('id', 'DESC')->get();

        return view('account::finance.finance_settings.receipt_category', compact('receipt_categories'));
    }

    public function receipt_category_store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:receipt_categories,name',
        ], ['name.unique' => 'يوجد تصنيف إيرادات بهذا الاسم.']);

        $data = new ReceiptCategory;

        if ($request->has('status')) {
            $data->status = 1;
        }
        $data->name = $request->name;
        $data->description = $request->description;
        $data->save();
        $Log = OperationLog::record(
            'recorded',
            'product_log',
            $data->id, // ID النشاط المرتبط
            'تم اضافة تصنيف  ايرادات **'.$request->name.'**'
        );

        return redirect()->back()->with(['success' => 'تمت إضافة تصنيف الإيرادات بنجاح']);
    }

    public function receipt_category_update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:receipt_categories,name,'.(int) $id,
        ], ['name.unique' => 'يوجد تصنيف إيرادات بهذا الاسم.']);

        $data = ReceiptCategory::findOrFail($id);
        $oldName = $data->name;
        if ($request->has('status')) {
            $data->status = 1;
        } else {
            $data->status = 0;
        }
        $data->name = $request->name;
        $data->description = $request->description;
        $data->update();
        OperationLog::record(
            'recorded',
            'finance_log',
            $data->id, // ID النشاط المرتبط
            'تم تعديل  تصنيف ايرادات من **'.$oldName.'** إلى **'.$request->name.'**'
        );

        return redirect()->back()->with(['success' => 'تم تحديث تصنيف الإيرادات بنجاح']);
    }

    public function receipt_category_delete($id)
    {
        $category = ReceiptCategory::find($id);

        if (! $category) {
            return redirect()->route('finance_settings.receipt_category')->with('error', 'هذا التصنيف غير موجود — ربما حُذف مسبقاً.');
        }

        // كان يُحذف ولو حمله كلُّ سندات القبض في النظام (٨٦٩١) — انظر expenses_category_delete()
        $used = \App\Models\Receipt::where('incomes_category_id', $category->id)->count();
        if ($used > 0) {
            return redirect()->back()->with('error', 'لا يمكن حذف التصنيف «'.$category->name.'» — مستعمَل في '.$used.' سند قبض. عطِّله من التعديل بدل حذفه.');
        }

        OperationLog::record(
            'recorded',
            'finance_log',
            $category->id, // ID النشاط المرتبط
            'تم حذف تصنيف  ايرادات **'.$category->name.'**'
        );
        $category->delete();

        return redirect()->back()->with('success', 'تم حذف تصنيف الإيرادات بنجاح');
    }
}

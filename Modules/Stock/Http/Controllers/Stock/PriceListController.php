<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Log as ModelsLogs;
use App\Models\PriceList;
use App\Models\PriceListItems;
use App\Models\Product;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class PriceListController extends Controller
{
    /*
        نطاقُ الرؤية: المنتجاتُ المعروضة هنا تتبع مستودعاتِ المستخدم كما
        تتبعها شاشةُ المنتجات — ومن يملك التجاوز أو ليس موظفاً يرى الكلّ.
    */
    use ScopesToEmployeeHierarchy;

    /**
     * كتالوج `stock.price_lists.*` الجديد — كانت الحراسة كلّها بالأسماء
     * القديمة (`products_view_price_groups` وأخواتها)، رغم أن نفس الأدوار
     * التي تملكها تملك مقابلها الجديد بالفعل، فلا أثر على وصول أحد.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.price_lists.view', ['only' => ['index', 'search']]);
        $this->middleware('permission.role:stock.price_lists.show', ['only' => ['show']]);
        $this->middleware('permission.role:stock.price_lists.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.price_lists.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.price_lists.manage_products', ['only' => ['add_product', 'delete_product']]);
        $this->middleware('permission.role:stock.price_lists.delete', ['only' => ['delete']]);
    }

    public function index()
    {
        $price_lists = PriceList::orderBy('id', 'DESC')->paginate(10);
        /*
            كانت القائمةُ كلَّ منتجات المنشأة: موظفٌ لا يرى في شاشة المنتجات
            إلّا مستودعَه يجد هنا كلَّ الأصناف بأسمائها — فيُسعّر ما لا يملك.
        */
        $products = $this->scopeToProductVisibility(Product::select('id', 'name'))->get();
        $category = Category::select('id', 'name')->get();

        return view('stock::price_list.index', compact('price_lists', 'products', 'category'));
    }

    public function create()
    {
        return view('stock::price_list.create');
    }

    public function edit($id)
    {
        $price_list = PriceList::findOrFail($id);

        return view('stock::price_list.edit', compact('price_list'));
    }

    public function show($id)
    {
        $products = $this->scopeToProductVisibility(Product::select('id', 'name', 'sale_price'))->get();
        $category = Category::select('id', 'name')->get();
        $price_list = PriceList::findOrFail($id);
        $list_products = $price_list->price_list_products()->get();

        return view('stock::price_list.show', compact('list_products', 'price_list', 'products', 'category'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
        ]);

        $price = PriceList::create([
            'name' => $request->name,
            // العمود NOT NULL (0 نشط، 1 موقوف): غيابُه من الطلب كان يُسقط الحفظ بخطأ SQL خام
            'status' => (int) $request->input('status', 0),
        ]);

        // تسجيل اشعار نظام جديد
        ModelsLogs::create([
            'type' => 'price_list',
            'type_id' => $price->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم  اضافة قائمة اسعار :  **'.$price->name.'**', // النص المنسق
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);

        return redirect()->route('price_list.index')->with(['success' => 'تم اضافه قائمه الاسعار بنجاج !!']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|max:255',
        ]);

        $price = PriceList::findOrFail($id);

        // حفظ الاسم القديم قبل التحديث
        $oldName = $price->name;

        // تحديث قائمة الأسعار
        $price->update([
            'name' => $request->name,
            // غيابُ الحالة يُبقيها كما هي — كان يكتب NULL في عمودٍ إلزامي
            'status' => (int) $request->input('status', $price->status),
        ]);

        // تسجيل اشعار نظام جديد
        ModelsLogs::create([
            'type' => 'price_list',
            'type_id' => $price->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => sprintf(
                'تم تعديل قائمة الأسعار من **%s** إلى **%s**',
                $oldName, // الاسم القديم
                $price->name // الاسم الجديد
            ),
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);

        // كانت رسالة التعديل تقول «تم اضافه» — نسخةٌ من رسالة الإنشاء
        return redirect()->route('price_list.index')->with(['success' => 'تم تعديل قائمة الأسعار بنجاح']);
    }

    public function delete($id)
    {
        $price = PriceList::findOrFail($id);
        // تسجيل اشعار نظام جديد
        ModelsLogs::create([
            'type' => 'product_log',
            'type_id' => $price->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم  حذف قائمة اسعار :  **'.$price->name.'**', // النص المنسق
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);
        PriceList::findOrFail($id)->delete();

        return redirect()->route('price_list.index')->with(['error' => 'تم حذف قائمه الاسعار بنجاج !!']);
    }

    public function add_product(Request $request, $id)
    {
        PriceList::findOrFail($id);

        /*
            التحقّق **قبل** `try`: كان داخلها، فيلتقط `catch (\Exception)`
            خطأَ التحقّق ويعيده رسالةً تحمل كائن الاستثناء كلَّه.

            والسعر إلزامي: الإضافة تكتب `sale_price` على الصنف نفسه، فسعرٌ
            غائب كان يمحو سعر بيع الصنف في النظام كلِّه.
        */
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'sale_price' => 'required|numeric|min:0',
        ], [
            'product_id.required' => 'اختر الصنف.',
            'product_id.exists' => 'الصنف المختار غير موجود.',
            'sale_price.required' => 'سعر البيع مطلوب.',
            'sale_price.numeric' => 'سعر البيع يجب أن يكون رقماً.',
            'sale_price.min' => 'سعر البيع لا يكون سالباً.',
        ]);

        // صنفٌ خارج ما يراه المستخدم في مستودعاته لا يُسعَّر — كقائمة الاختيار في الشاشة
        if (! $this->scopeToProductVisibility(Product::query())->whereKey((int) $request->product_id)->exists()) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا الصنف.');
        }

        try {
            DB::beginTransaction();

            PriceListItems::create([
                'product_id' => $request->product_id,
                'price_list_id' => $id,
            ]);

            Product::findOrFail($request->product_id)->update([
                'sale_price' => $request->sale_price,
            ]);

            DB::commit();

            return redirect()->back()->with(['success' => 'تم اضافه المنتج الي قائمه الاسعار بنجاج !!']);
        } catch (\Exception $exception) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($exception, 'PriceLists', 'add_product');

            DB::rollback();

            return redirect()->back()->with(['error' => $exception])->withInput();
        }

    }

    public function search(Request $request)
    {
        $query = PriceList::query();

        // Apply filters
        if ($request->filled('keywords')) {
            $query->where('name', 'like', '%'.$request->keywords.'%');
        }

        if ($request->filled('product')) {
            $query->whereHas('price_list_products', function ($q) use ($request) {
                $q->where('product_id', $request->product);
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('price_list_products.product', function ($q) use ($request) {
                $q->where('category_id', $request->category);
            });
        }

        if ($request->filled('brand')) {
            // Assuming you have a brand field in your products table
            $query->whereHas('price_list_products.product', function ($q) use ($request) {
                $q->where('brand_id', $request->brand);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Order and paginate
        $price_lists = $query->orderBy('id', 'DESC')->paginate(10);

        // Return the view partial for AJAX requests
        if ($request->ajax()) {
            return view('stock::price_list.partials.table', compact('price_lists'))->render();
        }

        // For non-AJAX requests, get additional data and return full view
        /*
            كانت القائمةُ كلَّ منتجات المنشأة: موظفٌ لا يرى في شاشة المنتجات
            إلّا مستودعَه يجد هنا كلَّ الأصناف بأسمائها — فيُسعّر ما لا يملك.
        */
        $products = $this->scopeToProductVisibility(Product::select('id', 'name'))->get();
        $category = Category::select('id', 'name')->get();

        return view('stock::price_list.index', compact('price_lists', 'products', 'category'));
    }

    public function export(Request $request)
    {
        // This is a placeholder for export functionality
        // You would typically use a package like Laravel Excel to implement this
        return response()->json(['message' => 'Export functionality not yet implemented'], 200);
    }

    public function import(Request $request)
    {
        // This is a placeholder for import functionality
        // You would typically use a package like Laravel Excel to implement this
        return response()->json(['message' => 'Import functionality not yet implemented'], 200);
    }

    /*
        حذف سلعة من قائمة أسعار.

        **ما كان**

        الدالّة **معلَّقة بالكامل** والمسار `price_list.delete_product` يشير
        إليها — فكل نقرة على زرّ الحذف تصطدم بـ500، إذ يبحث الموجِّه عن
        منهجٍ لا وجود له في الصنف.

        وحتى لو أُزيل التعليق لأخطأ الهدف: الزرّ يمرّر **معرّف المنتج**
        (`$product->id` من علاقة `belongsToMany`) والدالّة تبحث به في
        `price_list_items` — أي في معرّفات **أسطر الربط**. فتحذف سطراً
        لا صلة له بالمنتج المقصود، أو ترمي 404 حين لا يوافق شيء.

        **ما صار**

        الرابط يمرّر معرّف سطر الربط (`pivot->id`)، والحذف يقع عليه وحده —
        فالمنتج يبقى في النظام وتُحذف عضويّته في هذه القائمة فقط، وهو
        المقصود. والرسالة صارت `success` بعد أن كانت تُبلَّغ نجاحاً بلون
        الخطأ.
    */
    public function delete_product($id)
    {
        $item = PriceListItems::find($id);

        if (! $item) {
            return redirect()->back()->with(['error' => 'لم يُعثر على السلعة في هذه القائمة.']);
        }

        $item->delete();

        return redirect()->back()->with(['success' => 'حُذفت السلعة من قائمة الأسعار.']);
    }
}

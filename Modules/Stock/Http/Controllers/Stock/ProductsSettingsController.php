<?php


namespace Modules\Stock\Http\Controllers\Stock;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductsSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:stock.settings.products');
    }

    public function index()
    {
        return view('stock::products_settings.index');
    }

    public function category()
    {
        // إعادة التوجيه إلى صفحة الأصناف الجديدة
        return redirect()->route('category.index');
    }

    public function default_taxes()
    {
        return view('stock::products_settings.default_taxes');
    }

    public function barcode_settings()
    {
        return view('stock::products_settings.barcode_settings');
    }

}

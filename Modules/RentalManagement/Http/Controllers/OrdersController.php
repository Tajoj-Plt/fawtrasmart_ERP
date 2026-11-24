<?php

namespace Modules\RentalManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UnitType;

class OrdersController extends Controller
{
    public function __construct()
    {
        // حماية جميع الوظائف - يحتاج إلى صلاحية إدارة أوامر الحجز
        $this->middleware(function ($request, $next) {
            if (! hasPermission('rental_unit_manage_booking_orders')) {
                abort(403, 'غير مصرح لك بإدارة أوامر الحجز');
            }

            return $next($request);
        });
    }

    public function index()
    {
        return view('rentalmanagement::orders.index');
    }

    public function create()
    {
        $unitTypes = UnitType::with('pricingRule')->get(); // جلب البيانات مع قواعد التسعير

        return view('rentalmanagement::orders.create', compact('unitTypes'));
    }
}

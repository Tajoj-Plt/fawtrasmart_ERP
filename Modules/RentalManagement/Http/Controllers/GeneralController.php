<?php

namespace Modules\RentalManagement\Http\Controllers;

use App\Http\Controllers\Controller;

class GeneralController extends Controller
{
    public function __construct()
    {
        // حماية جميع الوظائف - يحتاج إلى صلاحية إدارة الإعدادات
        $this->middleware(function ($request, $next) {
            if (! hasPermission('rental_unit_manage_rental_settings')) {
                abort(403, 'غير مصرح لك بإدارة إعدادات الإيجارات');
            }

            return $next($request);
        });
    }

    public function index()
    {
        return view('rentalmanagement::Settings.general.index');
    }
}

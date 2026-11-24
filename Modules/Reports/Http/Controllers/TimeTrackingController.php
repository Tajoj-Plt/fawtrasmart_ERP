<?php

/*
    كان هذا الملفّ يعلن نفسه في `App\Http\Controllers\Reports` وهو قائمٌ في
    `Modules/Reports/Http/Controllers` — فلا يطابق أيّ خريطة PSR-4 في
    المشروع، ولا يُحمَّل إلّا من خريطة الفئات التي يبنيها composer مسحاً.
    فصار الاسم يطابق موضعه.
*/

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;

class TimeTrackingController extends Controller
{
    public function index()
    {
        return view('reports::time_tracking.index');
    }

    // عرض التقرير حسب الموظف
    public function reportByEmployee()
    {
        return view('reports::time_tracking.report_by_employee');
    }

    // عرض التقرير حسب المشروع
    public function reportByProject()
    {
        return view('reports::time_tracking.report_by_project');
    }

    // عرض التقرير حسب الحالة
    public function reportByStatus()
    {
        return view('reports::time_tracking.report_by_status');
    }

    // التقرير اليومي
    public function dailyReport()
    {
        return view('reports::time_tracking.daily_report');
    }

    // التقرير الأسبوعي
    public function weeklyReport()
    {
        return view('reports::time_tracking.weekly_report');
    }

    // التقرير الشهري
    public function monthlyReport()
    {
        return view('reports::time_tracking.monthly_report');
    }

    // التقرير السنوي
    public function yearlyReport()
    {
        return view('reports::time_tracking.yearly_report');
    }
}

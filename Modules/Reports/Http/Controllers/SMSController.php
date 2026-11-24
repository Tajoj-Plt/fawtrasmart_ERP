<?php

/*
    كان هذا الملفّ يعلن نفسه في `App\Http\Controllers\Reports` وهو قائمٌ في
    `Modules/Reports/Http/Controllers` — فلا يطابق أيّ خريطة PSR-4 في
    المشروع، ولا يُحمَّل إلّا من خريطة الفئات التي يبنيها composer مسحاً.
    فصار الاسم يطابق موضعه.
*/

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;

class SMSController extends Controller
{
    public function index()
    {
        return view('reports.sms.index');
    }

    public function Campaigns()
    {
        return view('reports.sms.Campaigns');
    }

    public function log()
    {
        return view('reports.sms.log');
    }
}

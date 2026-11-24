<?php

namespace Modules\Sales\Http\Controllers;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RevolvingInvoicesController extends Controller
{
    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.revolving_invoices.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.revolving_invoices.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:sales.revolving_invoices.create', ['only' => ['create']]);
    }

    public function index()
    {
        return view('sales::revolving_invoices.index');
    }

    public function create ()
    {
        return view('sales::revolving_invoices.create');


    }

    public function show ()
    {
        return view('sales::revolving_invoices.show');
    }
}

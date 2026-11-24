<?php
$user = \App\Models\User::first();
$request = \Illuminate\Http\Request::create('/x', 'GET', ['period' => 'year']);
app()->instance('request', $request);
\Illuminate\Support\Facades\Auth::login($user);
$html = (new \Modules\Purchases\Http\Controllers\Reports\SupplierReportController())->index($request)->render();
$start = strpos($html, 'pbs-kpis');
file_put_contents(base_path('tmp_check/out.html'), $html);
echo substr($html, $start, 3000);

<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
view()->share('errors', new \Illuminate\Support\ViewErrorBag);
auth()->setUser(App\Models\User::find(1));
$base = 'Modules\Purchases\Http\Controllers\Reports\\';
$q = ['from_date' => '2000-01-01', 'to_date' => date('Y-m-d')];

$render = function (string $c, string $m, string $out) use ($app, $base, $q) {
    $r = Illuminate\Http\Request::create('/x', 'GET', $q);
    $r->setLaravelSession($app['session.store']);
    app()->instance('request', $r);
    $html = app($base.$c)->$m($r)->render();
    $html = str_replace('http://localhost/', 'http://127.0.0.1:8000/', $html);
    file_put_contents($out, $html);
    echo "$out ", strlen($html), "\n";
};

$render('EmployeePurchasesReportController', 'index', 'public/_preview/by_employee.html');
$render('SupplierReportController', 'bySupplier', 'public/_preview/by_supplier.html');
$render('ProductReportController', 'index', 'public/_preview/by_product.html');
$render('SupplierReportController', 'directory', 'public/_preview/directory.html');
$render('SupplierReportController', 'supplierBalances', 'public/_preview/balances.html');

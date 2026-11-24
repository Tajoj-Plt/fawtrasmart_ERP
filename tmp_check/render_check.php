<?php
$user = \App\Models\User::first();

$targets = [
    'summary' => ['period' => 'month'],
    'details' => ['period' => 'year', 'view_type' => 'details'],
    'today'   => ['period' => 'today'],
    'legacy'  => ['period' => '30'],
    'sorted'  => ['period' => 'year', 'sort_by' => 'balance', 'sort_dir' => 'asc'],
];

$controller = new \Modules\Purchases\Http\Controllers\Reports\SupplierReportController();

foreach ($targets as $label => $params) {
    try {
        $request = \Illuminate\Http\Request::create('/purchases/reports/suppliers/by-supplier', 'GET', $params);
        app()->instance('request', $request);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
        \Illuminate\Support\Facades\Auth::login($user);

        $view = $controller->index($request);
        $html = $view->render();
        printf("%-9s OK bytes=%d\n", $label, strlen($html));
    } catch (\Throwable $e) {
        printf("%-9s FAIL %s @ %s:%d\n", $label, $e->getMessage(), basename($e->getFile()), $e->getLine());
    }
}

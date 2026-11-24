<?php
use Illuminate\Http\Request;
use Modules\Purchases\Http\Controllers\Reports\SupplierReportController;

$controller = new SupplierReportController();
$ref = new ReflectionMethod($controller, 'buildReport');
$ref->setAccessible(true);

foreach (['today', 'week', 'month', 'year'] as $p) {
    $req = Request::create('/x', 'GET', ['period' => $p, 'view_type' => 'details']);
    $data = $ref->invoke($controller, $req);
    $t = $data['totals'];
    echo str_pad($p, 8), ' ', $data['period']->labelWithDates(), PHP_EOL;
    echo sprintf(
        "   suppliers=%d entries=%d opening=%.2f purchases=%.2f returns=%.2f DN=%.2f net=%.2f payments=%.2f adj=%.2f balance=%.2f\n",
        $t['suppliers_count'], $t['entries_count'], $t['opening_balance'], $t['purchases'],
        $t['returns'], $t['debit_notes'], $t['net_purchases'], $t['payments'], $t['adjustments'], $t['balance']
    );
    foreach ($data['reportData'] as $row) {
        echo "   - {$row['supplier_name']} bal={$row['balance']} status={$row['status']['text']} items=".count($row['items']).PHP_EOL;
        foreach ($row['items'] as $it) {
            echo sprintf("       %s %-10s %-18s D=%9.2f C=%9.2f => %9.2f\n", $it['date'], $it['reference_number'], $it['type_name'], $it['debit'], $it['credit'], $it['running_balance']);
        }
    }
}
echo "\n--- account balance in DB ---\n";
foreach (\App\Models\Account::whereNotNull('supplier_id')->get() as $a) {
    echo "  account {$a->id}: balance={$a->balance} ({$a->balance_type})\n";
}

@php
$customersCount = \App\Models\Client::count();
$journalEntriesCount = \App\Models\JournalEntry::count();
$accountsCount = \App\Models\Account::count();
$branchesCount = \App\Models\Branch::count();
$employeesCount = \App\Models\Employee::count();
$regionGroupsCount = \App\Models\Region_groub::count();
@endphp

<!DOCTYPE html>
<html>
<head>
    <title>Data Check</title>
</head>
<body>
    <h1>Database Statistics</h1>
    <ul>
        <li>Clients: {{ $customersCount }}</li>
        <li>Journal Entries: {{ $journalEntriesCount }}</li>
        <li>Accounts: {{ $accountsCount }}</li>
        <li>Branches: {{ $branchesCount }}</li>
        <li>Employees: {{ $employeesCount }}</li>
        <li>Region Groups: {{ $regionGroupsCount }}</li>
    </ul>
</body>
</html>

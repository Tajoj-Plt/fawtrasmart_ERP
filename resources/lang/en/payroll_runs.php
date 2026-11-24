<?php

/*
 * Strings for the "Payroll disbursement records" screen (hr::salaries.payroll_runs.index).
 * Stage keys come from PayrollRun::stages().
 */
return [
    'page_title' => 'Disbursement records',
    'title' => 'Payroll disbursement records',
    'subtitle' => 'One run per employee per cycle — approved in a payroll batch, then disbursed to become "Paid"',
    'batches' => 'Payroll batches',
    'payrolls' => 'Payrolls',

    'stages' => [
        'awaiting_approval' => 'Awaiting payroll approval',
        'scheduled' => 'Approved — before pay day',
        'ready' => 'Due for payment',
        'held' => 'On hold',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],

    'due_today' => ':count due for disbursement',
    'this_month' => 'This month: :amount',

    'filters' => 'Search & filter',
    'filters_active' => 'Filters active',
    'employee' => 'Employee',
    'stage' => 'Stage',
    'all' => 'All',
    'period_from' => 'Period from',
    'period_to' => 'To',
    'reset' => 'Reset',
    'search' => 'Search',

    'list' => 'Disbursement records',
    'record' => 'Record',
    'period' => 'Period',
    'disbursed_date' => 'Disbursement date',
    'earnings' => 'Earnings',
    'deductions' => 'Deductions',
    'advances' => 'Advances',
    'net' => 'Net',
    'actions' => 'Actions',
    'options' => 'Options',
    'view' => 'View',
    'view_slip' => 'View salary slip',
    'slip_no' => 'Slip #:id',
    'auto' => 'Automatic',
    'manual' => 'Manual',
    'showing' => 'Showing :from–:to of :total',

    'empty_title' => 'No disbursement records',
    'empty_filtered' => 'No record matched your search — try broader criteria',
    'empty_hint' => 'Runs are generated monthly from approved salary slips',
    'clear_filters' => 'Clear filters',
];

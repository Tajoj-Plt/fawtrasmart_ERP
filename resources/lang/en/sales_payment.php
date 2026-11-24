<?php

/*
 * Customer payments — receipt vouchers.
 * Mirrors `purchase_cycle.payment` on the purchases side.
 */

return [
    'index_title' => 'Customer payments',
    'index_subtitle' => 'Receipt vouchers and the sales invoices they settle',
    'create_title' => 'Record a payment',
    'edit_title' => 'Edit payment',
    'show_title' => 'Receipt :code',
    'plural' => 'Customer payments',
    'singular' => 'Receipt voucher',
    'new' => 'New payment',

    'stats' => [
        'total' => 'Total collected',
        'count' => 'Receipts',
        'today' => 'Collected today',
    ],

    'table' => [
        'number' => 'Receipt number',
        'date' => 'Date',
        'client' => 'Customer',
        'invoice' => 'Invoice',
        'method' => 'Payment method',
        'treasury' => 'Treasury',
        'amount' => 'Amount',
        'status' => 'Status',
        'added_by' => 'Recorded by',
        'added_by_line' => 'Recorded by: :name',
        'via_client_portal' => 'via the client portal',
        'actions' => 'Options',
        'no_invoice' => 'No invoice',
    ],

    'status' => [
        1 => 'Complete',
        2 => 'Partial',
        3 => 'Draft',
        4 => 'Under review',
        5 => 'Failed',
        'unknown' => 'Unknown',
        'cancelled' => 'Cancelled',
    ],

    'filters' => [
        'keywords' => 'Search',
        'period_default' => 'Defaults to the current accounting period',
        'keywords_placeholder' => 'Receipt or invoice number, customer name…',
        'client' => 'Customer',
        'any_client' => 'All customers',
        'method' => 'Payment method',
        'any_method' => 'All methods',
        'treasury' => 'Treasury',
        'any_treasury' => 'All treasuries',
        'branch' => 'Branch',
        'any_branch' => 'All branches',
        'status' => 'Status',
        'any_status' => 'All statuses',
        'added_by' => 'Recorded by',
        'any_user' => 'Everyone',
        'amount_from' => 'Amount from',
        'amount_to' => 'Amount to',
        'date_range' => 'Period',
    ],

    'empty' => [
        'title' => 'No payments',
        'hint' => 'Nothing matches the current search criteria',
    ],

    'form' => [
        'document' => 'Receipt details',
        'invoice' => 'Invoice',
        'client' => 'Customer',
        'number' => 'Receipt number',
        'number_hint' => 'Generated automatically from document numbering — not editable here',
        'date' => 'Receipt date',
        'amount' => 'Amount paid',
        'amount_hint' => 'Cannot exceed the outstanding balance',
        'method' => 'Payment method',
        'select_method' => 'Select a payment method',
        'treasury' => 'Treasury',
        'select_treasury' => 'Select a treasury',
        'reference' => 'Reference number',
        'reference_hint' => 'Bank transaction or receipt number',
        'status' => 'Payment status',
        'employee' => 'Employee',
        'notes' => 'Notes',
        'notes_placeholder' => 'Add anything that clarifies this receipt…',
        'attachments' => 'Attachments',
        'save' => 'Save receipt',
        'back' => 'Back to payments',

        'status_derived' => 'Derived from the amount and the invoice balance',
        'status_full' => 'Settles the invoice in full',
        'status_remaining' => ':amount left',
        'collected_by' => 'Collected by',

        'confirm_title' => 'Confirm this receipt',
        'confirm_text' => 'A journal entry is posted; the treasury balance and the client balance both change.',
        'confirm_yes' => 'Save receipt',

        'update' => 'Save changes',
        'edit_document' => 'Edit receipt details',
        'confirm_update_title' => 'Confirm the change',
        'confirm_update_text' => 'The journal entry is re-posted with the new amount.',
    ],

    'invoice_facts' => [
        'grand_total' => 'Invoice total',
        'paid' => 'Previously paid',
        'remaining' => 'Outstanding',
    ],

    'show' => [
        'number' => 'Receipt number',
        'date' => 'Receipt date',
        'client' => 'Customer',
        'invoice' => 'Invoice',
        'method' => 'Payment method',
        'treasury' => 'Treasury',
        'reference' => 'Reference number',
        'added_by' => 'Recorded by',
        'currency' => 'Currency',
        'amount' => 'Receipt amount',
        'journal_entry' => 'Journal entry',
        'currency_frozen' => 'The receipt currency is fixed on save; switching the currency in Currencies does not revalue an issued receipt.',
        'documents' => 'Documents',
        'from_settings' => 'From document templates',
        'builtin_template' => 'Built-in template',
        'print' => 'Print',
        'pdf' => 'Download PDF',
        'no_template' => 'No template designed for this document',
        'open_invoice' => 'Open invoice',
        'edit' => 'Edit receipt',
        'cancel' => 'Cancel receipt',
        'cancel_title' => 'Cancel this receipt?',
        'cancel_text' => 'The journal entry is reversed and the amount returns to the customer balance; the receipt stays on record as cancelled.',
        'cancel_confirm' => 'Yes, cancel it',
        'cancel_dismiss' => 'Keep it',
        'tab_document' => 'Receipt',
        'tab_invoice' => 'Invoice',
        'tab_activity' => 'Activity log',
        'invoice_number' => 'Invoice number',
        'invoice_date' => 'Invoice date',
        'invoice_total' => 'Invoice total',
        'invoice_paid' => 'Paid',
        'invoice_due' => 'Outstanding',
        'invoice_payments' => 'Invoice payments',
        'no_invoice' => 'This receipt has no reference invoice',
        'no_activity' => 'No activity recorded yet',
        'unknown_user' => 'Unknown user',
        'no_branch' => 'Unknown branch',
    ],

    'notes' => [
        'period_blocked' => 'A payment cannot be dated inside a closed accounting period.',
    ],
    /*
    |--------------------------------------------------------------------------
    | Printed receipt voucher
    |--------------------------------------------------------------------------
    */

    'receipt' => [
        'title' => 'Receipt #:number',
        'heading' => 'Receipt voucher',
        'tax_number' => 'VAT number',

        'number' => 'Receipt no.',
        'date' => 'Date',
        'received_from' => 'Received from',
        'invoice' => 'Against invoice',
        'method' => 'Payment method',
        'treasury' => 'Treasury',
        'received_by' => 'Received by',
        'reference_data' => 'Payment details',
        'amount' => 'Amount received',

        'invoice_state' => 'Invoice status',
        'invoice_totals' => 'Invoice total :total — outstanding :due',
        'notes' => 'Notes',

        'payer_signature' => 'Payer signature',
        'receiver_signature' => 'Receiver signature',
        'thanks' => 'Thank you for your business',

        'stamp_paid' => 'PAID',
        'stamp_void' => 'VOID',

        'a4' => 'A4 voucher',
        'thermal' => 'Thermal receipt',
    ],

    /*
    |--------------------------------------------------------------------------
    | What blocks collection
    |--------------------------------------------------------------------------
    */

    'blocked' => [
        'has_return' => 'Invoice :code has a return, so it cannot take a payment. A return reverses the sale and puts the goods back, so collecting against it would charge for goods already returned.',
        'has_return_short' => 'Has a return — cannot take a payment',
    ],

    'guide' => [
        'index' => ['title' => 'What this screen is, and what its contents cause'],
        'create' => ['title' => 'Before you save this receipt'],
        'show' => ['title' => 'What you can do from this screen'],
        'bulk' => ['title' => 'Bulk collection — how the amount is allocated'],

        'lead_index' => 'These are customer receipts: each row is money that entered a treasury and a customer debt that fell by the same amount.',
        'lead_create' => 'A receipt really moves money: it increases the chosen treasury and reduces the customer balance by the same amount. Pick the right treasury before saving.',
        'lead_show' => 'This is a recorded receipt. Its effect on the treasury and on the customer account has already happened.',
        'lead_bulk' => 'One amount spread across several invoices — for a customer who settles multiple invoices with a single payment.',

        'what_is' => [
            'title' => 'What a receipt is',
            'a' => 'Proof the customer paid. So it is recorded only when payment actually happens — never as a promise of one.',
            'b' => 'The chosen treasury is the one the money enters: the rep\'s treasury for field collection, or the main one.',
            'c' => 'The payment method (cash, transfer, card) is documentation, and the payment-methods report is built on it.',
        ],

        'effects' => [
            'title' => 'What happens on save',
            'a' => 'An entry debiting the treasury and crediting the customer account by the same amount.',
            'b' => 'The linked invoice status is recomputed: "Paid" when collections reach its total, "Partially paid" when they fall short.',
            'c' => 'Cancelling writes an opposing entry and does not erase the receipt — it stays in the list marked "Cancelled".',
        ],

        'blockers' => [
            'title' => 'What prevents a save',
            'a' => 'A date inside a closed accounting period.',
            'b' => 'An invoice that has a return — it accepts no further collection, because its entry has been reversed in whole or in part.',
            'c' => 'Reaching your subscription plan allowance for receipts.',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside it',
            'a' => '"Treasuries" fills the treasury list, and "Payment methods" fills the method list.',
            'b' => '"Chart of accounts" decides the treasury and customer accounts, and "Accounting periods" which dates are accepted.',
        ],
    ],
];

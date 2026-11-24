<?php

/*
|--------------------------------------------------------------------------
| Recurring invoices
|--------------------------------------------------------------------------
|
| Wording for the "Recurring invoices" and "Create recurring invoice"
| screens. Vocabulary shared with invoices stays in `sales_invoice`, and
| general UI wording in `sales_settings.ui`.
|
*/

return [

    'index_title' => 'Recurring invoices',
    'index_subtitle' => 'Subscriptions that issue invoices on a schedule',
    'create_title' => 'Create a recurring invoice',
    'plural' => 'Recurring invoices',
    'singular' => 'Recurring invoice',
    'new' => 'New recurring invoice',

    'stats' => [
        'total' => 'Subscriptions total',
        'count' => 'Number of subscriptions',
        'active' => 'Active',
    ],

    'filters' => [
        'number' => 'Recurring invoice number',
        'subscription' => 'Subscription name',
        'repeat_type' => 'Repeat type',
        'all_repeat_types' => 'All types',
        'state' => 'State',
        'all_states' => 'All states',
        'min_total' => 'Total greater than',
        'max_total' => 'Total less than',
        'date_range' => 'First invoice range',
    ],

    'table' => [
        'subscription' => 'Subscription',
        'first_date' => 'First invoice',
        'repeat' => 'Repeat',
        'issued' => 'Issued',
        'issued_of' => ':done of :total',
    ],

    'repeat_types' => [
        1 => 'Daily',
        2 => 'Weekly',
        3 => 'Monthly',
        4 => 'Quarterly',
        5 => 'Yearly',
    ],

    'state' => [
        'active' => 'Active',
        'paused' => 'Paused',
    ],

    'empty' => [
        'title' => 'No recurring invoices',
        'hint' => 'Nothing matches the current filters',
    ],

    'generator' => [
        'no_items' => 'The subscription has no lines, so no invoice can be issued',
        'instance' => 'Instance :number due on :date',
        'log' => 'Issued invoice :invoice from subscription :subscription — instance :number of :total',
        'period_locked' => 'Issue date :date falls in a closed accounting period',
        'unknown_error' => 'The invoice was not saved for an unknown reason — check the error log',
        'no_actor' => 'The subscription creator is no longer a user — edit the subscription under an existing user',
        'already_issued' => 'This instance was already issued',
        'invoice_note' => 'Periodic invoice from subscription :subscription — instance :number — due on :due',
        'journal' => 'Periodic invoice — subscription :subscription, instance :number',
        'failed_title' => 'Periodic invoice not issued — :subscription',
        'failed_body' => 'Instance :number (issue date :date) for :client was not issued: :reason. It will be retried on the next run.',
    ],

    'partial_payment' => [
        'blocked' => 'Invoice :code does not accept partial payment — pay it in full: :amount',
    ],

    'saved' => [
        'created' => 'Periodic subscription created; :count invoice(s) scheduled',
        'updated' => 'Periodic subscription updated — :count invoice(s) scheduled, :cancelled removed',
        'issued_now' => 'Issued now: :codes',
        'issue_failed' => 'No invoice issued today: :reason',
        'overdue' => ':count invoice(s) are past their issue date — the scheduled job issues them, or use “Issue now” one at a time',
        'count_below_issued' => ':count invoice(s) were already issued; the count cannot go below that',
        'end_before_issued' => 'The last issued invoice is due on :date; the end date cannot precede it',
    ],

    'schedule_status' => [
        'scheduled' => 'Scheduled',
        'issued' => 'Issued',
        'failed' => 'Not issued',
        'cancelled' => 'Cancelled',
    ],

    'totals' => [
        'items_tax' => 'Items tax',
        'shipping_tax' => 'Shipping tax',
    ],

    'form' => [
        'document' => 'Subscription and customer details',
        'schedule' => 'Repeat schedule',
        'items' => 'Invoice lines',
        'options' => 'Issuing options',
        'subscription' => 'Subscription details',
        'first_invoice_date' => 'First invoice date',
        'repeat_type' => 'Repeat type',
        'repeat_interval' => 'Every (interval)',
        'repeat_count' => 'Number of invoices',
        'before_days' => 'Issue invoice days before',
        'before_days_hint' => 'How many days before the due date the invoice is issued',
        'payment_terms' => 'Payment terms',
        'auto_generate' => 'Issue invoices automatically',
        'auto_generate_hint' => 'Issued by the scheduled job without intervention',
        'send_copy' => 'Send a copy to the customer',
        'show_dates' => 'Show the date range on the invoice',
        'disable_partial' => 'Disable partial payment',
        'is_active' => 'Subscription is active',
        'back' => 'Back to recurring invoices',
    ],

    'mail' => [
        'subject' => 'Your invoice :code from :business',
        'header' => 'Invoice issued',
        'greeting' => 'Hello :client,',
        'body' => 'Invoice :number of :total has been issued under your subscription :subscription. The details are below.',
        'invoice_code' => 'Invoice number',
        'invoice_date' => 'Invoice date',
        'grand_total' => 'Total',
        'view_invoice' => 'View invoice',
        'footer_note' => 'This is an automated message sent with your recurring invoice; no reply is needed. Contact us with any questions.',
        'signature' => 'Best regards, :business',
        'sent' => 'A copy was emailed to the customer.',
        'no_email' => 'The customer has no valid email address, so no copy was sent.',
        'failed' => 'Could not email the invoice copy to the customer: :error',
    ],

    'items' => [
        'item' => 'Item',
        'select_item' => 'Select an item',
        'base_unit' => 'Base unit',
        'unit' => 'Unit',
        'tax_1' => 'Tax 1',
        'tax_2' => 'Tax 2',
        'no_tax' => 'No tax',
    ],

    'show' => [
        'title' => 'Recurring subscription :code',
        'number' => 'Subscription number',
        'client' => 'Customer',
        'details' => 'Subscription details',
        'first_date' => 'First due date',
        'end_date' => 'Ends on',
        'repeat' => 'Repeats',
        'every' => 'Every :n',
        'issued_count' => 'Issued',
        'created_by' => 'Created by',
        'sales_person' => 'Sales person',
        'currency' => 'Currency',
        'warehouse' => 'Warehouse',
        'payment_method' => 'Payment method',
        'payment_terms' => 'Payment terms',
        'offset' => 'Issued before due date',
        'offset_days' => ':n days',
        'auto_generate' => 'Automatic issuing',
        'send_copy' => 'Email a copy to the customer',
        'show_dates' => 'Show date range',
        'disable_partial' => 'Block partial payment',
        'taxed' => 'Taxable',
        'paid' => 'Collected',
        'unpaid' => 'Outstanding',
        'currency_frozen' => 'The subscription currency is fixed on save; switching the currency in Currencies does not reprice an existing subscription.',
        'cron_hint' => 'Invoices are issued by the "Generate recurring invoices" scheduled task; nothing is issued while it is disabled.',
        'tab_schedule' => 'Schedule',
        'tab_items' => 'Lines',
        'tab_activity' => 'Activity log',
        'schedule_number' => '#',
        'schedule_issue' => 'Issue date',
        'schedule_due' => 'Due date',
        'schedule_state' => 'State',
        'schedule_invoice' => 'Invoice',
        'schedule_total' => 'Total',
        'issued' => 'Issued',
        'pending' => 'Not yet issued',
        'no_items' => 'No lines on this subscription',
        'no_activity' => 'No activity recorded yet',
        'unknown_user' => 'Unknown user',
        'no_branch' => 'Unknown branch',
        'edit' => 'Edit subscription',
        'back' => 'Back to subscriptions',
    ],

    'create' => [
        'schedule' => 'Schedule',
        'schedule_hint' => 'When invoicing starts and how often it repeats',
        'document' => 'Subscription and customer',
        'items' => 'Subscription lines',
        'extra' => 'Discount, shipping and attachments',
        'client' => 'Customer',
        'select_client' => 'Select a customer',
        'employee' => 'Sales person',
        'select_employee' => 'Select a sales person',
        'warehouse' => 'Warehouse',
        'select_warehouse' => 'Select a warehouse',
        'warehouse_hint' => 'Lines of every issued invoice are deducted from it',
        'number' => 'Subscription number',
        'number_hint' => 'Generated automatically from document numbering — not editable here',
        'details' => 'Subscription details',
        'details_placeholder' => 'A free description that identifies this subscription…',
        'repeat_every' => 'Repeats every',
        'repeat_count' => 'Number of invoices',
        'repeat_count_hint' => 'How many invoices the subscription issues — or leave empty and set an end date',
        'first_date' => 'First due date',
        'end_date' => 'End date',
        'end_date_hint' => 'Nothing due after this date is scheduled — with a count, whichever comes first',
        'due_explained' => 'The due date is the last day to pay each invoice. The invoice is issued before it by the issue offset; unpaid after that day it becomes overdue, which drives payment reminders and debt aging.',
        'offset' => 'Issue before due date by',
        'offset_unit' => 'days',
        'offset_hint' => 'Zero means issuing on the due date itself',
        'payment_terms' => 'Payment terms',
        'payment_terms_unit' => 'days',
        'payment_method' => 'Payment method',
        'select_payment_method' => 'Select a payment method',
        'treasury' => 'Treasury',
        'deposit' => 'Deposit',
        'deposit_hint' => 'Collected when each invoice is issued — an amount or a percentage of its total. The rest stays outstanding on the client, and the journal entry is posted from the invoices screen.',
        'deposit_locked' => 'Locked to the total because the invoice is marked fully paid',
        'fully_paid' => 'Issue fully paid',
        'fully_paid_hint' => 'Every invoice this subscription issues is recorded settled with nothing due',
        'select_treasury' => 'Select a treasury',
        'options' => 'Options',
        'discount' => 'Document discount',
        'shipping' => 'Shipping',
        'tax_type' => 'Tax type',
        'shipping_cost' => 'Shipping cost',
        'shipping_tax' => 'Shipping tax',
        'notes' => 'Notes',
        'notes_placeholder' => 'Add anything that clarifies this subscription…',
        'attachments' => 'Attachments',
        'save' => 'Save subscription',
        'no_permission' => 'You are not allowed to create recurring invoices',
        'confirm_title' => 'Save the subscription?',
        'confirm_text' => 'It will issue sales invoices on the scheduled dates.',
        'confirm_yes' => 'Yes, save',
        'confirm_cancel' => 'Cancel',
        'need_client' => 'Select a customer first',
        'need_items' => 'Add at least one line',
    ],
    /*
    |--------------------------------------------------------------------------
    | Subscription management actions
    |--------------------------------------------------------------------------
    */

    'actions' => [
        'edit_title' => 'Edit recurring invoice',
        'no_permission' => 'You are not allowed to manage this subscription',

        'pause' => 'Pause subscription',
        'resume' => 'Resume subscription',
        'paused_done' => 'Subscription paused — no further invoices will be issued',
        'resumed_done' => 'Subscription resumed — anything due is issued on the next scheduled run',

        'pause_title' => 'Pause this subscription?',
        'pause_text' => 'New invoices stop being issued. Invoices already issued are untouched.',
        'resume_title' => 'Resume this subscription?',
        'resume_text' => 'Any missed instalments are issued on the next scheduled run.',

        'generate' => 'Issue what is due now',
        'generate_title' => 'Issue what is due now?',
        'generate_text' => 'Real sales invoices are created for every instalment that is due and not yet issued.',
        'generated' => ':count invoice(s) issued: :codes',
        'nothing_due' => 'Nothing is due today — the next instalment has not come round yet',
        'paused_blocked' => 'The subscription is paused — resume it before issuing invoices',

        'confirm_yes' => 'Confirm',
        'confirm_no' => 'Cancel',
    ],

    'log' => [
        'paused' => 'Paused recurring invoice :number',
        'resumed' => 'Resumed recurring invoice :number',
        'generated' => 'Issued :count invoice(s) from recurring invoice :number',
    ],

    'edit_issued_note' => 'Changes apply to future invoices only — instalments already issued are untouched.',

    'guide' => [

        'facts' => [
            'a' => 'A full sales invoice is issued every cycle',
            'b' => 'Each issue decreases stock and posts an entry',
            'c' => 'Pausing stops the next one and touches nothing issued',
        ],

        'money_flow' => [
            'title' => 'Where the money goes',
            'a' => 'The schedule itself has no financial effect: no entry and no receivable until the first issue happens.',
            'b' => 'And every invoice it issues is posted like any sales invoice: debit the customer, credit sales, tax to its own account.',
            'c' => 'So its effect is cumulative: a mistake shows not once but every cycle until the schedule is paused.',
        ],

        'noedit' => [
            'title' => 'What can and cannot be changed',
            'a' => 'The schedule itself is edited and paused while it stands - it is a rule, not a document.',
            'b' => 'But the invoices it has issued are final documents: pausing or editing the schedule voids none of them.',
        ],

        'index' => ['title' => 'What this screen is, and what its contents cause'],
        'create' => ['title' => 'Before you save this recurring invoice'],
        'show' => ['title' => 'What you can do from this screen'],

        'lead_index' => 'These are not invoices: these are **issue schedules**. Each row is a rule that issues a real invoice every period without anyone touching it.',
        'lead_create' => 'You are writing a rule, not a document. What you save here will issue real invoices automatically — decreasing stock, posting entries and creating receivables.',
        'lead_show' => 'This is an issue schedule. What matters: when the next invoice goes out, and how many have gone out so far.',

        'what_is' => [
            'title' => 'What a recurring invoice is',
            'a' => 'A subscription or contract that repeats: set its lines, its cycle and its first invoice date, and the system issues the rest.',
            'b' => 'Issuing is done by a scheduled job driven by "Scheduled tasks". If `cron` is stopped on the server, **nothing is issued** no matter how correct the schedule is.',
            'c' => 'It can also be issued by hand with "Generate now", for anyone who will not wait for the cycle.',
        ],

        'effects' => [
            'title' => 'What each issue causes',
            'a' => 'A full sales invoice is created: a number from the invoice sequence, a stock decrease, a ledger entry and a receivable.',
            'b' => 'The invoice date is the issue date and is checked against "Accounting periods" — a schedule starting inside a closed period is refused at save.',
            'c' => 'Pausing stops the next issue and touches nothing already issued: issued invoices are final documents and are not voided by pausing their schedule.',
        ],

        'statuses' => [
            'title' => 'Statuses and the cap',
            'a' => '"Active" means the cycle runs; "Paused" means it has stopped issuing — it does not mean deleted.',
            'b' => 'The plan cap counts **active** schedules, not everything ever created, so pausing an old one frees a slot for a new one.',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside it',
            'a' => '"Scheduled tasks" is what actually drives issuing — check it first if issuing stops.',
            'b' => '"Document number sequences", "Accounting periods" and "Chart of accounts" govern the resulting invoices exactly as they govern any invoice.',
        ],
    ],
];

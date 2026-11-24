<?php

/*
|--------------------------------------------------------------------------
| Sales returns
|--------------------------------------------------------------------------
|
| Wording for the "Sales returns" and "Create return" screens.
| Vocabulary shared with invoices stays in `sales_invoice`, and general
| UI wording in `sales_settings.ui` — neither is duplicated here.
|
*/

return [

    'index_title' => 'Sales returns',
    'index_subtitle' => 'Sales returns and the invoices they were issued against',
    'create_title' => 'Create a sales return',
    'plural' => 'Sales returns',
    'singular' => 'Sales return',

    'main_treasury_note' => 'No treasury is assigned to your account, so the main treasury is used for refunds. Ask an administrator for a dedicated one.',

    'reasons' => [
        'damaged' => 'Damaged item',
        'wrong_item' => 'Wrong item',
        'customer_request' => 'Customer request',
        'quality_issue' => 'Quality issue',
        'other' => 'Other',
    ],

    'stats' => [
        'total' => 'Returns total',
        'count' => 'Number of returns',
    ],

    'filters' => [
        'number' => 'Return number',
        'source_invoice' => 'Original invoice',
    ],

    'empty' => [
        'title' => 'No sales returns',
        'hint' => 'Nothing matches the current filters',
    ],

    'form' => [
        'date' => 'Return date',
        'warehouse' => 'Warehouse (returned to)',
        'reason' => 'Return reason',
        'refund_yes' => 'Refund the customer',
        'refund_yes_hint' => 'The amount is paid out of the treasury',
        'refund_no' => 'No refund',
        'refund_no_hint' => 'The amount is credited to the customer account',
        'refund_method' => 'Refund method',
        'refund_reference' => 'Reference number',
        'refund_reference_hint' => 'Transaction or receipt number',
        'items_hint' => 'Quantities default to the original invoice; adjust what is being returned.',
        'return_quantity' => 'Return quantity',
        'original_quantity' => 'Original: :n',
        'after_discount' => 'After discount',
        'return_total' => 'Return amount',
        'extra_discount' => 'Extra discount',
        'shipping_hint' => 'The shipping cost refunded to the customer. It is added to the return the same way it is added to the invoice.',
        'shipping_from_invoice' => 'Taken from invoice :code shipping (:amount) — adjust it for a partial return.',
        'attachments_title' => 'Upload return documents',
        'attachments_hint' => 'Photos of damaged items or any other document',
        'notes' => 'Return notes',
        'notes_placeholder' => 'Add details about the reason for the return…',
        'document' => 'Return and customer details',
        'items' => 'Return lines',
        'refund' => 'Refund',
        'refund_hint' => 'How the amount is refunded to the customer, and from which treasury',
        'back' => 'Back to returns',
    ],

    'show' => [
        'title' => 'Return invoice :code',
        'number' => 'Return number',
        'source_invoice' => 'Original invoice',
        'client' => 'Customer',
        'date' => 'Return date',
        'sales_person' => 'Sales person',
        'added_by' => 'Added by',
        'currency' => 'Currency',
        'journal_entry' => 'Journal entry',
        'taxed' => 'Taxable',
        'currency_frozen' => 'The document currency is fixed on save; switching the currency in Currencies does not reprice an issued return.',
        'documents' => 'Documents',
        'from_settings' => 'From sales templates',
        'builtin_template' => 'Built-in template',
        'print' => 'Print',
        'pdf' => 'Download PDF',
        'send_email' => 'Send by email',
        'whatsapp' => 'WhatsApp',
        'whatsapp_body' => 'Hello :client,

This is return invoice :number. You can view it here:
:link

Best regards,
:business',
        'no_template' => 'No template designed for this document',
        'tab_preview' => 'Preview',
        'tab_items' => 'Lines',
        'tab_taxes' => 'Taxes',
        'tab_activity' => 'Activity log',
        'no_items' => 'No lines on this return',
        'no_taxes' => 'No taxes on this return',
        'no_activity' => 'No activity recorded yet',
        'unknown_user' => 'Unknown user',
        'no_branch' => 'Unknown branch',
        'tax_name' => 'Tax',
        'tax_rate' => 'Rate',
        'tax_amount' => 'Amount',
    ],

    'guide' => [

        'facts' => [
            'a' => 'Goods return to the warehouse on a receipt permit',
            'b' => 'An entry is posted that lowers the customer balance',
            'c' => 'The source invoice is marked and accepts no further collection',
        ],

        'money_flow' => [
            'title' => 'Where the money goes',
            'a' => 'The return value is credited to the customer account and debited to sales returns.',
            'b' => 'Tax is reversed by the same amount into its own account, so nothing whose goods came back stays in the tax base.',
            'c' => 'And the cost of the returned goods goes back to stock in an entry opposing the original cost-of-sales entry.',
        ],

        'noedit' => [
            'title' => 'After saving there is no going back',
            'a' => 'An issued return is neither amended nor deleted - as with every issued document.',
            'b' => 'Correcting a mistake in it means a new document that reverses it, not editing it.',
        ],

        'index' => ['title' => 'What this screen is, and what its contents cause'],
        'create' => ['title' => 'Before you save this return'],
        'show' => ['title' => 'What you can do from this screen'],

        'lead_index' => 'This is the returns list: each row is goods that came back into stock and value that came off the customer\'s balance.',
        'lead_create' => 'A return reverses a sale that happened: it puts goods back in the warehouse, reduces the customer balance, and writes an opposing entry. Make sure this is what you want before saving.',
        'lead_show' => 'This is an issued return. Its effect on stock and on the ledger has already happened.',

        'what_is' => [
            'title' => 'Return vs credit note — the difference',
            'a' => 'A **return** is for goods that physically came back: it receives them into the warehouse and reduces the balance together.',
            'b' => 'A **credit note** reduces a value with no goods coming back — a pricing error or a later discount. It does not touch stock.',
            'c' => 'Using one for the other corrupts one of the two figures: stock that rises with no goods, or goods that came back with no effect on the balance.',
        ],

        'effects' => [
            'title' => 'What happens on save',
            'a' => 'A stock receipt permit for the line quantities into the chosen warehouse.',
            'b' => 'An entry debiting sales returns and crediting the customer, with tax reversed by the same amount.',
            'c' => 'The source invoice is marked as having a return, and accepts no further collection afterwards.',
            'd' => 'And once saved there is no edit and no delete — as with every issued document.',
        ],

        'blockers' => [
            'title' => 'What prevents a save',
            'a' => 'A date inside a closed accounting period.',
            'b' => 'Reaching your subscription plan allowance for returns.',
            'c' => 'The customer location is unrecorded — for reps, unless you hold "create a return from any location".',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside it',
            'a' => '"Warehouses" decides where goods go back to, and "Chart of accounts" the returns and tax accounts.',
            'b' => '"Document number sequences", "Accounting periods" and "Currencies" apply as they do on an invoice.',
        ],
    ],
];

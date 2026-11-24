<?php

/*
|--------------------------------------------------------------------------
| Credit notes
|--------------------------------------------------------------------------
|
| Wording for the "Credit notes" and "Create credit note" screens.
| Vocabulary shared with invoices stays in `sales_invoice`, and general
| UI wording in `sales_settings.ui` — neither is duplicated here.
|
*/

return [

    'index_title' => 'Credit notes',
    'index_subtitle' => 'Notes that reduce a customer balance, referenced to invoices',
    'create_title' => 'Create credit note',
    'plural' => 'Credit notes',
    'singular' => 'Credit note',
    'new' => 'New credit note',
    'source_invoice' => 'Source invoice',
    'source_invoice_hint' => 'The invoice this note was issued against',
    'no_source' => 'No source invoice',
    'cancel_hint' => 'The entry is reversed and quantities returned; the note stays on record',

    'stats' => [
        'total' => 'Credit notes total',
        'count' => 'Number of notes',
    ],

    'filters' => [
        'number' => 'Note number',
        'item' => 'Item',
    ],

    'status' => [
        'draft' => 'Draft',
        'pending' => 'Pending approval',
        'approved' => 'Approved',
        'converted' => 'Converted to invoice',
        'cancelled' => 'Cancelled',
    ],

    'actions' => [
        'cancel' => 'Cancel note',
    ],

    'empty' => [
        'title' => 'No credit notes',
        'hint' => 'Nothing matches the current filters',
    ],

    'form' => [
        'document' => 'Note and customer details',
        'items' => 'Note lines',
        'refund' => 'Refund',
        'refund_hint' => 'How the amount is refunded to the customer, and from which treasury',
        'goods' => 'Goods movement',
        'goods_hint' => 'The warehouse the goods are returned to',
        'release_date' => 'Release date',
        'credit_date' => 'Note date',
        'tax_type' => 'Tax type',
        'payment_method' => 'Payment method',
        'treasury' => 'Treasury',
        'select_payment_method' => 'Select a payment method',
        'select_treasury' => 'Select a treasury',
        'back' => 'Back to credit notes',
    ],

    'tax_types' => [
        1 => 'Value added tax',
        2 => 'Zero rated',
        3 => 'Exempt',
    ],

    'show' => [
        'title' => 'Credit note :code',
        'number' => 'Credit note number',
        'client' => 'Customer',
        'credit_date' => 'Credit note date',
        'release_date' => 'Release date',
        'added_by' => 'Added by',
        'sales_person' => 'Sales person',
        'currency' => 'Currency',
        'treasury' => 'Treasury',
        'warehouse' => 'Warehouse',
        'payment_method' => 'Payment method',
        'journal_entry' => 'Journal entry',
        'taxed' => 'Taxable',
        'currency_frozen' => 'The document currency is fixed on save; switching the currency in Currencies does not reprice an issued credit note.',
        'documents' => 'Documents',
        'from_settings' => 'From sales templates',
        'builtin_template' => 'Built-in template',
        'print' => 'Print',
        'send_email' => 'Send by email',
        'whatsapp' => 'WhatsApp',
        'whatsapp_body' => 'Hello :client,

This is credit note :number. You can view it here:
:link

Best regards,
:business',
        'no_template' => 'No template designed for this document',
        'tab_preview' => 'Preview',
        'tab_items' => 'Lines',
        'tab_taxes' => 'Taxes',
        'tab_activity' => 'Activity log',
        'no_items' => 'No lines on this credit note',
        'no_taxes' => 'No taxes on this credit note',
        'no_activity' => 'No activity recorded yet',
        'unknown_user' => 'Unknown user',
        'no_branch' => 'Unknown branch',
        'tax_name' => 'Tax',
        'tax_rate' => 'Rate',
        'tax_amount' => 'Amount',
        'cancel_title' => 'Cancel this credit note?',
        'cancel_text' => 'The journal entry is reversed and the quantities go back to the warehouse; the credit note stays on record as cancelled.',
        'cancel_confirm' => 'Yes, cancel it',
        'cancel_dismiss' => 'Keep it',
        'cancelled' => 'Cancelled',
    ],

    'guidance' => [
        'index' => 'This is the credit note list: each row is a note that reduces what a customer owes, and the figures above follow the selected date range, not the whole table.',
    ],

    'guide' => [

        'facts' => [
            'a' => 'An entry is posted that lowers the customer balance',
            'b' => 'Tax is reversed by the same amount',
            'c' => 'Stock is not touched at all',
        ],

        'noedit' => [
            'title' => 'After saving there is no going back',
            'a' => 'An issued note is neither amended nor deleted - it is cancelled, its entry reversed, and its row stays in the list.',
            'b' => 'Cancelling is a separate permission from creating, so the issuer is not necessarily the one who can cancel.',
        ],

        'create' => [
            'title' => 'Before you save this note',
            'lead' => 'A credit note reduces what the customer owes. First make sure this is the right document and not a return: if goods actually came back, the correct document is a return that puts them into stock.',
        ],

        'flow' => [
            'title' => 'What happens when you press Save',
            'a' => 'The number is reserved from the `credit-note` section of "Document number sequences" on save, not before.',
            'b' => 'The value is credited to the customer and debited to sales returns, and tax is reversed by the same amount into its own account.',
            'c' => 'Linking it to a source invoice is optional, but it is what lets reports pair an invoice with its reductions.',
            'd' => 'After saving there is no edit and no delete — it is cancelled, its entry reversed, and its row stays in the list.',
        ],

        'blockers' => [
            'title' => 'What prevents a save',
            'a' => 'A date inside a closed accounting period.',
            'b' => 'Reaching your subscription plan allowance for credit notes.',
            'c' => 'The customer location is unrecorded — for reps, unless you hold "create a credit note from any location".',
        ],

        'show' => [
            'title' => 'What you can do from this screen',
            'lead' => 'This is an issued note. Its entry is posted and the customer balance has already moved because of it.',
        ],

        'show_actions' => [
            'title' => 'The actions and what they cause',
            'a' => 'Cancelling writes an opposing entry that returns the balance to where it was — it does not erase the document: it stays in the list marked "Cancelled".',
            'b' => 'Cancelling is a separate permission from creating, so the person who issued it is not necessarily the one who can cancel it.',
            'c' => 'Sending by email dispatches the document as it stands; what is sent cannot be recalled.',
        ],

        'index' => [
            'title' => 'What this screen is, and what its contents cause',
        ],

        'what_is' => [
            'title' => 'What a credit note is',
            'a' => 'A credit note is a document that reduces what the customer owes: used when an issued invoice must be lowered — a pricing error, a later discount, or goods never delivered.',
            'b' => 'It is not a return: a return puts goods back into stock, whereas a credit note may be issued with no stock movement at all. If goods actually came back, the correct document is a return.',
            'c' => 'Once saved it is neither amended nor deleted, per ZATCA rules — it is cancelled, its entry reversed, and its trace stays visible in the log.',
        ],

        'money_flow' => [
            'title' => 'Where the money goes',
            'a' => 'The note value is credited to the customer account and debited to sales returns, with tax reversed by the same amount into its own account.',
            'b' => 'Linking the note to a source invoice is optional but useful: it records which invoice was reduced, and keeps reports that pair an invoice with its reductions correct.',
            'c' => 'The note date is what "Accounting periods" is checked against: a date inside a closed period is refused before anything is written.',
            'd' => 'The note settles the customer\'s debts itself: the reference invoice first, then the opening balance, then invoices from oldest to newest. Each invoice shows what was taken back as a "Returned" line, as a sales return does — not as paid. Whatever is left over stays as a credit balance for the customer.',
        ],

        'statuses' => [
            'title' => 'Statuses in the table',
            'a' => '"Issued" is the normal state after saving — the entry is posted and the customer balance has already changed.',
            'b' => '"Cancelled" means its entry was reversed by an opposing entry; the row deliberately stays in the list, so a document that once existed never disappears.',
            'c' => 'Cancelling is a separate permission from creating, so the person who issued a note is not necessarily the one who can cancel it.',
        ],

        'stats' => [
            'title' => 'The figures on the cards above',
            'a' => 'The first two cards follow the date range and filters currently applied, not the whole table.',
            'b' => 'The third card shows the range being read — so the period is known before the figures are questioned.',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside it',
            'a' => 'The `credit-note` section of "Document number sequences" decides the number prefix and width.',
            'b' => '"Currencies" decides the symbol and decimals; a document freezes its currency on save.',
            'c' => '"Chart of accounts" decides the sales-returns and tax accounts the amount lands in.',
            'd' => '"Accounting periods" decides which dates are accepted.',
        ],
    ],

    'stats_hints' => [
        'total' => 'An amount, over the selected date range',
        'count' => 'Number of documents over the same range',
        'range' => 'The range currently being read',
    ],

    'filter_hints' => [
        'number' => 'Matches part of the number as well as the whole of it',
        'client' => 'Shows this customer\'s notes only',
        'status' => 'A cancelled note stays in the list — filtering hides it, nothing deletes it',
        'date_range' => 'The range is inclusive and compares against the note date, not the entry date',
    ],

    // What the note settles from the customer's debts — `ClientDebtSettlement`
    'settlement' => [
        'title' => 'What this note will settle',
        'show_title' => 'What this note settled',
        'hint' => 'Settled automatically: the reference invoice if one is chosen, then the opening balance, then invoices from oldest to newest.',
        'empty' => 'Choose the customer and add items to see what will be settled.',
        'nothing_open' => 'This customer has no open debts — the whole note stays as a credit balance.',
        'item' => 'Item',
        'date' => 'Date',
        'due' => 'Due',
        'pay' => 'Settles',
        'paid' => 'Settled',
        'left' => 'Left',
        'previous_balance' => 'Opening / previous balance',
        'invoice' => 'Invoice :code',
        'total' => 'Total settled',
        'surplus' => 'The customer keeps a credit balance of :amount — deducted from future invoices or refunded.',
        'cash_hint' => 'If the customer pays cash for goods they keep, record it with a receipt voucher — it settles what is left in the same order.',
        'none_saved' => 'This note settled no debt — its value is a credit balance for the customer.',
    ],
];

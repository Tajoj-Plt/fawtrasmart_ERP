<?php

/*
|--------------------------------------------------------------------------
| Sales documents — screen strings
|--------------------------------------------------------------------------
|
| Mirrors resources/lang/ar/sales_documents.php key for key. Quotations
| first; the invoice, return and note screens share the same structure and
| will reuse these keys as they are rebuilt.
|
*/

return [

    'ui' => [
        'home' => 'Home',
        'sales' => 'Sales',
        'breadcrumb' => 'Breadcrumb',
        'add' => 'Add',
        'save' => 'Save',
        'update' => 'Update',
        'cancel' => 'Cancel',
        'back' => 'Back',
        'search' => 'Search',
        'reset' => 'Reset',
        'advanced_search' => 'Advanced search',
        'filter_title' => 'Search and filter',
        'filters_active' => 'Filters applied',
        'all' => 'All',
        'none' => 'None',
        'optional' => 'optional',
        'required_note' => 'Fields marked * are required',
        'actions' => 'Actions',
        'options' => 'Options',
        'print' => 'Print',
        'export' => 'Export',
        'export_shown' => 'Export what is shown',
        'export_all' => 'Export all results',
        'pdf' => 'PDF',
        'view' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'copy' => 'Duplicate',
        'send_email' => 'Send by email',
        'whatsapp' => 'Send via WhatsApp',
        'yes' => 'Yes',
        'no' => 'No',
        'per_page_show' => 'Show',
        'per_page_unit' => 'rows',
        'per_page_aria' => 'Rows per page',
        'showing' => 'Showing :from–:to of :total',
        'pages' => 'Pages',
        'page_first' => 'First',
        'page_prev' => 'Previous',
        'page_next' => 'Next',
        'page_last' => 'Last',
        'loading' => 'Loading…',
        'load_failed' => 'Could not load the data',
        'action_failed' => 'The action could not be completed',
        'no_permission' => 'You are not allowed to do this',
        'unknown' => 'Not set',
        'dash' => '—',
    ],

    // Renamed from 'quote': the same screens now serve sales orders too.
    'doc' => [
        'title' => 'Quotations',
        'singular' => 'Quotation',
        'subtitle' => 'Quotations sent to clients, their status, and which of them became invoices',
        'create_title' => 'New quotation',
        'edit_title' => 'Edit quotation',
        'show_title' => 'Quotation :number',
        'number' => 'Quotation no.',
        'date' => 'Quotation date',
        'expiry_date' => 'Valid until',
        'client' => 'Client',
        'created_by' => 'Created by',
        'convert' => 'Convert to invoice',
        'converted_to' => 'Converted to invoice :code',
        'new' => 'New quotation',
        'list' => 'Quotations',
        'whatsapp_body' => 'Hello :client

Quote :number is ready. You can view it here:
:link

Regards,
:business',
    ],

    'status' => [
        'label' => 'Status',
        'any' => 'Any status',
        'Draft' => 'Draft',
        'Pending' => 'Pending',
        'Approved' => 'Approved',
        'Converted_to_Invoice' => 'Converted to invoice',
        'Cancelled' => 'Cancelled',
        'Expired' => 'Expired',
    ],

    'stats' => [
        'total' => 'Quotes total',
        'count' => 'Number of quotes',
    ],

    'filters' => [
        'keywords' => 'Search',
        'keywords_placeholder' => 'Quotation number or client name…',
        'client' => 'Client',
        'any_client' => 'All clients',
        'number' => 'Quotation no.',
        'created_by' => 'Created by',
        'any_user' => 'All users',
        'currency' => 'Currency',
        'any_currency' => 'All currencies',
        'total_from' => 'Total from',
        'total_to' => 'Total to',
        'date_range' => 'Date range',
        'item' => 'Item',
        'item_placeholder' => 'An item inside the quotation…',
        'period_default' => 'The default range comes from the current accounting period',
    ],

    'columns' => [
        'number' => 'Quotation no.',
        'date' => 'Date',
        'client' => 'Client',
        'items_count' => 'Items',
        'total' => 'Total',
        'status' => 'Status',
        'actions' => 'Actions',
        'items_unit' => 'item|items',
    ],

    'sections' => [
        'document' => 'Document details',
        'items' => 'Items',
        'extra' => 'Additional details',
        'discount' => 'Discount and settlement',
        'shipping' => 'Shipping',
        'documents' => 'Attachments',
        'notes' => 'Notes and terms',
    ],

    'fields' => [
        'client' => 'Client',
        'select_client' => 'Select a client',
        'number' => 'Quotation no.',
        'number_hint' => 'Generated automatically from sequence numbering — not editable here.',
        'date' => 'Quotation date',
        'expiry_date' => 'Valid until',
        'expiry_hint' => 'Shown to the client; it does not close the quotation automatically',
        'employee' => 'Sales rep',
        'select_employee' => 'Select a rep',
        'status' => 'Status',
        'tax_type' => 'Tax treatment',
        'tax_rate' => 'Tax rate (%)',
        'currency' => 'Currency',
        'notes' => 'Notes',
        'notes_placeholder' => 'Terms, validity, and anything agreed with the client…',
        'terms' => 'Terms',
        'discount_amount' => 'Quotation discount',
        'discount_type' => 'Discount type',
        'shipping_cost' => 'Shipping cost',
        'shipping_tax' => 'Sales shipping tax',
        'shipping_tax_hint' => 'Pick a tax and the system computes its amount on the shipping cost. "No tax" means exactly that - shipping does not inherit the line tax.',
        'attachments' => 'Attachments',
    ],

    'tax_types' => [
        1 => 'Taxable',
        2 => 'Zero rated',
        3 => 'Exempt',
    ],

    'discount_types' => [
        'amount' => 'Amount',
        'percentage' => 'Percent %',
    ],

    'items' => [
        'legacy_line_discount' => 'Some lines on this document were saved with a line discount. It is kept as is but no longer shown — add any new discount from the Discount tab.',
        'row' => '#',
        'item' => 'Item',
        'select_item' => 'Select an item',
        'description' => 'Description',
        'quantity' => 'Qty',
        'unit' => 'Unit',
        'base_unit' => 'Base unit',
        'unit_price' => 'Unit price',
        'discount' => 'Discount',
        'tax_1' => 'Tax',
        'tax_2' => 'Second tax',
        'no_tax' => 'No tax',
        'total' => 'Total',
        'add_row' => 'Add item',
        'remove_row' => 'Remove item',
        'remove_confirm' => 'Remove this item from the quotation?',
        'last_row' => 'A quotation needs at least one item',
    ],

    'totals' => [
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'shipping' => 'Shipping',
        'tax' => 'Tax',
        'grand_total' => 'Grand total',
        'deposit' => 'Deposit (advance payment)',
        'paid' => 'Paid',
        'returned' => 'Returned',
        'remaining' => 'Remaining',
    ],

    'upload' => [
        'click' => 'Click to upload',
        'or' => 'or',
        'browse' => 'browse your files',
        'hint' => 'PDF, image or Word document — up to 5 MB',
        'current' => 'Current attachment',
        'replace' => 'Uploading a new file replaces the current one',
    ],

    'js' => [
        'confirm_save' => 'Save this quotation?',
        'confirm_save_text' => 'Totals and tax will be saved as shown above',
        'confirm_yes' => 'Yes, save',
        'confirm_cancel' => 'Go back',
        'confirm_discard' => 'Discard what you entered?',
        'confirm_discard_text' => 'Items and entered fields will be cleared',
        'saving' => 'Saving…',
        'need_client' => 'Select the client first',
        'need_date' => 'Enter the quotation date',
        'need_items' => 'Add at least one item',
        'need_product' => 'Select the item on row :row',
        'need_quantity' => 'Quantity on row :row must be greater than zero',
        'incomplete' => 'The form is incomplete',
    ],

    'empty' => [
        'title' => 'No quotations',
        'hint' => 'Nothing matches the current search',
        'hint_none' => 'Start by creating the first quotation',
        'items_title' => 'No items',
    ],

    'confirm' => [
        'delete_title' => 'Delete quotation :number?',
        'delete_text' => 'The quotation and its items will be removed. This cannot be undone.',
        'convert_title' => 'Convert quotation :number to an invoice?',
        'convert_text' => 'A sales invoice will be created from these items, stock will be drawn down, and the journal entry posted.',
    ],

    'email' => [
        'action' => 'Send by email',
        'title' => 'Email document :number',
        'recipient' => 'Recipient address',
        'no_client_email' => 'No address saved on :client — type one here for this message only.',
        'template' => 'Attached document template',
        'template_default' => 'default',
        'template_builtin' => 'Built-in system template',
        'template_none' => 'No designed template for this type yet; the built-in one is used. Templates are designed under Document Templates.',
        'note' => 'Note to the customer (optional)',
        'note_hint' => 'A line shown inside the message body.',
        'attachment_note' => 'A PDF copy of the document is attached to this message.',
        'submit' => 'Send',
        'sent' => 'Document sent to :email',
        'failed' => 'Could not send: :reason',
        'no_recipient' => 'No valid email address to send to',
        'no_template' => 'Email template ":code" is missing or inactive — seed it from System Update → Sales document email templates.',
        'no_template_reservations' => 'Email template ":code" is missing or inactive — seed it from System Update → Reservation and appointment email templates.',
    ],

    'flash' => [
        'created' => 'Quotation :number created',
        'updated' => 'Quotation :number updated',
        'deleted' => 'Quotation :number deleted',
        'converted' => 'Quotation :number converted to invoice :code',
        'save_failed' => 'The quotation could not be saved: :error',
    ],

    'notes' => [
        'period_blocked' => 'The accounting period for this date is closed: :reason',
        'expired_no_convert' => 'This quote has expired and cannot be converted to an invoice. Extend its validity date, restore its status, then convert it.',
        'period_hint' => 'This date falls in a closed period — change it or ask for the override permission',
        'no_template' => 'No print template has been designed for quotations yet, so the built-in one is used.',
        'template_menu' => 'Print layouts',
        'default_template' => 'Default template',
        'currency_frozen' => 'The quotation currency is frozen on save; changing the default currency later does not affect it.',
    ],

    'log' => [
        'created' => 'Created quotation :number for :client',
        'updated' => 'Updated quotation :number',
        'deleted' => 'Deleted quotation :number',
        'converted' => 'Converted quotation :number to invoice :code',
        'printed' => 'Printed quotation :number',
        'emailed' => 'Emailed quotation :number to the client',
        'timeline' => 'Activity',
        'no_events' => 'No events recorded on this quotation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Print documents — labels and pick list
    |--------------------------------------------------------------------------
    |
    | Rendered for browser printing via `sales::partials.print-document`
    | rather than Dompdf, which cannot shape Arabic text.
    |
    */

    'print' => [
        'print' => 'Print',
        'close' => 'Close',

        'parcel_label' => 'Parcel label',
        'picklist' => 'Pick list',
        'shipping_label' => 'Shipping label',

        'invoice_no' => 'Invoice no.',
        'invoice_date' => 'Invoice date',
        'printed_at' => 'Printed on',

        'bill_to' => 'Bill to',
        'ship_to' => 'Ship to',
        'sender' => 'Sender',
        'recipient' => 'Recipient',
        'warehouse' => 'Warehouse',

        'no_address' => 'No address on file',
        'no_phone' => 'No phone on file',
        'no_items' => 'This invoice has no lines',

        'row' => '#',
        'item' => 'Item',
        'description' => 'Description',
        'unit' => 'Unit',
        'quantity' => 'Qty',
        'line_total' => 'Total',
        'picked' => 'Picked',

        'lines' => 'Lines',
        'pieces' => 'Total pieces',
        'total' => 'Invoice total',

        'cod' => 'Collect on delivery',
        'cod_none' => 'Paid — nothing to collect',

        'notes' => 'Notes',
        'picker' => 'Picked by',
        'checker' => 'Checked by',
        'receiver' => 'Received by',
    ],

    'guide' => [

        'facts' => [
            'a' => 'Touches no stock and posts no entry',
            'b' => 'Creates no receivable against the customer',
            'c' => 'Its effect begins the day it becomes an invoice',
        ],

        'money_flow' => [
            'title' => 'Where the money is in this document',
            'a' => 'Nowhere yet: a quotation posts no entry and does not appear on the customer statement.',
            'b' => 'Its figures are a price offered for review, not amounts earned - which is why it appears in neither sales nor profit reports.',
            'c' => 'Conversion to an invoice is what creates the entire financial effect, all at once.',
        ],

        'terms' => [
            'title' => 'Sales rep and payment terms',
            'a' => 'The **sales rep** is who the sale is credited to, and it carries over to the invoice on conversion — commission and targets follow it. It may differ from whoever created the document.',
            'b' => 'The list is limited to **your data scope**: an own-data scope shows only you, a team scope shows your team, a branch scope shows your branch. A missing name means a narrower scope, not a broken screen.',
            'c' => '**Payment terms** are the days allowed for payment being offered — leave blank for a cash sale. The resulting due date appears under the field as you type.',
            'd' => 'They carry over to the invoice on conversion, so they are never typed twice. The follow-up itself — badge, filter and scheduled job — starts at the invoice: there is no receivable to chase before that.',
        ],

        'noedit' => [
            'title' => 'Editing and deleting - allowed here',
            'a' => 'Unlike an invoice: while it is a quote it is a draft, freely edited and deleted, since it has no entry to reverse and no stock to return.',
            'b' => 'Except after conversion: a converted quote is marked and cannot be converted again, and the resulting invoice follows invoice rules, not quotation rules.',
        ],

        'index' => ['title' => 'What this screen is, and what its contents cause'],
        'create' => ['title' => 'Before you save this quotation'],
        'show' => ['title' => 'What you can do from this screen'],

        'lead_index' => 'This is the quotation list: each row is a quote sent to a customer that has not become an invoice yet — no effect on stock and none on the ledger.',
        'lead_create' => 'A quotation is a promise of a price, not a sales document. It decreases no stock, posts no entry and creates no receivable — its effect starts the day it is converted to an invoice.',
        'lead_show' => 'This is an issued quotation. What matters is its state: still open for acceptance, expired, or already turned into an invoice?',

        'what_is' => [
            'title' => 'What a quotation is',
            'a' => 'A document telling the customer: these goods, at this price, until this date. It binds nobody until it is accepted.',
            'b' => 'That is why it can be freely edited and deleted — unlike an invoice, which is untouchable once issued. While it is a quote, it is a draft.',
            'c' => 'The expiry date is not decoration: past it the quote is "expired" and cannot be converted without renewing the date.',
        ],

        'conversion' => [
            'title' => 'Conversion to an invoice — the decisive moment',
            'a' => 'Conversion creates a **new invoice** from the quote lines, which decreases stock, posts the entry and creates the receivable. The quote is marked "converted" and cannot be converted again.',
            'b' => 'The invoice takes its number from the invoice sequence, not from the quote, and its date is the conversion date, not the quote date.',
            'c' => 'Whether notes and terms carry over depends on the "Copy notes on conversion" setting under "Invoice settings".',
            'd' => 'Promotions and gift offers are not applied to a quotation unless "Apply offers to quotations" is switched on.',
        ],

        'statuses' => [
            'title' => 'Statuses',
            'a' => '"Draft", "Sent", "Accepted" and "Rejected" are chosen by hand — follow-up, not computation. The picker appears only when "Manual quotation statuses" is on.',
            'b' => '"Converted" is never chosen by hand: only the system sets it, at conversion, so a conversion that never happened cannot be claimed.',
            'c' => '"Expired" is written by a scheduled job that runs daily over quotes past their expiry date (with an optional grace period in days, set in Scheduled jobs). An expired quote can still be **edited but not converted**: extend its date and restore its status to finish it.',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside it',
            'a' => '"Invoice settings" decides manual statuses, offer application, and note copying on conversion — and can hide the whole section from the menu via "Disable quotations".',
            'b' => '"Document number sequences" decides the quote number prefix, and "Currencies" the symbol and decimals.',
            'c' => 'Your subscription plan caps quotations per month — the bar at the top of the screen says how many are left.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sales orders — only what differs from the quotation wording
    |--------------------------------------------------------------------------
    |
    | The same screens serve both documents, so only the keys whose wording
    | must change are written here; anything absent falls back to the root of
    | this file. See `QuoteController::documentContext()`.
    |
    */

    'sales_order' => [

        'doc' => [
            'title' => 'Sales orders',
            'singular' => 'Sales order',
            'subtitle' => 'Sales orders confirmed by clients, their status, and which of them became invoices',
            'create_title' => 'New sales order',
            'edit_title' => 'Edit sales order',
            'show_title' => 'Sales order :number',
            'number' => 'Order no.',
            'date' => 'Order date',
            'expiry_date' => 'Delivery date',
            'convert' => 'Convert to sales invoice',
            'new' => 'New sales order',
            'list' => 'Sales orders',
            'whatsapp_body' => 'Hello :client

Sales order :number is ready. You can view it here:
:link

Regards,
:business',
        ],

        'stats' => [
            'total' => 'Sales orders total',
            'count' => 'Order count',
        ],

        'filters' => [
            'keywords_placeholder' => 'Order number or client name…',
            'number' => 'Order no.',
            'item_placeholder' => 'An item name inside the order…',
        ],

        'columns' => [
            'number' => 'Order no.',
        ],

        'fields' => [
            'number' => 'Order no.',
            'number_hint' => 'Generated automatically from document numbering — not editable here.',
            'date' => 'Order date',
            'expiry_date' => 'Delivery date',
            'expiry_hint' => 'The agreed delivery date — for follow-up; it does not close the order automatically',
            'notes_placeholder' => 'Delivery and payment terms, and anything else agreed…',
            'discount_amount' => 'Discount on the order',
        ],

        'items' => [
            'remove_confirm' => 'Remove this line from the order?',
        ],

        'js' => [
            'confirm_save' => 'Save the sales order?',
            'need_date' => 'Enter the order date',
        ],

        'empty' => [
            'title' => 'No sales orders',
            'hint_none' => 'Start by creating the first sales order',
        ],

        'confirm' => [
            'delete_title' => 'Delete sales order :number?',
            'delete_text' => 'The order and its lines will be deleted. This cannot be undone.',
            'convert_title' => 'Convert order :number into a sales invoice?',
            'convert_text' => 'A sales invoice will be created from the order lines, stock will be deducted, and the journal entry posted.',
        ],

        'flash' => [
            'created' => 'Sales order :number created',
            'updated' => 'Sales order :number updated',
            'deleted' => 'Sales order :number deleted',
            'converted' => 'Sales order :number converted into invoice :code',
            'save_failed' => 'Could not save the sales order: :error',
        ],

        'notes' => [
            'no_template' => 'No print template has been designed for sales orders yet, so the built-in one is used.',
            'currency_frozen' => 'The order currency is fixed on save and is not changed by switching the default currency later.',
        ],

        'log' => [
            'created' => 'Created sales order :number for client :client',
            'updated' => 'Edited sales order :number',
            'deleted' => 'Deleted sales order :number',
            'converted' => 'Converted sales order :number into invoice :code',
            'printed' => 'Printed sales order :number',
            'emailed' => 'Emailed sales order :number to the client',
            'no_events' => 'No events recorded on this order',
        ],

        'guide' => [
            'facts' => [
                'a' => 'Touches no stock and posts no journal entry',
                'b' => 'Creates no receivable on the client',
                'c' => 'Its effect begins the day it becomes an invoice',
            ],

            'money_flow' => [
                'title' => 'Where the money is in this document',
                'a' => 'Nowhere yet: a sales order posts no entry and does not appear on the client statement.',
                'b' => 'Its figures are an agreed commitment, not amounts earned — so it appears in neither the sales reports nor the profit figures.',
                'c' => 'Converting it into an invoice is what creates the whole financial effect, at once.',
            ],

            'noedit' => [
                'title' => 'Editing and deleting — allowed here',
                'a' => 'Unlike an invoice: while it is still an order it can be edited and deleted, since no entry has to be reversed and no stock returned.',
                'b' => 'Until it is converted: a converted order is marked and cannot be converted twice, and the resulting invoice follows the invoice rules.',
            ],

            'index' => ['title' => 'What is this screen, and what follows from what is on it?'],
            'create' => ['title' => 'Before you save the sales order'],
            'show' => ['title' => 'What you can do from this screen'],

            'lead_index' => 'This is the sales order list: every row is an order the client confirmed and that has not become an invoice yet — no effect on stock or on the ledger.',
            'lead_create' => 'A sales order is a commitment to deliver, not a sales document. It does not reduce stock, post an entry, or create a receivable — its effect begins the day it becomes an invoice.',
            'lead_show' => 'This is a confirmed order. What matters is its status: is it still in progress, or has it become an invoice?',

            'what_is' => [
                'title' => 'What a sales order is',
                'a' => 'A document that fixes what the client accepted: these items, these quantities, this price, and this delivery date. It is the step after the quotation and before the invoice.',
                'b' => 'Its counterpart in purchasing is the purchase order that becomes a purchase invoice — the same cycle: agreement, then fulfilment, then invoice.',
                'c' => 'So it can be edited and deleted while it has not been converted: a commitment under review, not a tax document that has been issued.',
            ],

            'conversion' => [
                'title' => 'Converting into an invoice — the decisive moment',
                'a' => 'On conversion a **new invoice** is created from the order lines, reducing stock, posting the entry, and creating the receivable. The order is marked converted and cannot be converted again.',
                'b' => 'The invoice takes its number from the invoice sequence, not from the order number, and its date is the conversion date.',
                'c' => 'Whether notes and terms carry over depends on the "copy notes on conversion" setting in invoice settings.',
                'd' => 'Conversion checks stock and the client credit limit exactly as a direct invoice does, so the order may be rejected then rather than earlier.',
            ],

            'statuses' => [
                'title' => 'Statuses',
                'a' => '"Draft", "Pending", "Approved" and "Cancelled" are chosen by hand — they are follow-up, not computation. The picker only appears when "give sales orders manual statuses" is enabled.',
                'b' => '"Converted" is never chosen by hand: the system sets it on conversion, so no conversion can be claimed that did not happen.',
                'c' => 'A cancelled order and a converted one are both locked: neither can be edited nor converted.',
            ],

            'settings' => [
                'title' => 'Settings that govern this screen from outside it',
                'a' => 'Invoice settings decide whether this section appears at all ("enable sales orders"), its manual statuses, and copying notes on conversion.',
                'b' => 'Document numbering decides the order number prefix (the "sales order" section), and Currencies the symbol and decimals.',
                'c' => 'The subscription plan caps how many documents a month — the bar at the top of the screen says how many are left.',
            ],
        ],

        'guide_hints' => [
            'index' => [
                'client' => 'Shows this client\'s orders only',
                'status' => 'A converted order stays in the list — the filter hides it, it is not deleted',
                'date_range' => 'The range is inclusive and compared against the order date',
            ],
        ],
    ],

    'guide_hints' => [
        'index' => [
            'number' => 'Matches part of the number as well as the whole of it',
            'client' => 'Shows this customer\'s quotations only',
            'status' => 'A converted quote stays in the list — filtering hides it, nothing deletes it',
            'date_range' => 'The range is inclusive and compares against the quote date',
        ],
    ],

    'revolving' => [
        'title' => 'What this screen is',
        'lead' => 'Revolving invoices are a preview: the screens exist but their backing has not been built — nothing is saved and no document is issued.',
        'a' => 'What you see here is interface only. Saving creates no row, and nothing is issued automatically.',
        'b' => 'What you probably want today is "Recurring invoices" — which is built and working: it issues a real invoice every cycle.',
        'c' => 'The plan defines the feature (`revolving_invoices`) with no numeric cap, so no allowance is shown here.',
    ],
];

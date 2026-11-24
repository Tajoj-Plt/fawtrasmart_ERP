<?php

/*
|--------------------------------------------------------------------------
| Sales invoices
|--------------------------------------------------------------------------
|
| Wording for the "Invoice management" and "Create sales invoice" screens.
| Shared vocabulary (search, export, print, per page…) stays in
| `sales_settings.ui` and is not duplicated here.
|
*/

return [

    // ── Screen ──
    'index_title' => 'Invoice management',
    'index_subtitle' => 'Sales invoices, payment status and returns',
    'create_title' => 'Create sales invoice',
    'documents' => 'Printouts',
    'new_invoice' => 'New invoice',
    'invoices' => 'Invoices',
    'invoice' => 'Invoice',
    'count_unit' => 'invoice',

    // ── Stat cards ──
    'stats' => [
        'sales_cost' => 'Current sales cost',
        'returns_cost' => 'Returned sales cost',
        'net_cost' => 'Net sales cost',
        'invoices_count' => ':count invoices',
        'returns_count' => ':count returns',
    ],

    // ── Filters ──
    'filters' => [
        'keywords' => 'Invoice number',
        'keywords_placeholder' => 'Printed number or id…',
        'client' => 'Customer',
        'all_clients' => 'All customers',
        'payment_status' => 'Payment status',
        'all_payment_statuses' => 'All statuses',
        'branch' => 'Branch',
        'all_branches' => 'All branches',
        'currency' => 'Currency',
        'all_currencies' => 'All currencies',
        'total_from' => 'Total greater than',
        'total_to' => 'Total less than',
        'date_range' => 'Date range',
        'added_by' => 'Added by',
        'sales_person' => 'Sales representative',
        'all' => 'All',
        'period_default' => 'Defaults to the current accounting period',
    ],

    // ── Table ──
    'table' => [
        'number' => 'Invoice no.',
        'client' => 'Customer',
        'date' => 'Date',
        'total' => 'Total amount',
        'status' => 'Status',
        'actions' => 'Actions',
        'added_by' => 'Added by: :name',
        'sales_person' => 'Rep: :name',
        'due' => 'Due: :amount',
        // What a return or credit note took back from the invoice — `returned_payment`
        'returned_amount' => 'Returned: :amount',
        'unknown_client' => 'Unknown customer',
        'no_branch' => 'No branch',
    ],

    // ── Statuses ──
    'status' => [
        'paid' => 'Paid in full',
        'partial' => 'Partially paid',
        'unpaid' => 'Unpaid',
        'received' => 'Received',
        'unknown' => 'Unknown',
        'returned' => 'Has a return',
        // Returns cleared what was left on it — `Invoice::returnSettlementState()`
        'fully_returned' => 'Returned',
        'settled' => 'Settled',
        'draft' => 'Draft',
        'under_review' => 'Under review',
        'failed' => 'Failed',
    ],

    // ── A second return is refused — `Invoice::returnBlockReason()` ──
    'return_blocked' => [
        'has_return' => 'This invoice already has a return; another one cannot be made.',
        'credit_note' => 'A credit note was issued for this invoice — that counts as its return, so another one cannot be made.',
        'not_sale' => 'This document is a return, not a sales invoice — it cannot be returned.',
    ],

    // The same block in the opposite direction: a credit note on a returned invoice
    'credit_note_blocked' => [
        'has_return' => 'This invoice already has a return — that is its refund, so a credit note cannot be issued for it as well.',
        'has_credit_note' => 'This invoice already has a credit note; another one cannot be issued.',
        'not_sale' => 'This document is a return, not a sales invoice — a credit note cannot be issued for it.',
    ],

    // Return limits — Modules\Sales\Support\ReturnLimits
    'return_limits' => [
        'invoice_missing' => 'The original invoice was not found.',
        'no_lines' => 'There are no lines to return — add at least one.',
        'not_on_invoice' => '":item" is not a line on invoice :code.',
        'quantity_positive' => 'The quantity of ":item" must be greater than zero.',
        'price_negative' => 'The price of ":item" cannot be negative.',
        'price_exceeded' => 'The return price of ":item" (:price per base unit) is higher than its sale price on the invoice (:original).',
        'quantity_exceeded' => 'Cannot return :requested base units of ":item": sold :sold, already returned :returned, so :remaining can be returned.',
        'client_mismatch' => 'Invoice :code does not belong to this customer.',
        'cancel_stock_short' => 'The credit note cannot be cancelled: ":item" has :available in ":store", but the note added :needed — the goods have left the warehouse since.',
    ],

    // ── Actions ──
    'actions' => [
        'quick_add_client' => 'Add a new customer',
        'quick_add_product' => 'Add a new product',
        'show' => 'View',
        'edit' => 'Edit',
        'pdf' => 'Download PDF',
        'print' => 'Print',
        'print_tax' => 'Print tax invoice',
        'send' => 'Send to customer',
        'add_payment' => 'Add payment',
        'delete' => 'Delete',
        'import' => 'Import',
        'export_visible' => 'Export visible rows (Excel)',
    ],

    // ── Empty state ──
    'empty' => [
        'title' => 'No invoices',
        'hint' => 'Nothing matches the current filters',
    ],

    // ── Create screen ──
    'form' => [
        'document' => 'Invoice and customer details',
        'items' => 'Invoice lines',
        'extras' => 'Additional details',
        'notes' => 'Notes',
        'required_note' => 'Fields marked * are required',
        'add_row' => 'Add line',
        'clear_rows' => 'Clear lines',
        'row' => '#',
        'remove_row' => 'Remove line',
        'back' => 'Back to invoices',
    ],

    // ── Settings banner on the create screen ──
    'context' => [
        'currency' => 'Currency',
        'numbering' => 'Numbering',
        'numbering_hint' => 'Number comes from "Document numbering" and is committed on save',
        'numbering_locked' => 'Generated automatically from "Document numbering" — not editable here',
        'period_blocked' => 'Saving with today\'s date is blocked',
        'settings_link' => 'Invoice settings',
    ],

    'show_title' => 'Sales invoice :code',

    // ── Show screen ──
    'show' => [
        'taxed' => 'Tax invoice',
        'returns_count' => ':count returns',
        'journal_entry' => 'Journal entry',
        'due' => 'Amount due',

        'from_settings' => 'Templates from settings',
        'builtin_template' => 'Built-in template',
        'more' => 'More',
        'labels' => 'Labels',
        'parcel_label' => 'Parcel label',
        'picklist' => 'Pick list',
        'shipping_label' => 'Shipping label',
        'send_via' => 'Send via',
        'whatsapp' => 'WhatsApp',
        'whatsapp_body' => 'Hello :client

Your invoice is ready. You can view it here:
:link

Regards,
:business',
        'add_note' => 'Add a note',
        'all_notes' => 'View notes',
        'installment_agreement' => 'Installment agreement',
        'copy' => 'Copy invoice',
        'copied_from' => 'Lines copied from invoice :code — review before saving.',
        'return' => 'Return',
        'return_invoice' => 'Issue a return invoice',
        'credit_note' => 'Issue a credit note',

        'tab_preview' => 'Preview',
        'tab_items' => 'Lines',
        'tab_payments' => 'Payments',
        'tab_stock' => 'Stock',
        'tab_returns' => 'Returns',
        'tab_profit' => 'Profit',
        'tab_signature' => 'Signature',
        'tab_activity' => 'Activity log',

        'payment_number' => 'Voucher no.',
        'treasury' => 'Treasury',
        'deducted' => 'Deducted',
        'on_hand' => 'On hand',
        'no_warehouse' => 'No warehouse',
        'sale_price' => 'Sale price',
        'cost_price' => 'Cost price',
        'profit' => 'Profit',
        'net_revenue' => 'Net revenue',
        'invoice_discount' => 'Invoice discount (allocated across lines)',
        'totals' => 'Total',
        'profit_basis' => 'Revenue is net of the line discount and the invoice discount, before tax. Cost is the current purchase cost of the item — the system does not store a per-line cost at the time of sale, so it may differ from the cost on the invoice date.',

        'signer_name' => 'Full name',
        'signer_role' => 'Role',
        'signature' => 'Signature',
        'sign_here' => 'Sign here',
        'sign_hint' => 'You can sign with a finger or a stylus',
        'signature_payment_hint' => 'Leave empty to record the signature only. Any amount here is posted as a receipt voucher with a journal entry.',
        'signature_payment_method' => 'Payment method',
        'signature_treasury' => 'Receiving treasury',
        'signature_treasury_auto' => 'Employee treasury (automatic)',
        'signature_treasury_hint' => 'The treasury the money goes into. Leave it empty to use yours, otherwise the main treasury.',
        'clear_signature' => 'Clear signature',
        'save_signature' => 'Save signature',
        'signatures_log' => 'Signature log',
        'unknown_user' => 'Unknown user',

        'no_items' => 'This invoice has no lines',
        'no_payments' => 'No payments',
        'no_payments_hint' => 'Nothing has been paid against this invoice yet',
        'no_stock' => 'No stock movement',
        'no_returns' => 'No returns against this invoice',
        'no_signatures' => 'No signatures recorded',
        'no_activity' => 'No activity recorded yet',
    ],

    // ── Create screen wording ──
    'ui' => [
        'price_list' => 'Price list',
        'warehouse' => 'Warehouse',
        'days' => 'days',
        'sales_person_scope_hint' => 'This list shows only the people within your data scope: yourself, those you manage, or your branch staff — depending on your permission.',
        'new_client' => 'New customer',
        'select_item' => 'Select product',
        'paid_amount_locked' => 'Locked: “Fully paid” is ticked, so the amount is the whole total. Untick it to record a partial payment.',
        'unit' => 'Unit',
        'base_unit' => 'Base unit',
        'conversion_factor' => 'Conversion factor',
        'available' => 'Available',
        'remaining' => 'Remaining',
        'balance' => 'Balance',
        'creditor' => 'Credit',
        'debtor' => 'Debit',
        'balanced' => 'Balanced',

        'discount_and_adjustment' => 'Discount and adjustment',
        'discount_value' => 'Discount value',
        'adjustment' => 'Adjustment',
        'adjustment_placeholder' => 'Adjustment name (e.g. cash discount)',
        'addition' => 'Addition',

        'deposit' => 'Deposit',
        'paid_amount' => 'Paid amount',
        'advance_payment' => 'Advance payment',
        'partial_advance' => 'Partial advance payment',
        'fully_paid' => 'Paid in full by the customer',
        'fully_paid_auto' => 'Enabled in "Invoice settings" and applied automatically on save',

        'delivery' => 'Delivery',
        'shipping' => 'Shipping cost',
        'discount_as_amount' => 'Amount (:symbol)',
        'discount_as_percent' => 'Percent %',
        'discount_percent_preview' => ':percent% of the line subtotal = :value',
        'advance_over_total' => 'The deposit (:advance) is larger than the invoice total (:total). Enter an amount within the total, or record the excess as a separate receipt.',
        'stock_short' => 'Not enough "​:item" in the ":store" warehouse: :needed requested, :available available.',
        'stock_short_unit' => 'Not enough "​:item" in the ":store" warehouse: :quantity :unit equals :needed base units, and only :available are available.',
        'product_missing' => 'Product #:id no longer exists - remove its line and try again.',
        'no_storehouse' => 'No warehouse exists, and stock cannot be issued without one. Add one under Inventory > Warehouses.',
        'permission_source_missing' => 'The "sales invoice" permit source is missing from the database - run System update > Permit sources.',
        'client_account_missing' => 'Client ":client" has no accounting account - create one from the client screen before invoicing.',
        'treasury_missing' => 'No treasury is available to receive the payment. Link one to the employee, or set the main treasury under Accounting accounts.',
        'order_source' => 'Order source',
        'order_source_select' => 'Choose an order source',
        'order_source_required' => 'An order source is required - pick one before saving the invoice. (Set under Sales settings > Order sources)',
        'order_source_required_hint' => 'Required because "order source mandatory" is on under Order sources.',
        'shipping_tax' => 'Sales shipping tax',
        'shipping_tax_hint' => 'Pick a tax and the system computes its amount on the shipping cost. "No tax" means exactly that - shipping does not inherit the line tax.',

        'attachments' => 'Attachments',
        'upload_new' => 'Upload a new document',
        'upload_hint' => 'Click to pick one or more files',

        'confirm_save' => 'Confirm save',
        'confirm_save_hint' => 'The invoice will be saved and its journal entry posted.',

        // A preview of what will be saved, not a bare "do you want to save?"
        'preview_title' => 'Preview the invoice before saving',
        'back_to_edit' => 'Back to editing',
        'no_items_yet' => 'The invoice has no items yet.',

        // Free line
        // Free lines were dropped; `free_item` stays to label ones saved before that.
        'free_item' => 'Free line',
        'product_required' => 'Every line needs a product picked from the list.',

        // Maximum discount
        'discount_over_limit' => 'The discount exceeds the maximum allowed (:max%).',
        'discount_over_limit_line' => 'The discount on ":item" (:percent%) exceeds the maximum allowed (:max%).',
        'discount_over_limit_general' => 'The invoice-level discount (:percent%) exceeds the maximum allowed (:max%).',
        'discount_over_limit_total' => 'The total discount — line discounts plus the invoice-level discount (:percent%) — exceeds the maximum allowed (:max%).',
        'discount_over_limit_amount' => 'The total discount on this invoice (:total) exceeds the maximum allowed discount amount (:max).',
        'discount_limit_hint' => 'Maximum allowed discount :max% — it applies to the line discount, to this invoice-level discount, and to their sum.',
        'discount_limit_amount_hint' => 'Maximum discount of :max per invoice — line discounts plus this one may not exceed it.',

        // No selling below cost — the cost of the whole line, by quantity and unit
        'below_cost_line' => 'The net of ":item" (:net) is below its cost (:cost).',

        // Manual invoice statuses
        'manual_paid_not_covered' => 'An invoice cannot be marked "Paid" unless payment covers it. Tick "Fully paid" or enter the full advance payment.',
        'ok' => 'OK',
    ],

    // ── Document fields ──
    'client_info' => 'Customer information',
    'method' => 'Delivery method',
    'print' => 'Print',
    'email' => 'Email',
    'client' => 'Customer',
    'select_client' => 'Select customer',
    'invoice_info' => 'Invoice information',
    'invoice_id' => 'Invoice number',
    'invoice_date' => 'Invoice date',
    'sales_manager' => 'Sales manager',
    'none' => 'None',
    'manager1' => 'Manager 1',
    'manager2' => 'Manager 2',
    'issue_date' => 'Issue date',
    'payment_terms' => 'Payment terms',
    'save' => 'Save',
    'add_items' => 'Add invoice lines',
    'item' => 'Item',
    'description' => 'Description',
    'unit_price' => 'Unit price',
    'quantity' => 'Quantity',
    'discount' => 'Discount',
    'percentage' => 'Percentage',
    'amount' => 'Amount',
    'tax1' => 'Tax 1',
    'tax2' => 'Tax 2',
    'total' => 'Total',
    'action' => 'Action',
    'delete' => 'Delete',
    'grand_total' => 'Grand total',
    'add_new_item' => 'Add new line',
    'save_items' => 'Save lines',
    'subtotal' => 'Subtotal',
    'tax' => 'Tax',

    'guidance' => [
        'index' => 'This is the sales invoice list: each row is an invoice issued to a customer, and the figures above follow the selected date range, not the whole table.',
    ],

    'guide' => [

        'facts' => [
            'a' => 'Stock is decreased and an issue permit created',
            'b' => 'The entry is posted and cost of goods sold recorded',
            'c' => 'The customer balance rises by the invoice value',
        ],

        'terms' => [
            'title' => 'Sales rep and payment terms',
            'a' => 'The **sales rep** is who the sale is credited to — commission, targets and sales reports follow this field, and it may differ from whoever keyed the invoice in.',
            'b' => 'The list is limited to **your data scope**: an own-data scope shows only you, a team scope shows your team, a branch scope shows your branch. Missing names mean a narrower scope, not a broken screen.',
            'c' => '**Payment terms** are the days allowed for payment, counted from the issue date — leave blank for cash sales. The resulting due date appears under the field as you type.',
            'd' => 'What you enter here drives follow-up: an overdue / due-soon badge on the list and the invoice page, an overdue filter on the list, and a daily scheduled job (“Customer payment reminders”) that alerts the sales rep before and after the due date.',
        ],

        'noedit' => [
            'title' => 'After saving there is no going back',
            'a' => 'An issued invoice is neither amended nor deleted, under ZATCA rules — not because the screen is lacking.',
            'b' => 'What stands in its place: a return if goods came back, or a credit note if the value was reduced without one.',
        ],

        'create' => [
            'title' => 'Before you save this invoice',
            'lead' => 'Everything entered here becomes a final document the moment you save: a receivable on the customer, a decrease in stock, and a journal entry. It cannot be amended or deleted afterwards.',
        ],

        'flow' => [
            'title' => 'What happens when you press Save',
            'a' => 'The invoice number is reserved from "Document number sequences" — on save, not when the screen opens, so leaving without saving creates no gap in the sequence.',
            'b' => 'Line quantities are deducted from the chosen warehouse, and a matching stock issue permit is created.',
            'c' => 'The total is debited to the customer and credited to sales, tax goes to its own account, and cost of goods sold is recorded in an opposing entry.',
            'd' => 'An advance payment, if entered, is recorded as a separate receipt linked to the invoice; the invoice keeps its full value and the receipt reduces the balance.',
        ],

        'blockers' => [
            'title' => 'What prevents a save',
            'a' => 'A date inside a closed accounting period — checked before anything is written, and flagged at the top of the screen before you fill anything in.',
            'b' => 'Reaching your subscription plan allowance for sales invoices — the invoice is refused with a message stating both figures.',
            'c' => 'Exceeding the customer credit limit, where one is configured.',
            'd' => 'Selling below cost or discounting above the cap — if those two settings are on in "Invoice settings"; the offending line is named in the message.',
            'e' => 'The customer location is unrecorded or too far from you — for reps, unless you hold "create an invoice from any location".',
        ],

        'show' => [
            'title' => 'What you can do from this screen',
            'lead' => 'This is an issued invoice. What you see is its current state, and the buttons above are actions upon it — some change figures and cannot be undone.',
        ],

        'show_actions' => [
            'title' => 'The actions and what they cause',
            'a' => 'Editing and deleting an issued invoice are forbidden under ZATCA rules. Their counterparts: a return if goods came back, or a credit note if the value was reduced without a return.',
            'b' => 'Recording a payment creates a receipt, a treasury movement and a journal entry; the payment status in the list is computed from those, never typed in.',
            'c' => 'Sending by email or social channels dispatches the document as it stands now — review first, since what is sent cannot be recalled.',
            'd' => 'Printouts are chosen from the "Document designs" templates, and a template is previewed before printing.',
        ],

        'show_tabs' => [
            'title' => 'What the tabs hold',
            'a' => '"Items" shows what was sold with its prices, discounts and taxes; "Stock" shows the matching issue permit and its effect on the warehouse.',
            'b' => '"Payments" lists every receipt collected against this invoice, and "Returns" what came back — and an existing return blocks any further collection.',
            'c' => '"Profit" appears only for someone holding the permission **and** with the "Show invoice profit" setting on; its absence is not a fault.',
            'd' => '"Activity" says who did what and when — it is where the answer lives when someone asks about a change to the document.',
        ],

        'index' => [
            'title' => 'What this screen is, and what its contents cause',
        ],

        'what_is' => [
            'title' => 'What an invoice is here',
            'a' => 'An invoice is a final sales document: it records a receivable against the customer, decreases stock by what was sold, and posts a journal entry to the ledger.',
            'b' => 'Saving an invoice is not a draft. Once saved it is neither deleted nor amended in any way that touches the amounts, per ZATCA rules — a credit note or a return is issued against it instead.',
            'c' => 'The invoice number comes from "Document number sequences" and is reserved on save, not when the screen opens, so opening the screen and leaving creates no gap in the sequence.',
        ],

        'money_flow' => [
            'title' => 'Where the money goes',
            'a' => 'The invoice total is debited to the customer account and credited to the sales account, with tax posted to its own account as configured under "Chart of accounts".',
            'b' => 'An advance payment entered on creation is recorded as a separate receipt linked to the invoice — the invoice keeps its full value and the receipt reduces the balance.',
            'c' => 'The invoice date is what "Accounting periods" is checked against: a date inside a closed period is refused before anything is written, not after.',
        ],

        'statuses' => [
            'title' => 'Payment statuses in the last column',
            'a' => '"Paid" means what has been settled reaches the invoice total — in cash, or applied from the customer\'s credit balance when auto-settlement is on.',
            'b' => '"Partially paid" means what has been settled is less than the total; the remainder is an outstanding balance on the customer statement.',
            'c' => 'A "Returned" badge under the status means the invoice has a return — no further collection is accepted on it, because its entry has been reversed in whole or in part.',
        ],

        'stats' => [
            'title' => 'The figures on the cards above',
            'a' => 'All three cards follow the date range and filters currently applied — changing a filter changes them; they are not whole-table totals.',
            'b' => '"Net" is sales minus returns over the same range, not the sum of paid invoices.',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside it',
            'a' => '"Invoice settings" decides which fields appear on the create screen: overall discount, second tax, signatures, warehouse and others. A field missing from create is usually a switched-off setting, not a broken screen.',
            'b' => '"Document number sequences" decides the number prefix, its width and its starting point.',
            'c' => '"Currencies" decides the currency symbol, its decimals and separators; a document freezes its currency on save, so changing the setting later does not rewrite history.',
            'd' => '"Accounting periods" and "Chart of accounts" decide which dates are accepted and which account each amount lands in.',
        ],
    ],

    'stats_hints' => [
        'sales' => 'An amount, over the selected date range',
        'returns' => 'The amount returned over the same range',
        'net' => 'Sales minus returns',
    ],

    'filter_hints' => [
        'keywords' => 'Matches part of the number as well as the whole of it',
        'client' => 'Shows this customer\'s invoices only',
        'payment_status' => 'Computed on save from the advance payment and any customer credit applied',
        'totals' => 'Both bounds are inclusive and compare against the invoice total after tax',
        'date_range' => 'The range is inclusive and compares against the invoice date, not the entry date',
        'added_by' => 'Who created the document in the system — may differ from the salesperson',
        'sales_person' => 'The rep the invoice is attributed to in reports and commissions',
    ],

    'settings_notice' => [
        'enabled_title' => 'Settings currently in force on this screen',
        'disabled_title' => 'Optional sections currently switched off',
        'manage' => 'Configure under "Invoice settings"',
        'delivery_off' => 'The delivery section is hidden because "Disable delivery options" is on — switch it off under Sales settings → Invoice settings.',
        'profit_off' => 'The profit tab is hidden because "Show invoice profit" is off — switch it on under Sales settings → Invoice settings.',
        'social_off' => 'The social-channel send buttons are hidden because "Send documents over social channels" is off.',
    ],
    'coupon' => [
        'section' => 'Discount coupon',
        'label' => 'Coupon code',
        'placeholder' => 'Enter the coupon code',
        'apply' => 'Apply',
        'clear' => 'Remove',
        'hint' => 'Deducted from the document total after line discounts; it does not change the tax base.',
        'applied' => 'Coupon :code applied — :amount off',
        'applied_free_shipping' => 'Coupon :code applied — free shipping',
        'row' => 'Coupon discount (:code)',
        'not_found' => 'No coupon with the code :code',
        'inactive' => 'Coupon :code is switched off',
        'not_started' => 'The coupon does not start before :date',
        'expired' => 'The coupon expired on :date',
        'limit_reached' => 'The coupon has reached its usage limit',
        'client_limit_reached' => 'This customer has reached their limit for this coupon',
        'client_not_eligible' => 'The coupon is reserved for other customers',
        'first_order_only' => 'The coupon is for a first purchase only, and this customer has earlier ones',
        'min_amount' => 'The coupon requires a document total of at least :amount',
        'excluded_product' => 'The document contains a product excluded from the coupon',
        'no_eligible_product' => 'No product in the document is covered by the coupon',
        'no_shipping' => 'A free-shipping coupon does not apply to a document without shipping',
        'zero_discount' => 'The coupon discount on this document is zero',
        'disabled' => 'Coupons are switched off under "Invoice settings"',
    ],

    'from_quote' => [
        'section' => 'Create from a quotation',
        'label' => 'Quotation',
        'placeholder' => 'Pick a quotation',
        'hint' => 'Only the selected customer quotations are listed; picking one fills the invoice from it.',
        'pick_client_first' => 'Pick the customer first to see their quotations',
        'none_for_client' => 'This customer has no convertible quotations',
        'already_converted' => 'This quotation has already been converted to an invoice',
        'filled' => 'Invoice filled from quotation :code',
        'option' => ':code — :total',
    ],
    /* == Customer scope == */
    'client_out_of_scope' => 'The selected customer is outside the customers you are allowed to work with.',
];

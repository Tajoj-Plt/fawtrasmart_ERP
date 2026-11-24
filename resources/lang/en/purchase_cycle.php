<?php

/*
 * Shared strings for the purchase-cycle sections:
 * Requests for Quotation, Purchase Quotations and Purchase Order Requests.
 *
 * The Purchase Orders section keeps its own richer file, purchase_orders.php.
 * Everything here is shared and takes the document name via :document.
 */

return [
    'period_blocked' => 'Saving under the current date is blocked',

    'sections' => [
        'purchase_invoice' => 'Purchase invoices',
        'quotation_request' => 'Requests for Quotation',
        'purchase_quotation' => 'Purchase Quotations',
        'purchase_order_request' => 'Purchase Order Requests',
        'purchase_return' => 'Purchase Returns',
        'debit_note' => 'Debit Notes',
    ],

    'documents' => [
        'purchase_invoice' => 'Purchase invoice',
        'quotation_request' => 'the request for quotation',
        'purchase_quotation' => 'the purchase quotation',
        'purchase_order_request' => 'the purchase order',
        'purchase_return' => 'the purchase return',
        'debit_note' => 'the debit note',
    ],

    // Statuses — shared across every section of the cycle
    'statuses' => [
        'all' => 'All statuses',
        'Under Review' => 'Under review',
        'approval' => 'Approved',
        'disagree' => 'Rejected',
        'Convert to Quotation' => 'Converted to quotation',
        'Convert to Order' => 'Converted to order',
    ],

    'fields' => [
        'code' => 'Number',
        'title' => 'Title',
        'supplier' => 'Supplier',
        'suppliers' => 'Suppliers',
        'creator' => 'Created by',
        'date' => 'Date',
        'due_date' => 'Due date',
        'branch' => 'Branch',
        'valid_days' => 'Valid for',
        'status' => 'Status',
        'items_count' => 'Line items',
        'grand_total' => 'Total',
        'currency' => 'Currency',
        'notes' => 'Notes',
        'attachments' => 'Attachments',
        'datetime' => 'Date & time',
    ],

    'actions' => [
        'create' => 'Add new',
        'show' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'duplicate' => 'Duplicate',
        'print' => 'Print',
        'print_preview' => 'Print preview',
        'download_pdf' => 'Download PDF',
        'export' => 'Export to Excel',
        'options' => 'Options',
        'search' => 'Search',
        'reset' => 'Reset',
        'back' => 'Back',
        'convert_to_invoice' => 'Convert to purchase invoice',
        'convert_to_order' => 'Convert to purchase order',
        'quick_add_supplier' => 'Supplier not listed? Add it from here',
    ],

    // Button tooltips — shown on hover (Bootstrap Tooltip)
    'tooltips' => [
        'export' => 'Export the currently displayed quotation-request list to a CSV file — opens directly in Excel.',
        'print_preview' => 'Print the currently displayed quotation-request list after filters are applied, without the page header and controls.',
        'filters_badge' => 'Number of active advanced-search fields right now — zero means no extra filtering beyond the basic search.',
    ],

    'empty' => [
        'title' => 'No records yet',
        'filtered' => 'No results match your search criteria',
        'unknown' => 'Not set',
    ],

    'templates' => [
        'from_settings' => 'Configured document templates',
    ],

    'search' => [
        'heading' => 'Search & Filter',
        'results_count' => 'Results: :count',
    ],

    // The trend line under each summary card — same slot as in Activity Logs
    'stats' => [
        'all_records' => 'All records',
        'share' => ':percent% of total',
    ],

    // Shipping, discount and adjustment — extra pricing sections
    'confirm' => [
        'convert_text' => 'A purchase invoice will be created with its journal entry and stock movement. This cannot be undone.',
        'convert_to_order_text' => 'A purchase order will be created from this price quotation. This cannot be undone.',
    ],

    'settlement' => [
        'heading' => 'Additional details',
        // Shown where the tab would be when the screen asks for it and the setting hides it
        'disabled_by_setting' => 'The "Discount & adjustment" tab is switched off in Purchase options, so the document-wide discount and the shipping cost do not appear here. Turn it on under: Purchases → Purchase options → Documents → "Settlement tab on purchase forms".',
        'tab_discount' => 'Discount & adjustment',
        'tab_shipping' => 'Shipping',
        'tab_documents' => 'Attach documents',
        'tax_type' => 'Tax type',
        'choose_tax' => 'Choose tax',
        'choose_tax_hint' => 'Leave empty to follow the line taxes.',
        'upload_new' => 'Upload a new document',
        'upload' => 'Upload',
        'shipping_cost' => 'Shipping cost',
        'discount_amount' => 'Overall discount',
        'discount_type' => 'Discount type',
        'amount' => 'Amount',
        'percentage' => 'Percentage',
        'adjustment_label' => 'Adjustment label',
        'adjustment_placeholder' => 'e.g. cash discount',
        'adjustment_type' => 'Adjustment type',
        'adjustment_value' => 'Adjustment value',
        'deduct' => 'Deduct',
        'add' => 'Add',
    ],

    // Account names used in conversion messages
    'accounts' => [
        'cash_treasuries_parent' => 'Cash treasuries parent account',
        'bank_accounts_parent' => 'Bank accounts parent account',
        'purchases' => 'Purchases',
        'vat_paid' => 'Input VAT',
        'purchase_discount' => 'Purchase discount',
        'purchase_shipping' => 'Purchase shipping cost',
        'suppliers' => 'Suppliers',
        'main_treasury' => 'Main treasury',
        'clients_account' => 'Clients account',
        'bad_debts' => 'Bad debts',
        'allowance_for_doubtful_debts' => 'Allowance for doubtful debts',
    ],

    'messages' => [
        'created' => ':document was created successfully.',
        'updated' => ':document was updated successfully.',
        'deleted' => ':document was deleted successfully.',
        'duplicated' => ':document was duplicated successfully.',
        'approved' => ':document was approved.',
        'rejected' => ':document was rejected.',
        'approval_cancelled' => 'Approval cancelled — :document is back under review.',
        'rejection_undone' => 'Rejection undone — :document is back under review.',
        'validation_failed' => 'Please check the submitted data.',
        'account_reason' => [
            'no_row' => 'No setting with that key (:key) exists in the table at all — contact support.',
            'unlinked' => 'The row exists on the screen but no account was chosen for it — open it, pick the account and save.',
            'inactive' => 'The row is mapped but disabled — enable it on the screen.',
            'deleted_account' => 'The row points at account #:id, which no longer exists in the chart of accounts — map it to another account.',
        ],
        'create_failed' => 'Could not create :document: :error',
        'update_failed' => 'Could not update :document: :error',
        'delete_failed' => 'Could not delete :document.',
        'pdf_failed' => 'Could not generate the PDF file: :error',
        'no_template' => 'No active document template is configured for this type in Settings.',
        'account_missing' => 'The «:account» account is not mapped under Accounting Settings.',
        'converted' => ':document was converted into a purchase invoice.',
        'convert_failed' => 'Could not convert into a purchase invoice: :error',
        'not_approved_yet' => ':document must be approved before it can be converted.',
    ],

    'log' => [
        'created' => ':document **:code** created',
        'updated' => ':document **:code** updated',
        'deleted' => ':document **:code** deleted',
        'duplicated' => ':document **:code** duplicated',
        'approved' => ':document **:code** approved',
        'rejected' => ':document **:code** rejected',
        'approval_cancelled' => 'Approval cancelled for :document **:code**',
        'rejection_undone' => 'Rejection undone for :document **:code**',
        'printed' => ':document **:code** print preview opened',
        'exported' => ':document **:code** downloaded as PDF',
        'status_changed' => 'Status changed for :document **:code**',
        'converted' => ':document **:code** converted',
        'note_added' => 'A note titled ":note" was added to :document **:code**',
        'note_deleted' => 'The note ":note" was removed from :document **:code**',
    ],

    // Purchase invoices — payment/receiving states and screen-specific labels
    'invoice' => [
        'due_alert_title' => 'Supplier dues alert',
        'due_alert' => ':supplier has one invoice due for :amount, earliest on :date (overdue: :overdue)|:supplier has :count invoices due for :amount, earliest on :date (overdue: :overdue)',
        'payment_status' => 'Payment status',
        'receiving_status' => 'Receiving status',
        'paid' => 'Paid',
        'partial' => 'Partially paid',
        'unpaid' => 'Unpaid',
        'received' => 'Received',
        'partially_received' => 'Partially received',
        'not_received' => 'Not received',
        'return' => 'Return',
        'source' => 'Source',
        'account' => 'Account',
        'tax_type' => 'Tax type',
        'tag' => 'Tag',
        'created_by' => 'Created by',
        'date_from' => 'Date (from)',
        'date_to' => 'Date (to)',
        'per_page' => 'Show',
        'terms' => 'Payment terms (days)',
        'terms_hint' => 'Days until due from the invoice date',
        'second_tax' => 'Second tax',
        'advance_payment' => 'Advance payment',
        'advance_payment_type' => 'Advance type',
        'full_advance_payment' => 'Advance payment is the total invoice',
        'full_advance_payment_hint' => 'When enabled, the full invoice amount is recorded as the advance payment automatically — just pick the receiving treasury.',
        'manual_status_hint' => 'The "manual purchase invoice status" setting is on — the status you pick here is the one saved, and the system will not derive it from the amount paid. Amounts and the outstanding balance are still calculated as usual, so an invoice can read "paid" while a balance remains if you choose so.',
        'payment_forced_by_setting' => 'The "automatic full payment" setting is on in Purchase settings — this invoice will be recorded as fully paid regardless of this checkbox or the entered amount. Turn the setting off to control this manually.',
        'advance_reference_number' => 'Advance reference no.',
        'received_date' => 'Receipt date',
        'deposit_fee' => 'Deposit fee',
        'net_total' => 'Net total',
        'paid_amount' => 'Paid',
        'due_value' => 'Outstanding',
        'add_payment' => 'Add payment',
        'create_return' => 'Create return',
        'create_debit_note' => 'Debit note',
        'receive' => 'Confirm receipt',
        'receiving_hint' => 'Confirming receipt issues the warehouse permit and records stock movement into the main warehouse.',
        'receiving_forced_by_setting' => 'The "treat as received/handed over by default" setting is on in Purchase settings — this document will be saved as received and its items will move in/out of the warehouse immediately, regardless of this checkbox. Turn the setting off to control this manually.',
        'cost_center' => 'Cost centre',
        'cost_center_current' => 'Current cost centre',
        'cost_center_none' => 'Not set',
        'cost_center_prompt' => 'Choose the cost centre this document should be attributed to',
        'cost_center_select' => 'Select a cost centre',
        'cost_center_saved' => 'Cost centre assigned successfully',
        'cost_center_save_error' => 'An error occurred while assigning the cost centre',
        'notes_pdf' => 'Notes file',
        'add_note' => 'Add note',
        'all_notes' => 'All notes',
        'payments' => 'Payments',
        'no_payments' => 'No payments recorded',
        'payment_method' => 'Payment method',
        'select_payment_method' => 'Select a payment method',
        'payment_date' => 'Payment date',
        'amount' => 'Amount',
        'treasury' => 'Treasury',
        'select_treasury' => 'Select a treasury',
        'warehouse' => 'Warehouse',
        'empty_title' => 'No purchase invoices',
        'empty_hint' => 'No purchase invoice has been added yet',
    ],

    /*
     * Goods movement on reversing documents (purchase return, debit note).
     *
     * Both send goods out of the warehouse back to the supplier — they issue
     * a goods-issue permit and deduct the items — so calling that "receiving"
     * inverts the meaning for the reader. These words are shared by both
     * documents because the movement is the same, and they replace the
     * `invoice.*` wording on those two screens only.
     */
    'receiving' => [
        'warehouse' => 'Warehouse',
        'warehouse_main' => 'main',
        'warehouse_hint' => 'Items enter this warehouse when receipt is confirmed, and their cost is taken from it.',
    ],

    'goods' => [
        'tab' => 'Goods return',
        'status' => 'Return status',
        'confirm' => 'Confirm goods return',
        'date' => 'Return date',
        'hint' => 'Confirming the return issues the goods-issue permit and deducts the items from the warehouse.',
        'returned' => 'Returned',
        'partially_returned' => 'Partially returned',
        'not_returned' => 'Not returned',
    ],

    /*
     * Purchase return — shares the purchase-invoice screen design, and adds
     * one rule of its own: a return belongs to exactly one invoice, so it is
     * never created from scratch and never issued twice for the same invoice.
     */
    'return' => [
        'empty_title' => 'No purchase returns',
        'empty_hint' => 'A return is created from an existing purchase invoice: open the invoice, then "Create return"',
        'source_invoice' => 'Original invoice',
        'source_hint' => 'The return is bound to this invoice and cannot be moved off it',
        'refund_tab' => 'Refund',
        'refund_amount' => 'Refunded amount',
        'refund_amount_hint' => 'Leave empty to derive it from the return share of what was paid on the original invoice',
        'refund_treasury' => 'Refund treasury',
        'already_exists' => 'Invoice **:invoice** already has return **:code** — an invoice may not be returned twice.',
        'from_invoice_only' => 'A purchase return is not created from this screen: open the purchase invoice you want to return, then choose "Create return".',
        'no_edit' => 'Per approved policy a purchase return cannot be edited after creation. Issue a debit note to settle any difference.',
        'returned_badge' => 'Returned',
        'has_return' => 'This invoice has a return',
    ],

    /*
     * Debit note — a purchase document issued to the supplier. Shares the
     * screen and design with returns; unlike a return it need not move stock.
     */
    'debit_note' => [
        'empty_title' => 'No debit notes',
        'empty_hint' => 'No debit note has been added yet',
        'source_invoice' => 'Reference invoice',
        'source_hint' => 'Optional — links the note to the purchase invoice it arose from',
        'no_edit' => 'Per approved policy a debit note cannot be edited after creation. Issue a new debit note to settle any difference.',
        'blocked_by_return' => 'Invoice **:invoice** already has a return, so no debit note can be issued against it.',
    ],

    /*
     * Supplier management and account statement.
     */
    'supplier' => [
        'section' => 'Supplier management',
        'document' => 'Supplier',
        'statement' => 'Supplier account statement',
        'empty_title' => 'No suppliers',
        'empty_hint' => 'No supplier has been added yet',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'inactive_block_warning' => 'The "block documents for an inactive supplier" setting is on in Purchase settings — once deactivated, no new invoice, return, or debit note can be saved for this supplier until it is reactivated.',
        'due' => 'Has outstanding dues',
        'number' => 'Supplier no.',
        'balance' => 'Balance',
        'opening_balance' => 'Opening balance',
        'email' => 'Email',
        'mobile' => 'Mobile',
        'phone' => 'Phone',
        'address' => 'Address',
        'tax_number' => 'Tax number',
        'commercial_registration' => 'Commercial registration',
        'from_date' => 'From date',
        'to_date' => 'To date',
        'total_debit' => 'Total debit',
        'total_credit' => 'Total credit',
        'final_balance' => 'Closing balance',
        'no_operations' => 'No movement on this account in the selected period',
        'no_account' => 'No accounting account is linked to this supplier',
    ],

    /*
     * Supplier payments.
     */
    'payment' => [
        'section' => 'Supplier payments',
        'document' => 'Payment voucher',
        'empty_title' => 'No payments',
        'empty_hint' => 'No supplier payment has been recorded yet',
        'number' => 'Voucher no.',
        'amount' => 'Amount',
        'date' => 'Payment date',
        'method' => 'Payment method',
        'treasury' => 'Treasury',
        'invoice' => 'Invoice',
        'status' => 'Status',
        'cancelled' => 'Cancelled',
        'confirmed' => 'Confirmed',
        'bulk' => 'Bulk payment',
        'total_paid' => 'Total paid',
        'due_invoices' => 'Outstanding invoices',
        'pick_supplier' => 'Pick a supplier to list their outstanding invoices',
        'distribution' => 'How the amount is spread across invoices',
        'summary' => 'Payment summary',
        'total_due' => 'Total outstanding',
        'selected_invoices' => 'Selected invoices',
        'remaining_after' => 'Remaining after payment',
        'pay_full' => 'Pay the full outstanding amount',
        'execute' => 'Execute bulk payment',
        'blocked_by_return' => 'Invoice **:invoice** has return **:code** — no payment can be recorded against a returned invoice.',
        'from_supplier_only' => 'Bulk payment opens from the supplier screen: open the supplier, then choose "Bulk payment".',
        'supplier_locked' => 'This screen is scoped to this supplier and cannot be switched here',
        'completed' => 'Settled',
        'incomplete' => 'Not settled',
        'completed_hint' => 'The invoice is fully paid',
        'incomplete_hint' => 'Invoice still owes :amount',
        'no_invoice' => 'Payment without an invoice',
        'no_treasury' => 'No treasury available — check your treasury settings.',
        'journal_entry' => 'Journal entry',
        'activity' => 'Activity log',
        'no_activity' => 'No activity recorded yet',
        'unknown_user' => 'Unknown user',
        'no_branch' => 'Unknown branch',

        /* ── Supplier payment create / show screens ── */
        'create_title' => 'Add a supplier payment',
        'show_title' => 'Payment voucher :code',
        'back' => 'Back',
        'form' => [
            'document' => 'Voucher details',
            'supplier' => 'Supplier',
            'invoice' => 'Invoice',
            'invoice_date' => 'Invoice date',
            'currency' => 'Currency',
            'grand_total' => 'Invoice total',
            'paid' => 'Paid',
            'remaining' => 'Remaining',
            'amount' => 'Amount',
            'amount_hint' => 'Cannot exceed what the invoice still owes.',
            'date' => 'Payment date',
            'method' => 'Payment method',
            'select_method' => 'Select a payment method',
            'treasury' => 'Treasury',
            'select_treasury' => 'Select a treasury',
            'status' => 'Payment status',
            'status_derived' => 'Worked out from the amount and what the invoice still owes',
            'status_full' => 'Settles the invoice in full',
            'status_remaining' => ':amount will remain',
            'invoice_remaining' => 'Invoice still owes :amount',
            'paid_by' => 'Paid by',
            'reference' => 'Payment details',
            'reference_hint' => 'E.g. cheque or transfer number',
            'attachments' => 'Attachments',
            'attachments_hint' => 'PDF or image — 2 MB maximum',
            'notes' => 'Notes',
            'notes_placeholder' => 'Any additional notes',
            'save' => 'Add payment',
            'cancel' => 'Cancel',
            'confirm_title' => 'Confirm the payment',
            'confirm_text' => 'The voucher posts a journal entry: the treasury balance and the supplier payable both go down by the amount.',
            'confirm_yes' => 'Yes, record the payment',
            'invalid_title' => 'Invalid data',
            'invalid_text' => 'Please fill in every required field',
            'ok' => 'OK',
        ],
        'show' => [
            'tab_document' => 'Voucher preview',
            'tab_invoice' => 'Invoice and its payments',
            'tab_activity' => 'Activity log',
            'documents' => 'Print',
            'open_invoice' => 'Open invoice',
            'edit' => 'Edit',
            'cancel' => 'Cancel voucher',
            'cancel_title' => 'Cancel this voucher?',
            'cancel_text' => 'Cancelling posts a reversing entry and returns the amount to the supplier payable; the voucher stays listed as "Cancelled".',
            'cancel_confirm' => 'Yes, cancel it',
            'delete_confirm' => 'Yes, delete',
            'dismiss' => 'Go back',
            'invoice_paid' => 'Total paid on the invoice',
            'no_invoice' => 'This voucher is not linked to an invoice',
            'statement' => 'Supplier statement',
            'created_by' => 'Created by',
            'updated_at' => 'Last updated',
        ],
        'guide' => [
            'index_title' => 'What is this screen and what follows from it?',
            'create_title' => 'Before you save this voucher',
            'show_title' => 'What you can do from this screen',
            'lead_index' => 'These are supplier payment vouchers: each row is money that left a treasury and cleared part of what a supplier is owed.',
            'lead_create' => 'The voucher moves real money: it lowers the chosen treasury and the supplier payable by the same amount. Pick the right treasury before saving.',
            'lead_show' => 'This is a recorded payment voucher. Its effect on the treasury and the supplier account has already happened.',
            'effects' => [
                'title' => 'What happens on save',
                'a' => 'A journal entry debits the supplier account and credits the treasury by the same amount.',
                'b' => 'The invoice status is recalculated: "Paid" once the total reaches it, "Partially paid" otherwise.',
                'c' => 'Cancelling posts a reversing entry and never erases the voucher — it stays listed as "Cancelled".',
            ],
            'blockers' => [
                'title' => 'When saving is refused',
                'a' => 'An amount above what the invoice still owes.',
                'b' => 'A treasury whose balance cannot cover the amount (after any supplier advance is applied).',
                'c' => 'A date in a locked accounting period, or an invoice that has a return.',
            ],
        ],
    ],

    /*
     * Client account statement — same template family as the supplier one.
     */
    'client_statement' => [
        'title' => 'Client account statement',
    ],

    /*
     * Page guidance — one line at the top of each screen saying what it is for.
     *
     * Kept here rather than in the templates: the wording changes and gets
     * translated far more often than the screen does.
     */
    'guidance' => [
        'invoice_index' => 'This screen lists the purchase invoices recorded against your suppliers. Track payment status (paid, partially paid, unpaid) and goods receipt status, and open any invoice to record a payment, raise a return, or print it. Use advanced search to narrow results by supplier, period, or account.',
        'invoice_create' => 'A purchase invoice records what you actually bought from the supplier: it brings the items into stock, books the amount as a liability towards the supplier, and posts the matching journal entry. Check the lines, prices, and tax before saving — once approved an invoice cannot be edited, and its effect is corrected with a purchase return or a debit note.',
        'invoice_edit' => 'Editing an invoice recalculates its totals and tax and corrects its effect on stock and on the supplier account. Editing is only available before approval; afterwards the invoice is corrected with a purchase return or a debit note rather than by editing, so the books stay consistent with the documents already issued.',
        'invoice_show' => 'This is the full invoice: its lines, totals, taxes, payment vouchers, and the log of everything done to it. The buttons above let you record a payment to the supplier, raise a purchase return, or print the invoice using a template you designed under Document templates.',
    ],

    /*
     * Collapsible guide panel — same shape as `sales_installments.guide`.
     *
     * Differs from `guidance` above: that is a single line readable without
     * opening anything, this is a sectioned explanation for whoever wants
     * more. The shared sections (`what_is`, `money_flow`, `statuses`,
     * `settings`) are reused across screens so one explanation is never
     * written twice nor allowed to contradict itself between screens.
     *
     * `**text**` renders bold — see `purchases::components.guide`.
     */
    'guide' => [
        'what_is' => 'A purchase invoice is the document that records what you actually bought from a supplier. It is three effects at once, not a filing-cabinet record: goods enter stock, a liability is booked against the supplier, and a journal entry is posted.',

        'cycle' => [
            'title' => 'Where it sits in the purchasing cycle',
            'a' => 'A **price quotation request** to survey supplier prices, then a **purchase order** to request the items, then the **purchase invoice** when the goods arrive and their value falls due.',
            'b' => 'Nothing earlier is required: create an invoice directly if you bought without a prior purchase order.',
            'c' => 'What comes after is for correction: a **purchase return** for goods sent back, and a **debit note** for a value correction with no goods returned.',
        ],

        'money_flow' => [
            'title' => 'Where does the money sit?',
            'a' => 'Saving posts a **journal entry** and raises the supplier account balance by the invoice value — the supplier is now owed that amount.',
            'b' => 'Payment is recorded either as an **advance payment** from the Payment tab on the invoice itself at creation time, or as a **later payment voucher** from the Supplier payments screen or the "Record payment" button on the invoice page.',
            'c' => 'The "outstanding" figure is total minus everything paid. It is a calculated result, not a field you type.',
            'd' => 'Shipping cost and the overall discount enter the total from the **Settlement tab** — which depends on the "Settlement tab on purchase forms" setting; with it off the tab does not appear and none of it is applied.',
        ],

        'statuses' => [
            'title' => 'Two statuses: payment and receipt',
            'a' => '**Payment status** (paid, partially paid, unpaid) is derived from the amounts settled — it is not chosen.',
            'b' => '**Receipt status** (received, not received) follows the "goods received" checkbox; when ticked the system creates a **stock entry permit** that raises item quantities.',
            'c' => 'With the "Manual status for the purchase invoice" setting on, a payment-status field appears in the Payment tab and the status becomes whatever you pick — amounts stay calculated as usual, so an invoice can read "paid" while a balance remains.',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside it',
            'a' => '**Purchase options** — default receipt and payment, the settlement tab, purchase-price updates, the total-discounts line, and manual status.',
            'b' => '**Document numbering** — where the invoice number comes from; what you see before saving is only a preview, and the number is consumed on save alone.',
            'c' => '**Tax settings** and **Currencies** — rates, symbol, and decimals. **Document templates** — the shape of the printout.',
            'd' => 'So a missing field or a value that fills itself in is usually one of these settings, not a fault in the screen.',
        ],

        'index' => [
            'title' => 'Purchase invoices — what is this screen?',
            'stats' => [
                'title' => 'The cards at the top',
                'a' => 'Calculated over every invoice you are entitled to see, **not over the search result** — they do not change when you change the filters.',
                'b' => 'They count invoices, not amounts: "paid" means the number of fully settled invoices.',
            ],
            'subscription' => [
                'title' => 'The subscription limit bar',
                'a' => 'Appears when your Fawtra plan caps the number of invoices per month, and shows how much of that cap is used.',
                'b' => 'Reaching the cap blocks saving a new invoice **and copying an existing one** — the message appears when you try.',
            ],
        ],

        'form' => [
            'title' => 'What happens when you save?',
            'save' => [
                'title' => 'Four things happen at once',
                'a' => '**The invoice number is consumed** from the sequence. What you see now is only a preview.',
                'b' => '**The journal entry is posted** and the supplier balance rises by the invoice value.',
                'c' => '**A stock entry permit is created** and item quantities rise — if "goods received" is ticked or default receipt is on.',
                'd' => '**Each item\'s purchase price is updated** from its price on this invoice — if the "Update product purchase price" setting is on.',
            ],
            'blocked' => [
                'title' => 'If the save is rejected',
                'a' => 'A date inside a **closed accounting period** rejects the save before anything is written.',
                'b' => 'A **deactivated supplier** rejects it too, if the "block documents for inactive suppliers" setting is on.',
                'c' => 'And reaching your **Fawtra plan limit** rejects it with a message stating how much of the limit is used.',
            ],
            'noedit' => [
                'title' => 'No editing after approval',
                'a' => 'An approved invoice cannot be edited or deleted: the books must match the documents already issued to the supplier and to Zakat.',
                'b' => 'Correct it with a **purchase return** if goods went back, or a **debit note** if only the value needs correcting.',
            ],
        ],

        'show' => [
            'title' => 'The invoice page — what can I do here?',
            'lead' => 'The invoice reference: what was agreed, what has been settled, and who changed it and when.',
            'actions' => [
                'title' => 'The buttons at the top',
                'a' => '**Record payment** opens a supplier payment voucher against this invoice; that is what moves the payment status, not editing the invoice.',
                'b' => '**Create return** appears only once: an invoice is not returned twice, and if a return already exists the button becomes a link to it.',
                'c' => '**Printing** follows the template designed under Document templates; the built-in templates (detailed, compact, thermal) remain as a fallback.',
            ],
        ],

        'facts' => [
            'stock' => 'Receipt really does raise stock',
            'ledger' => 'Saving posts a journal entry',
            'supplier' => 'The balance is booked to the supplier',
            'stockless' => 'Service and digital items get no stock permit',
        ],

        /* ═══════════ Purchase return ═══════════ */
        'return' => [
            'what_is' => 'A purchase return sends back goods you received from a supplier: it takes the items out of stock, reduces what you owe the supplier by their value, and posts the matching entry. It is the mirror image of a purchase invoice, not a document independent of one.',
            'index' => ['title' => 'Purchase returns — what is this screen?'],
            'form' => ['title' => 'Purchase return — what happens when you save?'],
            'show' => [
                'title' => 'The return page',
                'lead' => 'The return reference: which lines went back, their value, and the effect on the supplier account.',
            ],
            'source' => [
                'title' => 'A return comes from an invoice',
                'a' => 'Every return is tied to one specific purchase invoice, whose number appears on the document. Lines and prices are read from it rather than typed from scratch.',
                'b' => 'And one invoice **is not returned twice** unless the "allow multiple returns per invoice" setting is on — without it the second return is rejected with a message pointing at the existing one.',
            ],
            'effect' => [
                'title' => 'Its effect on stock and on the account',
                'a' => 'A **stock exit permit** is created and item quantities fall — if the goods-delivery box is ticked or the "treat returns as delivered by default" setting is on.',
                'b' => '**The supplier balance falls** by the value of the return: what you owed them is reduced by that amount.',
                'c' => 'And if you had already paid the invoice, whatever comes back in cash is recorded from the Refund tab with its treasury.',
            ],
        ],

        /* ═══════════ Debit note ═══════════ */
        'notice' => [
            'what_is' => 'A debit note corrects the value of a purchase invoice **without returning goods**: a wrong price, a discount agreed after issue, or a shortfall in the quantity received. It reduces what you owe the supplier by the correction and posts the matching entry.',
            'index' => ['title' => 'Debit notes — what is this screen?'],
            'form' => ['title' => 'Debit note — what happens when you save?'],
            'show' => [
                'title' => 'The debit note page',
                'lead' => 'The note reference: what was corrected, its value, and the effect on the supplier account.',
            ],
            'vs_return' => [
                'title' => 'How does it differ from a return?',
                'a' => 'A **return** is goods physically going back to the supplier, so it takes them out of stock.',
                'b' => 'A **debit note** is a value being corrected while the goods stay with you — so it should not touch stock at all.',
                'c' => 'If the error is in price or discount, it is a debit note; if the goods went back, it is a return.',
            ],
            'enable' => [
                'title' => 'This screen depends on a setting',
                'a' => 'The "Debit notes" link in the menu appears only if the "enable debit note" setting is on under Purchase options.',
            ],
        ],

        /* ═══════════ Suppliers ═══════════ */
        'supplier' => [
            'what_is' => 'A supplier is a party you buy from. Every supplier gets an **accounting account** created automatically beneath Creditors, and their invoices, returns, notes, and payments accumulate in it — so the balance you see is not a typed number but the result of posted movement.',
            'index' => ['title' => 'Suppliers — what is this screen?'],
            'form' => ['title' => 'Supplier details — what is required, and why?'],
            'show' => [
                'title' => 'The supplier page',
                'lead' => 'The supplier file: their details, their invoices, returns and payments, and their balance and statement.',
            ],
            'required' => [
                'title' => 'Which fields are required follows the settings',
                'a' => 'Name, mobile, tax number, and commercial registration: each becomes mandatory or optional from Purchase options — Suppliers section.',
                'b' => 'The same goes for uniqueness of tax number, mobile, and email: with it on, saving a second supplier with the same value is rejected.',
                'c' => 'So a field starred here may not be starred in another installation — the settings are the reference, not the template.',
            ],
            'balance' => [
                'title' => 'The opening balance and its direction',
                'a' => 'Recorded from the "Opening balance" button on the supplier page, and it needs **an amount and a direction**, not an amount alone.',
                'b' => '**They are owed by us** — the usual case: goods received before you started using the system and not yet paid for.',
                'c' => '**They owe us** — an advance payment handed to them, or a balance left in our favour from earlier dealings.',
                'd' => 'The amount is entered **without a minus sign**: direction comes from the choice, not the sign. A journal entry is posted against the Opening balances account, and saving again replaces the previous entry rather than adding to it.',
            ],
            'inactive' => [
                'title' => 'Deactivating a supplier',
                'a' => 'Deactivating deletes nothing and changes no balance: it only hides the supplier from selection lists.',
                'b' => 'And if the "block documents for inactive suppliers" setting is on, saving any invoice, return, or note for them is also rejected until they are reactivated.',
            ],
            'facts' => [
                'account' => 'Every supplier gets an account automatically',
                'balance' => 'The balance is movement, not a typed number',
            ],
        ],

        /* ═══════════ Quotation request ═══════════ */
        'quotation_request' => [
            'what_is' => 'A quotation request is sent to one or more suppliers to learn their prices on specific items, with no purchase commitment. It deliberately carries no prices — it is a question to the supplier, not a quote — and has no financial or stock effect of its own.',

            'cycle' => [
                'title' => 'Where it sits in the purchase cycle',
                'a' => 'Created directly, or **converted from an approved purchase order** — its items carry over in full so they are never re-typed.',
                'b' => 'Once approved it is converted into an actual **purchase quotation** with the prices the supplier provides — that screen ("Purchase Quotations") is what carries the prices.',
                'c' => 'The quotation request itself touches neither stock nor the books — the real effect starts later, from the quotation and then the invoice.',
            ],

            'statuses' => [
                'title' => 'Request statuses',
                'a' => '**Under review** is the default status on creation — it can still be edited or deleted.',
                'b' => '**Approved** after clicking "Approve": it becomes convertible to a quotation, and editing it is permanently locked.',
                'c' => '**Rejected** after clicking "Reject" — there is no way to cancel an approval once granted: approval is final.',
            ],

            'index' => [
                'title' => 'Quotation Requests — what is this screen?',
                'stats' => [
                    'title' => 'The cards at the top',
                    'a' => 'Calculated over every request you are allowed to see, **not the search result**.',
                    'b' => 'They count requests, not amounts — this document carries no prices at all.',
                ],
                'subscription' => [
                    'title' => 'The subscription-limit bar',
                    'a' => 'Shown when your Fawtra plan caps a monthly number of quotation requests.',
                    'b' => 'Reaching the limit blocks saving a new request — the message appears on the attempt.',
                ],
            ],

            'form' => [
                'title' => 'What happens on save?',
                'save' => [
                    'title' => 'On save',
                    'a' => '**The request number is consumed** from the numbering sequence. What shows now is a preview only.',
                    'b' => 'You can pick **more than one supplier**: the same request is sent to all of them so their offers can be compared.',
                    'c' => 'No accounting entry and no stock movement on save — the items deliberately carry no prices.',
                ],
                'noedit' => [
                    'title' => 'No editing after approval',
                    'a' => 'An approved request cannot be edited or deleted — the edit button only shows for a request "Under review".',
                    'b' => 'No cancelling approval here: unlike a purchase order, approving a quotation request is final with no undo button.',
                ],
            ],

            'show' => [
                'title' => 'The request page — what can I do from here?',
                'lead' => "The request's record: the suppliers it was sent to, its items, status, and activity log.",
                'actions' => [
                    'title' => 'The buttons at the top',
                    'a' => '**Approve/Reject** only show for a request "Under review".',
                    'b' => '**Duplicate** creates a new request with the same data — useful for repeating an earlier request without re-typing it.',
                    'c' => '**Purchase Quotation** only shows once approved, and carries the request\'s items into the screen where the supplier\'s prices are recorded.',
                ],
            ],

            'facts' => [
                'no_prices' => 'Deliberately priceless — a question to the supplier, not a quote',
                'no_ledger' => 'No accounting entry, no stock movement',
                'final' => 'Approval is final, no undo',
            ],
        ],

        /* ═══════════ Purchase order (with supplier & prices) ═══════════ */
        'order_request' => [
            'what_is' => 'This purchase order is sent to a specific supplier with specific items and prices, and converts directly into a **purchase invoice** once approved. Unlike the simple "purchase order" (no supplier, no prices), this document carries the supplier, prices, and settlement in full from the moment it is created.',

            'cycle' => [
                'title' => 'Where it sits in the purchase cycle',
                'a' => 'A document with a supplier and prices from creation — it skips the quotation-request and quotation stages that the simple purchase order goes through.',
                'b' => 'Once approved it converts to a **purchase invoice** with one button: its items, prices, and settlement carry over to the invoice in full.',
                'c' => 'No accounting entry and no stock movement before the conversion — the real effect starts from the invoice it produces.',
            ],

            'statuses' => [
                'title' => 'Order statuses',
                'a' => '**Under review** is the default status on creation — it can still be edited or deleted.',
                'b' => '**Approved**: it becomes convertible to an invoice, and editing or cancelling the approval is permanently locked.',
                'c' => '**Rejected**: rejection can be undone to bring the order back to "Under review".',
            ],

            'index' => [
                'title' => 'Purchase Orders — what is this screen?',
                'stats' => [
                    'title' => 'The cards at the top',
                    'a' => 'Calculated over every order you are allowed to see, **not the search result**.',
                    'b' => 'They count orders, not amounts.',
                ],
            ],

            'form' => [
                'title' => 'What happens on save?',
                'save' => [
                    'title' => 'On save',
                    'a' => '**The order number is consumed** from the numbering sequence. What shows now is a preview only.',
                    'b' => 'Shipping and the overall discount enter the total from the **settlement tab** — gated by the "settlement tab on purchase forms" setting.',
                    'c' => 'No accounting entry and no stock movement when the order itself is saved — the entry and the stock movement are created when it is **converted to an invoice**.',
                ],
                'noedit' => [
                    'title' => 'No editing after approval',
                    'a' => 'An approved order is **fully locked**: no editing, no cancelling approval — the only option is converting it to an invoice.',
                    'b' => 'After conversion, the order page shows a link to the resulting invoice instead of the approval buttons.',
                ],
            ],

            'show' => [
                'title' => 'The order page — what can I do from here?',
                'lead' => "The order's record: the supplier, its items and prices, totals, and activity log.",
                'actions' => [
                    'title' => 'The buttons at the top',
                    'a' => '**Approve/Reject** only show for an order "Under review".',
                    'b' => '**Convert to invoice** only shows once approved and before conversion — a link to the resulting invoice replaces it afterward.',
                    'c' => '**Print** follows the template configured under "Document Templates"; the built-in layouts remain a fallback.',
                ],
            ],

            'facts' => [
                'no_ledger_yet' => 'No accounting entry before conversion to invoice',
                'to_invoice' => 'Converts directly into a purchase invoice',
                'final' => 'Approval is final — no editing, no undo',
            ],
        ],
    ],

    /*
     * Per-card guidance — shown under the label rather than in a tooltip that
     * needs hovering. A card reading "Paid: 12" does not say whether that is a
     * count or an amount, nor whether it follows the search result or the lot.
     */
    'stats_hints' => [
        'total' => 'All purchase invoices, counted',
        'paid' => 'Settled in full',
        'partial' => 'Part-settled, a balance remains',
        'unpaid' => 'Nothing settled yet',

        'quotation_request' => [
            'total' => 'Count of every quotation request',
            'review' => 'Awaiting approval or rejection',
            'approved' => 'Approved, and can be converted to a quotation',
            'rejected' => 'Rejected',
        ],
        'order_request' => [
            'total' => 'Count of every purchase order',
            'review' => 'Awaiting approval or rejection',
            'approved' => 'Approved, and can be converted to an invoice',
            'rejected' => 'Rejected — rejection can be undone',
        ],
    ],

    /*
     * Per-filter guidance — what exactly this field searches. The difference
     * between "starts with", "matches anywhere", and whether a date range is
     * inclusive is what makes a search come back empty for no visible reason.
     */
    'scope_filters' => [
        'branch' => 'Branch',
        'all_branches' => 'All branches',
        'employee' => 'Responsible employee',
        'all_employees' => 'All employees',
    ],

    'list_scope' => [
        'period' => 'Showing: :from to :to',
        'period_default' => '(the current fiscal year — change it under "Advanced search")',
        'period_source' => [
            'fiscal_year' => '(the current fiscal year — change it under "Advanced search")',
            'period' => '(the current accounting period — change it under "Advanced search")',
            'all' => '(all dates — no fiscal year configured yet)',
            'type' => '(a default range around today, which falls outside the configured fiscal years — change it under "Advanced search")',
        ],
        'branch_all' => 'Branch: all branches',
        'branch_one' => 'Branch: :name and what is under it only — switch it at the top of the screen',
        'people_all' => 'Visibility: everyone\'s documents',
        'people_own' => 'Visibility: only what you and the people you manage created (:count users) — your account lacks "see all data"',
        'role_fallback' => 'Your account has no role under "Roles and permissions", so it was treated as a manager because its type is ":role" — link it to its role from the users screen',
    ],

    'filter_hints' => [
        'branch' => 'The branch stamped on the document when it was created — it does not follow its creator to another branch.',
        'employee' => 'The employee the document is attributed to, not the user account that entered it.',
        'supplier' => 'Invoices for this supplier only.',
        'code' => 'Part of the invoice number is enough — the full number is not required.',
        'payment_status' => 'The payment status stored on the invoice.',
        'dates' => 'By invoice date, not entry date. Both ends are included.',
        'period_default' => 'Defaults to the current accounting period',
        'created_by' => 'Who registered the invoice in the system, not who approved it.',
        'receiving_status' => "The invoice's saved goods-receiving status.",
        'source' => 'Purchase invoices only, or the returns listed alongside them in this list.',
        'goods_status' => "The document's saved goods-return status.",
        'account' => 'Documents for this accounting account only.',
        'tax_type' => 'Documents this specific tax was applied to.',
        'grand_total' => 'Grand-total value range — a minimum and a maximum, or either one alone.',
        'status' => "The document's saved approval status.",

        'quotation_request' => [
            'code' => 'Part of the request number is enough — no need for the full number.',
            'supplier' => 'Requests sent to this supplier only.',
            'status' => "The request's saved status.",
            'dates' => 'The request date or the due date, depending on the field. Both ends are inclusive.',
        ],
        'order_request' => [
            'code' => 'Part of the order number is enough — no need for the full number.',
            'supplier' => "This supplier's orders only.",
            'status' => "The order's saved status.",
            'dates' => 'The order date or the due date, depending on the field. Both ends are inclusive.',
        ],
    ],

    // Printouts — business header
    'print' => [
        'phone' => 'Phone',
        'tax_number' => 'Tax number',
        'commercial_registration' => 'Commercial registration',
        'printed_at' => 'Printed at',
        'page' => 'Page',
    ],
];

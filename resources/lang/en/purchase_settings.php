<?php

/*
|--------------------------------------------------------------------------
| Purchase settings strings
|--------------------------------------------------------------------------
|
| Every key in `PurchaseSettings::catalog()` reads two lines from here by a
| fixed convention: `options.<key>` is its title and `options.<key>_hint` its
| explanation. Add a key without its two lines and the screen shows the key
| itself, verbatim — which is how the omission announces itself.
|
*/

return [

    // ═══════════════════════ Screen ═══════════════════════
    'page' => [
        'title' => 'Purchase Settings',
        'subtitle' => 'Every option here changes the behaviour of one or more purchasing screens — each states where it applies.',
        'breadcrumb' => 'Settings',
        'active_count' => 'Options enabled',
        'save' => 'Save options',
        'reset' => 'Restore defaults',
        'reset_confirm' => 'All options will return to their default values. Continue?',
        'applies_label' => 'Applies to',
        'search' => 'Search options…',
        'no_match' => 'No option matches your search',
        'unsaved' => 'You have unsaved changes',
        'sections' => 'Sections',
        'departments_label' => 'Allowed departments',
        'departments_hint' => 'One or more departments — no employee picker in Purchases lists anyone outside them, whichever branch is chosen in the header, within the visibility scope of the user. Nothing selected means no restriction.',
        'departments_placeholder' => 'Choose departments…',
        'links_title' => 'Related settings',
        'links_hint' => 'Screens that shape purchasing but live outside this page.',
        'of' => 'of',
    ],

    // ═══════════════════════ Groups ═══════════════════════
    'groups' => [
        'employees' => 'Purchasing staff',
        'invoices' => 'Purchase invoice',
        'orders' => 'Purchase requests & orders',
        'quotations' => 'Quotations',
        'returns' => 'Purchase returns',
        'notices' => 'Debit notes',
        'suppliers' => 'Suppliers',
        'documents' => 'All purchase documents',
    ],

    'groups_hint' => [
        'employees' => 'Which departments feed the “Employee” pickers across purchase documents, suppliers and reports.',
        'invoices' => 'What is filled in automatically when a purchase invoice is saved, and who sets its status.',
        'orders' => 'Purchase request and purchase order status: derived by the system or chosen by the clerk.',
        'quotations' => 'The status of quotation requests and quotations on save.',
        'returns' => 'Return receipt, and how many returns one invoice may have.',
        'notices' => 'Whether the supplier debit note document and its menu link are available.',
        'suppliers' => 'What a supplier record requires, what may not be duplicated, and who may be traded with.',
        'documents' => 'Sections that appear across every purchase form and in its totals.',
    ],

    // ═══════════════════════ Guidance ═══════════════════════
    /*
    | The `purchases::components.guide` panel at the top of the screen (`hub`)
    | and a smaller one in each group head (`groups.<key>`). `facts.<key>_on|_off`
    | is the "what actually happens" line for a key in its current state.
    | `**text**` renders bold inside the panel.
    */
    'guide' => [
        'hub' => [
            'title' => 'What is this screen, and what follows from it?',
            'lead' => 'Every switch that shapes purchasing behaviour, in one place: each one changes what the system does when a document or supplier record is **saved**, not just how a screen looks. Changes apply to whatever is saved **after** you press “Save options” and never touch an issued document.',
            'groups' => [
                'title' => 'How the groups relate',
                'a' => 'Groups are arranged by document, not by kind of setting: purchase invoices, requests and orders, quotations, returns and debit notes each have their own switches; “Suppliers” guards the supplier record; “All purchase documents” applies to every purchase form.',
                'b' => '“Manual status” switches mean one thing across four groups: off, the system derives the document status from payment and receipt or pins it to “under review”; on, it stays as the clerk chooses.',
                'c' => '“Received by default” switches are the ones that move stock: an invoice, return or debit note saved as “received” brings goods in or sends them out **the moment it is saved**, with no tick from the clerk.',
            ],
            'first' => [
                'title' => 'What to check first',
                'a' => 'Start with “Purchase invoice”: default receipt and default payment post entries and move stock and treasury on every save.',
                'b' => 'Then “Suppliers”: whatever is off there lets a supplier be saved without a name or with a duplicate tax number, splitting one supplier’s purchases across two records.',
                'c' => 'The sidebar badges count what is on in each group; the ring in the header counts the whole screen. “Restore defaults” resets **every** switch, not just the open group.',
            ],
            'save' => [
                'title' => 'Saving',
                'a' => 'Saving applies to all seven groups together, not only the open one; the save bar appears at the bottom on the first change, and “Cancel” returns the switches to the last saved state.',
            ],
        ],

        'groups' => [
            'title' => 'Before you change this group',
            'invoices' => [
                'a' => 'Default receipt and default payment act at save time: “received” brings the goods into stock and posts the receipt entry, “paid” pays the full amount from the selected treasury — enable them only where goods and payment really arrive with the invoice.',
                'b' => '“Automatic payment from the supplier wallet” runs before the treasury: any advance balance held with the supplier is used first. “Manual status” stops the status being derived from payment and receipt.',
            ],
            'orders' => [
                'a' => 'A purchase request is an internal request that precedes the order; the purchase order is what goes to the supplier. Off, the system pins both to “under review” and derives the order status from the advance payment and receipt.',
                'b' => 'Enabling shows the status field on the form and leaves the choice to the clerk, and no longer resets the order to “under review” on every edit.',
            ],
            'quotations' => [
                'a' => 'A quotation request is how you canvass supplier prices; the quotation is the supplier’s recorded reply. The derived status starts at “under review” and changes on conversion to a purchase order.',
                'b' => 'Enabling lets the status be chosen on save and edit — “accepted” or “rejected” — without waiting for a conversion.',
            ],
            'returns' => [
                'a' => '“Handed to the supplier by default” sends items out of the warehouse the moment the return is saved, even if the original invoice was never received; off, they leave only when “received” is ticked on save or confirmed from the view screen.',
                'b' => '“More than one return” allows returns in instalments; off, a second return for an invoice that already has one is refused. The quantity cap is the original invoice quantity either way.',
            ],
            'notices' => [
                'a' => 'A debit note reduces what you owe the supplier — a later discount, a price difference, damaged goods — while a return sends items back. Turning off “Enable debit notes” hides its screens and menu link and bounces anyone opening them.',
                'b' => '“Handed by default” works like the return switch: the items on the note leave the warehouse the moment it is saved.',
            ],
            'suppliers' => [
                'a' => '“Require” switches add mandatory fields to the supplier form; “Prevent duplicate” switches refuse a second record with the same tax number, mobile or email.',
                'b' => '“Block documents for suspended suppliers” refuses the invoice, order and return for a supplier marked “suspended” with a message naming them; off, the document is accepted and the clerk never sees the status.',
                'c' => 'Enabling does not touch saved records: a supplier without a mobile stays, and the mobile is asked for on the first edit.',
            ],
            'employees' => [
                'a' => '“Restrict purchasing staff to departments” is a switch: off, every employee card is listed in Purchases pickers as before.',
                'b' => 'On, only staff of the departments chosen under “Allowed departments” appear in any employee picker — invoices, returns, debit notes, suppliers and report filters. A saved document keeps its own employee in the list when edited even if their department changed.',
            ],
            'documents' => [
                'a' => 'The “settlement tab” appears below the line table on the purchase invoice, order and return: overall discount, adjustments, shipping and attachments. Turning it off hides the tab and bounces anyone opening it by link.',
                'b' => '“Total discounts” is a line in the totals summary and printout; off, the discount folds into the net and the net itself does not change.',
            ],
        ],

        'facts' => [
            'active' => 'Enabled now: :on of :total',

            'default_received_invoices_on' => 'Purchase invoices are saved as “received” automatically',
            'default_received_invoices_off' => 'Purchase invoice receipt is ticked manually',
            'receive_in_main_warehouse_on' => 'Purchases are received into the main warehouse',
            'receive_in_main_warehouse_off' => 'Purchases are received into the employee warehouse',
            'default_paid_invoices_on' => 'Purchase invoices are paid from the treasury on save',
            'default_paid_invoices_off' => 'Purchase invoice payment is recorded manually',
            'auto_payment_on' => 'The supplier wallet pays before the treasury',
            'auto_payment_off' => 'No payment from the supplier wallet',
            'update_product_prices_on' => 'Product purchase price follows the latest invoice',
            'update_product_prices_off' => 'Product purchase price is not changed by invoices',
            'manual_invoice_status_on' => 'Invoice status is chosen by the clerk',
            'manual_invoice_status_off' => 'Invoice status is derived by the system',

            'manual_purchase_orders_on' => 'Purchase request status is manual',
            'manual_purchase_orders_off' => 'Purchase requests start “under review”',
            'manual_purchase_orders_status_on' => 'Purchase order status is manual',
            'manual_purchase_orders_status_off' => 'Purchase order status follows advance payment and receipt',

            'manual_quote_orders_on' => 'Quotation request status is manual',
            'manual_quote_orders_off' => 'Quotation requests start “under review”',
            'manual_purchase_quotes_on' => 'Quotation status is manual',
            'manual_purchase_quotes_off' => 'Quotations start “under review”',

            'return_default_received_on' => 'Returns move items out on save',
            'return_default_received_off' => 'Return items leave only on an explicit tick',
            'allow_multiple_returns_on' => 'More than one return per invoice is allowed',
            'allow_multiple_returns_off' => 'One return per invoice',

            'enable_debit_notice_on' => 'Debit notes are available',
            'enable_debit_notice_off' => 'Debit notes are disabled',
            'notice_default_received_on' => 'Debit notes move items out on save',
            'notice_default_received_off' => 'Debit note items leave only on an explicit tick',

            'supplier_required' => 'Mandatory supplier fields: :fields',
            'supplier_none_required' => 'No mandatory field on the supplier record',
            'supplier_unique' => 'Duplicates refused for: :fields',
            'supplier_none_unique' => 'No duplicate check on supplier data',
            'supplier_block_inactive_on' => 'No documents for suspended suppliers',
            'supplier_block_inactive_off' => 'Suspended suppliers are accepted on documents',

            'enable_settlement_on' => 'Settlement tab shown',
            'enable_settlement_off' => 'Settlement tab hidden',
            'total_discounts_on' => 'Total discounts line shown',
            'total_discounts_off' => 'Discounts folded into the net',
        ],

        'supplier_fields' => [
            'name' => 'name',
            'mobile' => 'mobile',
            'tax_number' => 'tax number',
            'commercial_registration' => 'commercial registration',
            'email' => 'email',
        ],
    ],

    // ═══════════════════════ Options ═══════════════════════
    'options' => [
        // ── Purchase invoice ──
        'default_received_invoices' => 'Treat purchase invoices as fully received by default',
        'default_received_invoices_hint' => 'The invoice is saved as “received” and stamped with the save time, so the clerk need not tick it.',

        'default_paid_invoices' => 'Treat purchase invoices as fully paid by default',
        'default_paid_invoices_hint' => 'The invoice is saved as “paid” and its full amount is settled from the selected treasury on save.',

        'auto_payment' => 'Automatic payment from the supplier wallet',
        'auto_payment_hint' => 'If we hold a balance in the supplier wallet (advance payments), the invoice is settled from it without touching the treasury, and the entry is posted: Dr supplier / Cr supplier wallet.',

        'receive_in_main_warehouse' => 'Receive purchases into the main warehouse',
        'receive_in_main_warehouse_hint' => 'On: the main warehouse is the default on purchase invoices and goods receipts. Off: the employee’s linked warehouse, then the main one. A warehouse picked by hand on the screen always wins.',
        'update_product_prices' => 'Update product purchase price from the invoice',
        'update_product_prices_hint' => 'Each product’s purchase price is refreshed from its price on the latest saved purchase invoice.',

        'manual_invoice_status' => 'Manual status for the purchase invoice',
        'manual_invoice_status_hint' => 'The system does not derive invoice status from payment and receipt; it stays as the clerk sets it.',

        // ── Requests & orders ──
        'manual_purchase_orders' => 'Manual status for the purchase request',
        'manual_purchase_orders_hint' => 'A status may be set when saving a purchase request instead of being pinned to “under review”.',

        'manual_purchase_orders_status' => 'Manual status for the purchase order',
        'manual_purchase_orders_status_hint' => 'The system neither derives purchase order status from advance payment and receipt, nor resets it to “under review” on every edit.',

        // ── Quotations ──
        'manual_quote_orders' => 'Manual status for the quotation request',
        'manual_quote_orders_hint' => 'A status may be set when saving a quotation request instead of being pinned to “under review”.',

        'manual_purchase_quotes' => 'Manual status for the purchase quotation',
        'manual_purchase_quotes_hint' => 'A status may be set when saving and editing a quotation instead of being pinned to “under review”.',

        // ── Returns ──
        'return_default_received' => 'Treat returns as handed to the supplier by default',
        'return_default_received_hint' => 'The return is saved as “received”, so items leave the warehouse immediately without the clerk ticking it.',

        'allow_multiple_returns' => 'Allow more than one return per invoice',
        'allow_multiple_returns_hint' => 'Enables partial returns in instalments; without it, a second return for an invoice that already has one is refused.',

        // ── Notices ──
        'enable_debit_notice' => 'Enable debit notes',
        'enable_debit_notice_hint' => 'Opens the supplier debit note screens and their link in the purchasing menu.',

        'notice_default_received' => 'Treat debit notes as handed to the supplier by default',
        'notice_default_received_hint' => 'The debit note is saved as “received”, so items leave the warehouse immediately without the clerk ticking it.',

        // ── Suppliers ──
        'supplier_require_name' => 'Require a supplier name',
        'supplier_require_name_hint' => 'A supplier cannot be saved without a trade or personal name; without it, a nameless supplier is accepted and shows as a number on documents.',

        'supplier_require_mobile' => 'Require the supplier mobile number',
        'supplier_require_mobile_hint' => 'The mobile field is mandatory when adding and editing a supplier.',

        'supplier_require_tax_number' => 'Require the supplier tax number',
        'supplier_require_tax_number_hint' => 'The tax number field is mandatory — needed by anyone issuing tax invoices against their purchases.',

        'supplier_require_commercial_registration' => 'Require the supplier commercial registration',
        'supplier_require_commercial_registration_hint' => 'The commercial registration number is mandatory when adding and editing a supplier.',

        'supplier_unique_tax_number' => 'Prevent duplicate tax numbers',
        'supplier_unique_tax_number_hint' => 'Saving a supplier with a tax number already registered to another is refused, so one supplier’s purchases do not split across two records.',

        'supplier_unique_mobile' => 'Prevent duplicate supplier mobiles',
        'supplier_unique_mobile_hint' => 'Saving a supplier with a mobile already registered to another is refused.',

        'supplier_unique_email' => 'Prevent duplicate supplier emails',
        'supplier_unique_email_hint' => 'Saving a supplier with an email already registered to another is refused.',

        'supplier_block_inactive' => 'Block documents for suspended suppliers',
        'supplier_block_inactive_hint' => 'No purchase invoice, order or return is saved for a supplier marked “suspended”; without it the document is accepted and the status stays hidden from the clerk.',

        // ── All documents ──
        'enable_settlement' => 'Settlement tab on purchase forms',
        'enable_settlement_hint' => 'Shows the overall discount, adjustments, shipping and attachments tab below the line table.',

        'total_discounts' => 'Show a total discounts line',
        'total_discounts_hint' => '“Total discounts” appears in the document totals summary and its printout instead of being folded into the net.',
        'restrict_employees_to_departments' => 'Restrict purchasing staff to specific departments',
        'restrict_employees_to_departments_hint' => 'Employee pickers in Purchases only list staff of the departments chosen below. Off, every employee card is listed.',
    ],

    // ═══════════════════════ Where each option applies ═══════════════════════
    'applies' => [
        'employee_pickers' => 'Employee pickers in purchase documents, suppliers and report filters',
        'warehouse_default' => 'Default warehouse on purchase invoices and goods receipts',
        'invoice_save' => 'Saving a purchase invoice (create and edit), and the online purchase store',
        'order_save' => 'Saving a purchase request',
        'order_request_save' => 'Saving a purchase order (create and edit)',
        'quotation_request_save' => 'Saving a quotation request',
        'quotation_save' => 'Saving a purchase quotation (create and edit)',
        'return_save' => 'Saving a purchase return',
        'debit_notice' => 'Debit note screens and their menu link',
        'supplier_save' => 'Saving a supplier record (create and edit)',
        'supplier_documents' => 'Saving a purchase invoice, purchase order and return',
        'document_form' => 'Purchase invoice, purchase order and return forms',
        'document_totals' => 'Purchase document totals summary and printouts',
    ],

    // ═══════════════════════ Related settings ═══════════════════════
    'links' => [
        'templates' => 'Purchase document templates',
        'templates_hint' => 'Print templates for purchase invoices, orders, quotations, returns and payment vouchers, and the default for each type.',

        'designs' => 'Purchase invoice designs',
        'designs_hint' => 'Print designs bound to the purchase invoice, with a preview before you commit.',

        'sequence' => 'Purchase document numbering',
        'sequence_hint' => 'Document number prefix, digit count and starting point.',

        'taxes' => 'Tax settings',
        'taxes_hint' => 'Tax rates offered on purchase invoice lines, and which of them are included in the price.',

        'payments' => 'Payment methods',
        'payments_hint' => 'Supplier payment methods shown on the invoice and payment voucher, and their accounts.',

        'accounting' => 'Accounting account settings',
        'accounting_hint' => 'The supplier, purchases and inventory accounts that purchase invoice entries post to.',
    ],

    // ═══════════════════════ Messages ═══════════════════════
    'messages' => [
        'saved' => 'Purchase options saved',
        'save_failed' => 'Could not save the options — check the error log',
        'reset' => 'Purchase options restored to their defaults',
        'reset_failed' => 'Could not restore the defaults — check the error log',
        'setting_disabled' => 'This screen is disabled in purchase settings',
        'enable_debit_notice_disabled' => 'Debit notes are disabled — enable them in purchase settings',
        'enable_settlement_disabled' => 'The settlement tab is disabled — enable it in purchase settings',
        'supplier_inactive' => 'Supplier “:name” is suspended, so no document can be saved for them. Reactivate them in supplier management, or turn off “Block documents for suspended suppliers”.',
    ],

    'validation' => [
        'supplier_name_required' => 'A supplier name is required — either the trade name or the first and last name.',
        'supplier_mobile_required' => 'The supplier mobile number is required.',
        'supplier_tax_number_required' => 'The supplier tax number is required.',
        'supplier_commercial_registration_required' => 'The supplier commercial registration number is required.',
        'supplier_tax_number_unique' => 'This tax number is already registered to another supplier.',
        'supplier_mobile_unique' => 'This mobile number is already registered to another supplier.',
        'supplier_email_unique' => 'This email address is already registered to another supplier.',
    ],

    'errors' => [
        'array' => 'The submitted options are malformed.',
        'unknown_key' => 'One of the submitted options is unknown.',
        'unknown_department' => 'One of the submitted departments does not exist.',
    ],
];

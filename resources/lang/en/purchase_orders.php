<?php

/*
 * Purchase Orders section strings — mirrors resources/lang/ar/purchase_orders.php.
 * Keep both files in step: every key used in a Blade template must exist here.
 */

return [
    'section' => 'Purchase Orders',

    'titles' => [
        'index' => 'Purchase Orders',
        'create' => 'New Purchase Order',
        'edit' => 'Edit Purchase Order',
        'show' => 'Purchase Order Details',
    ],

    'breadcrumb' => [
        'home' => 'Home',
        'index' => 'Purchase Orders',
        'create' => 'Create',
        'edit' => 'Edit',
        'show' => 'Details',
    ],

    'fields' => [
        'code' => 'Order No.',
        'title' => 'Title',
        'order_date' => 'Order Date',
        'due_date' => 'Due Date',
        'branch' => 'Branch',
        'status' => 'Status',
        'follow_status' => 'Follow-up Status',
        'employee' => 'Employee',
        'creator' => 'Created By',
        'notes' => 'Notes',
        'attachments' => 'Attachments',
        'items_count' => 'Line Items',
        'currency' => 'Currency',
        'approved_by' => 'Approved By',
        'approved_at' => 'Approved On',
        'rejected_by' => 'Rejected By',
        'rejected_at' => 'Rejected On',
        'approval_note' => 'Approval Note',
        'updated_at' => 'Last Updated',
    ],

    'placeholders' => [
        'title' => 'Enter the order title',
        'code' => 'Generated from sequence settings',
        'notes' => 'Write your notes here…',
        'search_code' => 'Search by order number',
        'select_employee' => 'Select employee',
        'select_item' => 'Select item',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit price',
        'discount' => 'Discount',
    ],

    'statuses' => [
        'all' => 'All statuses',
        'Under Review' => 'Under review',
        'approval' => 'Approved',
        'disagree' => 'Rejected',
        'Convert to Quotation' => 'Converted to quotation',
    ],

    'breadcrumb_nav' => 'Breadcrumb',

    'items' => [
        'heading' => 'Order Items',
        'row' => '#',
        'item' => 'Item',
        'description' => 'Description',
        'quantity' => 'Qty',
        'unit' => 'Unit',
        'base_unit' => 'Base unit',
        'unit_price' => 'Unit Price',
        'discount' => 'Discount',
        'discount_type' => 'Discount Type',
        'tax' => 'Tax',
        'total' => 'Total',
        'add_row' => 'Add item',
        'remove_row' => 'Remove item',
        'locked_row' => 'Carried over from the source document — it cannot be edited or removed here.',
        'no_tax' => 'None',
        'tax_included' => 'included in price',
        'tax_excluded' => 'added to price',
        'empty' => 'This order has no items',
    ],

    'totals' => [
        'subtotal' => 'Subtotal',
        'discount' => 'Total Discount',
        'shipping' => 'Shipping',
        'tax' => 'Total Tax',
        'shipping_tax' => 'Shipping tax',
        'grand_total' => 'Grand Total',
        'deposit' => 'Deposit (advance payment)',
        'paid' => 'Paid',
        'refund' => 'Refunded',
        'remaining' => 'Remaining',
    ],

    'search' => [
        'heading' => 'Search & Filter',
        'advanced' => 'Advanced',
        'hide' => 'Hide',
        'show' => 'Show',
        'submit' => 'Search',
        'reset' => 'Reset',
        'clear' => 'Clear filters',
        'date_from' => 'From',
        'date_to' => 'To',
        'order_date_from' => 'Order date (from)',
        'order_date_to' => 'Order date (to)',
        'due_date_from' => 'Due date (from)',
        'due_date_to' => 'Due date (to)',
        'results_count' => 'Results: :count',
    ],

    'actions' => [
        'create' => 'New purchase order',
        'show' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'copy' => 'Duplicate',
        'save' => 'Save',
        'update' => 'Save changes',
        'cancel' => 'Cancel',
        'back' => 'Back',
        'print' => 'Print',
        'export' => 'Export',
        'print_preview' => 'Print preview',
        'download_pdf' => 'Download PDF',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'cancel_approval' => 'Cancel approval',
        'undo_rejection' => 'Undo rejection',
        'to_quotation' => 'Convert to quotation request',
        'open_attachment' => 'Open attachment',
        'options' => 'Options',
        'quick_add_product' => "Item not listed? Add it from here",
    ],

    'tabs' => [
        'details' => 'Details',
        'items' => 'Items',
        'notes' => 'Notes',
        'activity' => 'Activity log',
    ],

    'templates' => [
        'default' => 'Default layout',
        'detailed' => 'Detailed layout',
        'simple' => 'Simple layout',
        'compact' => 'Compact layout',
        'summary' => 'Order summary',
        'from_settings' => 'Configured document templates',
        'settings_hint' => 'Templates defined under Settings → Document Templates',
    ],

    'empty' => [
        'title' => 'No purchase orders yet',
        'hint' => 'Nothing has been added yet — start by creating the first order.',
        'filtered' => 'No results match your search criteria',
        'no_notes' => 'No notes',
        'no_attachments' => 'No attachments',
        'no_activity' => 'No activity recorded for this order',
        'unknown' => 'Not set',
        'unknown_user' => 'Unknown user',
        'unknown_branch' => 'Unknown branch',
    ],

    'upload' => [
        'click' => 'Click here',
        'or' => 'or',
        'browse' => 'browse your device',
        'hint' => 'PDF, DOC, DOCX, JPG, PNG (max 2 MB)',
        'current' => 'Current attachment',
        'replace' => 'Uploading a new file replaces the current attachment',
    ],

    'required_note' => 'Fields marked with * are required',

    'messages' => [
        'created' => 'Purchase order created successfully with :count item(s).',
        'updated' => 'Purchase order updated successfully.',
        'deleted' => 'Purchase order deleted successfully.',
        'approved' => 'The order has been approved.',
        'rejected' => 'The order has been rejected.',
        'approval_cancelled' => 'Approval cancelled — the order is back under review.',
        'rejection_undone' => 'Rejection undone — the order is back under review.',
        'validation_failed' => 'Please check the submitted data.',
        'create_failed' => 'Could not create the purchase order: :error',
        'update_failed' => 'Could not update the purchase order: :error',
        'delete_failed' => 'Could not delete the purchase order.',
        'approve_failed' => 'Could not approve the order.',
        'reject_failed' => 'Could not reject the order.',
        'cancel_approval_failed' => 'Could not cancel the approval.',
        'undo_rejection_failed' => 'Could not undo the rejection.',
        'pdf_failed' => 'Could not generate the PDF file: :error',
        'edit_locked' => 'An approved or rejected purchase order cannot be edited.',
        'not_approved' => 'Approval cannot be cancelled — the order is not approved.',
        'not_rejected' => 'Rejection cannot be undone — the order is not rejected.',
    ],

    'log' => [
        'created' => 'Purchase order **:code** created with :count item(s)',
        'updated' => 'Purchase order **:code** updated',
        'deleted' => 'Purchase order **:title** deleted',
        'approved' => 'Purchase order **:title** approved',
        'rejected' => 'Purchase order **:title** rejected',
        'approval_cancelled' => 'Approval cancelled for purchase order **:title**',
        'rejection_undone' => 'Rejection undone for purchase order **:title**',
        'printed' => 'Purchase order **:code** print preview opened',
        'exported' => 'Purchase order **:code** downloaded as PDF',
    ],

    'confirm' => [
        'save_title' => 'Confirm save',
        'save_text' => 'Save :document?',
        'update_text' => 'Save the changes to :document?',
        'delete_title' => 'Delete purchase order',
        'delete_text' => 'Delete purchase order “:code”? This cannot be undone.',
        'cancel_title' => 'Discard input',
        'cancel_text' => 'All entered data will be cleared.',
        'remove_row_title' => 'Remove item',
        'remove_row_text' => 'This line will be removed from the document.',
        'last_row' => 'The last line cannot be removed — at least one item must remain.',
        'approve_title' => 'Approve order',
        'approve_text' => 'The order will be approved and can then be converted to a quotation.',
        'reject_title' => 'Reject order',
        'reject_text' => 'The purchase order will be rejected.',
        'cancel_approval_text' => 'The order will return to “Under review”.',
        'undo_rejection_text' => 'The order will return to “Under review”.',
        'yes' => 'Yes, continue',
        'yes_delete' => 'Yes, delete',
        'no' => 'Go back',
        'ok' => 'OK',
        'saving' => 'Saving…',
        'please_wait' => 'Please wait',
    ],

    'validation' => [
        'items_required' => 'At least one item is required.',
        'title_required' => 'Please enter the order title.',
        'order_date_required' => 'Please enter the order date.',
        'item_required' => 'Please pick an item on row :row.',
        'quantity_required' => 'Please enter a valid quantity on row :row.',
    ],

    // Bulk selection — same control as the Activity Log screen
    'bulk' => [
        'select_all' => 'Select all',
        'delete' => 'Delete selected',
        'selected' => 'Selected: :count',
        'none_selected' => 'No orders selected.',
        'confirm_title' => 'Delete selected orders',
        'confirm_text' => ':count order(s) will be permanently deleted. This cannot be undone.',
        'deleted' => ':count order(s) deleted successfully.',
        'delete_failed' => 'Could not delete the selected orders.',
    ],

    // Fawtra subscription-limit badge — shown only once the billing
    // dashboard defines this feature in its catalog (see
    // UsageReporter::catalog()); no badge at all until then
    'subscription' => [
        'usage_line' => 'You have used :used of :allowance this month under your Fawtra plan.',
        'usage_line_exhausted' => 'You have reached your plan limit this month (:used of :allowance). Check the Fawtra dashboard to upgrade or buy an add-on.',
    ],

    // Button tooltips — shown on hover (Bootstrap Tooltip)
    'tooltips' => [
        'print' => 'Print the currently displayed purchase-order list after filters are applied.',
        'export' => 'Export the currently displayed purchase-order list to an Excel file.',
    ],

    // Page guidance — a fixed banner explaining what the screen is for, for first-time visitors
    'guidance' => [
        'purchase_requests_index' => 'This screen lists every purchase order sent to suppliers. From here you track each order\'s status (under review, approved, rejected), approve or reject pending orders, and convert an approved order into a price-quotation request when needed.',
        'purchase_requests_create' => 'A purchase order is a document sent to a supplier requesting specific items with their quantities and prices. Once approved it cannot be edited or have its approval undone, so verify the data before submitting it for approval.',
        'purchase_requests_edit' => 'Editing stays available only while the order is "Under review". Once approved or rejected, editing is permanently locked — so double-check the items and prices before sending it for approval.',
        'purchase_requests_show' => 'This is the full order page: its items, totals, status, and activity log. The buttons above let you approve or reject the order, or convert it to a purchase quotation once approved.',
        'quotations_index' => 'This screen lists price-quotation requests sent to suppliers to learn their prices before issuing an actual purchase order. A quotation request deliberately carries no prices — it is a question to the supplier, not a commitment to buy.',
        'quotations_create' => 'A price-quotation request is sent to one or more suppliers to learn their prices on specific items, with no purchase commitment. Once approved it can be converted into an actual price quotation using the prices the supplier provides — and once approved the request itself can no longer be edited or have its approval undone.',
        'quotations_edit' => 'Editing stays available only while the request is "Under review". Once approved, editing is permanently locked with no undo — so double-check the suppliers and items before sending it for approval.',
        'quotations_show' => "This is the full request page: the suppliers it was sent to, its items, status, and activity log. The buttons above let you approve or reject the request, duplicate it, or convert it to a purchase quotation once approved.",
        'purchase_orders_index' => "This screen lists purchase orders sent to a specific supplier with specific items and prices. Unlike the simple purchase order, this document carries the supplier and prices from creation and converts directly into a purchase invoice once approved.",
        'purchase_orders_create' => 'This purchase order carries a specific supplier and prices, and converts directly into a purchase invoice once approved. Once approved it is fully locked — no editing, no cancelling approval — so verify the prices and settlement before sending it for approval.',
        'purchase_orders_edit' => 'Editing stays available only while the order is "Under review". Once approved, the order is fully locked and the only option left is converting it to an invoice.',
        'purchase_orders_show' => "This is the full order page: the supplier, its items and prices, totals, and activity log. The buttons above let you approve or reject the order, or convert it to a purchase invoice once approved.",
    ],

    /*
     * Collapsible guidance panel — same shape as `purchase_cycle.guide`.
     * Unlike `guidance` above (one line, always visible), this is split into
     * sections for whoever wants more detail.
     *
     * `**text**` renders bold — see `purchases::components.guide`.
     */
    'guide' => [
        'what_is' => 'A purchase order is a document sent to a supplier requesting specific items with their quantities, ahead of converting it into a purchase quotation and then a purchase invoice once the goods arrive. It is a planning step with no financial or stock effect of its own.',

        'cycle' => [
            'title' => 'Where it sits in the purchase cycle',
            'a' => 'A **purchase order** requests items from a supplier; once approved it can be converted into a **purchase quotation** with agreed prices, then into a **purchase invoice** when the goods arrive.',
            'b' => 'Going through it is not required — a purchase invoice can be issued directly without a prior purchase order.',
            'c' => 'A purchase order carries no accounting entry and no stock movement by itself — the real effect only starts from the invoice.',
        ],

        'statuses' => [
            'title' => 'Order statuses',
            'a' => '**Under review** is the default status on creation — the order can still be edited or deleted.',
            'b' => '**Approved** after clicking "Approve": it becomes convertible to a quotation, and editing or deleting it is permanently locked.',
            'c' => '**Rejected** after clicking "Reject": rejection can be undone to bring the order back to "Under review".',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside',
            'a' => '**Purchase settings** — the "Manual purchase-order status" setting shows the status field on creation; otherwise the order is always saved as "Under review".',
            'b' => '**Document numbering sequence** — where the order number comes from; what shows before saving is a preview only, and the number is consumed on save alone.',
            'c' => '**Branches** — which branch you can pick from the list follows your user permission; a user without branch-selection permission has the order saved on their assigned branch automatically.',
        ],

        'index' => [
            'title' => 'Purchase Orders — what is this screen?',
            'stats' => [
                'title' => 'The cards at the top',
                'a' => 'Calculated over every order you are allowed to see, **not the search result** — they do not change as you filter.',
                'b' => 'They count orders, not amounts.',
            ],
            'subscription' => [
                'title' => 'The subscription-limit bar',
                'a' => 'Shown when your Fawtra plan caps a monthly number of purchase orders, and shows how much of it is used.',
                'b' => 'Reaching the limit blocks saving a new order — the message appears on the attempt.',
            ],
        ],

        'form' => [
            'title' => 'What happens on save?',
            'save' => [
                'title' => 'On save',
                'a' => '**The order number is consumed** from the numbering sequence. What shows now is a preview only.',
                'b' => 'The order is saved as "Under review" unless manual status is enabled and another status is explicitly chosen.',
                'c' => 'No accounting entry and no stock movement happens when a purchase order is saved — the real effect starts later, from the invoice.',
            ],
            'noedit' => [
                'title' => 'No editing after approval or rejection',
                'a' => 'An approved or rejected order cannot be edited or deleted — the edit button only shows for an order "Under review".',
                'b' => 'Undoing a rejection returns the order to "Under review"; approval, however, is final with no way to cancel it.',
            ],
        ],

        'show' => [
            'title' => 'The order page — what can I do from here?',
            'lead' => 'The order\'s record: its items, totals, status, and activity log.',
            'actions' => [
                'title' => 'The buttons at the top',
                'a' => '**Approve/Reject** only show for an order "Under review", and decide its fate.',
                'b' => '**Convert to purchase quotation** only shows once approved, and carries the order\'s items into the quotation-creation screen.',
                'c' => '**Print** follows the template configured under "Document Templates"; the built-in layouts (detailed, simple, compact, summary) remain a fallback.',
            ],
        ],

        'facts' => [
            'no_stock' => 'Does not touch stock',
            'no_ledger' => 'No accounting entry on save',
            'final' => 'Approval is final, no editing after',
        ],
    ],

    /*
     * Single-card guidance — shown right under its heading, not a hover
     * tooltip: a card reading "Approved: 12" does not say whether it is a
     * count or an amount.
     */
    'stats_hints' => [
        'total' => 'Count of every purchase order',
        'review' => 'Awaiting approval or rejection',
        'approved' => 'Approved, and can be converted to a quotation',
        'rejected' => 'Rejected — rejection can be undone',
    ],

    /*
     * Filter-field guidance — exactly what each field matches.
     */
    'filter_hints' => [
        'employee' => "This employee's orders only — the order's creator, not who it was sent to.",
        'code' => 'Part of the order number is enough — no need for the full number.',
        'status' => "The order's saved status.",
        'title' => "Part of the order's title is enough.",
        'follow_status' => 'Same as the status field above, for extra filtering within advanced search.',
        'dates' => 'The order date or the due date, depending on the field. Both ends are inclusive.',
    ],

    // Activity table columns — same labels as the Activity Log screen
    'activity' => [
        'heading' => 'Operations on this order',
        'user' => 'User',
        'branch' => 'Branch',
        'description' => 'Description',
        'datetime' => 'Date & time',
        'device' => 'Device',
        'ip' => 'IP address',
        'changes' => 'Changes',
        'system_user' => 'System',
        'main_branch' => 'Main branch',
        'total' => 'Total operations',
        'from' => 'Before',
        'to' => 'After',
        'show_changes' => 'Show changes',
    ],

    'pdf' => [
        'title' => 'Purchase Order',
        'issued_by' => 'Issued by',
        'printed_at' => 'Printed on',
        'signature' => 'Signature',
        'stamp' => 'Stamp',
        'page' => 'Page',
    ],
];

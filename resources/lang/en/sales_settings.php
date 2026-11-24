<?php

/*
|--------------------------------------------------------------------------
| Sales settings section — English
|--------------------------------------------------------------------------
|
| Mirrors ../ar/sales_settings.php key for key. Words shared with the general
| settings section (save, cancel, status …) are read from `settings.common.*`
| and are deliberately absent here.
|
| Placeholders (:name, :count …) must stay identical to the Arabic file — the
| code passes them by name.
|
*/

return [

    /*
     * Validation messages.
     *
     * The project's ar/validation.php is still an untranslated English copy,
     * so sales settings screens supply their own messages via `messages()`.
     * These English versions keep the two locales key-for-key.
     */
    'errors' => [
        'required' => 'The :attribute field is required.',
        'numeric' => ':attribute must be a number.',
        'integer' => ':attribute must be a whole number.',
        'min_num' => ':attribute must be at least :min.',
        'max_num' => ':attribute may not exceed :max.',
        'max_len' => ':attribute may not exceed :max characters.',
        'in' => 'The value chosen for :attribute is not supported.',
        'exists' => 'The value chosen for :attribute does not exist.',
        'boolean' => ':attribute can only be enabled or disabled.',
    ],

    /*
     |--------------------------------------------------------------------------
     | Shared UI vocabulary
     |--------------------------------------------------------------------------
     |
     | The Arabic file has carried this block since the settings screens were
     | rewritten; the English one never did, so every `sales_settings.ui.*`
     | call rendered the raw key on an English session — "sales_settings.ui.save"
     | on the save button. Key-for-key with ar/sales_settings.php.
     */
    'ui' => [
        'home' => 'Home',
        'breadcrumb' => 'Breadcrumb',

        'per_page_show' => 'Show',
        'per_page_unit' => 'rows',
        'per_page_aria' => 'Rows per page',
        'showing' => 'Showing :from to :to of :total',
        'pages' => 'Result pages',
        'page_first' => 'First page',
        'page_prev' => 'Previous',
        'page_next' => 'Next',
        'page_last' => 'Last page',

        'search' => 'Search',
        'search_placeholder' => 'Type to search…',
        'filters' => 'Search & filter',
        'filters_active' => 'Filters active',
        'filters_apply' => 'Apply',
        'filters_clear' => 'Clear filters',
        'advanced' => 'Advanced search',

        'actions' => 'Actions',
        'view' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'back' => 'Back',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'add' => 'Add',
        'print' => 'Print',
        'export' => 'Export',
        'load_failed' => 'Could not load the data',

        'status' => 'Status',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'all_statuses' => 'All statuses',
        'name' => 'Name',
        'description' => 'Description',
        'created_at' => 'Created',
        'updated_at' => 'Last changed',
        'yes' => 'Yes',
        'no' => 'No',
        'none' => '—',
        'list_separator' => ', ',
        'delete_failed' => 'Could not delete — try again.',
        'optional' => 'optional',
        'options' => 'Options',

        'validation_failed' => 'Could not save — check the following:',
        'confirm_delete_title' => 'Confirm deletion',
        'confirm_yes' => 'Yes, delete',
        'no_results' => 'Nothing matches your search',
        'no_results_hint' => 'Try widening the filters, or clear them.',
    ],

    /*
     |--------------------------------------------------------------------------
     | System settings that govern the sales screens
     |--------------------------------------------------------------------------
     |
     | A strip at the head of each sales-settings screen naming the system
     | settings it actually runs on: which currency formats its amounts, which
     | language and date format, whether today's accounting period is open, and
     | how many accounting keys are mapped.
     |
     | These values were read in the background and never surfaced, so a user
     | saw an unsymbolled amount or a refused journal entry with no hint where
     | to fix it.
     */
    'context' => [
        'title' => 'System settings in force here',
        'hint' => 'These are not set in Sales — they come from system settings, and a change there applies to the sales screens as soon as it is saved.',
        'open' => 'Open screen',

        'appearance' => 'Appearance',
        'mode_light' => 'Light mode',
        'mode_dark' => 'Dark mode',
        'language' => 'Interface language',
        'currency' => 'System currency',

        'payments' => 'Payment methods',
        'payments_value' => ':count active',
        'payments_none' => 'None active',

        'accounts' => 'Accounting accounts',
        'accounts_value' => ':count keys mapped',
        'accounts_none' => 'No key mapped',

        'period' => 'Accounting period',
        'period_open' => 'Open today',
        'period_blocked' => 'Closed today',

        'notifications' => 'Notification settings',
        'notifications_value' => ':enabled of :total sales events',
        'business' => 'Business information',
        'business_ok' => 'Complete',
        'business_missing' => 'Incomplete',

        'logs_hint' => 'Every save on a sales-settings screen is written to the activity log and the event log; every failure is written to the error log.',
    ],

    // ═══════════════════════ Sales settings hub ═══════════════════════
    'hub' => [
        'title' => 'Sales settings',
        'subtitle' => 'Everything that governs invoices, quotes, orders and commissions, in one place.',
        'search_placeholder' => 'Search sales settings…',
        'screens_available' => 'Screens available',
        'groups_count' => 'Sections',
        'no_match' => 'No screen matches your search',
        // مفتاحان كانا في العربية وحدها، فيظهران نصّاً خاماً في الإنجليزية
        'system_currency' => 'System currency',
        'system_language' => 'Interface language',
    ],

    // ═══════════════════════ Screen registry ═══════════════════════
    'nav' => [
        'groups' => [
            'invoicing' => 'Invoices & quotes',
            'orders' => 'Orders & shipping',
            'offers' => 'Offers & pricing',
            'targets' => 'Targets & commissions',
            'linked' => 'Related general settings',
            'system' => 'Appearance & language',
            'logs' => 'Trace & logs',
        ],

        'invoice' => 'Invoice & quote settings',
        'invoice_hint' => 'How invoices behave on creation: free entry, minimum price, manual statuses, preview before save.',

        'designs' => 'Sales document designs',
        'designs_hint' => 'Print templates for invoices, quotes, returns, notes and receipts, and the default per type.',

        'sequence' => 'Invoice numbering',
        'sequence_hint' => 'Invoice number prefix, digit count and where the sequence starts.',

        'electronic' => 'Electronic invoice',
        'electronic_hint' => 'Integration with the e-invoicing platform, sending and signing options.',

        'order_sources' => 'Order sources',
        'order_sources_hint' => 'The channels orders arrive through, the default source and whether it is required.',

        'shipping' => 'Shipping options',
        'shipping_hint' => 'Shipping methods with their cost, tax and default ledger account.',

        'exchange_reasons' => 'Exchange reasons',
        'exchange_reasons_hint' => 'Reasons offered to staff when exchanging a product, with order and activation.',

        'supply' => 'Supply order settings',
        'supply_hint' => 'Supply order procedures and serial numbering.',
        'supply_procedures' => 'Procedures',
        'supply_numbering' => 'Numbering',

        'gift_offers' => 'Offers & gifts',
        'gift_offers_hint' => 'Purchase offers and product gifts, with their validity periods.',

        'discount_offers' => 'Discount offers',
        'discount_offers_hint' => 'Percentage or fixed discounts applied to invoices and quotes.',

        'targets' => 'Target & commission settings',
        'targets_hint' => 'How a target is calculated, the commission account, auto-settlement and its frequency.',
        'targets_sales' => 'Sales targets',
        'targets_collections' => 'Collection targets',

        'payments' => 'Payment methods',
        'payments_hint' => 'Payment methods available on invoices and receipts.',

        'taxes' => 'Taxes',
        'taxes_hint' => 'Tax rates available and default on invoice lines.',

        'currencies' => 'Currencies',
        'currencies_hint' => 'Currencies and exchange rates used in pricing.',

        // Appearance and language are set in the Settings module but govern
        // every sales screen; listing them here saves a trip to another hub.
        'appearance' => 'Appearance',
        'appearance_hint' => 'Primary colour, font, corner radius and dark mode — applied to the sales screens as-is.',

        'languages' => 'Languages',
        'languages_hint' => 'Interface language, direction, and the date/time format used across sales documents.',

        'accounting' => 'Accounting accounts',
        'accounting_hint' => 'The accounts sales entries post to: revenue, tax, receivables and discount.',

        'periods' => 'Accounting periods',
        'periods_hint' => 'A closed period refuses any invoice or receipt dated inside it.',

        'notifications' => 'Notification settings',
        'notifications_hint' => 'Which sales events raise a notification, to whom, and over which channel.',

        'account' => 'Business information',
        'account_hint' => 'Name, logo, tax number and address — printed on the head of every sales document.',

        // The three logs are filled by App\Support\OperationLog, which the
        // sales-settings controllers call on every save and every failure.
        'activity_log' => 'Activity log',
        'activity_log_hint' => 'Who changed which setting and when, and what its value was beforehand.',

        'event_log' => 'Event log',
        'event_log_hint' => 'The operation key, IP address and user agent behind each change.',

        'error_log' => 'Error log',
        'error_log_hint' => 'What failed to save in sales settings, and why.',
    ],

    // ═══════════════════════ Invoice settings ═══════════════════════
    'invoice' => [
        'title' => 'Invoice & quote settings',
        'saved' => 'Invoice settings saved',
        'required_note' => 'Fields marked * are required.',
        'toggle_hint' => 'Enable or disable the following to match your workflow — changes apply to new invoices.',
        'enabled_count' => 'settings enabled',
        'last_update' => 'Last changed',
        'never' => 'Never changed',
        'subtitle' => 'Twenty-two settings governing what staff see while entering an invoice, what they are stopped from doing, and what happens on save.',
        'save_failed' => 'Could not save the invoice settings — details were written to the error log.',
        'log_updated' => 'Invoice & quote settings changed',
        'guide' => [

            'title' => 'Before you change anything',
            'toggle' => 'Guidance',
            'intro' => 'This screen controls how every sales screen behaves: invoices, returns, credit notes and quotations. What you change here applies to **new** documents and does not touch what has already been issued.',
            'scope_title' => 'Where it shows up',
            'scope' => 'The four sections below map to places on the screens: "Pricing" in the lines table, "Invoice behaviour" on the create screen, "Quotations" in the sales menu, and "Accounting" in the posted journal entry.',
            'tax_title' => 'Tax and its type',
            'tax_intro' => 'The tax type is set in Tax settings, not here, and it alone decides whether the rate is **included** in the price or **added** on top — every document total is built on that.',
            'tax_current' => 'Currently set: :name — :type at :rate%',
            'tax_included' => 'included in the price',
            'tax_excluded' => 'added on top of the price',
            'tax_none' => 'No tax has been set in Tax settings yet.',
            'tax_link' => 'Open tax settings',
            'warehouse_title' => 'The selling warehouse',
            'warehouse' => 'Which warehouse does the stock leave from? "Employee warehouse" shows each employee the warehouses assigned to them under Default warehouses, falling back to the main warehouse when they have none — the behaviour in place before this setting. "Main warehouse" makes every employee sell from one central warehouse and ignores their assigned ones, which stay in place for their other uses: stocktaking, transfers and rep custody. It applies to sales invoices, sales returns, credit notes, and to an invoice converted from a quote.',
            'min_price_title' => 'The minimum and tax',
            'min_price' => '"Prevent selling below cost" below always compares the line to its cost **before tax**: when tax is included in the price it is extracted first and the net is compared. Cost itself is recorded without tax, so a price carrying its tax is never compared against it.',
            'save_title' => 'Saving',
            'save' => 'Saving applies to all four sections together, not only the open one.',
        ],

        'sections' => [
            'pricing' => 'Pricing & limits',
            'pricing_hint' => 'What staff see about price, and the limits they cannot cross.',
            'behavior' => 'Invoice behaviour',
            'behavior_hint' => 'What happens as an invoice is created and saved.',
            'quotes' => 'Quotes & sales orders',
            'quotes_hint' => 'Enabling preliminary documents, their statuses and conversion to invoices.',
            'accounting' => 'Accounting & notifications',
            'accounting_hint' => 'How an invoice affects journals, and what is sent about it.',
        ],

        'fields' => [
            'allow_free_entry' => 'Disable free product entry on invoices',
            'allow_free_entry_hint' => 'When enabled only a registered product is accepted on an invoice, and the "free line" option disappears from the item rows.',
            'no_sell_below_cost' => 'Prevent selling below cost price',
            'no_sell_below_cost_hint' => 'Blocks saving an invoice when a line price falls below its purchase cost.',
            'enable_max_discount' => 'Enable maximum discount',
            'enable_max_discount_hint' => 'Enables a ceiling on the allowed discount — on the line discount, on the invoice-level discount, and on their sum — which the sales person cannot exceed.',
            'max_discount_percent' => 'Maximum allowed discount (%)',
            'max_discount_amount' => 'Maximum allowed discount amount',
            'max_discount_type' => 'Maximum discount unit',
            'max_discount_value' => 'Maximum discount',
            'max_discount_value_hint' => 'One ceiling, in the unit you pick beside it: as a **percentage (%)** no single line discount, no invoice-level discount and no sum of the two may exceed it; as an **amount** the total discount on one invoice may not exceed it — otherwise the invoice is refused. The percentage applies company-wide and a branch that sets its own "maximum allowed discount" overrides it; the amount applies company-wide with no override. The other unit\'s number stays saved if you switch back.',
            'select_price_list' => 'Choose a price list on invoices',
            'select_price_list_hint' => 'Shows the price-list picker on the invoice screen so lines are priced from it.',
            'show_invoice_profit' => 'Show invoice profit',

            'show_invoice_profit_hint' => 'Shows the profit tab on the invoice view — only for users with the "view invoice profit" permission.',
            'warehouse_source' => 'Selling warehouse source',
            'warehouse_source_hint' => 'The default warehouse on sales documents: the employee\'s assigned warehouse, or the main warehouse for everyone. What the employee picks on the document screen still wins over the default, and the "create invoices" permission set on the warehouse stays above both choices — when the chosen source holds no warehouse this employee may use, the other source is used so the list is never left empty. A user with no employee record — an administrator — still sees every warehouse.',
            'preview_before_save' => 'Preview invoice before saving',
            'preview_before_save_hint' => 'Shows a preview of the invoice before saving, so it can be reviewed before it is issued.',
            'default_paid_status' => 'Treat invoices as paid by default',
            'default_paid_status_hint' => 'A new invoice starts as "paid" instead of "unpaid".',
            'auto_pay_if_balance' => 'Auto-pay the invoice from the customer credit balance',
            'auto_pay_if_balance_hint' => 'Settles from the customer receivable credit automatically when it is sufficient, instead of leaving the invoice on credit. That offset is completed by the invoice entry itself with no extra journal entry — it is not the wallet settlement below.',
            'auto_pay_from_wallet' => 'Automatic settlement from the customer wallet',
            'auto_pay_from_wallet_hint' => 'If the customer holds a wallet balance (money deposited in advance), the invoice is settled from it in full or in part with no new cash entering the treasury, and the entry is posted: Dr customer wallet / Cr customer receivable. The wallet is never overdrawn, so anything above its balance stays due or is paid in cash.',
            'manual_invoice_status' => 'Give invoices manual statuses',
            'manual_invoice_status_hint' => 'Lets the invoice status be chosen manually instead of derived from its payments.',
            'disable_delivery_options' => 'Disable delivery options',
            'disable_delivery_options_hint' => 'Hides shipping and delivery options from the invoice screen.',
            'show_service_products' => 'Show service products to employees',
            'show_service_products_hint' => 'Lists services for employees on invoice, quote, sales order, return and credit note lines. An employee\'s list is limited to products stocked in their warehouse, and a service has no stock, so it is hidden without this option. Administrators always see every product.',
            'show_digital_products' => 'Show digital products to employees',
            'show_digital_products_hint' => 'Lists digital products for employees on invoice, quote, sales order, return and credit note lines. A digital product has no warehouse stock, so it is hidden from employees without this option. Administrators always see every product.',
            'disable_quotes' => 'Disable quotes',
            'disable_quotes_hint' => 'Hides "quotations" from the sales menu.',
            'manual_quote_status' => 'Give quotes manual statuses',
            'manual_quote_status_hint' => 'Lets the quotation status be chosen manually instead of derived from conversion.',
            'enable_sales_orders' => 'Enable sales orders',
            'enable_sales_orders_hint' => 'Enables "sales orders" as a step between the quotation and the invoice — they appear in the sales menu and convert into a sales invoice.',
            'manual_sales_order_status' => 'Give sales orders manual statuses',
            'manual_sales_order_status_hint' => 'Lets the sales order status be chosen manually instead of deriving it from the conversion. Depends on "Enable sales orders" above.',
            'apply_offers_to_quotes' => 'Apply offers to quotes',
            'apply_offers_to_quotes_hint' => 'Gift and discount offers apply to quotations as they do to invoices.',
            'enable_coupons' => 'Enable discount coupons',
            'enable_coupons_hint' => 'Shows the coupon-code field on sales invoices and quotations and deducts the coupon from the document total. Coupons themselves are managed under Online store → Coupons.',
            'copy_notes_on_conversion' => 'Copy notes and terms on conversion to an invoice',
            'copy_notes_on_conversion_hint' => 'When a sales order or quote becomes an invoice.',

            'custom_journal_description' => 'Custom journal entry description',

            'custom_journal_description_hint' => 'Allows writing the journal entry description manually instead of the generated one.',
            'send_on_social' => 'Send transactions over social channels',
            'send_on_social_hint' => 'Shows the WhatsApp and email send buttons on the invoice view.',
        ],

        /* Dropdown options — the key is the value stored in the column
           itself, so the template builds the translation key from the
           value with no intermediate map. */
        'options' => [
            'employee' => 'Employee warehouse',
            'main' => 'Main warehouse',
        ],

    ],

    // ═══════════════════════ Sales document designs ═══════════════════════
    'designs' => [
        'title' => 'Sales document designs',
        'subtitle' => 'Print templates for invoices, quotes, returns, notes and receipts — edited with the same document template editor.',
        'new' => 'New template',
        'all_templates' => 'All document templates',
        'total' => 'Total templates',
        'active' => 'Active',
        'types_covered' => 'Types with a default',
        'missing_default' => 'Types without a default',
        'missing_default_note' => 'These types have no default template, so printing falls back to the system template: :types',
        'paper' => 'Paper',
        'is_default' => 'Default',
        'set_default' => 'Make default',
        'duplicate' => 'Duplicate',
        'preview' => 'Preview',
        'empty_type' => 'No templates for this type yet',
        'empty' => 'No sales document templates yet',
        'create_for' => 'Create a template for :type',
        'no_permission' => 'Editing templates requires the system settings permission — you can view but not change them.',
        'deleted' => 'Template deleted',
        'default_locked' => 'The default template cannot be deleted',
        'confirm_delete' => 'Delete the template :name?',
    ],

    // ═══════════════════════ Order sources ═══════════════════════
    'order_sources' => [
        'subtitle' => 'How the customer arrived: a visit, a call, the online store… Chosen on the invoice and the basis of the sales-source report.',
        'save_failed' => 'Could not save the order sources — details were written to the error log.',
        'log_saved' => 'Order sources saved — :added added, :updated changed',
        'log_deleted' => 'Order source ":name" deleted',
        'title' => 'Order sources',
        'new' => 'New source',
        'total' => 'Total sources',
        'active' => 'Active',
        'list' => 'Source list',
        'defaults' => 'Default behaviour',
        'default_source' => 'Default source',
        'default_source_hint' => 'Pre-selected on a new invoice.',
        'mandatory' => 'Order source is required',
        'mandatory_hint' => 'Blocks saving an invoice without an order source.',
        'sort' => 'Order',
        'drag_hint' => 'Drag a row to change display order.',
        'empty' => 'No order sources yet',
        'saved' => 'Order sources saved',
        'deleted' => 'Source deleted',
        'confirm_delete' => 'Delete the source :name?',
        'name_required' => 'Source name is required',
        'row_name' => 'Source name',
        'available_on_invoice' => 'Available on invoices',
        'empty_hint' => 'Add a source so the channel appears on new invoices.',
    ],

    // ═══════════════════════ Shipping options ═══════════════════════
    'shipping' => [
        'title' => 'Shipping options',
        'subtitle' => 'Shipping methods, their cost, tax treatment and ledger account.',
        'log_created' => 'Shipping option ":name" added',
        'log_updated' => 'Shipping option ":name" changed',
        'log_deleted' => 'Shipping option ":name" deleted',
        'log_status' => 'Shipping option ":name" is now :state',
        'fallback_account' => 'Falls back to',
        'fallback_account_none' => 'No general revenue account is mapped in accounting settings.',
        'new' => 'New shipping option',
        'total' => 'Total options',
        'active' => 'Active',
        'avg_cost' => 'Average cost',
        'cost' => 'Cost',
        'tax' => 'Tax',
        'display_order' => 'Display order',
        'default_account' => 'Default account',
        'empty' => 'No shipping options yet',
        'confirm_delete' => 'Delete the shipping option :name?',
        'toggle_hint' => 'Click to toggle status',

        'create_title' => 'Add shipping option',
        'edit_title' => 'Edit shipping option',
        'show_title' => 'Shipping option',
        'details' => 'Shipping option details',
        'accounting' => 'Ledger link',
        'name_hint' => 'The name staff see when choosing a shipping method on an invoice.',
        'cost_hint' => 'Amount added to the invoice when this method is chosen. Leave at zero for free shipping.',
        'tax_type' => 'Tax treatment',
        'tax_hint' => 'Whether VAT applies to the shipping charge or it is zero-rated.',
        'tax_types' => [
            1 => 'Standard VAT',
            2 => 'Zero-rated',
        ],
        'display_order_hint' => 'Lower numbers appear first.',
        'account_hint' => 'The account shipping revenue is posted to. Leave empty to use the general revenue account.',
        'no_account' => 'No specific account',
        'created' => 'Shipping option added',
        'updated' => 'Shipping option updated',
        'deleted' => 'Shipping option deleted',
        'status_updated' => 'Shipping option status updated',
        'save_failed' => 'Could not save the shipping option',
        'all_options' => 'All defined methods',
        'available_on_invoice' => 'Available on invoices',
        'across_options' => 'Across all methods',
        'empty_hint' => 'Add a shipping method so staff can pick it when creating an invoice.',
        'order_auto' => 'Automatic — last in the list',
        'tax_and_status' => 'Tax & status',
        'status_hint' => 'A disabled method does not appear on invoices.',
        'not_wired_note' => 'Shipping options are defined here but the invoice form does not consume them yet — invoices store free text in the shipping field.',
    ],

    // ═══════════════════════ Exchange reasons ═══════════════════════
    'exchange_reasons' => [
        'subtitle' => 'The reasons staff pick when exchanging a product — the exchange report is built on them, so disable rather than delete once used.',
        'delete_failed' => 'Could not delete the reason — it may still be used by existing exchanges.',
        'title' => 'Exchange reasons',
        'new' => 'New reason',
        'total' => 'Total reasons',
        'active' => 'Active',
        'sort_order' => 'Order',
        'empty' => 'No exchange reasons yet',
        'confirm_delete' => 'Delete the reason :name?',
        'toggle_hint' => 'Click to toggle status',
        'create_title' => 'Add exchange reason',
        'edit_title' => 'Edit exchange reason',
        'details' => 'Reason details',
        'all_reasons' => 'All defined reasons',
        'shown_on_exchange' => 'Shown on the exchange form',
        'name_hint' => 'What staff read in the exchange reason list.',
        'name_placeholder' => 'e.g. Damaged, Defective, Expired…',
        'description_placeholder' => 'Optional note on when this reason applies.',
        'sort_hint' => 'Lower shows first. Leave empty to place it last.',
        'order_auto' => 'Automatic — last in the list',
        'is_active_hint' => 'Only active reasons appear on the exchange form.',
        'empty_hint' => 'Add a reason so staff can pick it when exchanging a product.',
        'created' => 'Exchange reason added',
        'updated' => 'Exchange reason updated',
        'deleted' => 'Exchange reason deleted',
        'status_updated' => 'Reason status updated',
        'name_unique' => 'This reason already exists.',

        // The controller has written to the three logs through `OperationLog`
        // since it was fixed, but these keys never existed — so the activity
        // log recorded the raw key: "sales_settings.exchange_reasons.log_created".
        'save_failed' => 'Could not save the exchange reason — details were written to the error log.',
        'log_created' => 'Exchange reason ":name" added',
        'log_updated' => 'Exchange reason ":name" changed',
        'log_deleted' => 'Exchange reason ":name" deleted',
        'log_status' => 'Exchange reason ":name" is now :state',
    ],

    // ═══════════════════════ Target & commission settings ═══════════════════════
    'targets' => [
        'title' => 'Target & commission settings',
        'subtitle' => 'How achievement is calculated, which account and treasury commissions are paid from, and how settlement is scheduled and approved.',
        'back_to_targets' => 'Targets',

        'types' => [
            'sales' => 'Sales targets',
            'collections' => 'Collection targets',
        ],

        'general' => 'General — :type',
        'enabled' => 'Enable :type',
        'enabled_hint' => 'Turning this off freezes every target of this kind; no achievement is calculated for them.',

        'calculation_method' => 'Calculation method',
        'calculation_method_hint' => 'How an employee’s achievement is measured against their target.',
        'methods' => [
            'net_sales' => 'Net sales (invoices − returns − credit notes)',
            'gross_sales' => 'Gross invoices only',
            'paid_only' => 'Paid invoices only',
            'total_collected' => 'Total collections (cash and bank)',
            'cash_only' => 'Cash collections only',
            'bank_only' => 'Bank transfers only',
        ],

        'commission' => 'Commission',
        'commission_account' => 'Commission account',
        'commission_account_hint' => 'The ledger account commissions are posted to — from the chart of accounts, leaf accounts only.',
        'account_placeholder' => 'Search for an account…',
        'default_treasury' => 'Default payout treasury',
        'default_treasury_hint' => 'Used when the employee has no treasury of their own.',
        'pay_from_employee_treasury' => 'Pay from the employee’s treasury',
        'pay_from_employee_treasury_hint' => 'When on, commission is paid from the employee’s treasury; if they have none, from the default above.',

        'settlement' => 'Scheduled settlement',
        'auto_settlement' => 'Enable automatic settlement',
        'auto_settlement_hint' => 'Pay commissions automatically on the schedule below.',
        'frequency' => 'Frequency',
        'frequencies' => [
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'yearly' => 'Yearly',
        ],
        'day' => 'Run day',
        'day_placeholder' => '1–7 for a week, 1–31 for a month',
        'last_day' => 'Last day of the period',
        'last_day_hint' => 'Handles months of different length (28, 29, 30, 31) — it runs on the actual last day.',

        'day_meaning' => [
            'weekly' => 'Weekly: 1 = Sunday, 2 = Monday … 7 = Saturday. Settles the seven days ending yesterday.',
            'monthly' => 'Monthly: a day of the month (1-31). What is settled — the month just ended or the current month up to the run day — follows "What is settled" below. A day past the month length falls back to its last day.',
            'quarterly' => 'Quarterly: a day of the first month of the quarter (Jan/Apr/Jul/Oct); settles the quarter just ended.',
            'yearly' => 'Yearly: a day of January; settles the year just ended.',
        ],
        'last_day_period' => 'With "last day": the period ending on that same day is settled — set a late time, since anything after the run hour falls outside it.',
        'time' => 'Run time',
        'scope' => 'What is settled',
        'scopes' => [
            'previous' => 'The period just ended — the month/quarter/year before the run day',
            'current' => 'The current period up to the run day — from the start of the month/quarter/year to that day',
        ],
        'scope_hint' => '"Current" suits paying this month\'s commission with the salary (e.g. on the 26th); anything after the run hour on that day falls into the next period.',
        'cron_note' => 'Scheduled settlement needs the Laravel scheduler running on the server:',
        'next_run' => 'Next automatic settlement',
        'next_run_period' => 'it will settle the period from :from to :to',
        'next_run_note' => 'Computed from the saved settings — save after any change to recompute. The payout is executed by the "Sales & collections targets settlement" scheduled task at its first cycle after this time (every ten minutes). The period shown follows "What is settled": the period just ended pays last month\'s commission, the current period pays this month\'s up to the run day. A summary of every run reaches you in notifications.',
        'next_run_none' => [
            'type_disabled' => 'Automatic settlement will not run: this target type is disabled above.',
            'auto_off' => 'Automatic settlement is off — commissions are paid manually from the target report.',
            'no_frequency' => 'Automatic settlement will not run: choose a frequency, then save.',
            'no_account' => 'Automatic settlement will not run: no commission account is set under "Account & payout" above.',
            'cron_job_disabled' => 'Automatic settlement will not run: the "Sales & collections targets settlement" scheduled task is disabled — it is the payout switch, enable it there.',
            'cron_job_missing' => 'Automatic settlement will not run: the "Sales & collections targets settlement" scheduled task does not exist — run "System update" to seed it.',
        ],
        'cron_job_link' => 'Open scheduled tasks',
        'scheduler_status' => 'Scheduler status on the server',

        'notifications' => 'Notifications & approval',
        'notify_on_settlement' => 'Notify when commission is paid',
        'notify_on_settlement_hint' => 'Raises an alert in the system bar on settlement — subject to what is set in Notification settings.',
        'require_approval' => 'Require approval before payout',
        'require_approval_hint' => 'Settlement waits for a manager to approve it before commission is paid.',

        'save' => 'Save :type settings',
        'saved' => ':type settings saved',
        'save_failed' => 'Could not save the target settings — details were written to the error log.',
        'log_updated' => ':type settings changed',
    ],

    // ═══════════════════════ Discount offers ═══════════════════════
    'offers' => [
        'title' => 'Discount offers',
        'subtitle' => 'A discount applied automatically to invoice lines — fixed or percentage, across all products or on chosen categories or products.',
        'new' => 'New discount offer',
        'create_title' => 'Add discount offer',
        'edit_title' => 'Edit discount offer',
        'show_title' => 'Discount offer details',
        'details' => 'Offer details',
        'scope' => 'Offer scope',

        'total' => 'Total offers',
        'running' => 'Running today',
        'expired' => 'Expired',
        'pending' => 'Not started',

        'name_placeholder' => 'e.g. End-of-season discount',
        'valid_from' => 'Valid from',
        'valid_to' => 'Valid to',
        'period' => 'Window',
        'no_period' => 'No window',

        'type' => 'Offer type',
        'types' => [
            1 => 'Discount per line',
            2 => 'Buy a quantity, get a discount',
        ],

        'quantity' => 'Quantity required',
        'quantity_hint' => 'Fixed at one for a per-line discount — it applies to every unit.',

        'discount' => 'Discount',
        'discount_type' => 'Discount type',
        'discount_types' => [
            1 => 'Fixed amount',
            2 => 'Percentage',
        ],
        'discount_value' => 'Discount value',

        'unit_type' => 'Applies to',
        'unit_types' => [
            1 => 'All products',
            2 => 'Chosen categories',
            3 => 'Chosen products',
        ],

        'products' => 'Products covered',
        'products_placeholder' => 'Search for a product…',
        'categories' => 'Categories covered',
        'categories_placeholder' => 'Search for a category…',
        'clients' => 'Customers covered',
        'clients_placeholder' => 'Search for a customer…',
        'clients_hint' => 'Leave empty and the offer applies to every customer.',
        'all_clients' => 'All customers',
        'some_clients' => ':count customers',
        'is_active_hint' => 'A disabled offer is not applied to new invoices.',

        'created' => 'Discount offer created',
        'updated' => 'Discount offer updated',
        'deleted' => 'Discount offer deleted',
        'save_failed' => 'Could not save the discount offer — details were written to the error log.',
        'delete_failed' => 'Could not delete the discount offer — details were written to the error log.',
        'confirm_delete' => 'Delete the discount offer ":name"?',

        'empty' => 'No discount offers yet',
        'empty_hint' => 'Add an offer and it will apply automatically to invoice lines that match it.',

        'log_created' => 'Discount offer ":name" added',
        'log_updated' => 'Discount offer ":name" changed',
        'log_deleted' => 'Discount offer ":name" deleted',
        'applied_notice' => 'Discount offers were applied to :count line(s) (:names).',
    ],

    // ═══════════════════════ Gift offers ═══════════════════════
    'gift_offers' => [
        'title' => 'Gift offers',
        'subtitle' => '"Buy this, get that" — applied to the invoice automatically once the customer reaches the required quantity inside the offer window.',
        'new' => 'New gift offer',
        'create_title' => 'Add gift offer',
        'edit_title' => 'Edit gift offer',
        'details' => 'Condition and gift',
        'audience' => 'Who it applies to',
        'name_placeholder' => 'e.g. Eid gift',
        'name_hint' => 'Optional — shown in the list and in the activity log.',
        'start_date' => 'Starts',
        'end_date' => 'Ends',
        'period_hint' => 'Leave both empty and the offer runs with no fixed window.',
        'all_clients_hint' => 'Turn off to limit the offer to customers you choose.',
        'all_employees' => 'All employees',
        'all_employees_hint' => 'Turn off to limit the offer to sales by employees you choose.',
        'pick_clients' => 'Customers covered',
        'pick_employees' => 'Employees covered',
        'employees_placeholder' => 'Search for an employee…',
        'excluded_clients' => 'Excluded customers',
        'excluded_clients_hint' => 'The offer never applies to them, even under "all customers".',
        'search_placeholder' => 'Search by offer, product or gift…',

        'total' => 'Total offers',
        'active' => 'Enabled',
        'running' => 'Running today',
        'expired' => 'Expired',
        'pending' => 'Not started',

        'buy' => 'Target product',
        'get' => 'Gift',
        'min_quantity' => 'Required quantity',
        'gift_quantity' => 'Free units',
        'period' => 'Window',
        'no_period' => 'No window',

        'all_clients' => 'All customers',
        'some_clients' => 'Selected customers',

        'toggle_hint' => 'Toggle offer status',
        'disable' => 'Disable offer',
        'confirm_disable' => 'Offer ":name" will stop applying to new invoices. Invoices it already applied to are unaffected.',

        'created' => 'Gift offer created',
        'updated' => 'Gift offer updated',
        'disabled' => 'Gift offer disabled',
        'status_updated' => 'Offer status updated',
        'save_failed' => 'Could not save the gift offer — details were written to the error log.',

        'empty' => 'No gift offers yet',
        'empty_hint' => 'Add an offer and it will apply automatically to invoices that reach its quantity.',

        'log_created' => 'Gift offer ":name" added',
        'log_updated' => 'Gift offer ":name" changed',
        'log_disabled' => 'Gift offer ":name" disabled',
        'log_status' => 'Gift offer ":name" is now :state',
    ],

    // ═══════════════════════ Supply order settings ═══════════════════════
    'supply' => [
        'title' => 'Supply order settings',
        'subtitle' => 'Supply order statuses and numbering — both set on the screen that owns them, not on a second copy here.',
        'moved_note' => 'Two screens here saved nothing — forms with no action and no save handler. Both settings work on the screens below.',
        'open_orders' => 'Supply orders',

        'statuses' => 'Supply order statuses',
        'statuses_hint' => 'The list an order moves through: draft, approved, sent to supplier, partially received… with their colours and open/closed state.',

        'numbering' => 'Supply order numbering',
        'numbering_hint' => 'Prefix, digit count and reset cycle — on the "supply order" section of the sequence numbering screen.',

        'unavailable' => 'Neither screen is available right now',
        'unavailable_hint' => 'The Settings or Sales module is disabled in application settings, so its routes are not registered.',
    ],

    // ═══════════════════════ Electronic invoice ═══════════════════════
    'electronic' => [
        'title' => 'Electronic invoice settings',
        'subtitle' => 'Integration with the e-invoicing platform: active environment, the company data required to register, and sending behaviour.',
        'not_configured' => 'Integration not configured yet',

        'status' => 'Service status',
        'ready' => 'Ready to send',
        'incomplete' => 'Data incomplete',
        'disabled' => 'Disabled',
        'active_env' => 'Active environment',
        'data_state' => 'Data completeness',
        'complete' => 'Complete',

        'env' => [
            'sandbox' => 'Developer sandbox',
            'simulation' => 'Simulation environment',
            'production' => 'Live gateway',
        ],
        'env_desc' => [
            'sandbox' => 'Test the integration against simulated data - invoices sent here have no legal standing.',
            'simulation' => 'A full rehearsal with the establishment\'s ZATCA account and a real certificate, with no legal effect. The step before going live.',
            'production' => 'Real submission to ZATCA. Requires complete company data.',
        ],
        'activate' => 'Activate',
        'active_now' => 'Currently active',
        'switch_to' => 'Switch to it',
        'deactivate' => 'Disable service',
        'deactivate_confirm' => 'Disable electronic invoicing? No invoice will be sent until it is enabled again.',

        'behavior' => 'Sending behaviour',
        'behavior_hint' => 'When the invoice is sent to the platform, and what prints with it.',
        'company' => 'Company data',
        'establishment_data' => 'Establishment data',
        'establishment_hint' => 'This data comes from the Account settings screen - shown here, not entered, so no two copies drift apart.',
        'platform_data' => 'Platform-specific data',
        'read_only' => 'Read only',
        'from_account' => 'From account settings',
        'missing_short' => 'Missing',
        'missing_count' => ':count fields missing',
        'missing_in_establishment' => 'Establishment data is incomplete: :fields - complete it in Account settings.',
        'open_account_settings' => 'Open account settings',
        'company_hint' => 'The data the company registers with on the platform. Sending is impossible without it.',
        'connection' => 'Connection details',
        'connection_hint' => 'Issued by the platform on registration. Stored encrypted; leaving them empty keeps what is saved.',
        'open_settings' => 'Open settings',
        'open_company' => 'Edit company data',

        'fields' => [
            'environment' => 'Environment',
            'auto_send_after_create' => 'Send the invoice automatically once created',
            'auto_send_after_create_hint' => 'Without this it is sent manually from the invoice page.',
            'send_on_payment' => 'Send when payment completes',
            'send_on_payment_hint' => 'Defers sending until the invoice is settled.',
            'attach_qr_to_print' => 'Print the QR code on the invoice',
            'attach_qr_to_print_hint' => 'The code is legally required on the printed invoice.',

            'trade_name' => 'Trade name',
            'tax_number' => 'Tax number',
            'commercial_register' => 'Commercial register',
            'country' => 'Country',
            'address' => 'Address',
            'street' => 'Street name',
            'city' => 'City',
            'postal_code' => 'Postal code',
            'district' => 'District',
            'building_number' => 'Building number',
            'invoice_types' => 'Invoice types',
            'zero_tax_category' => 'Category of untaxed lines',
            'zero_tax_reason_code' => 'Exemption reason code',
            'zero_tax_reason' => 'Exemption reason text',

            'tax_group_name' => 'Branch name or tax group number',
            'tax_group_name_hint' => 'Identifies the branch on the platform when the company is a tax group.',
            'business_category' => 'Business category',
            'common_name' => 'Certificate common name',
            'common_name_hint' => 'Used on the signing certificate. Leave empty to use the trade name.',

            'api_base_url' => 'Service URL',
            'client_id' => 'Client ID',
            'client_secret' => 'Client secret',
        ],

        'secret_kept' => 'Saved - leave empty to keep it',
        'missing_note' => 'Sending is blocked until these are provided: :fields',
        'complete_first' => 'Could not activate the live gateway - first provide: :fields',
        'disabled_incomplete' => 'The service was disabled because company data became incomplete.',
        'activated' => ':env activated',
        'deactivated' => 'Electronic invoicing disabled',
        'saved' => 'Electronic invoice settings saved',

        'errors' => [
            'url' => 'The service URL must start with http:// or https://',
        ],
    ],

    'guide' => [
        'offers' => [
            'title' => 'What this screen is, and what its contents cause',
            'lead' => 'A promotion is a discount applied automatically at invoicing whenever its condition is met — so its effect on revenue happens without anyone deciding it per invoice.',
            'fields' => [
                'title' => 'What each field means',
                'a' => '"Offer type" decides when the discount lands: a line discount comes off every unit, while "buy a quantity" waits for the threshold and then repeats once per multiple of it - threshold 30 with a 50 discount on a quantity of 100 deducts 150. "Discount type" decides what is deducted: a fixed amount or a percentage of the line value, and the percentage never multiplies.',
                'b' => '"Scope" decides what it lands on: specific products, or whole categories — and its breadth is the most commonly overlooked part.',
                'c' => '"From" and "to" are the validity window. Leaving "to" empty means a discount with no end.',
                'd' => '"Customers" narrows the offer to those chosen; leaving it on "all" makes it general.',
            ],
            'effects' => [
                'title' => 'What happens on save',
                'a' => 'It applies when a **sales invoice**, **quotation** or **sales return** is saved: written as a line discount, so it reaches the totals, the tax base and the journal entry. A manually typed discount wins; if two offers match one line the larger applies, and no discount exceeds the line total.',
                'b' => '"Running" is not "active": an active offer past its dates discounts nothing.',
                'c' => 'The plan cap counts active offers, and deactivating an expired one frees a slot.',
            ],
        ],
        'commissions' => [
            'title' => 'What this screen is, and what its contents cause',
            'lead' => 'These are your own commissions: what you earned from settled sales targets, and the payout button really takes money out of your treasury.',
            'a' => 'What is shown is limited to your own employee record — nobody else is paid from here, whatever your permissions.',
            'b' => 'Paying out writes an entry and reduces your treasury balance, and marks the entitlement as paid so it cannot be paid twice.',
            'c' => 'What does not appear here has targets not yet settled — settlement runs on a scheduled cycle from "Target settings", not from a button on this screen.',
            'd' => 'Viewing and paying out are separate permissions: a company that wants reps to see but not self-pay grants the first and withholds the second.',
        ],
        'shipping' => [
            'title' => 'What this screen is, and what its contents cause',
            'lead' => 'Shipping options are the list a user picks from when invoicing an order that ships — and the pick adds a shipping cost to the invoice total.',
            'fields' => [
                'title' => 'What each field means',
                'a' => '"Cost" is added to the invoice total as its own line, and enters the tax base when a tax is attached.',
                'b' => '"Duration" is an expectation shown to the customer and binds nothing — no stock or scheduling changes because of it.',
                'c' => '"Active" decides whether the option appears on the invoice screen. Deactivating hides it from new invoices while keeping it on those that chose it.',
            ],
            'effects' => [
                'title' => 'What happens on save',
                'a' => 'The option appears on the invoice screen immediately — no past invoice is recalculated.',
                'b' => 'And the whole delivery section of the invoice screen disappears when "Disable delivery options" is on, so this option is not seen however active it is.',
                'c' => 'The plan cap counts active options only.',
            ],
        ],
        'order_sources' => [
            'title' => 'What this screen is, and what its contents cause',
            'lead' => 'The order source records where a sale came from: a field visit, a phone call, the online store. The "where do our sales come from" reports are built on it.',
            'fields' => [
                'title' => 'What each field means',
                'a' => 'The screen saves the whole table at once: rows with an id are updated, rows without one are created.',
                'b' => 'Order comes from the row position after dragging, not from a number you type — what you see is what is saved.',
                'c' => '"Default source" is preselected on a new invoice, and "mandatory" blocks saving without a source.',
            ],
            'effects' => [
                'title' => 'What happens on save',
                'a' => 'It applies to new invoices only — past invoices keep the source recorded on them.',
                'b' => 'The plan cap counts active sources, and the check runs against the number of **new** rows in this save, not the whole table.',
            ],
        ],
        'designs' => [
            'title' => 'What this screen is, and what its contents cause',
            'lead' => 'Document designs are the print templates: the invoice\'s printed shape is chosen from them when printing or exporting.',
            'a' => 'A template changes the shape, not the figures: amounts, taxes and lines come from the document itself whatever the design.',
            'b' => 'The printed letterhead, logo and tax number come from "Company information", not from the template — fix them there, not here.',
            'c' => 'The plan cap counts invoice templates; previews do not count.',
        ],
        'hub' => [
            'title' => 'What this dashboard is',
            'lead' => 'The entry point to all Sales settings: each card is a screen governing one aspect of the module\'s behaviour.',
            'a' => 'What you set here applies across all Sales screens, not to one document.',
            'b' => 'Some settings hide whole fields or sections of the invoice screen — so if a field you know is missing, start at "Invoice settings" before assuming the screen is broken.',
            'c' => 'And a card that will not open means a feature outside your subscription plan, not a fault.',
        ],
        'gift_offers_form' => [
            'title' => 'Before you save this offer',
            'lead' => 'An offer is a rule applied automatically to every invoice that matches it — not a document issued once. Its effect keeps running until you deactivate it or its dates expire.',
            'fields' => [
                'title' => 'What each field means',
                'a' => '"Buy" and "minimum quantity": the condition. The offer does not apply until that product reaches the stated quantity on an invoice.',
                'b' => '"Get" and "gift quantity": the result. That quantity is added as a zero-priced gift line and leaves stock exactly as sold goods do.',
                'c' => '"From" and "to": the validity window. Leaving "to" empty means an offer with no end — the most commonly forgotten setting, and the one that drains stock.',
                'd' => '"Customers" and "Employees": narrowing. Leaving the "all" flag raised while also picking names means the names are ignored and everyone gets it.',
                'e' => '"Excluded customers": an exception that outranks "all" — for when the offer should be general except for these.',
            ],
            'effects' => [
                'title' => 'What happens on save',
                'a' => 'Nothing is recomputed on past invoices: the rule applies to what is created after it.',
                'b' => 'Every create, edit and deactivation is written to the activity and event logs — an offer gives goods away, so its financial trace deserves an owner.',
                'c' => 'The subscription cap is checked on save and counts **running** offers — so deactivating an expired one frees a slot for a new one.',
            ],
        ],
        'exchange_reasons_form' => [
            'title' => 'Before you save this reason',
            'lead' => 'A reason is an entry in the list a rep picks from when exchanging — and the entire exchange report is built on that list.',
            'fields' => [
                'title' => 'What each field means',
                'a' => '"Name" is what the rep sees in the list — make it distinguishing rather than generic, or every case ends up under one entry.',
                'b' => '"Description" is an internal note; searching the list matches it as well as the name.',
                'c' => '"Sort order" decides where the reason sits in the exchange screen list — put what gets picked most at the top.',
                'd' => '"Active" decides whether reps see it. Deactivating hides it from the screen while keeping it on past exchanges that chose it.',
            ],
            'effects' => [
                'title' => 'What happens on save',
                'a' => 'The change shows on the exchange screen immediately, and is written to all three logs.',
                'b' => 'A reason attached to an existing exchange refuses deletion with a message — deactivate it instead.',
                'c' => 'The subscription cap counts active reasons only.',
            ],
        ],
        'electronic_company' => [
            'title' => 'Company details for e-invoicing',
            'lead' => 'These details are sent with every invoice to the platform and printed on the document. An error here is rejected by the platform, not by this system.',
            'a' => 'The tax number and address must match what is registered with the authority exactly — not what is written on your letterhead.',
            'b' => 'Anything missing from the company record is fixed under "Account settings", not here; this screen separates the two kinds of gap and points at each.',
            'c' => 'Do not activate the production environment with incomplete details: sending would fail on the first invoice, and the failure lands on the accountant rather than on whoever set it up.',
        ],
        'electronic_behavior' => [
            'title' => 'Sending behaviour',
            'lead' => 'This is where you decide when an invoice is sent and what happens when sending fails.',
            'a' => 'Immediate sending makes the user wait for the platform to answer; deferred sending saves the invoice and leaves dispatch to a scheduled job.',
            'b' => 'A failed send does not void the invoice: it is a valid local document, and sending is retried.',
            'c' => 'The scheduled job runs from "Scheduled tasks" in system settings — so if `cron` is stopped on the server, nothing deferred goes out.',
        ],

        'gift_offers' => [
            'title' => 'What this screen is, and what its contents cause',
            'lead' => 'Gift offers are "buy this, get that" rules applied when an invoice is created — an offer here gives goods away, so its effect on stock and cost is real.',
            'what_is' => [
                'title' => 'What an offer is here',
                'a' => 'An offer is a rule, not a document: it names a target product and a minimum quantity, and when an invoice reaches that quantity the gift product is added free.',
                'b' => 'The gift leaves stock exactly as sold goods do, and its cost is charged to sales. A broad offer with no end date drains stock without ever showing up in a sales report.',
                'c' => 'An offer can be restricted to specific customers or specific employees, or left open to all. Leaving the "all" flag on while also picking three means the three are ignored and everyone gets it.',
            ],
            'statuses' => [
                'title' => 'The difference between "active" and "running today"',
                'a' => '"Active" is a flag someone raised; "running today" means active **and** dated to include today. An active offer whose dates have passed applies to nothing.',
                'b' => 'The "delete" button on this screen deactivates rather than deletes: the row stays and loses its active flag, so a rule already applied to past invoices never vanishes.',
            ],
            'stats' => [
                'title' => 'The figures on the cards above',
                'a' => 'All three cards count the whole table, not the search result — searching does not change them.',
            ],
        ],

        'exchange_reasons' => [
            'title' => 'What this screen is, and what its contents cause',
            'lead' => 'Exchange reasons are the list a rep picks from when swapping a product for a customer — and the whole exchange report is built on them.',
            'what_is' => [
                'title' => 'Why they are configured here',
                'a' => 'An exchange touches stock and journal entries, and the reason is what explains why. A thin list puts every exchange under one reason and makes the report useless.',
                'b' => 'The sort order decides where a reason sits in the exchange screen list — put what gets picked most at the top.',
                'c' => 'Every add, edit, deactivation and delete is written to the activity and event logs, so it is known who changed a classification the figures depend on.',
            ],
            'statuses' => [
                'title' => 'Deactivating vs deleting',
                'a' => 'Deactivating hides the reason from the exchange screen while keeping it on past exchanges that chose it — usually what is wanted.',
                'b' => 'Deleting is refused with a message when the reason is attached to an existing exchange; deactivate it instead.',
            ],
            'stats' => [
                'title' => 'The figures on the cards above',
                'a' => 'Both cards count the whole table, not the search result. Only the active ones are shown to reps when exchanging.',
            ],
        ],

        'electronic' => [
            'title' => 'What this screen is, and what its contents cause',
            'lead' => 'This is where the system\'s invoices are wired to the e-invoicing platform: fill in the company details, then activate the environment invoices are sent to.',
            'what_is' => [
                'title' => 'The two environments',
                'a' => '"Sandbox" is for testing: nothing sent to it has any legal standing, and it is the right place to confirm the certificate and details before any real invoice.',
                'b' => '"Production" is the legal environment — it is not activated with incomplete details, because sending would fail on the first invoice and the failure would land on the accountant, not on whoever set it up.',
            ],
            'effects' => [
                'title' => 'What activation changes',
                'a' => 'Activating does not resend the past: invoices issued before it stay as they are, and sending starts with what is issued after.',
                'b' => 'Deactivating stops sending and keeps the saved details, so coming back does not mean setting everything up again.',
            ],
        ],
    ],

    'guide_hints' => [
        'gift_offers' => [
            'total' => 'A count over the whole table',
            'active' => 'Flag raised, regardless of dates',
            'running' => 'Active and dated to include today',
            'search' => 'Matches the offer name, the target product name and the gift name',
            'status' => 'Splits by the flag, not by whether the dates are current',
        ],
        'exchange_reasons' => [
            'search' => 'Matches the name and the description together — not the name alone',
            'status' => 'Only active reasons are shown to reps when exchanging',
        ],
    ],
];

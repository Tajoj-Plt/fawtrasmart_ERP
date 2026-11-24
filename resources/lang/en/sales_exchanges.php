<?php

/**
 * Product-exchange screen strings.
 *
 * @see resources/lang/ar/sales_exchanges.php — the primary locale.
 */
return [

    'title' => 'Exchange Management',
    'subtitle' => 'Exchange damaged or short items for customers — a stock movement and a journal entry per operation.',
    'one' => 'exchange',
    'new' => 'New Exchange',
    'edit' => 'Edit Exchange',
    'view' => 'Exchange Details',
    'dashboard' => 'Exchanges Dashboard',
    'reasons' => 'Exchange Reasons',

    'empty' => 'No exchanges recorded',
    'empty_hint' => 'Record an exchange: pick the customer, the issuing warehouse, the warehouse receiving the damaged goods, then the items.',

    'form' => [
        'details' => 'Exchange details',
        'route' => 'Stock route',
        'responsible' => 'Responsible employee',
        'responsible_hint' => 'The exchange is recorded under this employee and counts against their daily limit.',
        'pick_client' => 'Pick a customer',
        'pick_employee' => 'Pick an employee',
        'need_two_storehouses' => 'An exchange needs two different warehouses: one to issue from and one to return to. Fewer are available to you — ask for a second warehouse to be linked to your account.',
        'saving' => 'Saving…',
        'notice' => 'Notice',
        'done' => 'Saved',
        'image_type' => 'Pick an image file.',
        'image_size' => 'The image is larger than 5 MB.',
    ],

    'fields' => [
        'code' => 'Reference',
        'client' => 'Customer',
        'employee' => 'Employee',
        'storehouse' => 'Issuing warehouse',
        'storehouse_hint' => 'The sound item leaves this warehouse for the customer.',
        'return_storehouse' => 'Return warehouse',
        'return_storehouse_hint' => 'The damaged item from the customer enters here — it must differ from the issuing warehouse.',
        'exchange_date' => 'Exchange date',
        'notes' => 'Notes',
        'image' => 'Proof photo',
        'image_hint' => 'Photo of the damaged item — 5 MB max (jpeg, png, jpg, gif, webp).',
        'image_keep' => 'Leave empty to keep the current photo.',
        'total_value' => 'Exchange value',
        'total_value_hint' => 'Computed automatically from the item purchase price — never entered by hand.',
        'status' => 'Status',
        'created_by' => 'Created by',
        'created_at' => 'Recorded at',
        'current_image' => 'Current photo',
        'no_image' => 'No photo',
    ],

    'items' => [
        'title' => 'Exchanged items',
        'hint' => 'The quantity leaves the issuing warehouse and enters the return warehouse in equal number.',
        'product' => 'Item',
        'quantity' => 'Quantity',
        'available' => 'Available',
        'reason' => 'Exchange reason',
        'unit_price' => 'Unit price',
        'total' => 'Total',
        'add' => 'Add item',
        'remove' => 'Remove item',
        'empty' => 'No items — add at least one',
        'count' => 'Item count',
        'quantity_total' => 'Total quantity',
    ],

    'effects' => [
        'title' => 'Operation effect',
        'hint' => 'What this operation produced in stock and in the journal.',
        'permits' => 'Warehouse permits',
        'permit_out' => 'Issue permit (outgoing)',
        'permit_in' => 'Receipt permit (incoming)',
        'journals' => 'Journal entries',
        'journal_out' => 'Goods-out entry',
        'journal_in' => 'Returned-goods-in entry',
        'none' => 'Not generated',
        'view_permit' => 'View permit',
        'view_journal' => 'View entry',
    ],

    'stats' => [
        'total' => 'Total exchanges',
        'items' => 'Items exchanged',
        'value' => 'Exchange value',
        'clients' => 'Customers',
        'today' => 'Today\'s exchanges',
    ],

    'filters' => [
        'code' => 'Reference',
        'code_placeholder' => 'Search by reference',
        'client' => 'Customer',
        'all_clients' => 'All customers',
        'from_date' => 'From',
        'to_date' => 'To',
        'branch' => 'Branch',
        'all_branches' => 'All branches',
    ],

    'messages' => [
        'created' => 'Exchange :code recorded.',
        'updated' => 'Exchange :code updated — its stock movement and entries were re-applied to the new state.',
        'deleted' => 'Exchange :code deleted; its stock movement and entries were reversed.',
        'delete_confirm' => 'Exchange :code will be deleted, its stock movement reversed, and its permits and journal entries removed.',
        'edit_warning' => 'Editing a completed operation reverses its stock movement and entries, then re-applies them with the new data. Review quantities before saving.',
        'save_failed' => 'Could not save the exchange.',
        'delete_failed' => 'Could not delete the exchange.',
        'period_blocked' => 'The accounting period for this date accepts no entry: :reason',
        'period_blocked_original' => 'The accounting period of the original date (:date) is closed, so its entry cannot be reversed: :reason',
        'insufficient_stock' => 'Only :available of ":product" is available in the issuing warehouse.',
        'same_storehouse' => 'The return warehouse must differ from the issuing warehouse.',
        'accounts_missing' => 'The exchange is not saved without its journal entry — these accounts are not mapped in Accounting Settings: :accounts',
        'warehouse_account' => 'warehouse account ":name"',
    ],

    'log' => [
        'created' => 'Created exchange :code for :client worth :value',
        'updated' => 'Updated exchange :code',
        'deleted' => 'Deleted exchange :code and reversed its effect',
    ],

    'guide' => [
        'index' => ['title' => 'What this screen is, and what its contents cause'],
        'create' => ['title' => 'Before you save this exchange'],
        'show' => ['title' => 'What you can do from this screen'],
        'dashboard' => ['title' => 'What this dashboard measures'],

        'lead_index' => 'An exchange is two movements in one operation: goods coming back into one warehouse and goods leaving another — both really touch stock.',
        'lead_create' => 'You are about to create two opposing stock movements, not one document: the returned item enters the returns warehouse, the replacement leaves the sales warehouse.',
        'lead_show' => 'This is a recorded exchange. Both the returned and the replacement lines have already affected stock.',
        'lead_dashboard' => 'A monitoring dashboard, not a data-entry screen: it measures how much is exchanged, why, and by which customers and reps.',

        'what_is' => [
            'title' => 'What an exchange is',
            'a' => 'The customer brings an item back and takes another. The system records both movements as one operation instead of a separate return and invoice.',
            'b' => 'The two warehouses differ deliberately: returned goods do not go straight back on the sales shelf before inspection, which is why the return warehouse must differ from the sales one.',
            'c' => 'What gets posted is the value difference: a dearer replacement leaves the customer owing, a cheaper one leaves them in credit.',
        ],

        'limits' => [
            'title' => 'The limits',
            'a' => 'Each rep and each customer has a daily exchange limit set on the "Exchange limits" screen — reaching it blocks further exchanges the same day.',
            'b' => 'The exchange reason is mandatory and comes from the "Exchange reasons" list. The whole dashboard is built on it, so a generic reason empties the report of value.',
            'c' => 'Your subscription plan caps exchanges per month.',
        ],

        'effects' => [
            'title' => 'What happens on save',
            'a' => 'A receipt permit for the returned item, an issue permit for the replacement, and an entry for the value difference where there is one.',
            'b' => 'The operation is written to all three logs — it touches stock, so its trace deserves an owner.',
        ],

        'settings' => [
            'title' => 'Settings that govern this screen from outside it',
            'a' => '"Exchange reasons" fills the reason list, and "Exchange limits" sets the daily cap.',
            'b' => '"Warehouses" and "Chart of accounts" decide where goods go and which account the difference lands in.',
        ],
    ],

    'board' => [
        'title' => 'Exchanges dashboard',
        'subtitle' => 'Exchange volume, value and reasons, plus employee and client activity over the selected period.',
        'back' => 'Exchanges list',
        'piece' => 'pc',

        'period' => [
            'title' => 'Analysis range',
            'today' => 'Today',
            'last7' => 'Last 7 days',
            'last30' => 'Last 30 days',
            'last90' => 'Last 90 days',
            'custom' => 'Custom range',
            'from' => 'From',
            'to' => 'To',
            'apply' => 'Update',
            'range' => 'Showing :from to :to',
        ],

        'branch' => [
            'label' => 'Branch',
            'all' => 'All branches',
        ],

        'kpi' => [
            'operations' => 'Exchange operations',
            'value' => 'Exchange value',
            'quantity' => 'Items exchanged',
            'clients' => 'Clients exchanging',
        ],

        'limit' => [
            'title' => 'Your daily limit',
            'hint' => 'How many pieces you can still exchange today',
            'used' => 'Used',
            'remaining' => 'Remaining',
            'total' => 'Limit',
        ],

        'charts' => [
            'trend' => 'Trend over time - value and quantity',
            'trend_value' => 'Value',
            'trend_qty' => 'Quantity',
            'top_products' => 'Top 8 exchanged items',
            'top_products_series' => 'Quantity',
            'reasons' => 'Exchange reasons',
            'reasons_total' => 'Total',
        ],

        'tables' => [
            'employees' => 'Employee activity',
            'clients' => 'Most active clients',
            'latest' => 'Latest operations today',
            'top20' => 'Top 20',
            'employee' => 'Employee',
            'client' => 'Client',
            'operations' => 'Operations',
            'quantity' => 'Quantity',
            'code' => 'Reference',
            'empty' => 'No data for the selected period',
            'empty_today' => 'No exchanges today',
        ],

        'limits' => [
            'employees_btn' => 'Employee limits',
            'clients_btn' => 'Client limits',
            'employees_title' => 'Daily employee limits',
            'employees_hint' => 'Pieces an employee may exchange per day. Leave blank for no limit.',
            'apply_all' => 'Apply one limit to everyone',
            'apply_all_placeholder' => 'Fills every row',
            'employee_name' => 'Employee',
            'per_day' => 'Limit (pcs/day)',
            'no_limit' => 'No limit',
            'clients_title' => 'Client limits',
            'global_client' => 'Global client limit',
            'global_client_hint' => 'Applies to any client without a custom limit',
            'search_client' => 'Search for a client...',
            'client_name' => 'Client',
            'client_limit' => 'Custom limit (pcs)',
            'uses_global' => 'Follows global limit',
            'has_custom' => 'Custom limit',
            'loading' => 'Loading...',
            'saving' => 'Saving...',
            'save' => 'Save',
            'close' => 'Close',
            'fetch_failed' => 'Could not load the data',
            'save_failed' => 'Could not save the limits',
        ],
    ],
];

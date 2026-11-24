<?php

/*
|--------------------------------------------------------------------------
| Client settings strings
|--------------------------------------------------------------------------
|
| English mirror of resources/lang/ar/client_settings.php. Arabic is the
| primary locale; keep the keys identical in both so a missing key never
| falls through to the raw dotted path.
|
*/

return [

    'ui' => [
        'home' => 'Home',
        'breadcrumb' => 'Breadcrumb',
        'save' => 'Save Settings',
        'cancel' => 'Cancel',
        'saved' => 'Settings saved successfully.',
        'save_failed' => 'Could not save settings, please try again.',
        'updated_at' => 'Last updated:',
        'none' => 'No change',
        'unlimited' => 'Unlimited',
        'unlimited_hint' => 'Leave empty for "unlimited"',
    ],

    'hub' => [
        'title' => 'Client Settings',
    ],

    /*
    |----------------------------------------------------------------------
    | "System settings in effect here" bar
    |----------------------------------------------------------------------
    */
    'context' => [
        'title' => 'System settings in effect on client screens',
        'hint' => 'Configured outside this screen, but they govern every client screen — click any item to open its screen.',
        'open' => 'Open screen',

        'appearance' => 'Appearance',
        'language' => 'Language',
        'currency' => 'Currency',
        'payments' => 'Payment methods',
        'accounts' => 'Accounting accounts',
        'period' => 'Accounting period',
        'business' => 'Account & business info',
        'templates' => 'Document templates',
        'cron' => 'Scheduled jobs',
        'notifications' => 'Client notifications',
        'subscription' => 'Subscription',

        'count_value' => ':count enabled',
        'ratio_value' => ':enabled of :total enabled',
        'none' => 'Nothing configured',

        'period_open' => 'Open today',
        'period_blocked' => 'Today falls in a closed period',

        'business_ok' => 'Complete',
        'business_missing' => 'Name or tax number missing',

        'mode_light' => 'Light mode',
        'mode_dark' => 'Dark mode',

        'subscription_active' => 'Active',
        'subscription_inactive' => 'Suspended or expired',
    ],

    /*
    |----------------------------------------------------------------------
    | Enforcement messages — what a setting says when it blocks an action
    |----------------------------------------------------------------------
    */
    'enforcement' => [
        'section_disabled' => 'The ":section" section is disabled in Client Modules Settings.',

        'appointment_past' => 'An appointment in the past is not allowed. Change this under Reservation Settings → Appointments → Client appointments.',
        'appointments_disabled' => 'Client appointments are disabled under Reservation Settings → Appointments.',
        'appointment_outside_hours' => 'The appointment falls outside allowed working hours (:start – :end).',
        'appointment_overlap' => 'This employee already has an appointment at that time, and overlaps are disabled in the appointment settings.',

        'note_location_required' => 'You must be near the client location to add a note.',

        'itinerary_full' => 'This itinerary has reached its cap (:max clients); it currently holds :current.',
        'itinerary_locked' => 'The first visit on this itinerary has started, and editing after start is locked in settings.',
        'itinerary_not_assigned' => 'This client is not assigned to that employee, and additions are limited to assigned clients.',

        'employee_single_only' => 'More than one responsible employee per client is not allowed.',
        'employee_limit' => 'This client has reached its cap of responsible employees (:max).',
        'employee_primary_required' => 'A primary employee must be set when a client has more than one.',

        'transfer_paid_invoice' => 'This invoice is fully paid, and transferring paid invoices is disabled in settings.',
        'transfer_limit' => 'The receiving employee has reached the cap on pending transfers (:max).',

        'writeoff_needs_approval' => 'Writing off more than :threshold requires the "Approve write-offs above the allowed limit" permission.',
    ],

    /*
    |----------------------------------------------------------------------
    | Clients list screen
    |----------------------------------------------------------------------
    */
    'index' => [
        'results' => 'Clients',
        'results_count' => ':count clients',
        'stats' => [
            'total' => 'Total clients',
            'active' => 'Active clients',
            'due' => 'With outstanding balance',
            'stopped' => 'Stopped clients',
        ],
    ],

    'guide' => [
        'index' => [
            'title' => 'What is this screen, and what follows from it?',
            'lead' => 'The client directory: who you sell to, their balances, and which employees follow them. What you see is bounded by your permission and your branch, not by the whole table.',
            'scope' => [
                'title' => 'What you see and why',
                'a' => 'Four alternative view permissions govern this list: all clients, your own, your groups, or your daily itinerary — and the tiles above count what you see, not what the system holds.',
                'b' => 'A client hidden from the map stays in the list; hiding applies to the rep map only and expires automatically per the settings.',
                'c' => 'Sections disabled under Client Modules Settings hide their buttons here and block direct access to their routes.',
            ],
            'balance' => [
                'title' => 'Balances and dues',
                'a' => 'A client balance is read from their accounting account, not from a column on the clients table — so it follows the journal, not manual entry.',
                'b' => 'The credit limit and credit period block issuing a credit invoice to anyone past them — unless the user holds the permission to invoice a client in debt.',
            ],
            'facts' => [
                'status' => 'The default client status and registration rules are set under Client Modules Settings',
                'currency' => 'Currency and number formatting come from Currencies; colours from Appearance',
            ],
        ],

        'form' => [
            'title' => 'What are you filling in here, and what follows from it?',
            'lead' => 'The client record: their details, financial limits, and accounting account. What is required here is set under Client Modules Settings, not in this template.',
            'required' => [
                'title' => 'Required fields',
                'a' => 'The trade name is always required — it is what appears on invoices and statements.',
                'b' => 'Phone, tax number and email: each is a switch under Client Modules Settings → Clients. The asterisk here follows that switch.',
                'c' => 'Location and group may also be required, per the same settings.',
            ],
            'balance' => [
                'title' => 'Balance and limits',
                'a' => 'The opening balance creates a journal entry on the client account and cannot be edited after saving except by an adjusting entry.',
                'b' => 'The credit limit and credit period block credit invoices for anyone past them.',
            ],
            'facts' => [
                'account' => 'An accounting account is created automatically under the Clients account mapped in Accounting Accounts',
                'fields' => 'Which additional fields appear is toggled under Client Modules Settings',
            ],
        ],

        'show' => [
            'title' => 'What are you looking at on the client record?',
            'lead' => 'Everything that happened with this client in one place: their details, balance, invoices and payments, follow-up notes, and timeline.',
            'balance' => [
                'title' => 'Balance and dues',
                'a' => 'The balance shown comes from the client accounting account and moves with each journal entry, not with manual edits.',
                'b' => 'Writing off a debt or moving it to "doubtful" creates real journal entries, and is refused when the date falls in a closed accounting period.',
            ],
            'followup' => [
                'title' => 'Follow-up and notes',
                'a' => 'The note edit window, whether deletion is allowed, and the attachment count all come from Client Modules Settings → Follow-up.',
                'b' => 'The client status may move automatically after the first note, if that transition is configured.',
            ],
            'facts' => [
                'log' => 'Every operation on this record is written to the activity and event logs',
                'period' => 'Accounting operations are subject to accounting periods and year-end closing',
            ],
        ],
    ],

    'statement' => [
        'movements' => 'Statement movements',
    ],

    'module_settings' => [
        'title' => 'Client Modules Settings',
        'subtitle' => 'One screen for everything that governs the Client module: section toggles, and the rules for clients, visits, itineraries, follow-up, employees and invoices.',
        'nav_title' => 'Client Modules Settings',
        'nav_hint' => 'Feature toggles, and client/visit/appointment/transfer rules',

        'tabs' => [
            'clients' => 'Clients',
            'visits' => 'Visits',
            'itinerary' => 'Itineraries',
            'follow_up' => 'Follow-up',
            'employees' => 'Employees',
            'invoices' => 'Invoices & Debts',
        ],

        'sections' => [
            'features' => 'Section Toggles',
            'client_fields' => 'Additional Client Fields',
            'client_defaults' => 'Client Defaults & Registration Rules',
            'client_status_change' => 'Client Status Change Approval',
            'location_group' => 'Location & Group',
            'upload_limits' => 'Attachment Size Limits',
            'map' => 'Client Map Visibility',
            'show_actions' => 'Client Show Page Buttons',
            'employee_visit' => 'Visit Note',
            'visit_proximity' => 'Allowed Visit Proximity',
            'employee_proximity_overrides' => 'Per-Employee Proximity Overrides',
            'visit_workspace_sections' => 'Visit Workspace Sections',
            'visit_justification' => 'Incomplete Visit Justifications',
            'finished_visit_clients' => 'Client After Visit Completion',
            'route_tracking' => 'Route Tracking',
            'itinerary_assignment' => 'Restrict Itinerary Additions',
            'itinerary_order' => 'Itinerary Visit Order',
            'create_screen_client_scope' => 'Client List Scope in Create Screens',
            'appointments' => 'Appointments',
            'client_dashboard' => 'Clients Dashboard',
            'assignment_notification' => 'Notify Employees on Assignment Change',
            'client_employees' => 'Assigning Employees to Clients',
            'invoice_transfer' => 'Invoice Handover & Receipt',
            'exchange_transaction' => 'Daily Transaction Requirement for Product Exchange',
            'debt_writeoff' => 'Debt Write-off Approval Threshold',
        ],

        'fields' => [
            // ── Section toggles ──
            'visits_enabled' => 'Visits',
            'itinerary_enabled' => 'Itineraries',
            'location_enabled' => 'Client Location',
            'notes_enabled' => 'Appointment Notes',
            'appointments_enabled' => 'Appointments',
            'crm_enabled' => 'Client Relationship Management (CRM)',
            'doubtful_debts_enabled' => 'Doubtful Debts',
            'invoice_transfer_enabled' => 'Invoice Transfers Between Employees',
            'customer_targets_enabled' => 'Client Targets',
            'exempt_location_enabled' => 'Location Check Exemption',

            // ── Additional fields ──
            'client_fields_hint' => 'A disabled field is hidden from both the create and edit client forms.',
            'client_fields_count' => 'Total fields',
            'client_fields_active' => 'Enabled fields',
            'client_fields_empty' => 'No additional fields defined yet.',
            'client_field_hint_image' => 'Shows the client photo field on both forms; required on create only.',
            'client_field_hint_location' => 'Shows the map location button — whether it is required is set by "Location required" above.',
            'client_field_hint_credit_limit' => 'Shows the credit limit and the max quantity per line item fields.',
            'client_field_hint_credit_duration' => 'Shows the credit period (days) field.',
            'client_field_hint_unwired' => 'Not tied to any field on the client form yet — enabling it has no effect.',

            // ── Client defaults ──
            'default_new_client_status_id' => 'Default client status on creation',
            'default_registration_stage_id' => 'New client condition',
            'default_registration_stage_id_hint' => 'Preselected on the add-client form and applied automatically to any client saved with an empty stage. Leave empty so new clients carry no condition at all.',
            'client_type_scope' => 'Allowed client type',
            'client_type_scope_hint' => 'Limits what appears in the client form\'s "Type" list: both, individual only, or commercial only.',
            'client_type_both' => 'Both',
            'client_type_individual' => 'Individual',
            'client_type_commercial' => 'Commercial',
            'client_require_phone' => 'Phone number is required when adding a client',
            'client_require_mobile' => 'Mobile number is required when adding a client',
            'client_unique_phone' => 'Prevent duplicate phone numbers across clients',
            'client_unique_phone_hint' => 'When on, saving a client with a phone number already on file is rejected.',
            'client_require_tax_number' => 'Tax number is required',
            'client_require_email' => 'Email is required',

            // ── Required fields on the client form ──
            'client_require_trade_name' => 'Trade name is required',
            'client_require_commercial_registration' => 'Commercial registration is required',
            'client_require_category' => 'Client category is required',
            'client_require_registration_stage' => 'Client stage is required',
            'client_require_registration_stage_hint' => 'When off, the client stage stays optional on the add form and falls back to the default stage above when left empty.',

            // ── Location & group ──
            'location_required' => 'Location is required when adding/editing a client',
            'group_required' => 'Group is required when adding a client',

            // ── Attachments & map ──
            'max_upload_size_kb' => 'Unified maximum size for any attachment (KB)',
            'image_quality_percent' => 'Image compression quality before upload (%)',
            'image_max_dimension_px' => 'Max image dimension after compression (px)',
            'max_upload_size_kb_hint' => '102400 = 100 MB',
            'map_hide_duration_hours' => 'Duration a client stays hidden from the map (hours)',
            'map_hide_duration_hours_hint' => 'When a client is hidden from an employee map, the hide expires automatically after this period.',

            // ── Client show page buttons ──
            // Account statement / create invoice / receipt voucher / credit
            // note are intentionally absent: always visible, no hide option.
            'show_actions_hint' => 'Show/hide each button on the client show page action bar — hiding what the business doesn\'t need reduces button clutter. Account statement, create invoice, receipt voucher, and credit note are always visible and not listed here.',
            'show_edit_button' => 'Edit data button',
            'show_credit_limit_button' => 'Credit limit button',
            'show_quantity_limit_button' => 'Quantity limit button',
            'show_opening_balance_button' => 'Opening balance button',
            'show_add_note_button' => 'Add note button',
            'show_add_employee_note_button' => 'Add client employee button',
            'show_due_invoices_button' => 'Due invoices button',
            'show_product_exchange_button' => 'Product exchange button',
            'show_appointment_button' => 'Schedule appointment button',
            'show_assign_employees_button' => 'Assign employees button',
            'show_debt_management_button' => 'Debt management button',
            'show_hide_client_button' => 'Hide/show client button',
            'show_delete_button' => 'Delete client button',

            // ── Visits ──
            'employee_can_edit_visit_status' => 'Employee can edit their own visit status',
            'employee_can_edit_visit_status_hint' => 'Non-admins — limited to their own visits',
            'employee_can_add_visit_notes' => 'Employee can add notes to their own visit',
            'visit_note_required' => 'Require a visit note before other actions unlock',
            'visit_note_required_hint' => 'Turning this off unlocks all client actions without requiring a visit note or main note first.',
            'visit_proximity_radius_km' => 'Maximum distance (km) between employee and client to log a visit/note',
            'visit_proximity_radius_km_hint' => 'Can be overridden for a specific employee from the "Per-employee proximity overrides" list below.',

            // ── Visit workspace sections ──
            'visit_workspace_sections_title' => 'Visit workspace sections',
            'visit_workspace_sections_hint' => 'Show/hide each card or tab in the "Complete visit" screen — hiding what your business does not need simplifies the screen for field reps.',
            'show_location_card' => 'Visit location & map card',
            'show_quick_actions_card' => 'Quick actions card',
            'show_notes_card' => 'Notes summary card',
            'show_contact_card' => 'Client contact card',
            'show_details_card' => 'Visit details card',
            'show_notes_tab' => 'Notes tab (full)',
            'show_competitors_tab' => 'Competitor watch tab',
            'show_competitor_capture' => 'Competitor-observation step in the visit (after the note)',
            'show_quick_visit_note' => 'Quick visit-note button (photo & deposit only)',
            'note_deposit_count' => 'Deposit-count field in the visit note',
            'note_photo' => 'Photo field in the visit note',
            'note_min_photos' => 'Required photos per note',
            'note_min_photos_hint' => 'Minimum photos to attach. Applies to visit, quick and main notes.',
            'show_docs_tab' => 'Documents tab',
            'show_attachments_tab' => 'Attachments tab',
            'show_signature_section' => 'Signature card (end of visit)',

            // ── Per-employee proximity overrides ──
            'employee_proximity_overrides_title' => 'Per-employee proximity overrides',
            'employee_proximity_overrides_hint' => 'Overrides the general proximity radius above for a specific employee across every visit/note screen.',
            'employee_proximity_overrides_add' => 'Add override',
            'employee_proximity_overrides_employee' => 'Employee',
            'employee_proximity_overrides_radius' => 'Radius (meters)',
            'employee_proximity_overrides_remove' => 'Remove override',
            'employee_proximity_overrides_empty' => 'No employees have a custom radius yet — everyone uses the general setting.',

            // ── Client after visit completion ──
            'finished_visit_hide_clients' => 'Hide the client from the rep\'s list and map after completing the visit',
            'finished_visit_hide_clients_hint' => 'The client is hidden for the rest of the day only; tomorrow\'s itinerary shows it again. Managers and anyone who sees all clients are not affected. Off: the client stays visible and opening it starts a new visit.',
            'finished_visit_allow_return' => 'Allow the rep to return the client to the itinerary the same day',
            'finished_visit_allow_return_hint' => 'The clients list shows a «Visits completed today» strip with a «Return to itinerary» button per client, making it visible and visitable again. Works only while hiding is on.',

            // ── Incomplete visit justifications ──
            'visit_justification_required' => 'Require employees to justify incomplete visits',
            'visit_justification_required_hint' => 'If disabled, no employee is blocked from working over incomplete visits — the individual exemption permission still applies on top of this when enabled.',

            // ── Itinerary ──
            'enable_route_tracking' => 'Enable route tracking (live map)',
            'enable_route_tracking_hint' => 'Shows the employee live location on the itinerary map during visits.',

            'itinerary_restrict_to_assigned_clients' => 'Limit adding a client to an employee itinerary to that employee\'s assigned clients only',
            'itinerary_restrict_to_assigned_clients_hint' => 'When off, any user can add any client to any employee itinerary regardless of assignment.',

            'create_screens_restrict_to_itinerary' => "Limit create screens' client lists to today's itinerary",
            'create_screens_restrict_to_itinerary_hint' => "Covers invoices, receipts, credit notes, returns, and product exchange. Turning this off shows the employee all clients instead of only today's itinerary clients.",

            'itinerary_order_strategy' => 'Itinerary client ordering strategy',
            'itinerary_order_manual' => 'Manual — in the order added',
            'itinerary_order_nearest' => 'Nearest first (geographic)',
            'itinerary_order_last_visit' => 'Longest since last visit first',
            'itinerary_max_clients_per_day' => 'Maximum clients on a single day\'s itinerary',
            'itinerary_lock_after_start' => 'Lock the itinerary from edits once its first visit starts',
            'itinerary_allow_reorder_by_employee' => 'Allow the employee to reorder their own itinerary',

            'dashboard_needs_visit_days' => 'A client "needs a visit" after this many days since the last visit',
            'dashboard_overdue_days' => 'A client is "overdue" when debt is past due by more than (days)',
            'dashboard_settings_hint' => 'Used by the "Needs visit" and "Overdue" cards and their lists on the clients dashboard. Both can be overridden temporarily from the dashboard toolbar.',
            'dashboard_segments' => [
                'lifecycle_title' => '"Client lifecycle" segments',
                'lifecycle_help' => 'Read the grey line under each segment name: it says exactly who enters it. Rows are checked top to bottom and a client enters the first one that matches.',
                'aging_title' => '"Debt aging" segments',
                'aging_help' => 'Enter each row\'s upper bound in days past due; its start follows the previous row, and the last row is open-ended.',
                'add' => 'Add segment',
                'reset' => 'Restore defaults',
                'reset_confirm' => 'Replace the current segments with the defaults? Nothing is saved until you press Save.',
                'name' => 'Name — and who enters',
                'color' => 'Color',
                'from' => 'Last invoice: from day',
                'to' => 'To day',
                'aging_from' => 'Overdue from (days)',
                'aging_to' => 'To (days) — upper bound',
                'to_placeholder' => 'No limit',
                'min_invoices' => 'Condition: min. invoices',
                'min_gap' => 'Condition: returned after a gap (days)',
                'move_up' => 'Move up',
                'move_down' => 'Move down',
                'remove' => 'Remove',
                'sentence' => [
                    'enters' => 'A client enters when:',
                    'last_activity' => 'their last invoice was',
                    'last_invoice' => 'their last invoice was',
                    'range_any' => 'at any time',
                    'range_within' => 'within the last :to days',
                    'range_older' => ':from days ago or more',
                    'range_between' => ':from to :to days ago',
                    'min_invoices' => 'with :n invoices or more',
                    'min_gap' => 'and they returned after a gap of :n days or more',
                    'joiner' => ', ',
                    'unless_above' => '— unless a row above already matched',
                    'aging_all' => 'All unpaid debt',
                    'aging_first' => 'Invoices not yet due, or up to :to days past due',
                    'aging_mid' => 'Invoices :from to :to days past due',
                    'aging_last' => 'Invoices more than :days days past due',
                    'aging_open_not_last' => 'No upper bound — this row becomes the last one on save',
                    'aging_unsorted' => 'Its bound is below the previous row — rows are sorted by bound on save',
                ],
                'guide' => [
                    'title' => 'How do segments work?',
                    'lead' => 'Each row defines one part of a chart on the client dashboard: its name, color and who enters it.',
                    'lines' => [
                        'Lifecycle: days since the last sales invoice (visits do not count). Rows are checked top to bottom and a client enters the first row that matches.',
                        'Debt aging: days an invoice is past due; enter each row\'s upper bound.',
                        'The grey line under each row name shows who enters it.',
                    ],
                ],
                'last_row' => 'At least one segment must remain.',
            ],

            // ── Employees ──
            'notify_employee_on_assignment_change' => 'Notify the employee when assigned to or removed from a client/group',
            'client_employees_allow_multiple' => 'Allow more than one responsible employee per client',
            'client_employees_max' => 'Maximum employees per client',

            // ── Invoices ──
            'invoice_transfer_require_approval' => 'Transfers require the receiving employee to accept',
            'invoice_transfer_require_approval_hint' => 'When off, the transfer takes effect immediately with no acceptance step.',
            'invoice_transfer_reason_required' => 'Require a written reason on transfer and rejection',
            'invoice_transfer_notify' => 'Notify both parties on transfer',
            'invoice_transfer_allow_paid' => 'Allow transferring a fully paid invoice',
            'invoice_transfer_max_open' => 'Maximum pending transfers per employee',

            'bad_debt_writeoff_approval_threshold' => 'Write-offs above this amount need an extra approval permission',
            'bad_debt_writeoff_approval_threshold_hint' => 'Leave empty to require no extra approval (current behavior)',

            'exchange_require_transaction' => 'Require an invoice/receipt/credit note today for product exchange',
            'exchange_require_transaction_hint' => 'Turning this off allows exchange on an itinerary day without a same-day transaction.',

            'statuses_link' => 'Manage client statuses (including which count as "active"/"stopped" for dashboard indicators)',

            'registration_stages_link' => 'Manage client conditions (an extra tag on clients — does not affect their status)',

            'client_status_change_requires_approval' => 'Require manager approval for client status changes',
            'client_status_change_requires_approval_hint' => 'On: an employee without the "Approve client status change" permission sends a request to their manager instead of changing the status directly. Off: any employee with the "Change client status" permission changes it immediately, no extra approval needed.',
        ],

        'guide' => [
            'title' => 'What is this screen, and what follows from it?',
            'lead' => 'This is the single Client settings screen: sub-section toggles, and the business rules that govern their behavior — not decorative switches.',
            'a' => 'Disabling a section hides its sidebar link **and blocks** direct access to its routes — not a visual hide only.',
            'b' => 'The old "General" screen was merged here: additional fields and client type now live under the Clients tab.',
            'c' => 'Anything that depends on a scheduled job does nothing until that job is enabled under Scheduled Jobs — the bar at the top of this screen tells you how many are on.',
            'd' => 'Leaving the debt write-off threshold empty keeps current behavior: any user with debt management permission can write off any amount with no extra approval.',
            'fact1' => 'Changes are written to the activity and event logs as soon as they are saved',
            'fact2' => 'Numeric fields left empty mean "unlimited"',
        ],

        'log_updated' => 'Updated client modules settings',
    ],

    // CRM workspace screen — filled in incrementally as each section is redesigned
    'crm' => [
        'notes' => [
            'print_title' => 'Print Note',
            'print_button' => 'Print',
            'unknown_client' => 'Unknown client',
            'unknown' => 'Unknown',
            'employee' => 'Employee',
            'process' => 'Process',
            'deposit_count' => 'Deposit count',
            'client_contact' => 'Client contact',
            'description' => 'Note',
            'attachments' => 'Attachments',
        ],
    ],

];

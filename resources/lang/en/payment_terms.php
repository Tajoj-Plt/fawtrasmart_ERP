<?php

return [
    'label' => 'Payment terms',
    'days_unit' => 'days',
    'hint' => 'Days allowed for payment, counted from the issue date. Leave blank or zero for immediate cash sales.',
    'summary' => ':days days — due on :date',
    'due_date' => 'Due date',
    'immediate' => 'Immediate — no terms',

    'state' => [
        'overdue_one' => 'Overdue by 1 day',
        'overdue' => 'Overdue by :days days',
        'due_today' => 'Due today',
        'due_tomorrow' => 'Due tomorrow',
        'due_soon' => 'Due in :days days',
        'scheduled' => 'Due on :date',
    ],

    'filter' => [
        'label' => 'Due',
        'all' => 'All',
        'overdue' => 'Overdue only',
        'due_soon' => 'Due within a week',
        'has_terms' => 'On terms',
    ],

    'alert' => [
        'title' => 'Alert: customer due payment',
        'body_one' => ':client has 1 invoice on terms totalling :amount now due or nearly due, dated :date (overdue: :overdue)',
        'body' => ':client has :count invoices on terms totalling :amount now due or nearly due, earliest :date (overdue: :overdue)',
        'none' => 'No customer receivables within the given window.',
        'dry_run' => 'Dry run — no notifications created.',
        'sent' => 'Sent :count customer due-payment alerts.',
    ],
];

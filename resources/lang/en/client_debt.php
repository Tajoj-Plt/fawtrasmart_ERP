<?php

/*
 * Guidance for the client debt screens — ledger debt aging and invoice debt aging.
 * Keys mirror resources/lang/ar/client_debt.php exactly. ":days" = overdue days
 * from the client dashboard settings.
 */
return [
    'guide' => [
        'title' => 'How to read the debt figures',

        'ledger' => [
            'lead' => "Each client's debt from the general ledger up to the \"To\" date, split into age buckets. The same figure appears in Client Balances and the Client Dashboard.",
            'source_title' => 'Where the debt comes from',
            'source' => [
                "Client balance = total debit − total credit of every posted entry on the client's account up to the \"To\" date.",
                'It includes invoices, receipts, returns, notes, journal vouchers and opening-balance entries.',
                'The "From" date does not change the aging: debt is a snapshot at the cut-off date, so it matches Client Balances for any range.',
            ],
        ],

        'invoices' => [
            'lead' => 'What is still unpaid on each invoice up to the "To" date, grouped by days overdue.',
            'source_title' => 'Where the debt comes from',
            'source' => [
                'Invoice due = invoice total − returns against it − payments on it up to the "To" date.',
                'The selected range shows its own invoices only; "Show older open debts" brings back earlier ones.',
                'It differs from ledger aging when there are movements without an invoice: opening balances, journal vouchers, payments on the previous balance — the reconciliation bar shows the gap.',
            ],
        ],

        'age_title' => 'How debt age is calculated',
        'age' => [
            "Due date = movement date + the client's credit period (from the client file; zero means due on the invoice date).",
            'Collections and returns clear the oldest amounts first, so old debt stays old.',
            'Age = days from the due date to the "To" date or today, whichever is earlier.',
        ],

        'overdue_title' => 'What "overdue" means',
        'overdue' => [
            'Overdue debt = only what is more than :days days past due — not the whole balance.',
            'The number of days comes from the Client Dashboard settings (Follow-up tab), so "Overdue debt" here equals the dashboard figure.',
            'The buckets (1–30, 31–60 …) show all debt by age; overdue is a part of them.',
        ],

        'credit_title' => 'Credit balances (in favour of clients)',
        'credit' => [
            'A client who paid more than owed, or in advance, has a credit balance: money **we owe them**, not a debt.',
            'Credit balances are shown on their own with count and total, never added to or deducted from "debt" — on both dashboards and every report.',
            'In ledger aging: outside the aging table, in a separate card and table below it.',
            'In invoice aging: an open invoice of a client whose account is not in debit is flagged "account not in debit" — the client paid on account, not against the invoice.',
        ],

        'opening_title' => 'Opening balances',
        'opening' => [
            'Opening-balance entries imported from the previous system are dated 2025-12-04, so their age counts from that date, not their real date.',
            'An opening balance later written off as bad debt nets to zero and does not appear as debt.',
        ],

        'facts' => [
            'Overdue = more than :days days',
            'Same figures as the Client Dashboard',
            'Collections clear the oldest first',
        ],
    ],

    'kpi' => [
        'overdue_clients' => 'Overdue clients',
        'overdue_clients_sub' => 'over :days days · of :total',
        'overdue_debt' => 'Overdue debt',
        'overdue_debt_sub' => ':pct% of debt · over :days days',
        'overdue_invoices' => 'Overdue invoices',
        'overdue_invoices_sub' => 'over :days days · of :total',
    ],
];

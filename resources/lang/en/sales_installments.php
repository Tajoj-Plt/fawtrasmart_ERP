<?php

/**
 * Installment agreements — English.
 *
 * The section never had a language file: every string was Arabic written
 * into the templates and the controller, and the currency symbol `ر.س` was
 * hardcoded in ten places across four templates whatever the system currency.
 *
 * Keys mirror `resources/lang/ar/sales_installments.php` exactly.
 */
return [

    // ══════════════════ Screens ══════════════════

    'title' => 'Installment agreements',
    'subtitle' => 'Plans for settling customer invoices in instalments: financed amount, due schedule and collection.',
    'singular' => 'Installment agreement',

    'create_title' => 'New installment agreement',
    'create_subtitle' => 'The schedule is computed from the financed amount and the count — what you see in the preview is what gets saved.',

    'edit_title' => 'Edit agreement :code',
    'edit_subtitle' => 'Changing the amounts or the count rebuilds the whole instalment schedule.',

    'show_title' => 'Agreement :code',
    'show_subtitle' => 'Amounts, due schedule, collection and timeline.',

    'schedule_title' => 'Instalment schedule',
    'schedule_subtitle' => 'Every instalment across agreements: due, overdue and settled.',

    'new' => 'New agreement',

    // ══════════════════ Fields ══════════════════

    'fields' => [
        'code' => 'Agreement number',
        'invoice' => 'Invoice',
        'client' => 'Customer',
        'total_amount' => 'Total due',
        'down_payment' => 'Down payment',
        'principal_amount' => 'Principal',
        'profit_rate' => 'Murabaha rate',
        'profit_amount' => 'Murabaha amount',
        'admin_fees' => 'Administrative fees',
        'tax' => 'Tax',
        'tax_rate' => 'Tax rate',
        'tax_amount' => 'Tax amount',
        'no_tax' => 'No tax',
        'financed_amount' => 'Financing',
        'financed_total' => 'Total financing',
        'scheduled_amount' => 'Split into instalments',
        'installment_amount' => 'Instalment amount',
        'installment_number' => 'Number of instalments',
        'payment_rate' => 'Payment frequency',
        'first_due_date' => 'First instalment date',
        'last_due_date' => 'Last instalment date',
        'late_fee_type' => 'Late fee type',
        'late_fee_value' => 'Late fee value',
        'late_fee_grace_days' => 'Grace days',
        'late_fee_basis' => 'Accrual basis',
        'late_fee_max' => 'Fee cap per instalment',
        'late_fee_account' => 'Instalment late fees account',
        'treasury' => 'Down payment treasury',
        'currency' => 'Currency',
        'payment_method' => 'Settlement method',
        'status' => 'Agreement status',
        'note' => 'Notes',
        'created_by' => 'Created by',
        'created_at' => 'Created at',
        'branch' => 'Branch',

        // Single instalment
        'number' => 'Instalment no.',
        'due_date' => 'Due date',
        'amount' => 'Amount',
        'paid_amount' => 'Paid',
        'remaining' => 'Remaining',
        'paid_at' => 'Paid on',
        'late_fee' => 'Late fee accrued',
        'late_fee_charged' => 'Late fee charged',
        'late_fee_outstanding' => 'Uncollected late fees',
        'late_fee_collected' => 'Collected late fees',
        'days_late' => 'Days late',
        'chargeable_days' => 'Chargeable days',
    ],

    /*
     * A line of help under every field — it says what filling the field
     * causes, not what its name means.
     */
    'hints' => [
        'code' => 'Generated from the "Installment agreement" sequence section with the INS prefix, and read-only. The number shown is a preview claimed on save — if it is taken by then, the next free one is used. Change the format in "Document numbering".',
        'invoice' => 'The invoice whose balance is being spread. An invoice is never split twice: if it already has an agreement, that one is opened instead of creating a second.',
        'client' => 'The debtor. The schedule appears on that customer\'s record and receipts are issued against them.',
        'total_amount' => 'What the invoice owes before splitting. Filled from the invoice and editable if a different figure was agreed.',
        'down_payment' => 'Paid in cash at signing. **It is recorded as a receipt against the invoice and posted to the ledger the moment the agreement is saved** — a real first payment, not a number in a box — then deducted from the financed amount so it never enters the instalments. Once collected it cannot be edited here; cancel its receipt first.',
        'profit_rate' => 'A flat Murabaha applied once to the principal and then divided over the instalments — not declining interest on the balance. Leave at zero for a profit-free plan.',
        'admin_fees' => 'A one-off amount added to the financed total — file-opening charges and the like.',
        'tax' => 'Picked from "Tax settings" — the rate is never typed here, so two agreements in the same company cannot carry two different taxes just because different people entered them. It applies to the Murabaha and the administrative fees only, never to the goods: their tax is already on the invoice and charging it again is double taxation. The rate is frozen with the agreement, so changing it in settings later does not touch what was signed.',
        'installment_number' => 'How many payments the financed amount is divided into. The instalment amount is derived from it, never typed.',
        'payment_rate' => 'The gap between one instalment and the next. Monthly does not drift: a first instalment on 31 January is followed by 28 February, not 3 March.',
        'first_due_date' => 'When the first payment falls due; the whole schedule is built from it. It is proposed from the **start of the next period**, not from today: weekly starts on the coming Saturday, monthly on the first of next month — so the signing day is not counted against the first period. It is a proposal, not a rule. If its accounting period is locked, the save is refused and the reason appears here.',
        'late_fee_type' => 'A percentage of the instalment, or a fixed amount. Leave empty if there is no late fee.',
        'late_fee_value' => 'A percentage of the instalment\'s outstanding balance, or a fixed amount. Setting it here **authorises the charge**: the "Charge instalment late fees" job posts it to the customer against the late fees account. Leave it at zero if there is no fee.',
        'late_fee_grace_days' => 'Days after the due date during which no fee accrues — **and which do not count as days late**: an instalment ten days past due with seven grace days is three days late for charging. Zero means it accrues from the next day.',
        'late_fee_basis' => 'Once after the grace period ends, or for **every day** of delay. The daily basis accumulates until the instalment is settled or the cap is reached.',
        'late_fee_max' => 'The most the fee can reach on a single instalment however long the delay. Empty or zero means no cap — not advisable with daily accrual: a year-abandoned instalment would accrue a fee several times its own value.',
        'currency' => 'Frozen with the agreement on save, so changing the system currency later does not touch it.',
        'payment_method' => 'How the instalments were agreed to be settled. It prints on the document; the list comes from the active entries in "Payment methods".',
        'treasury' => 'The treasury or bank account the down payment receipt lands in. Leave it to use the one linked to you in "Default treasuries", then the one set in "Accounting accounts".',
        'no_taxes_configured' => 'No tax is defined in "Tax settings" yet — the agreement saves without tax. Define one on the taxes screen for it to appear here.',
        'preview_chain' => 'The first line builds the **total financing** — the amount the down payment is deducted from. The second deducts it and divides the rest by the instalment count. The Murabaha is on the whole total, not on what is left after the down payment: the down payment settles standing financing, it does not shrink its base. The figures are estimates — the server builds the schedule on save and gives the remainder to the last instalment.',
        'orphan_tax_rate' => 'This agreement carries a stored tax rate (:rate%) with no matching entry in "Tax settings". Pick a tax from the list before saving, or it will be saved without tax.',
        'late_fee_posting' => 'Setting a late fee policy here **authorises the charge**: the "Charge instalment late fees" job posts it from the customer account to the instalment late fees account, and attaches it to the instalment that slipped, so that instalment is claimed with its fee. Leave the type empty if you do not want a fee.',
        'status' => 'Active (running), completed (settled), defaulted (has an overdue instalment), cancelled. The first three update automatically with collection — only cancelling is manual.',
        'note' => 'The terms and anything no other field holds. It prints on the document.',
    ],

    'filter_hints' => [
        'search' => 'Searches agreement number, customer name and invoice number.',
        'client' => 'Every agreement for one customer.',
        'status' => 'Agreement status.',
        'collection' => 'Defaulted = has an instalment past its due date.',
        'dates' => 'The range applies to the first instalment date.',
    ],

    // ══════════════════ Frequencies and statuses ══════════════════

    'rates' => [
        'monthly' => 'Monthly',
        'weekly' => 'Weekly',
        'yearly' => 'Yearly',
        'quarterly' => 'Quarterly',
        'biweekly' => 'Every two weeks',
    ],

    'status' => [
        'active' => 'Active',
        'completed' => 'Completed',
        'defaulted' => 'Defaulted',
        'cancelled' => 'Cancelled',
        'all' => 'All statuses',
    ],

    'detail_status' => [
        'pending' => 'Due',
        'partial' => 'Partly paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
        'all' => 'All statuses',
    ],

    'late_fee_types' => [
        'percent' => 'Percentage of the instalment',
        'fixed' => 'Fixed amount',
        'none' => 'No late fee',
    ],

    'late_fee_bases' => [
        'once' => 'Once',
        'daily' => 'Per day of delay',
    ],

    // ══════════════════ Sections ══════════════════

    'sections' => [
        'parties' => 'Invoice and customer',
        'money' => 'Amounts and financing',
        'schedule' => 'Scheduling',
        'penalty' => 'Late fee',
        'settings' => 'Currency and settlement',
        'preview' => 'Schedule preview',
        'fees_summary' => 'Late fees',
    ],

    // ══════════════════ Stats ══════════════════

    'stats' => [
        'total' => 'Agreements',
        'total_hint' => 'What you may view in your branch',
        'active' => 'Active',
        'active_hint' => 'Running, not yet settled',
        'overdue' => 'Defaulted',
        'overdue_hint' => 'Has an instalment past due',
        'financed' => 'Financed amount',
        'financed_hint' => 'System currency only',
        'collected' => 'Collected',
        'collected_hint' => 'From receipts against the invoices',
        'outstanding' => 'Outstanding',
        'outstanding_hint' => 'Financed less collected',
    ],

    // ══════════════════ Tabs and tables ══════════════════

    'tabs' => [
        'summary' => 'Summary',
        'schedule' => 'Instalment schedule',
        'activity' => 'Activity log',
    ],

    'table' => [
        'agreement' => 'Agreement',
        'client' => 'Customer',
        'financed' => 'Financed',
        'progress' => 'Collection',
        'next_due' => 'Next instalment',
        'status' => 'Status',
    ],

    'filters' => [
        'search_placeholder' => 'Agreement number, customer or invoice…',
        'from_date' => 'From',
        'to_date' => 'To',
        'collection' => 'Follow-up',
        'all_clients' => 'All customers',
        'all_collection' => 'All',
        'overdue' => 'Defaulted',
        'due_soon' => 'Due within 30 days',
    ],

    'empty' => 'No installment agreements yet',
    'empty_hint' => 'An agreement is created from an invoice: open the invoice, then "Split into instalments".',
    'schedule_empty' => 'No instalments match the search',
    'no_invoice' => 'No invoice linked to this agreement',

    // ══════════════════ Buttons ══════════════════

    'actions' => [
        'collect' => 'Record a payment',
        'collect_hint' => 'Opens a receipt against the agreement\'s invoice — the single money path in the system.',
        'sync' => 'Re-read collection',
        'sync_hint' => 'Redistributes what receipts recorded across the instalments.',
        'cancel_detail' => 'Cancel instalment',
        'print' => 'Print',
        'pdf' => 'PDF preview',
        'open_invoice' => 'Open invoice',
        'open_client' => 'Open customer',
        'schedule' => 'Instalment schedule',
    ],

    // ══════════════════ Messages ══════════════════

    'messages' => [
        'created' => 'Agreement and its instalment schedule created.',
        'updated' => 'Agreement updated and its schedule rebuilt.',
        'deleted' => 'Agreement and its instalments deleted.',
        'synced' => 'Collection re-read from the receipts.',
        'detail_cancelled' => 'Instalment cancelled.',

        'save_failed' => 'Could not save — the detail was written to the error log.',
        'no_client_account' => 'This customer has no account in the chart of accounts, so nothing can be posted against them. Create it from the customer screen and try again.',
        'has_down_payment' => 'The down payment on this agreement was collected and posted — it cannot be deleted. Cancel its receipt from the receipts screen first.',
        'has_late_fees' => 'A late fee has been posted on the instalments of this agreement — a standing debt on the customer is not erased by deleting a document.',
        'down_payment_posted' => 'The down payment of :amount was collected and posted — it cannot be changed here. Cancel its receipt from the receipts screen, then edit.',
        'delete_failed' => 'Could not delete — the agreement may be referenced by another document.',
        'has_payments' => 'This agreement has started being paid — its schedule is not rebuilt and it is not deleted. Cancel the unpaid instalments instead.',
        'detail_has_payment' => 'Part of this instalment has been paid — it cannot be cancelled.',
        'already_has_agreement' => 'This invoice already has an agreement — this is it.',
        'invoice_already_paid' => 'Invoice :code is fully settled — there is no debt to spread. An installment agreement is a repayment schedule for a standing amount; scheduling one after collection creates a receivable on the customer with nothing behind it.',
        'invoice_has_no_value' => 'This invoice has no value — there is nothing to spread.',
        'not_found' => 'No invoice is linked to this agreement — collection is not possible.',

        'confirm_delete' => 'Agreement :code and its instalment schedule will be deleted. This cannot be undone.',
        'confirm_cancel_detail' => 'Instalment number :number will be cancelled and dropped from the claim.',
    ],

    // ══════════════════ Validation ══════════════════

    'down_payment' => [
        'uncollected' => 'The down payment has not been collected yet: :amount. The first receipt recorded on the invoice is applied to it before the instalments.',
        'collect' => 'Collect down payment',
        'posted' => 'Down payment of :amount collected and posted against the invoice — receipt :reference.',

        'voucher_note' => 'First (down) payment for installment agreement :code',
        'journal' => 'First payment for installment agreement :code on invoice :invoice',
        'debit_line' => 'First payment received in cash',
        'credit_line' => 'First payment from the customer',
    ],

    // ══════════════════ Installment financing ══════════════════

    'financing' => [
        'journal' => 'Installment agreement :code financing — murabaha profit, admin fees and their tax charged to the client',
        'journal_reversal' => 'Reversal of installment agreement :code financing',
        'client_line' => 'Installment financing — agreement :code',
        'profit_line' => 'Deferred murabaha profit — agreement :code',
        'fees_line' => 'Admin fees — agreement :code',
        'tax_line' => 'Installment VAT (profit and fees) — agreement :code',
        'recognition' => 'Murabaha profit earned on collected installments — agreement :code',
        'recognition_reversal' => 'Murabaha profit reversed after a cancelled receipt — agreement :code',
        'deferred_line' => 'From deferred murabaha profit',
        'revenue_line' => 'Murabaha profit earned — agreement :code',

        'deferred_profit_account' => 'Deferred murabaha profit',
        'profit_revenue_account' => 'Murabaha profit',
        'admin_fees_account' => 'Installment admin fees',
        'vat_account' => 'Installment VAT',

        // Lines appended to a receipt's journal, making it compound
        'compound_financing' => 'Installment agreement :code financing',
        'compound_recognition' => 'Murabaha profit earned — agreement :code',
    ],

    // ══════════════════ Late fee ══════════════════

    'late_fee' => [
        'journal' => 'Late fee on instalment :number of agreement :code — :days days late',
        'debit_line' => 'Late fee on instalment :number',
        'credit_line' => 'Late fee revenue — agreement :code',

        'alert_title' => 'Late fee charged',
        'alert_body' => 'A late fee was charged on :count instalment(s) of agreement :code for :client totalling :amount, and posted to the instalment late fees account.',

        'none' => 'No fee on this agreement — no late fee policy was set on it.',
        'policy' => ':type :value, :grace grace days, :basis.',
    ],

    // ══════════════════ Alerts ══════════════════

    'alerts' => [
        'due_title' => 'Instalment due',
        'due_today' => 'Instalment :number of :count on agreement :code for :client falls due today, amount :amount.',
        'due_soon' => 'Instalment :number of :count on agreement :code for :client falls due in :days days (:date), amount :amount.',

        'overdue_title' => 'Instalment overdue',
        'overdue' => 'Instalment :number on agreement :code for :client is overdue — due :date, :days days ago, :amount outstanding.',
        'overdue_with_fee' => 'Instalment :number on agreement :code for :client is overdue — due :date, :days days ago, :amount outstanding plus :fee in charged late fees.',
    ],

    'validation' => [
        'late_fee_value_required' => 'You picked a late fee type with no value — either set the value or clear the type.',
        'late_fee_type_required' => 'You entered a late fee value with no type — a percentage or a fixed amount?',
        'late_fee_max_too_small' => 'The cap is smaller than the fixed fee itself — the instalment reaches it on day one, which makes daily accrual a one-off charge written the long way.',
    ],

    // ══════════════════ Logs ══════════════════

    'log' => [
        'created' => 'Installment agreement **:code** created with **:count** instalments',
        'updated' => 'Installment agreement **:code** updated and its schedule rebuilt',
        'deleted' => 'Installment agreement **:code** deleted',
        'status' => 'Agreement **:code** status became **:status**',
        'detail_cancelled' => 'Instalment number **:number** cancelled on agreement **:code**',
        'down_payment_posted' => 'First payment of **:amount** collected for installment agreement **:code** on invoice **:invoice**',
        'empty' => 'No activity recorded on this agreement yet.',
    ],

    // ══════════════════ Guide panels ══════════════════

    'guide' => [
        'what_is' => 'An installment agreement is a plan for settling an invoice in stages: how much is paid up front, how much stays financed, over how many instalments it is spread, and when each falls due.',

        'levels' => [
            'title' => 'Two levels, not one',
            'a' => '**The agreement** — the plan: customer, invoice, amounts, frequency. One row.',
            'b' => '**The instalment** — one payment out of the plan: its number, due date, amount and what has been paid against it. One row per payment, created by the system.',
            'c' => 'You do not create instalments by hand nor edit their amounts individually: editing the agreement rebuilds the whole schedule.',
        ],

        'money_flow' => [
            'title' => 'Where does the money sit?',
            'a' => 'Payment is recorded as a **receipt against the invoice** — from the "Record a payment" button or straight from the receipts screen. That is the single money path in the system, and the agreement never records a settlement of its own.',
            'b' => 'The **down payment is the first thing through it**: it is written as a receipt and a journal entry the moment the agreement is saved — a real first payment, not a number in a box — so it leaves the customer\'s balance and enters the treasury that day.',
            'c' => 'What is collected is then spread over the instalments in due order: the oldest first, and within each instalment **its amount then its fee**. So no old instalment stays overdue while a later one is paid.',
            'd' => 'That is why there is no "mark instalment as paid" button: the status is a consequence of what was received, not a cause of it.',
            'e' => 'A late fee is **a debt, not an instalment**: it posts from the customer account to the instalment late fees account and touches no treasury until collected. An agreement whose instalments are all settled is "completed" even if an uncollected fee remains on it.',
        ],

        'formula' => [
            'title' => 'How is the financed amount computed?',
            'a' => 'Murabaha = **total due** × rate — flat, applied once, not declining on the balance. The down payment does not shrink its base: it is a settlement against standing financing.',
            'b' => 'Tax = (Murabaha + administrative fees) × the rate of the tax picked from "Tax settings" — never on the goods, whose tax is on the invoice.',
            'c' => 'Financing = total + Murabaha + fees + tax.',
            'd' => '**What gets split = financing − down payment.** An agreement of 10,000 with 2,000 down and 10% Murabaha finances 11,000 and splits 9,000 of it.',
            'e' => 'Fractions go to the last instalment, so the schedule totals the split amount to the cent.',
            'f' => 'And the first due date is proposed from the **start of the next period**: weekly on the coming Saturday, monthly on the first of next month — the signing day is not counted against the first period.',
        ],

        'statuses' => [
            'title' => 'Statuses',
            'a' => '**Active** running, **completed** fully settled, **defaulted** has an instalment past due, **cancelled** stopped by hand.',
            'b' => 'The first three update automatically on every read of collection. Only "cancelled" is your decision.',
            'c' => 'And "overdue" on an instalment is not a stored status but a property read from its date: an instalment due today becomes overdue tomorrow without anyone touching it.',
        ],

        'index' => [
            'title' => 'Installment agreements — what is this screen?',
            'stats' => [
                'title' => 'The cards at the top',
                'a' => '"Financed", "collected" and "outstanding" cover the system currency only: agreements in other currencies are deliberately excluded — adding two currencies into one figure is a wrong figure.',
                'b' => 'They are computed over everything you may view, not over the search result.',
            ],
            'create' => [
                'title' => 'How is an agreement created?',
                'a' => 'From an invoice: open the invoice, then "Split into instalments". No agreement exists without an invoice to split.',
                'b' => 'And no invoice is split twice — if it already has an agreement, that one is opened instead of creating a second.',
            ],
        ],

        'form' => [
            'title' => 'What happens when you save?',
            'save' => [
                'title' => 'Four things happen',
                'a' => '**The agreement number is consumed** from sequence numbering under a lock. What is shown now is only a preview.',
                'b' => '**The whole instalment schedule is built** from the amounts, the count and the frequency — with exactly the number of rows you chose, no more and no fewer.',
                'c' => '**The currency is frozen** with the agreement.',
                'd' => '**A line is written to the activity log** under your name with a value diff.',
            ],
            'rebuild' => [
                'title' => 'Editing rebuilds the schedule',
                'a' => 'Changing the amount, the count or the frequency wipes the instalments and rebuilds them — deliberately: a schedule that disagrees with its agreement is worse than one that gets rebuilt.',
                'b' => 'Which is why **editing is refused once payment has started**: rebuilding would erase recorded payments. Cancel the unpaid instalments instead.',
            ],
            'blocked' => [
                'title' => 'If the save is refused',
                'a' => 'A first instalment date inside a locked accounting period refuses the save, and the reason appears under the field itself.',
                'b' => 'And a down payment equal to or above the total means a financed amount of zero — an agreement with no instalments.',
            ],
        ],

        'show' => [
            'title' => 'The agreement screen',
            'lead' => 'The agreement\'s reference: what was agreed, how far collection got, and who changed it.',
            'sync' => [
                'title' => 'The "Re-read collection" button',
                'a' => 'Receipts are read automatically whenever this screen opens. The button is for someone who recorded a receipt in another tab and wants to see its effect now.',
            ],
            'late' => [
                'title' => 'The two fee columns',
                'a' => '**"Late fee accrued"** is computed now for information — what would be charged if the fee job ran today. **"Late fee charged"** is what has actually been posted against the customer.',
                'b' => 'Nothing is charged unless a late fee policy is set on the agreement; otherwise both columns stay at zero however late its instalments run.',
                'c' => 'Once charged, collect it from the "Record a payment" button on the instalment\'s own row: the screen opens on the instalment amount **and its fee** together.',
            ],
        ],

        'schedule' => [
            'title' => 'Instalment schedule — the collection screen',
            'lead' => 'Every agreement\'s instalments in one place, ordered by due date — so you know what falls due this week and what has slipped.',
            'use' => [
                'title' => 'How to use it',
                'a' => 'Filtering on "overdue" gives you the chase list.',
                'b' => 'And the "Record a payment" button on a row opens a receipt against that instalment\'s invoice.',
            ],
        ],
    ],

    // ══════════════════ Printing ══════════════════

    'print' => [
        'title' => 'Installment agreement',
        'party' => 'Second party',
        'schedule' => 'Payment schedule',
        'signature_client' => 'Customer signature',
        'signature_company' => 'For the company',
        'total_row' => 'Total',
    ],
];

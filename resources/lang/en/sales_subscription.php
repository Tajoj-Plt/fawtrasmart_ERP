<?php

/*
 * Fawtra subscription wording for the Sales screens — shared across all of them.
 *
 * Its Arabic counterpart is `ar/sales_subscription.php`; the keys must match
 * exactly, since every screen reads the same key in either language.
 */

return [

    'features' => [
        'invoices' => 'sales invoices',
        'credit_notes' => 'credit notes',
        'gift_offers' => 'gift offers',
        'exchange_reasons' => 'exchange reasons',
    ],

    'blocked' => [
        'not_in_plan' => 'Your current Fawtra plan does not include the ":feature" feature. Upgrade from the Fawtra dashboard.',
        'limit_reached' => 'You have reached your Fawtra plan limit for this feature. Buy an add-on or wait for the next period.',
    ],

    'bar' => [
        'usage_line' => ':used of :allowance used on your subscription plan.',
        'usage_line_exhausted' => 'Plan limit reached: :used of :allowance. Nothing new will be accepted until you upgrade or the period rolls over.',
    ],

    'guide' => [
        'title' => 'Fawtra subscription limits',
        'a' => 'The coloured bar at the top of the screen shows how much of your plan allowance this document type has consumed — not how many exist in the system.',
        'b' => 'The figures come from a snapshot fetched periodically, not on every page load, so the number can lag reality by a few minutes. The decision taken when you save uses that same snapshot.',
        'c' => 'When the allowance runs out the save is refused with a message stating both figures — nothing already saved is removed.',
        'd' => 'The bar is hidden when the feature is unlimited on your plan, or when this installation is not linked to a subscription at all.',
    ],
];

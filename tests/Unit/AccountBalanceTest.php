<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function leaf_accounts_get_balances_from_journal_entries()
    {
        // Create a leaf account (no children)
        $account = Account::factory()->create([
            'name' => 'Test Asset Account',
            'balance_type' => Account::DEBIT,
            'parent_id' => null
        ]);

        // Create a journal entry
        $journalEntry = JournalEntry::factory()->create();

        // Create journal entry details
        JournalEntryDetail::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $account->id,
            'debit' => 1000,
            'credit' => 0
        ]);

        // Update the account balance
        $account->updateBalanceFromJournalEntries();

        // For a debit account, balance should be debits - credits = 1000 - 0 = 1000
        $this->assertEquals(1000, $account->balance);
    }

    /** @test */
    public function parent_accounts_get_balances_as_sum_of_children()
    {
        // Create a parent account
        $parentAccount = Account::factory()->create([
            'name' => 'Parent Account',
            'balance_type' => Account::DEBIT,
            'parent_id' => null
        ]);

        // Create child accounts
        $childAccount1 = Account::factory()->create([
            'name' => 'Child Account 1',
            'balance_type' => Account::DEBIT,
            'parent_id' => $parentAccount->id,
            'balance' => 500
        ]);

        $childAccount2 = Account::factory()->create([
            'name' => 'Child Account 2',
            'balance_type' => Account::DEBIT,
            'parent_id' => $parentAccount->id,
            'balance' => 300
        ]);

        // Update the parent account balance
        $parentAccount->updateBalanceFromJournalEntries();

        // Parent balance should be sum of children = 500 + 300 = 800
        $this->assertEquals(800, $parentAccount->balance);
    }

    /** @test */
    public function update_all_balances_hierarchically_works_correctly()
    {
        // Create a hierarchy of accounts
        $rootAccount = Account::factory()->create([
            'name' => 'Root Account',
            'balance_type' => Account::DEBIT,
            'parent_id' => null
        ]);

        $level1Account = Account::factory()->create([
            'name' => 'Level 1 Account',
            'balance_type' => Account::DEBIT,
            'parent_id' => $rootAccount->id
        ]);

        // Create leaf accounts
        $leaf1 = Account::factory()->create([
            'name' => 'Leaf 1',
            'balance_type' => Account::DEBIT,
            'parent_id' => $level1Account->id
        ]);

        $leaf2 = Account::factory()->create([
            'name' => 'Leaf 2',
            'balance_type' => Account::DEBIT,
            'parent_id' => $level1Account->id
        ]);

        // Add journal entries to leaf accounts
        $journalEntry1 = JournalEntry::factory()->create();
        JournalEntryDetail::factory()->create([
            'journal_entry_id' => $journalEntry1->id,
            'account_id' => $leaf1->id,
            'debit' => 1000,
            'credit' => 0
        ]);

        $journalEntry2 = JournalEntry::factory()->create();
        JournalEntryDetail::factory()->create([
            'journal_entry_id' => $journalEntry2->id,
            'account_id' => $leaf2->id,
            'debit' => 500,
            'credit' => 0
        ]);

        // Update all balances hierarchically
        Account::updateBalancesHierarchically();

        // Refresh models to get updated balances
        $leaf1->refresh();
        $leaf2->refresh();
        $level1Account->refresh();
        $rootAccount->refresh();

        // Leaf accounts should have balances from journal entries
        $this->assertEquals(1000, $leaf1->balance);
        $this->assertEquals(500, $leaf2->balance);

        // Parent accounts should have balances as sum of children
        $this->assertEquals(1500, $level1Account->balance); // 1000 + 500
        $this->assertEquals(1500, $rootAccount->balance);   // Same as level1Account
    }
}
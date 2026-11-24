<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Client\Models\Client;
use Modules\Account\Models\Account;
use Modules\Stock\Models\JournalEntryDetail;

class TestUtf8 extends Command
{
    protected $signature = 'test:utf8';
    protected $description = 'Test UTF-8 encoding';

    public function handle()
    {
        try {
            $client = Client::first();
            
            if (!$client) {
                $this->error('No clients found');
                return;
            }
            
            $this->info("Client ID: " . $client->id);
            
            $account = Account::ledger()->where('client_id', $client->id)->first();
            
            if (!$account) {
                $this->error('No account found');
                return;
            }
            
            $this->info("Account ID: " . $account->id);
            
            $transaction = JournalEntryDetail::where('account_id', $account->id)->first();
            
            if (!$transaction) {
                $this->error('No transactions found');
                return;
            }
            
            $this->info("Transaction ID: " . $transaction->id);
            
            // تطبيق iconv على كل حقل
            $clientName = @iconv('UTF-8', 'UTF-8//IGNORE', (string) ($client->trade_name ?? ''));
            $clientCode = @iconv('UTF-8', 'UTF-8//IGNORE', (string) ($client->code ?? ''));
            $accountName = @iconv('UTF-8', 'UTF-8//IGNORE', (string) ($account->name ?? ''));
            
            $this->info("Data prepared successfully");
            
            // محاولة تحويل البيانات إلى JSON
            $data = [
                'client_name' => $clientName,
                'client_code' => $clientCode,
                'account_name' => $accountName,
                'transaction_id' => (int) $transaction->id,
                'debit' => (float) $transaction->debit,
                'credit' => (float) $transaction->credit
            ];
            
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            
            if ($json === false) {
                $this->error("JSON encode failed: " . json_last_error_msg());
            } else {
                $this->info("JSON encode successful!");
                $this->line($json);
            }
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}

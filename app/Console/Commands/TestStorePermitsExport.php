<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WarehousePermits;
use App\Exports\StorePermitsExport;
use Maatwebsite\Excel\Facades\Excel;

class TestStorePermitsExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:store-permits-export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the store permits export functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing store permits export functionality...');
        
        // Get some warehouse permits
        $permits = WarehousePermits::limit(5)->get();
        
        if ($permits->isEmpty()) {
            $this->warn('No warehouse permits found in the database.');
            return;
        }
        
        $this->info("Found {$permits->count()} warehouse permits.");
        
        // Try to export them
        try {
            $export = new StorePermitsExport($permits);
            $this->info('StorePermitsExport class instantiated successfully.');
            
            // Try to get the collection
            $collection = $export->collection();
            $this->info("Collection contains {$collection->count()} items.");
            
            // Try to get headings
            $headings = $export->headings();
            $this->info('Headings: ' . implode(', ', $headings));
            
            // Try to map the first item
            if ($permits->isNotEmpty()) {
                $mapped = $export->map($permits->first());
                $this->info('First item mapped successfully.');
                $this->info('Mapped data: ' . json_encode($mapped, JSON_UNESCAPED_UNICODE));
            }
            
            $this->info('Export functionality test completed successfully!');
        } catch (\Exception $e) {
            $this->error('Error during export test: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
        }
    }
}
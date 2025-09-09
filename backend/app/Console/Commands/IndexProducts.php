<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IndexProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:index-products 
                            {--fresh : Delete the index before importing}
                            {--chunk=500 : The number of records to import at a time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index all products in the search engine';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting product indexing...');

        try {
            $fresh = $this->option('fresh');
            $chunkSize = (int) $this->option('chunk');

            if ($fresh) {
                $this->info('Flushing existing product index...');
                Product::removeAllFromSearch();
                $this->info('Index flushed successfully.');
            }

            $totalProducts = Product::where('status', 'active')->count();
            
            if ($totalProducts === 0) {
                $this->warn('No active products found to index.');
                return self::SUCCESS;
            }

            $this->info("Found {$totalProducts} active products to index.");
            
            $bar = $this->output->createProgressBar($totalProducts);
            $bar->start();

            $indexed = 0;
            $errors = 0;

            Product::where('status', 'active')
                ->with(['categories', 'tags'])
                ->chunk($chunkSize, function ($products) use ($bar, &$indexed, &$errors) {
                    try {
                        $products->searchable();
                        $indexed += $products->count();
                        $bar->advance($products->count());
                    } catch (\Exception $e) {
                        $errors += $products->count();
                        $bar->advance($products->count());
                        Log::error('Failed to index product chunk', [
                            'error' => $e->getMessage(),
                            'chunk_size' => $products->count()
                        ]);
                    }
                });

            $bar->finish();
            $this->newLine(2);

            if ($errors > 0) {
                $this->warn("Indexing completed with errors. Indexed: {$indexed}, Errors: {$errors}");
                $this->warn('Check the logs for detailed error information.');
                return self::FAILURE;
            }

            $this->info("Successfully indexed {$indexed} products!");
            $this->info('Products are now searchable via the search API.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to index products: ' . $e->getMessage());
            Log::error('Product indexing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }
}
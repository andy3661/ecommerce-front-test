<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessEmailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:process-emails 
                            {--timeout=60 : Maximum execution time in seconds}
                            {--sleep=3 : Sleep time between jobs in seconds}
                            {--tries=3 : Number of attempts for failed jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process email queue jobs specifically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = $this->option('timeout');
        $sleep = $this->option('sleep');
        $tries = $this->option('tries');

        $this->info('Starting email queue processor...');
        $this->info("Configuration: timeout={$timeout}s, sleep={$sleep}s, tries={$tries}");

        Log::info('Email queue processor started', [
            'timeout' => $timeout,
            'sleep' => $sleep,
            'tries' => $tries
        ]);

        try {
            // Process only the emails queue
            $exitCode = Artisan::call('queue:work', [
                '--queue' => 'emails',
                '--timeout' => $timeout,
                '--sleep' => $sleep,
                '--tries' => $tries,
                '--verbose' => true
            ]);

            if ($exitCode === 0) {
                $this->info('Email queue processing completed successfully.');
                Log::info('Email queue processing completed successfully');
            } else {
                $this->error('Email queue processing failed with exit code: ' . $exitCode);
                Log::error('Email queue processing failed', ['exit_code' => $exitCode]);
            }

            return $exitCode;
        } catch (\Exception $e) {
            $this->error('Error processing email queue: ' . $e->getMessage());
            Log::error('Email queue processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ArchiveOldInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:cleanup {--days=60 : Number of days old to consider for archiving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive unpaid invoices older than specified days (default: 60 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Looking for unpaid invoices older than {$days} days (before {$cutoffDate->format('Y-m-d')})...");

        // Get unpaid invoices older than the specified days
        $query = Invoice::where('paid_status', Invoice::STATUS_UNPAID)
            ->where('is_archived', false)
            ->where('created_at', '<', $cutoffDate);

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            $this->info('No unpaid invoices found to archive.');
            return 0;
        }

        $this->info("Found {$invoices->count()} unpaid invoices to archive:");

        // Archive the invoices
        $archivedCount = 0;
        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();

        foreach ($invoices as $invoice) {
            try {
                $invoice->is_archived = true;
                $invoice->status = Invoice::STATUS_ARCHIVED;
                $invoice->save();
                $archivedCount++;
            } catch (\Exception $e) {
                $this->error("Failed to archive invoice {$invoice->invoice_number}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Successfully archived {$archivedCount} out of {$invoices->count()} invoices.");

        if ($archivedCount < $invoices->count()) {
            $this->warn("Some invoices could not be archived. Check the errors above.");
            return 1;
        }

        $this->info('Archive operation completed successfully!');
        return 0;
    }
}

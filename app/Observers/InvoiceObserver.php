<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\InvoicePaidAuditLog;

class InvoiceObserver
{
    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        // Check if paid_status changed to PAID
        if ($invoice->wasChanged('paid_status') && $invoice->paid_status === Invoice::STATUS_PAID) {
            $oldStatus = $invoice->getOriginal('paid_status');
            
            // Confirm the status actually changed (not from PAID to PAID)
            if ($oldStatus !== Invoice::STATUS_PAID) {
                InvoicePaidAuditLog::create([
                    'invoice_id' => $invoice->id,
                    'user_id' => request()->user()?->id,
                    'old_status' => $oldStatus,
                    'new_status' => Invoice::STATUS_PAID,
                ]);
            }
        }
    }
} 
<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;

class ToggleInvoiceArchiveController extends Controller
{
    /**
     * Toggle the archived status of an invoice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request, Invoice $invoice)
    {
        $this->authorize('edit-invoice', $invoice);

        if ($invoice->is_archived) {
            $invoice->is_archived = false;
            $invoice->status = $invoice->getPreviousStatus();
        } else {
            $invoice->is_archived = true;
            $invoice->status = Invoice::STATUS_ARCHIVED;
        }
        
        $invoice->save();

        return new InvoiceResource($invoice);
    }
}

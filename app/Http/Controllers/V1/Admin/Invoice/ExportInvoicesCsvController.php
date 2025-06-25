<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExportInvoicesCsvController extends Controller
{
    /**
     * Export invoices as CSV including line items and customer information.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $filename = 'invoices_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($request) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Invoice ID',
                'Invoice Number',
                'Reference Number',
                'Invoice Date',
                'Due Date',
                'Status',
                'Paid Status',
                'Sub Total',
                'Discount',
                'Discount Type',
                'Tax',
                'Total',
                'Due Amount',
                'Notes',
                'Customer ID',
                'Customer Name',
                'Customer Email',
                'Customer Phone',
                'Customer Company',
                'Currency Code',
                'Item ID',
                'Item Name',
                'Item Description',
                'Item Quantity',
                'Item Unit Name',
                'Item Price',
                'Item Discount',
                'Item Discount Type',
                'Item Tax',
                'Item Total',
                'Created At',
                'Updated At'
            ]);

            // Process invoices using generator for memory efficiency
            foreach ($this->getInvoiceGenerator($request) as $invoice) {
                $this->processInvoice($invoice, $file);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Generator function to yield invoices in chunks for memory efficiency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Generator
     */
    private function getInvoiceGenerator($request)
    {
        $query = Invoice::whereCompany()
            ->applyFilters($request->all())
            ->with(['items', 'customer', 'currency'])
            ->latest();

        $lastId = 0;
        
        do {
            $invoices = $query->where('id', '>', $lastId)
                ->limit(100)
                ->get();

            foreach ($invoices as $invoice) {
                yield $invoice;
                $lastId = $invoice->id;
            }
        } while ($invoices->count() === 100);
    }

    /**
     * Process a single invoice and its items for CSV export.
     *
     * @param  \App\Models\Invoice  $invoice
     * @param  resource  $file
     * @return void
     */
    private function processInvoice($invoice, $file)
    {
        if ($invoice->items->count() > 0) {
            // If invoice has items, create a row for each item
            foreach ($invoice->items as $item) {
                fputcsv($file, [
                    $invoice->id,
                    $invoice->invoice_number,
                    $invoice->reference_number,
                    $invoice->invoice_date,
                    $invoice->due_date,
                    $invoice->status,
                    $invoice->paid_status,
                    $invoice->sub_total,
                    $invoice->discount,
                    $invoice->discount_type,
                    $invoice->tax,
                    $invoice->total,
                    $invoice->due_amount,
                    $invoice->notes,
                    $invoice->customer ? $invoice->customer->id : '',
                    $invoice->customer ? $invoice->customer->name : '',
                    $invoice->customer ? $invoice->customer->email : '',
                    $invoice->customer ? $invoice->customer->phone : '',
                    $invoice->customer ? $invoice->customer->company : '',
                    $invoice->currency ? $invoice->currency->code : '',
                    $item->id,
                    $item->name,
                    $item->description,
                    $item->quantity,
                    $item->unit_name,
                    $item->price,
                    $item->discount,
                    $item->discount_type,
                    $item->tax,
                    $item->total,
                    $invoice->created_at,
                    $invoice->updated_at
                ]);
            }
        } else {
            // If invoice has no items, create a single row with empty item fields
            fputcsv($file, [
                $invoice->id,
                $invoice->invoice_number,
                $invoice->reference_number,
                $invoice->invoice_date,
                $invoice->due_date,
                $invoice->status,
                $invoice->paid_status,
                $invoice->sub_total,
                $invoice->discount,
                $invoice->discount_type,
                $invoice->tax,
                $invoice->total,
                $invoice->due_amount,
                $invoice->notes,
                $invoice->customer ? $invoice->customer->id : '',
                $invoice->customer ? $invoice->customer->name : '',
                $invoice->customer ? $invoice->customer->email : '',
                $invoice->customer ? $invoice->customer->phone : '',
                $invoice->customer ? $invoice->customer->company : '',
                $invoice->currency ? $invoice->currency->code : '',
                '', // Item ID
                '', // Item Name
                '', // Item Description
                '', // Item Quantity
                '', // Item Unit Name
                '', // Item Price
                '', // Item Discount
                '', // Item Discount Type
                '', // Item Tax
                '', // Item Total
                $invoice->created_at,
                $invoice->updated_at
            ]);
        }
    }
} 
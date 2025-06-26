<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Services\SerialNumberFormatter;
use Vinkla\Hashids\Facades\Hashids;

class InvoiceService
{
    /**
     * Create a new invoice with all related data.
     *
     * @param \App\Http\Requests\InvoicesRequest $request
     * @return \App\Models\Invoice
     */
    public function createInvoice($request)
    {
        $data = $request->getInvoicePayload();

        if ($request->has('invoiceSend')) {
            $data['status'] = Invoice::STATUS_SENT;
        }

        $invoice = Invoice::create($data);

        $this->setInvoiceNumbers($invoice, $request);
        $this->createInvoiceItems($invoice, $request->items);
        $this->handleExchangeRateLog($invoice, $request);
        $this->createInvoiceTaxes($invoice, $request);
        $this->handleCustomFields($invoice, $request);

        return $this->loadInvoiceWithRelations($invoice);
    }

    /**
     * Set invoice numbers and unique hash.
     *
     * @param \App\Models\Invoice $invoice
     * @param \App\Http\Requests\InvoicesRequest $request
     * @return void
     */
    private function setInvoiceNumbers($invoice, $request)
    {
        $serial = (new SerialNumberFormatter)
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $invoice->sequence_number = $serial->nextSequenceNumber;
        $invoice->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();
    }

    /**
     * Create invoice items with their taxes and custom fields.
     *
     * @param \App\Models\Invoice $invoice
     * @param array $invoiceItems
     * @return void
     */
    private function createInvoiceItems($invoice, $invoiceItems)
    {
        $exchange_rate = $invoice->exchange_rate;

        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItem['company_id'] = $invoice->company_id;
            $invoiceItem['exchange_rate'] = $exchange_rate;
            $invoiceItem['base_price'] = $invoiceItem['price'] * $exchange_rate;
            $invoiceItem['base_discount_val'] = $invoiceItem['discount_val'] * $exchange_rate;
            $invoiceItem['base_tax'] = $invoiceItem['tax'] * $exchange_rate;
            $invoiceItem['base_total'] = $invoiceItem['total'] * $exchange_rate;

            if (array_key_exists('recurring_invoice_id', $invoiceItem)) {
                unset($invoiceItem['recurring_invoice_id']);
            }

            $item = $invoice->items()->create($invoiceItem);

            $this->createItemTaxes($item, $invoiceItem, $invoice);
            $this->createItemCustomFields($item, $invoiceItem);
        }
    }

    /**
     * Create taxes for an invoice item.
     *
     * @param \App\Models\InvoiceItem $item
     * @param array $invoiceItem
     * @param \App\Models\Invoice $invoice
     * @return void
     */
    private function createItemTaxes($item, $invoiceItem, $invoice)
    {
        if (!array_key_exists('taxes', $invoiceItem) || !$invoiceItem['taxes']) {
            return;
        }

        foreach ($invoiceItem['taxes'] as $tax) {
            $tax['company_id'] = $invoice->company_id;
            $tax['exchange_rate'] = $invoice->exchange_rate;
            $tax['base_amount'] = $tax['amount'] * $invoice->exchange_rate;
            $tax['currency_id'] = $invoice->currency_id;

            if (gettype($tax['amount']) !== 'NULL') {
                if (array_key_exists('recurring_invoice_id', $invoiceItem)) {
                    unset($invoiceItem['recurring_invoice_id']);
                }

                $item->taxes()->create($tax);
            }
        }
    }

    /**
     * Create custom fields for an invoice item.
     *
     * @param \App\Models\InvoiceItem $item
     * @param array $invoiceItem
     * @return void
     */
    private function createItemCustomFields($item, $invoiceItem)
    {
        if (!array_key_exists('custom_fields', $invoiceItem) || !$invoiceItem['custom_fields']) {
            return;
        }

        $item->addCustomFields($invoiceItem['custom_fields']);
    }

    /**
     * Handle exchange rate logging if needed.
     *
     * @param \App\Models\Invoice $invoice
     * @param \App\Http\Requests\InvoicesRequest $request
     * @return void
     */
    private function handleExchangeRateLog($invoice, $request)
    {
        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $invoice->currency_id !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($invoice);
        }
    }

    /**
     * Create invoice-level taxes.
     *
     * @param \App\Models\Invoice $invoice
     * @param \App\Http\Requests\InvoicesRequest $request
     * @return void
     */
    private function createInvoiceTaxes($invoice, $request)
    {
        if (!$request->has('taxes') || empty($request->taxes)) {
            return;
        }

        $exchange_rate = $invoice->exchange_rate;

        foreach ($request->taxes as $tax) {
            $tax['company_id'] = $invoice->company_id;
            $tax['exchange_rate'] = $invoice->exchange_rate;
            $tax['base_amount'] = $tax['amount'] * $exchange_rate;
            $tax['currency_id'] = $invoice->currency_id;

            if (gettype($tax['amount']) !== 'NULL') {
                if (array_key_exists('recurring_invoice_id', $tax)) {
                    unset($tax['recurring_invoice_id']);
                }

                $invoice->taxes()->create($tax);
            }
        }
    }

    /**
     * Handle custom fields for the invoice.
     *
     * @param \App\Models\Invoice $invoice
     * @param \App\Http\Requests\InvoicesRequest $request
     * @return void
     */
    private function handleCustomFields($invoice, $request)
    {
        if ($request->customFields) {
            $invoice->addCustomFields($request->customFields);
        }
    }

    /**
     * Load invoice with all necessary relations.
     *
     * @param \App\Models\Invoice $invoice
     * @return \App\Models\Invoice
     */
    private function loadInvoiceWithRelations($invoice)
    {
        return Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
        ])->find($invoice->id);
    }
} 
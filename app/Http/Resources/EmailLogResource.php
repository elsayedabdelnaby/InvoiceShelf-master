<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'body' => $this->body,
            'mailable_type' => $this->mailable_type,
            'mailable_id' => $this->mailable_id,
            'token' => $this->token,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'mailable' => [
                'id' => $this->mailable->id,
                'type' => class_basename($this->mailable_type),
                'number' => $this->getMailableNumber(),
                'customer' => $this->getMailableCustomer(),
            ],
        ];
    }

    /**
     * Get the mailable number based on type.
     */
    private function getMailableNumber(): ?string
    {
        if (!$this->mailable) {
            return null;
        }

        switch ($this->mailable_type) {
            case 'App\Models\Invoice':
                return $this->mailable->invoice_number;
            case 'App\Models\Estimate':
                return $this->mailable->estimate_number;
            case 'App\Models\Payment':
                return $this->mailable->payment_number;
            default:
                return null;
        }
    }

    /**
     * Get the mailable customer information.
     */
    private function getMailableCustomer(): ?array
    {
        if (!$this->mailable || !$this->mailable->customer) {
            return null;
        }

        return [
            'id' => $this->mailable->customer->id,
            'name' => $this->mailable->customer->name,
        ];
    }
} 
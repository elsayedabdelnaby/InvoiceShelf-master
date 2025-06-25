<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoicePaidAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoicePaidAuditLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = InvoicePaidAuditLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'user_id' => User::factory(),
            'old_status' => $this->faker->randomElement(['UNPAID', 'PARTIALLY_PAID']),
            'new_status' => 'PAID',
        ];
    }
} 
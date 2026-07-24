<?php

namespace App\Services\Loyalty;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintains customer loyalty point balances through an append-only ledger.
 * Every change locks the customer row, so concurrent earn/redeem operations
 * cannot corrupt the running balance.
 */
class LoyaltyService
{
    public function earn(Customer $customer, int $points, ?int $saleId = null, ?int $userId = null, ?string $note = null): LoyaltyTransaction
    {
        return $this->record($customer, 'earn', abs($points), $saleId, $userId, $note);
    }

    public function redeem(Customer $customer, int $points, ?int $userId = null, ?string $note = null): LoyaltyTransaction
    {
        return $this->record($customer, 'redeem', -abs($points), null, $userId, $note);
    }

    public function adjust(Customer $customer, int $points, ?int $userId = null, ?string $note = null): LoyaltyTransaction
    {
        return $this->record($customer, 'adjust', $points, null, $userId, $note);
    }

    protected function record(Customer $customer, string $type, int $points, ?int $saleId, ?int $userId, ?string $note): LoyaltyTransaction
    {
        if ($points === 0) {
            throw ValidationException::withMessages(['points' => ['Points must be non-zero.']]);
        }

        return DB::transaction(function () use ($customer, $type, $points, $saleId, $userId, $note) {
            /** @var Customer $locked */
            $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $balance = (int) $locked->loyalty_points + $points;

            if ($balance < 0) {
                throw ValidationException::withMessages([
                    'points' => ["Insufficient loyalty points: balance is {$locked->loyalty_points}."],
                ]);
            }

            $locked->update(['loyalty_points' => $balance]);

            return LoyaltyTransaction::create([
                'customer_id' => $locked->id,
                'sale_id' => $saleId,
                'user_id' => $userId,
                'type' => $type,
                'points' => $points,
                'balance_after' => $balance,
                'note' => $note,
            ]);
        });
    }
}

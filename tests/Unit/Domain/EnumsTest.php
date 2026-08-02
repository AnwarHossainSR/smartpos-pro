<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\AccountType;
use App\Domain\Enums\CashMovementType;
use App\Domain\Enums\DiscountType;
use App\Domain\Enums\JournalLineType;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\PurchaseStatus;
use App\Domain\Enums\SaleStatus;
use App\Domain\Enums\StockDirection;
use App\Domain\Enums\StockMovementType;
use App\Domain\Enums\TransferStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnumsTest extends TestCase
{
    /**
     * Direction used to be passed alongside type, which let a caller record a
     * `purchase` that decremented stock. It is now derived, so this table is the
     * contract the ledger relies on.
     */
    #[DataProvider('movementDirections')]
    public function test_movement_type_determines_direction(StockMovementType $type, StockDirection $expected): void
    {
        $this->assertSame($expected, $type->direction());
    }

    /** @return array<string, array{StockMovementType, StockDirection}> */
    public static function movementDirections(): array
    {
        return [
            'sale' => [StockMovementType::Sale, StockDirection::Out],
            'purchase' => [StockMovementType::Purchase, StockDirection::In],
            'adjustment in' => [StockMovementType::AdjustmentIn, StockDirection::In],
            'adjustment out' => [StockMovementType::AdjustmentOut, StockDirection::Out],
            'transfer in' => [StockMovementType::TransferIn, StockDirection::In],
            'transfer out' => [StockMovementType::TransferOut, StockDirection::Out],
            'sale return' => [StockMovementType::SaleReturn, StockDirection::In],
            'purchase return' => [StockMovementType::PurchaseReturn, StockDirection::Out],
            'opening' => [StockMovementType::Opening, StockDirection::In],
        ];
    }

    public function test_every_movement_type_is_covered_by_the_direction_table(): void
    {
        $covered = array_map(fn (array $case) => $case[0], array_values(self::movementDirections()));

        $this->assertEqualsCanonicalizing(StockMovementType::cases(), $covered);
    }

    public function test_stock_direction_signs(): void
    {
        $this->assertSame(1, StockDirection::In->sign());
        $this->assertSame(-1, StockDirection::Out->sign());
        $this->assertSame(1, CashMovementType::In->sign());
        $this->assertSame(-1, CashMovementType::Out->sign());
    }

    /** Assets and expenses grow on the debit side; everything else on the credit side. */
    #[DataProvider('accountSides')]
    public function test_account_normal_side(AccountType $type, bool $debitNormal): void
    {
        $this->assertSame($debitNormal, $type->isDebitNormal());
        $this->assertSame(
            $debitNormal ? JournalLineType::Debit : JournalLineType::Credit,
            $type->normalSide(),
        );
    }

    /** @return array<string, array{AccountType, bool}> */
    public static function accountSides(): array
    {
        return [
            'cash' => [AccountType::Cash, true],
            'bank' => [AccountType::Bank, true],
            'mobile banking' => [AccountType::MobileBanking, true],
            'wallet' => [AccountType::Wallet, true],
            'asset' => [AccountType::Asset, true],
            'expense' => [AccountType::Expense, true],
            'income' => [AccountType::Income, false],
            'liability' => [AccountType::Liability, false],
            'equity' => [AccountType::Equity, false],
        ];
    }

    #[DataProvider('paymentStatusCases')]
    public function test_payment_status_is_derived_from_amounts(float $paid, float $total, PaymentStatus $expected): void
    {
        $this->assertSame($expected, PaymentStatus::fromAmounts($paid, $total));
    }

    /** @return array<string, array{float, float, PaymentStatus}> */
    public static function paymentStatusCases(): array
    {
        return [
            'settled exactly' => [100.0, 100.0, PaymentStatus::Paid],
            'overpaid (change given)' => [120.0, 100.0, PaymentStatus::Paid],
            'part paid' => [40.0, 100.0, PaymentStatus::Partial],
            'nothing paid' => [0.0, 100.0, PaymentStatus::Due],
            'a sub-cent remainder still counts as settled' => [99.999, 100.0, PaymentStatus::Paid],
        ];
    }

    public function test_only_a_completed_sale_moves_stock(): void
    {
        $this->assertTrue(SaleStatus::Completed->affectsStock());

        foreach ([SaleStatus::Draft, SaleStatus::Held, SaleStatus::Quotation, SaleStatus::Cancelled] as $status) {
            $this->assertFalse($status->affectsStock(), "{$status->value} must not move stock");
        }
    }

    public function test_only_received_purchases_move_stock(): void
    {
        $this->assertTrue(PurchaseStatus::Received->affectsStock());
        $this->assertTrue(PurchaseStatus::Partial->affectsStock());
        $this->assertFalse(PurchaseStatus::Draft->affectsStock());
        $this->assertFalse(PurchaseStatus::Ordered->affectsStock());
        $this->assertFalse(PurchaseStatus::Cancelled->affectsStock());
    }

    public function test_discount_type_applies_against_a_base(): void
    {
        $this->assertSame(15.0, DiscountType::Percentage->apply(15, 100));
        $this->assertSame(15.0, DiscountType::Fixed->apply(15, 100));
    }

    public function test_coerce_accepts_enums_strings_and_absence(): void
    {
        $this->assertSame(SaleStatus::Held, SaleStatus::coerce(SaleStatus::Held));
        $this->assertSame(SaleStatus::Held, SaleStatus::coerce('held'));
        $this->assertSame(SaleStatus::Completed, SaleStatus::coerce(null, SaleStatus::Completed));
        $this->assertSame(SaleStatus::Completed, SaleStatus::coerce('', SaleStatus::Completed));
        $this->assertNull(SaleStatus::coerce(null));
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Mobile banking', AccountType::MobileBanking->label());
        $this->assertSame('In transit', TransferStatus::InTransit->label());
    }

    public function test_values_helper_returns_wire_strings(): void
    {
        $this->assertSame(['in', 'out'], StockDirection::values());
    }
}

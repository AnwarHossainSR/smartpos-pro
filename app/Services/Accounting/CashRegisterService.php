<?php

namespace App\Services\Accounting;

use App\Domain\Enums\CashMovementType;
use App\Domain\Enums\CashSessionStatus;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages cashier drawer sessions: open with a float, record cash in/out during
 * the shift, and close with a physical count that is reconciled against the
 * expected balance (opening + ins − outs).
 */
class CashRegisterService
{
    public function open(CashRegister $register, float $openingCash, User $user): CashRegisterSession
    {
        if ($register->openSession()) {
            throw ValidationException::withMessages([
                'cash_register_id' => ['This register already has an open session.'],
            ]);
        }

        return CashRegisterSession::create([
            'cash_register_id' => $register->id,
            'user_id' => $user->id,
            'opening_cash' => round($openingCash, 2),
            'expected_cash' => round($openingCash, 2),
            'status' => CashSessionStatus::Open,
            'opened_at' => Carbon::now(),
        ]);
    }

    public function movement(CashRegisterSession $session, CashMovementType $type, float $amount, ?string $reason, User $user): CashRegisterSession
    {
        return DB::transaction(function () use ($session, $type, $amount, $reason, $user) {
            $this->assertOpen($session);
            $amount = round($amount, 2);

            $session->transactions()->create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            $column = $type === CashMovementType::In ? 'cash_in' : 'cash_out';
            $session->increment($column, $amount);
            $session->increment('expected_cash', $amount * $type->sign());

            return $session->fresh();
        });
    }

    public function close(CashRegisterSession $session, float $actualCash, ?string $note, User $user): CashRegisterSession
    {
        $this->assertOpen($session);

        $expected = (float) $session->expected_cash;
        $session->update([
            'actual_cash' => round($actualCash, 2),
            'difference' => round($actualCash - $expected, 2),
            'status' => CashSessionStatus::Closed,
            'note' => $note,
            'closed_at' => Carbon::now(),
        ]);

        return $session->fresh();
    }

    /** Renders a session (open or closed) as a printable/downloadable cash slip PDF. */
    public function sessionSlipPdf(CashRegisterSession $session): PdfDocument
    {
        $session->loadMissing(['transactions', 'user', 'register.branch.business']);

        return Pdf::loadView('pdf.cash-slip', [
            'session' => $session,
            'branch' => $session->register->branch,
            'business' => $session->register->branch->business,
        ]);
    }

    protected function assertOpen(CashRegisterSession $session): void
    {
        if ($session->status !== CashSessionStatus::Open) {
            throw ValidationException::withMessages([
                'session' => ['This register session is already closed.'],
            ]);
        }
    }
}

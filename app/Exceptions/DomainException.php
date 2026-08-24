<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class DomainException extends RuntimeException
{
    protected array $context = [];

    private string $errorCode;

    private const MESSAGES = [
        'payment.failed' => 'Payment processing failed.',
        'stock.transfer.cancelled' => 'Stock transfer has been cancelled.',
        'stock.insufficient' => 'Insufficient stock available.',
        'order.not_found' => 'Order not found.',
        'user.locked' => 'Account is locked due to too many failed attempts.',
        'token.invalid' => 'Invalid or expired token.',
        'credentials.invalid' => 'Invalid credentials.',
        'password.incorrect' => 'Current password is incorrect.',
        'otp.invalid' => 'Invalid OTP code.',
    ];

    public static function of(string $code, int $status = 422): static
    {
        $message = self::MESSAGES[$code] ?? 'The requested operation could not be completed.';

        $exception = new static($message, $status);
        $exception->errorCode = $code;

        return $exception;
    }

    public function withContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function render(): JsonResponse
    {
        $data = [
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ];

        if (! empty($this->context)) {
            $data['context'] = $this->context;
        }

        return response()->json($data, $this->getStatusCode());
    }

    public function getStatusCode(): int
    {
        return (int) $this->getCode();
    }

    public function context(): array
    {
        return ['context' => $this->context];
    }
}

<?php

namespace Tests\Feature\Api;

use App\Exceptions\DomainException;
use Tests\TestCase;

class DomainExceptionTest extends TestCase
{
    public function test_domain_exception_returns_structured_response(): void
    {
        $exception = DomainException::of('test.error');

        $this->assertEquals('The requested operation could not be completed.', $exception->getMessage());
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function test_domain_exception_with_context(): void
    {
        $exception = DomainException::of('payment.failed')
            ->withContext(['order_id' => '123', 'amount' => 100]);

        $this->assertArrayHasKey('context', $exception->context());
    }

    public function test_domain_exception_known_code(): void
    {
        $exception = DomainException::of('payment.failed');

        $this->assertEquals('Payment processing failed.', $exception->getMessage());
    }

    public function test_domain_exception_custom_status(): void
    {
        $exception = DomainException::of('test.error', 404);

        $this->assertEquals(404, $exception->getStatusCode());
    }
}

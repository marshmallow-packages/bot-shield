<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Stands in for an API exception base class that carries an HTTP status without
 * extending Symfony's HttpException, which Laravel already never reports.
 */
class ApiClientException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        private readonly int $status,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}

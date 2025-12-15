<?php

declare(strict_types=1);

namespace App\Messenger\Middleware;

use App\Message\TestRunMessage;
use App\Messenger\Stamp\EnvironmentQueueStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Middleware to add EnvironmentQueueStamp to TestRunMessages.
 */
final class EnvironmentQueueMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if ($message instanceof TestRunMessage) {
            $envelope = $envelope->with(new EnvironmentQueueStamp($message->environmentId));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}

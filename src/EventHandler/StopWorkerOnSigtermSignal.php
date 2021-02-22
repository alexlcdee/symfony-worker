<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker\EventHandler;

use AlexLcDee\SymfonyWorker\Event\WorkerRunningEvent;
use AlexLcDee\SymfonyWorker\Event\WorkerStartedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function function_exists;
use function pcntl_signal;
use function pcntl_signal_dispatch;

use const SIGTERM;

class StopWorkerOnSigtermSignal implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $logger = $this->logger;
        pcntl_signal(SIGTERM, static function () use ($event, $logger) {
            $logger->notice('SIGTERM received!');
            $event->getOutput()->writeln('SIGTERM received!');
            $event->getWorker()->stop();
        });
    }

    public static function getSubscribedEvents()
    {
        if (!function_exists('pcntl_signal')) {
            return [];
        }

        return [
            WorkerStartedEvent::class => ['onWorkerStarted', 100],
            WorkerRunningEvent::class => ['onWorkerRunning', 100],
        ];
    }

    public function onWorkerRunning(): void
    {
        pcntl_signal_dispatch();
    }
}
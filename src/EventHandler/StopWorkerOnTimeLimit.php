<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker\EventHandler;


use AlexLcDee\SymfonyWorker\Event\WorkerRunningEvent;
use AlexLcDee\SymfonyWorker\Event\WorkerStartedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StopWorkerOnTimeLimit implements EventSubscriberInterface
{
    private int $timeLimitInSeconds;
    private float $endTime;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(int $timeLimitInSeconds, LoggerInterface $logger)
    {
        $this->timeLimitInSeconds = $timeLimitInSeconds;
        $this->logger = $logger;
    }

    public function onWorkerStarted(): void
    {
        $startTime = microtime(true);
        $this->endTime = $startTime + $this->timeLimitInSeconds;
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->endTime < microtime(true)) {
            $event->getWorker()->stop();
            $this->logger->info('Worker stopped due to time limit of {timeLimit}s exceeded', [
                'timeLimit' => $this->timeLimitInSeconds,
            ]);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
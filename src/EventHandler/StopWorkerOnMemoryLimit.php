<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker\EventHandler;


use AlexLcDee\SymfonyWorker\Event\WorkerRunningEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StopWorkerOnMemoryLimit implements EventSubscriberInterface
{
    private int $memoryLimit;
    private LoggerInterface $logger;
    private $memoryResolver;

    public function __construct(int $memoryLimit, LoggerInterface $logger, callable $memoryResolver = null)
    {
        $this->memoryLimit = $memoryLimit;
        $this->logger = $logger;
        $this->memoryResolver = $memoryResolver ?? static function () {
            return memory_get_usage(true);
        };
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $memoryResolver = $this->memoryResolver;
        $usedMemory = $memoryResolver();
        if ($usedMemory > $this->memoryLimit) {
            $event->getWorker()->stop();
            $this->logger->info('Worker stopped due to memory limit of {limit} bytes exceeded ({memory} bytes used)', [
                'limit'  => $this->memoryLimit,
                'memory' => $usedMemory,
            ]);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
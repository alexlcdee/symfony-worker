<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker;


use AlexLcDee\SymfonyWorker\Event\WorkerRunningEvent;
use AlexLcDee\SymfonyWorker\Event\WorkerStartedEvent;
use AlexLcDee\SymfonyWorker\Event\WorkerStoppedEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function array_merge;
use function usleep;

abstract class Worker
{
    private bool $shouldStop = false;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;

    public function __construct(EventDispatcherInterface $eventDispatcher, LoggerInterface $workerLogger = null)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $workerLogger ?? new NullLogger();
    }

    /**
     * @param OutputInterface|null $output
     * @param array|null $options
     * @throws Throwable
     */
    final public function start(?OutputInterface $output = null, ?array $options = [])
    {
        $output = $output ?? new NullOutput();
        $options = array_merge(['sleep' => 0], $this->defaultOptions(), $options ?? []);

        $this->eventDispatcher->dispatch(new WorkerStartedEvent($this, $output, $options));

        while (false === $this->shouldStop) {
            try {
                $this->eventDispatcher->dispatch(new WorkerRunningEvent($this, $output, $options));
                $this->loop($output, $options);
            } catch (RecoverableException $exception) {
                $this->logger->error($exception);
                continue;
            } catch (Throwable $exception) {
                $this->logger->error($exception);
                $this->stop();

                throw $exception;
            }
            usleep($options['sleep'] * 1000000);
        }

        $this->eventDispatcher->dispatch(new WorkerStoppedEvent($this, $output, $options));
    }

    final public function stop()
    {
        $this->shouldStop = true;
    }

    public function getName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    abstract protected function loop(OutputInterface $io, array $options = []);

    protected function defaultOptions(): array
    {
        return [
            'sleep' => 10,
        ];
    }
}
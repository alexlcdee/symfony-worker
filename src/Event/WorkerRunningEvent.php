<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker\Event;


use AlexLcDee\SymfonyWorker\Worker;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @codeCoverageIgnore
 */
class WorkerRunningEvent
{
    private Worker $worker;
    private OutputInterface $output;
    private array $options;

    public function __construct(Worker $worker, OutputInterface $output, array $options)
    {
        $this->worker = $worker;
        $this->output = $output;
        $this->options = $options;
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker\Command;


use AlexLcDee\SymfonyWorker\EventHandler\StopWorkerOnMemoryLimit;
use AlexLcDee\SymfonyWorker\EventHandler\StopWorkerOnSigtermSignal;
use AlexLcDee\SymfonyWorker\EventHandler\StopWorkerOnTimeLimit;
use AlexLcDee\SymfonyWorker\Worker;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_pop;
use function function_exists;
use function implode;
use function intval;
use function ltrim;
use function rtrim;
use function strpos;
use function strtolower;
use function substr;

class RunWorkerCommand extends Command
{
    protected static $defaultName = 'symfony-worker:run';

    private EventDispatcherInterface $eventDispatcher;
    /**
     * @var iterable<Worker>
     */
    private iterable $workers;
    private $logger;

    public function __construct(
        iterable $workers,
        EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $workerLogger = null,
        ?string $name = null
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->workers = $workers;
        $this->logger = $workerLogger ?? new NullLogger();
        parent::__construct($name);
    }

    final protected function configure()
    {
        $this
            ->addArgument(
                'worker-name',
                InputArgument::REQUIRED
            )
            ->addOption(
                'sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'Seconds to sleep before next loop run',
                5
            )
            ->addOption(
                'memory-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'The memory limit the worker can consume'
            )
            ->addOption(
                'time-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'The time limit in seconds the worker can run'
            )
            ->setDescription("Run worker");
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stopsWhen = [];
        if ($timeLimit = $input->getOption('time-limit')) {
            $stopsWhen[] = "been running for {$timeLimit}s";
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTimeLimit(
                (int)$timeLimit,
                $this->logger
            ));
        }
        if ($memoryLimit = $input->getOption('memory-limit')) {
            $stopsWhen[] = "exceeded {$memoryLimit} of memory";
            $this->eventDispatcher->addSubscriber(new StopWorkerOnMemoryLimit(
                $this->convertToBytes($memoryLimit),
                $this->logger
            ));
        }

        $this->eventDispatcher->addSubscriber(new StopWorkerOnSigtermSignal(
            $this->logger
        ));

        if (function_exists('pcntl_signal')) {
            $stopsWhen[] = 'received a SIGTERM';
        }

        if ($stopsWhen) {
            $last = array_pop($stopsWhen);
            $stopsWhen = ($stopsWhen ? implode(', ', $stopsWhen) . ' or ' : '') . $last;
            $io->comment("The worker will automatically exit once it has {$stopsWhen}.");
        }

        /** @var array<string, Worker> $workers */
        $workers = [];
        foreach ($this->workers as $worker) {
            $workers[$worker->getName()] = $worker;
        }

        if (!isset($workers[$input->getArgument('worker-name')])) {
            $io->error("Worker {$input->getArgument('worker-name')} not found");

            return 1;
        }

        $worker = $workers[$input->getArgument('worker-name')];

        $worker->start($io, $input->getOptions());

        return 0;
    }

    /** @noinspection PhpMissingBreakStatementInspection */
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = strtolower($memoryLimit);
        $max = strtolower(ltrim($memoryLimit, '+'));
        if (0 === strpos($max, '0x')) {
            $max = intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = (int)$max;
        }

        switch (substr(rtrim($memoryLimit, 'b'), -1)) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }

        return $max;
    }
}
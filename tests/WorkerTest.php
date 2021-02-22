<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker\Tests;

use AlexLcDee\SymfonyWorker\Command\RunWorkerCommand;
use AlexLcDee\SymfonyWorker\RecoverableException;
use AlexLcDee\SymfonyWorker\Worker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class WorkerTest extends TestCase
{
    public function test_can_run_and_stop_worker()
    {
        $eventDispatcher = new EventDispatcher();
        $worker = new class($eventDispatcher) extends Worker {
            public int $iteration = 0;
            protected function loop(OutputInterface $io, array $options = [])
            {
                $this->iteration++;
                if ($this->iteration > 2) {
                    $this->stop();
                }
            }
        };

        $command = new RunWorkerCommand([$worker], $eventDispatcher);

        $command->run(new ArrayInput([
            'worker-name' => get_class($worker),
            '--sleep' => 0
        ], $command->getDefinition()), new NullOutput());

        $this->assertEquals(3, $worker->iteration);
    }

    public function test_worker_stops_on_exception()
    {
        $this->expectException(\LogicException::class);

        $eventDispatcher = new EventDispatcher();
        $worker = new class($eventDispatcher) extends Worker {
            public int $iteration = 0;
            protected function loop(OutputInterface $io, array $options = [])
            {
                throw new \LogicException();
            }
        };

        $command = new RunWorkerCommand([$worker], $eventDispatcher);

        $command->run(new ArrayInput([
            'worker-name' => get_class($worker),
            '--sleep' => 0
        ], $command->getDefinition()), new NullOutput());
    }

    public function test_worker_can_run_on_recoverable_exception()
    {
        $eventDispatcher = new EventDispatcher();
        $worker = new class($eventDispatcher) extends Worker {
            public int $iteration = 0;
            protected function loop(OutputInterface $io, array $options = [])
            {
                $this->iteration++;
                if ($this->iteration > 2) {
                    $this->stop();
                }
                throw new class extends \Exception implements RecoverableException {
                };
            }
        };

        $command = new RunWorkerCommand([$worker], $eventDispatcher);

        $command->run(new ArrayInput([
            'worker-name' => get_class($worker),
            '--sleep' => 0
        ], $command->getDefinition()), new NullOutput());

        $this->assertEquals(3, $worker->iteration);
    }

    public function test_worker_can_stop_on_memory_limit()
    {
        $eventDispatcher = new EventDispatcher();
        $worker = new class($eventDispatcher) extends Worker {
            public int $iteration = 0;
            private string $memo = '';
            protected function loop(OutputInterface $io, array $options = [])
            {
                $this->memo .= str_pad('x', 1024 * 1024 * 5); // add 5 megabytes
                $this->iteration++;
            }
        };

        $command = new RunWorkerCommand([$worker], $eventDispatcher);

        $memory = memory_get_usage(true) / 1024 / 1024 + 10;

        $command->run(new ArrayInput([
            'worker-name' => get_class($worker),
            '--sleep' => 0,
            '--memory-limit' => "{$memory}m"
        ], $command->getDefinition()), new NullOutput());

        $this->assertEquals(3, $worker->iteration);
    }

    public function test_worker_can_stop_on_time_limit()
    {
        $eventDispatcher = new EventDispatcher();
        $worker = new class($eventDispatcher) extends Worker {
            public int $iteration = 0;
            protected function loop(OutputInterface $io, array $options = [])
            {
                $this->iteration++;
                sleep(1);
            }
        };

        $command = new RunWorkerCommand([$worker], $eventDispatcher);

        $command->run(new ArrayInput([
            'worker-name' => get_class($worker),
            '--sleep' => 0,
            '--time-limit' => 5
        ], $command->getDefinition()), new NullOutput());

        $this->assertEquals(6, $worker->iteration);
    }
}

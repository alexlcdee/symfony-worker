<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker\DependencyInjection;


use AlexLcDee\SymfonyWorker\Worker;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TagAllWorkers implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->registerForAutoconfiguration(Worker::class)
            ->addTag('symfony_worker.worker');
    }
}
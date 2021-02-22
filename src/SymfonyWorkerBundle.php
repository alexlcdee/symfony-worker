<?php

declare(strict_types=1);

namespace AlexLcDee\SymfonyWorker;


use AlexLcDee\SymfonyWorker\DependencyInjection\TagAllWorkers;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function dirname;

class SymfonyWorkerBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new TagAllWorkers(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 500);
    }
}
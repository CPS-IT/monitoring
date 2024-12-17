<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "cpsit/monitoring".
 *
 * Copyright (C) 2021 Elias Häußler <e.haeussler@familie-redlich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace CPSIT\Monitoring\Tests\Unit\DependencyInjection;

use CPSIT\Monitoring\DependencyInjection\MonitoringProviderCompilerPass;
use CPSIT\Monitoring\Middleware\MonitoringMiddleware;
use CPSIT\Monitoring\Provider\MonitoringProvider;
use CPSIT\Monitoring\Tests\Unit\Fixtures\TestContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * MonitoringProviderCompilerPassTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
#[CoversClass(MonitoringProviderCompilerPass::class)]
final class MonitoringProviderCompilerPassTest extends TestCase
{
    private TestContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = $this->buildContainer();
        $this->container->addCompilerPass(new MonitoringProviderCompilerPass());
    }

    #[Test]
    public function processDoesNothingIfMiddlewareDefinitionIsMissing(): void
    {
        $this->container->removeDefinition(MonitoringMiddleware::class);
        $this->container->compile();

        self::assertFalse($this->container->taggedMonitoringProvidersWereRequested);
    }

    #[Test]
    public function processAutoconfiguresMonitoringProviders(): void
    {
        $middlewareDefinition = $this->container->findDefinition(MonitoringMiddleware::class);

        self::assertCount(0, $middlewareDefinition->getArguments());

        $this->container->compile();

        self::assertTrue($this->container->taggedMonitoringProvidersWereRequested);
        self::assertCount(3, $middlewareDefinition->getArguments());
        self::assertIsArray($middlewareDefinition->getArguments()[2]);
        self::assertCount(2, $middlewareDefinition->getArguments()[2]);

        foreach ($middlewareDefinition->getArguments()[2] as $monitoringProvider) {
            self::assertInstanceOf(Definition::class, $monitoringProvider);
            self::assertNotNull($monitoringProvider->getClass());
            self::assertTrue(is_subclass_of($monitoringProvider->getClass(), MonitoringProvider::class));
        }
    }

    private function buildContainer(): TestContainerBuilder
    {
        $container = new TestContainerBuilder();

        $yamLoader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__).'/Fixtures'));
        $yamLoader->load('Services.yaml');

        return $container;
    }
}

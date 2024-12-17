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
use CPSIT\Monitoring\DependencyInjection\ServiceConfigurator;
use CPSIT\Monitoring\Monitoring;
use CPSIT\Monitoring\Provider\MonitoringProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\InlineServiceDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * ServiceConfiguratorTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
#[CoversClass(ServiceConfigurator::class)]
final class ServiceConfiguratorTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        $this->modifyContainerPassConfig();
    }

    #[Test]
    public function configureLoadsServicesYaml(): void
    {
        self::assertFalse($this->container->hasDefinition(Monitoring::class));
        self::assertFalse($this->container->hasDefinition(ServiceConfigurator::class));

        ServiceConfigurator::configure($this->container);

        $this->container->compile();

        self::assertTrue($this->container->hasDefinition(Monitoring::class));
        // DI-related classes should be excluded from service container
        self::assertFalse($this->container->hasDefinition(ServiceConfigurator::class));
    }

    #[Test]
    public function configureHandlesMonitoringProvidersDifferently(): void
    {
        self::assertSame([], $this->container->getAutoconfiguredInstanceof());

        ServiceConfigurator::configure($this->container);

        $expected = [
            MonitoringProvider::class => (new ChildDefinition(''))->addTag(MonitoringProviderCompilerPass::TAG_NAME),
        ];

        self::assertCount(1, $this->container->getAutoconfiguredInstanceof());
        self::assertEquals($expected, $this->container->getAutoconfiguredInstanceof());
        self::assertContainsEquals(
            new MonitoringProviderCompilerPass(),
            $this->container->getCompilerPassConfig()->getBeforeOptimizationPasses(),
        );
    }

    private function modifyContainerPassConfig(): void
    {
        $removingPasses = $this->container->getCompilerPassConfig()->getRemovingPasses();

        // Remove some passes to avoid removing too many definitions during container compilation
        $passesToRemove = [
            InlineServiceDefinitionsPass::class,
            RemoveUnusedDefinitionsPass::class,
        ];

        foreach ($removingPasses as $key => $removingPass) {
            if (in_array($removingPass::class, $passesToRemove, true)) {
                unset($removingPasses[$key]);
            }
        }

        $this->container->getCompilerPassConfig()->setRemovingPasses($removingPasses);
    }
}

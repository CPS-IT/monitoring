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

namespace CPSIT\Monitoring\DependencyInjection;

use CPSIT\Monitoring\Middleware\MonitoringMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * MonitoringProviderCompilerPass.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
final class MonitoringProviderCompilerPass implements CompilerPassInterface
{
    public const TAG_NAME = 'monitoring.provider';

    public function __construct(
        private readonly string $serviceId = MonitoringMiddleware::class,
    ) {}

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has($this->serviceId)) {
            return;
        }

        $definition = $container->findDefinition($this->serviceId);
        $taggedProviders = $container->findTaggedServiceIds(self::TAG_NAME);
        $providerReferences = [];

        foreach ($taggedProviders as $id => $tags) {
            $providerDefinition = $container->findDefinition($id);
            if ($providerDefinition->isAutoconfigured() && !$providerDefinition->isAbstract()) {
                $providerReferences[] = new Reference($id);
            }
        }

        $definition->setArgument('$monitoringProviders', $providerReferences);
    }
}

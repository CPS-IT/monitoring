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

namespace CPSIT\Monitoring\Tests\Unit\Fixtures;

use CPSIT\Monitoring\Provider\MonitoringProvider;

/**
 * TestMonitoringProvider.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
final class TestMonitoringProvider implements MonitoringProvider
{
    public function __construct(
        public bool $healthy = true,
        public bool $failOnJsonEncode = false,
    ) {}

    public function getName(): string
    {
        if ($this->failOnJsonEncode) {
            return 'test'.iconv('UTF-8', 'ISO-8859-1', 'errör');
        }

        return 'test';
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }
}

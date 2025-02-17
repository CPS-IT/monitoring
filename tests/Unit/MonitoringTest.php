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

namespace CPSIT\Monitoring\Tests\Unit;

use CPSIT\Monitoring\Monitoring;
use CPSIT\Monitoring\Tests\Unit\Fixtures\CommunicativeMonitoringProvider;
use CPSIT\Monitoring\Tests\Unit\Fixtures\TestMonitoringProvider;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MonitoringTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
#[CoversClass(Monitoring::class)]
final class MonitoringTest extends TestCase
{
    private Monitoring $subject;

    protected function setUp(): void
    {
        $this->subject = new Monitoring();
    }

    #[Test]
    public function checkHealthReturnsHealthyResultIfNoProvidersAreGiven(): void
    {
        $result = $this->subject->checkHealth([]);

        self::assertTrue($result->isHealthy());
        self::assertSame([], $result->getProviderResults());
    }

    #[Test]
    public function checkHealthReturnsHealthyResultIfAllProvidersAreHealthy(): void
    {
        $provider = new CommunicativeMonitoringProvider();
        $result = $this->subject->checkHealth([$provider]);

        self::assertTrue($result->isHealthy());
        self::assertCount(1, $result->getProviderResults());
        self::assertSame($provider->getName(), $result->getProviderResults()[0]->getName());
        self::assertTrue($result->getProviderResults()[0]->isHealthy());
        self::assertSame('Hello world!', $result->getProviderResults()[0]->getStatusInformation());
    }

    #[Test]
    public function checkHealthReturnsUnhealthyResultIfAnyProviderIsUnhealthy(): void
    {
        $exception1 = new Exception('Oops, an error occurred.', 1614071643);
        $exception2 = new RequestException('Oops, another error occurred.', new Request('GET', 'https://www.example.com'), new Response(404));
        $provider1 = new TestMonitoringProvider(true);
        $provider2 = new CommunicativeMonitoringProvider(false, $exception1);
        $provider3 = new CommunicativeMonitoringProvider(false);
        $provider4 = new CommunicativeMonitoringProvider(false, $exception2);
        $result = $this->subject->checkHealth([$provider1, $provider2, $provider3, $provider4]);

        self::assertFalse($result->isHealthy());
        self::assertCount(4, $result->getProviderResults());

        self::assertSame($provider1->getName(), $result->getProviderResults()[0]->getName());
        self::assertSame($provider2->getName(), $result->getProviderResults()[1]->getName());
        self::assertSame($provider3->getName(), $result->getProviderResults()[2]->getName());
        self::assertSame($provider4->getName(), $result->getProviderResults()[3]->getName());

        self::assertTrue($result->getProviderResults()[0]->isHealthy());
        self::assertFalse($result->getProviderResults()[1]->isHealthy());
        self::assertFalse($result->getProviderResults()[2]->isHealthy());
        self::assertFalse($result->getProviderResults()[3]->isHealthy());

        self::assertSame('Oops, an error occurred.', $result->getProviderResults()[1]->getErrorMessage());
        self::assertSame(1614071643, $result->getProviderResults()[1]->getErrorCode());
        self::assertSame('unknown', $result->getProviderResults()[2]->getErrorMessage());
        self::assertSame('unknown', $result->getProviderResults()[2]->getErrorCode());
        self::assertSame('Oops, another error occurred.', $result->getProviderResults()[3]->getErrorMessage());
        self::assertSame(404, $result->getProviderResults()[3]->getErrorCode());
    }
}

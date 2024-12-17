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

namespace CPSIT\Monitoring\Tests\Unit\Result;

use CPSIT\Monitoring\Result\MonitoringProviderResult;
use CPSIT\Monitoring\Result\MonitoringStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MonitoringProviderResultTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
#[CoversClass(MonitoringProviderResult::class)]
final class MonitoringProviderResultTest extends TestCase
{
    private MonitoringProviderResult $subject;

    protected function setUp(): void
    {
        $this->subject = new MonitoringProviderResult('foo', true);
    }

    #[Test]
    public function getNameReturnsNameOfMonitoringProvider(): void
    {
        self::assertSame('foo', $this->subject->getName());
    }

    #[Test]
    public function setNameSetsNameOfMonitoringProvider(): void
    {
        self::assertSame('baz', $this->subject->setName('baz')->getName());
    }

    #[Test]
    public function isHealthyReturnsHealthinessOfProvider(): void
    {
        self::assertTrue($this->subject->isHealthy());
        self::assertFalse((new MonitoringProviderResult('baz', false))->isHealthy());
    }

    #[Test]
    public function setHealthySetsHealthinessOfProvider(): void
    {
        self::assertFalse($this->subject->setHealthy(false)->isHealthy());
    }

    #[Test]
    public function getErrorMessageReturnsErrorMessage(): void
    {
        self::assertNull($this->subject->getErrorMessage());
        self::assertSame(
            'Oops, an error occured.',
            $this->subject->setErrorMessage('Oops, an error occured.')->getErrorMessage(),
        );
    }

    #[Test]
    public function getErrorCodeReturnsErrorCode(): void
    {
        self::assertNull($this->subject->getErrorCode());
        self::assertSame(
            17,
            $this->subject->setErrorCode(17)->getErrorCode(),
        );
    }

    #[Test]
    public function getStatusInformationThrowsExceptionIfStatusInformationIsNotJsonSerializable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1642411760);

        $this->subject->setStatusInformation($this);
    }

    #[Test]
    public function getStatusInformationReturnsStatusInformation(): void
    {
        self::assertNull($this->subject->getStatusInformation());
        self::assertSame(
            'Hello world!',
            $this->subject->setStatusInformation('Hello world!')->getStatusInformation(),
        );
    }

    #[Test]
    public function getStatusReturnsHumanReadableStatusOfHealthiness(): void
    {
        // Status: healthy
        self::assertSame(MonitoringStatus::Ok, $this->subject->getStatus());

        // Status: unhealthy
        self::assertSame(MonitoringStatus::Error, $this->subject->setHealthy(false)->getStatus());
    }

    #[Test]
    public function subjectCanBeJsonEncoded(): void
    {
        // Healthy without status message
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok"}',
            json_encode($this->subject, JSON_THROW_ON_ERROR),
        );

        // Healthy with status message
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","info":{"my_message":"Hello world!"}}',
            json_encode($this->subject->setStatusInformation(['my_message' => 'Hello world!']), JSON_THROW_ON_ERROR),
        );

        // Unhealthy without error
        self::assertJsonStringEqualsJsonString(
            '{"status":"error"}',
            json_encode($this->subject->setHealthy(false), JSON_THROW_ON_ERROR),
        );

        // Unhealthy with error
        self::assertJsonStringEqualsJsonString(
            '{"status":"error","error":"Oops, an error occurred.","code":17}',
            json_encode(
                $this->subject->setErrorMessage('Oops, an error occurred.')->setErrorCode(17),
                JSON_THROW_ON_ERROR,
            ),
        );
    }
}

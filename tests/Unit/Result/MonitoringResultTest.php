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
use CPSIT\Monitoring\Result\MonitoringResult;
use CPSIT\Monitoring\Result\MonitoringStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MonitoringResultTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
#[CoversClass(MonitoringResult::class)]
final class MonitoringResultTest extends TestCase
{
    private MonitoringResult $subject;

    protected function setUp(): void
    {
        $this->subject = new MonitoringResult();
    }

    #[Test]
    public function isHealthyReturnsTrueIfNoProviderResultsAreAdded(): void
    {
        self::assertTrue($this->subject->isHealthy());
    }

    #[Test]
    public function isHealthyReturnsTrueIfAllProviderResultsAreHealthy(): void
    {
        $this->subject
            ->addProviderResult(new MonitoringProviderResult('foo', true))
            ->addProviderResult(new MonitoringProviderResult('baz', true))
        ;

        self::assertTrue($this->subject->isHealthy());
    }

    #[Test]
    public function isHealthyReturnsFalseIfAnyProviderResultIsUnhealthy(): void
    {
        $this->subject
            ->addProviderResult(new MonitoringProviderResult('foo', true))
            ->addProviderResult(new MonitoringProviderResult('baz', false))
        ;

        self::assertFalse($this->subject->isHealthy());
    }

    #[Test]
    public function getProviderResultsReturnsAllAttachedProviderResults(): void
    {
        self::assertSame([], $this->subject->getProviderResults());

        $this->subject
            ->addProviderResult($providerResult1 = new MonitoringProviderResult('foo', true))
            ->addProviderResult($providerResult2 = new MonitoringProviderResult('baz', true))
        ;

        self::assertCount(2, $this->subject->getProviderResults());
        self::assertSame([$providerResult1, $providerResult2], $this->subject->getProviderResults());
    }

    #[Test]
    public function addProviderResultAttachesGivenProviderResult(): void
    {
        $this->subject->addProviderResult($providerResult = new MonitoringProviderResult('foo', true));

        self::assertCount(1, $this->subject->getProviderResults());
        self::assertSame([$providerResult], $this->subject->getProviderResults());
    }

    #[Test]
    public function removeProviderResultDetachesGivenProviderResult(): void
    {
        $this->subject->addProviderResult($providerResult = new MonitoringProviderResult('baz', true));

        self::assertCount(1, $this->subject->getProviderResults());
        self::assertSame([$providerResult], $this->subject->getProviderResults());

        $this->subject->removeProviderResult($providerResult);

        self::assertSame([], $this->subject->getProviderResults());
    }

    #[Test]
    public function removeProviderResultDoesNothingIfGivenProviderResultIsNotAttached(): void
    {
        self::assertSame([], $this->subject->getProviderResults());

        $this->subject->removeProviderResult(new MonitoringProviderResult('foo', true));

        self::assertSame([], $this->subject->getProviderResults());
    }

    #[Test]
    public function getStatusReturnsHumanReadableStatusOfHealthiness(): void
    {
        // Status: healthy
        self::assertSame(MonitoringStatus::Ok, $this->subject->getStatus());

        // Status: unhealthy
        self::assertSame(
            MonitoringStatus::Error,
            $this->subject->addProviderResult(new MonitoringProviderResult('foo', false))->getStatus(),
        );
    }

    #[Test]
    public function subjectCanBeJsonEncoded(): void
    {
        // No monitoring providers
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok"}',
            json_encode($this->subject, JSON_THROW_ON_ERROR),
        );

        // Healthy monitoring providers
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","services":{"foo":{"status":"ok"}}}',
            json_encode(
                $this->subject->addProviderResult(new MonitoringProviderResult('foo', true)),
                JSON_THROW_ON_ERROR,
            ),
        );

        // Unhealthy monitoring providers
        self::assertJsonStringEqualsJsonString(
            '{"status":"error","services":{"foo":{"status":"ok"},"baz":{"status":"error"}}}',
            json_encode(
                $this->subject->addProviderResult(new MonitoringProviderResult('baz', false)),
                JSON_THROW_ON_ERROR,
            ),
        );
    }
}

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

namespace CPSIT\Monitoring\Tests\Unit\Authorization;

use CPSIT\Monitoring\Authorization\NullAuthorizer;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * NullAuthorizerTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
#[CoversClass(NullAuthorizer::class)]
final class NullAuthorizerTest extends TestCase
{
    private NullAuthorizer $subject;

    protected function setUp(): void
    {
        $this->subject = new NullAuthorizer();
    }

    #[Test]
    public function isAuthorizedReturnsAlwaysTrue(): void
    {
        self::assertTrue($this->subject->isAuthorized(new ServerRequest('GET', 'https://www.example.com')));
    }

    #[Test]
    public function getPriorityReturnsLowestPossiblePriority(): void
    {
        self::assertSame(PHP_INT_MIN, $this->subject->getPriority());
    }
}

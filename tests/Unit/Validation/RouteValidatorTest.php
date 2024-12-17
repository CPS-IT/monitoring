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

namespace CPSIT\Monitoring\Tests\Unit\Validation;

use CPSIT\Monitoring\Validation\RouteValidator;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RouteValidatorTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
#[CoversClass(RouteValidator::class)]
final class RouteValidatorTest extends TestCase
{
    private RouteValidator $subject;

    protected function setUp(): void
    {
        $this->subject = new RouteValidator();
    }

    #[Test]
    public function isValidRequestReturnsTrueIfRequestRouteMatchesValidRoute(): void
    {
        $request = new Request('GET', 'http://www.example.com/monitor/health');

        self::assertTrue($this->subject->isValidRequest($request));
    }

    #[Test]
    public function isValidRequestReturnsFalseIfRequestRouteDoesNotMatchValidRoute(): void
    {
        $request = new Request('GET', 'http://www.example.com');

        self::assertFalse($this->subject->isValidRequest($request));
    }
}

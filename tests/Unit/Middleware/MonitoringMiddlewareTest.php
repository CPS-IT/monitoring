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

namespace CPSIT\Monitoring\Tests\Unit\Middleware;

use CPSIT\Monitoring\Authorization\NullAuthorizer;
use CPSIT\Monitoring\Middleware\MonitoringMiddleware;
use CPSIT\Monitoring\Monitoring;
use CPSIT\Monitoring\Provider\MonitoringProvider;
use CPSIT\Monitoring\Tests\Unit\Fixtures\CommunicativeMonitoringProvider;
use CPSIT\Monitoring\Tests\Unit\Fixtures\TestAuthorizer;
use CPSIT\Monitoring\Tests\Unit\Fixtures\TestMonitoringProvider;
use CPSIT\Monitoring\Tests\Unit\Fixtures\TestRequestHandler;
use CPSIT\Monitoring\Tests\Unit\Fixtures\TestValidator;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionObject;

/**
 * MonitoringMiddlewareTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
#[CoversClass(MonitoringMiddleware::class)]
final class MonitoringMiddlewareTest extends TestCase
{
    private ServerRequestInterface $request;
    private TestMonitoringProvider $monitoringProvider;

    /**
     * @var MonitoringProvider[]
     */
    private array $monitoringProviders;
    private TestValidator $validator;
    private TestAuthorizer $authorizer;
    private TestRequestHandler $requestHandler;
    private Monitoring $monitoring;
    private MonitoringMiddleware $subject;

    protected function setUp(): void
    {
        $this->request = new ServerRequest('GET', 'https://www.example.com');
        $this->monitoringProvider = new TestMonitoringProvider();
        $this->monitoringProviders = [
            $this->monitoringProvider,
            new CommunicativeMonitoringProvider(),
        ];
        $this->validator = new TestValidator();
        $this->authorizer = new TestAuthorizer();
        $this->requestHandler = new TestRequestHandler();
        $this->monitoring = new Monitoring();
        $this->subject = new MonitoringMiddleware(
            $this->validator,
            $this->monitoring,
            $this->monitoringProviders,
            $this->authorizer,
        );
    }

    #[Test]
    public function constructorSortsAuthorizersByPriority(): void
    {
        $authorizers = [
            $authorizer1 = new TestAuthorizer(),
            $authorizer2 = new NullAuthorizer(),
            $authorizer3 = new TestAuthorizer(),
            $authorizer4 = new TestAuthorizer(false, PHP_INT_MAX),
        ];

        $subject = new MonitoringMiddleware($this->validator, new Monitoring(), $this->monitoringProviders, $authorizers);

        $reflectionObject = new ReflectionObject($subject);
        $reflectionProperty = $reflectionObject->getProperty('authorizers');

        self::assertSame(
            [
                $authorizer4,
                $authorizer1,
                $authorizer3,
                $authorizer2,
            ],
            $reflectionProperty->getValue($subject),
        );
    }

    #[Test]
    public function processDoesNotHandleRequestIfRequestIsNotValid(): void
    {
        $this->validator->expectedValidRequest = false;
        $this->requestHandler->expectedResponse = $response = new Response();

        self::assertSame($response, $this->subject->process($this->request, $this->requestHandler));
    }

    #[Test]
    public function processReturnsUnauthorizedResponseIfRequestIsNotAuthorized(): void
    {
        $response = $this->getUnauthorizedResponse();

        $this->authorizer->expectedAuthorization = false;
        $this->requestHandler->expectedResponse = $unhandledResponse = new Response();

        $actual = $this->subject->process($this->request, $this->requestHandler);

        self::assertNotSame($unhandledResponse, $actual);
        self::assertJsonStringEqualsJsonString($response->getBody()->getContents(), $actual->getBody()->getContents());
        self::assertResponseTypeIsJson($actual);
        self::assertResponseIsNotCacheable($actual);
    }

    #[Test]
    public function processReturns200ResponseForHealthyResult(): void
    {
        $actual = $this->subject->process($this->request, $this->requestHandler);
        self::assertSame(200, $actual->getStatusCode());
        self::assertJson($actual->getBody()->getContents());
        self::assertResponseTypeIsJson($actual);
        self::assertResponseIsNotCacheable($actual);
    }

    #[Test]
    public function processReturns424ResponseForUnhealthyResult(): void
    {
        $this->monitoringProvider->healthy = false;

        $actual = $this->subject->process($this->request, $this->requestHandler);
        self::assertSame(424, $actual->getStatusCode());
        self::assertJson($actual->getBody()->getContents());
        self::assertResponseTypeIsJson($actual);
        self::assertResponseIsNotCacheable($actual);
    }

    #[Test]
    public function processReturns500ResponseForJsonEncodingFailure(): void
    {
        $this->monitoringProvider->failOnJsonEncode = true;

        $actual = $this->subject->process($this->request, $this->requestHandler);
        self::assertSame(500, $actual->getStatusCode());
        self::assertJson($actual->getBody()->getContents());
        self::assertResponseTypeIsJson($actual);
        self::assertResponseIsNotCacheable($actual);
    }

    private function getUnauthorizedResponse(): ResponseInterface
    {
        $response = new Response(401, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]);

        $response->getBody()->write(json_encode(['error' => 'Unauthorized'], JSON_THROW_ON_ERROR));
        $response->getBody()->rewind();

        return $response;
    }

    private static function assertResponseTypeIsJson(ResponseInterface $actual): void
    {
        self::assertResponseHeaderEquals(
            'Content-Type',
            'application/json; charset=utf-8',
            $actual,
        );
    }

    private static function assertResponseIsNotCacheable(ResponseInterface $actual): void
    {
        self::assertResponseHeaderEquals(
            'Cache-Control',
            'private, no-store',
            $actual,
        );
    }

    private static function assertResponseHeaderEquals(
        string $expectedHeader,
        string $expectedValue,
        ResponseInterface $actual,
    ): void {
        self::assertTrue($actual->hasHeader($expectedHeader));
        self::assertSame($expectedValue, $actual->getHeader($expectedHeader)[0]);
    }
}

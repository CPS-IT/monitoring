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

namespace CPSIT\Monitoring\Middleware;

use CPSIT\Monitoring\Authorization\Authorizer;
use CPSIT\Monitoring\Monitoring;
use CPSIT\Monitoring\Provider\MonitoringProvider;
use CPSIT\Monitoring\Result\MonitoringResult;
use CPSIT\Monitoring\Validation\Validator;
use GuzzleHttp\Psr7\Response;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonitoringMiddleware.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
final class MonitoringMiddleware implements MiddlewareInterface
{
    /**
     * @var Authorizer[]
     */
    private readonly array $authorizers;

    /**
     * @param MonitoringProvider[]    $monitoringProviders
     * @param Authorizer|Authorizer[] $authorizers
     */
    public function __construct(
        private readonly Validator $validator,
        private readonly Monitoring $monitoring,
        private readonly array $monitoringProviders,
        Authorizer|array $authorizers = [],
    ) {
        if (!is_array($authorizers)) {
            $authorizers = [$authorizers];
        }

        $this->authorizers = $this->sortAuthorizers($authorizers);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Continue request process if middleware cannot handle current request
        if (!$this->validator->isValidRequest($request)) {
            return $handler->handle($request);
        }

        // Check if current request is authorized using available authorizers
        if (!$this->isAuthorizedRequest($request)) {
            return $this->buildUnauthorizedResponse();
        }

        // Perform monitoring health check and send monitoring result as response
        $monitoringResult = $this->monitoring->checkHealth($this->monitoringProviders);

        return $this->buildResponseFromResult($monitoringResult);
    }

    /**
     * Check whether current request is correctly authorized.
     *
     * @param ServerRequestInterface $request Current request
     *
     * @return bool `true` if current request is correctly authorized, `false` otherwise
     */
    private function isAuthorizedRequest(ServerRequestInterface $request): bool
    {
        foreach ($this->authorizers as $authorizer) {
            if ($authorizer->isAuthorized($request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort given authorizers by their priority (from higher to lower priority).
     *
     * @param Authorizer[] $authorizers Unsorted list of authorizers
     *
     * @return Authorizer[] List of authorizers, sorted from higher to lower priority
     */
    private function sortAuthorizers(array $authorizers): array
    {
        usort(
            $authorizers,
            static fn (Authorizer $a, Authorizer $b) => ($a->getPriority() <=> $b->getPriority()) * -1,
        );

        return $authorizers;
    }

    /**
     * Return JSON response for monitoring result.
     *
     * @param MonitoringResult $result Monitoring result
     *
     * @return ResponseInterface JSON response for the given monitoring result
     */
    private function buildResponseFromResult(MonitoringResult $result): ResponseInterface
    {
        try {
            $data = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        } catch (JsonException $exception) {
            // Handle errors during json_encode
            return $this->buildServerErrorResponse($exception->getMessage());
        }

        // Handle unhealthy monitoring result
        if (!$result->isHealthy()) {
            return $this->buildJsonResponse($data, 424);
        }

        return $this->buildJsonResponse($data);
    }

    private function buildUnauthorizedResponse(): ResponseInterface
    {
        $json = json_encode(['error' => 'Unauthorized']);

        // Handle errors during json_encode
        if (!is_string($json)) {
            return $this->buildServerErrorResponse(json_last_error_msg());
        }

        return $this->buildJsonResponse($json, 401);
    }

    private function buildServerErrorResponse(string $message): ResponseInterface
    {
        return $this->buildJsonResponse(sprintf('{"error":"%s"}', $message), 500);
    }

    private function buildJsonResponse(string $json, int $status = 200): ResponseInterface
    {
        $response = new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]);

        $response->getBody()->write($json);
        $response->getBody()->rewind();

        return $response;
    }
}

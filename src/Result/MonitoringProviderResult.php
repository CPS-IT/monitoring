<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "monitoring".
 *
 * Copyright (C) 2021 Elias Häußler <e.haeussler@familie-redlich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
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

namespace CPSIT\Monitoring\Result;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;

/**
 * MonitoringProviderResult.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @phpstan-type JsonResult array{
 *     status: MonitoringStatus,
 *     error?: string,
 *     code?: int|string,
 *     info?: mixed,
 * }
 */
class MonitoringProviderResult implements JsonSerializable
{
    protected ?string $errorMessage = null;
    protected int|string|null $errorCode = null;
    protected mixed $statusInformation = null;

    /**
     * @param non-empty-string $name
     */
    public function __construct(
        protected string $name,
        protected bool $healthy,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param non-empty-string $name
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function setHealthy(bool $healthy): self
    {
        $this->healthy = $healthy;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getErrorCode(): int|string|null
    {
        return $this->errorCode;
    }

    public function setErrorCode(int|string $errorCode): self
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    public function getStatusInformation(): mixed
    {
        return $this->statusInformation;
    }

    public function setStatusInformation(mixed $statusInformation): self
    {
        $this->statusInformation = $statusInformation;
        $this->validateStatusInformation();

        return $this;
    }

    public function getStatus(): MonitoringStatus
    {
        return $this->isHealthy() ? MonitoringStatus::Ok : MonitoringStatus::Error;
    }

    /**
     * @phpstan-return JsonResult
     */
    public function jsonSerialize(): array
    {
        $jsonArray = [
            'status' => $this->getStatus(),
        ];

        if (!$this->isHealthy()) {
            if (null !== $this->errorMessage) {
                $jsonArray['error'] = $this->errorMessage;
            }
            if (null !== $this->errorCode) {
                $jsonArray['code'] = $this->errorCode;
            }
        } elseif (null !== $this->statusInformation) {
            $jsonArray['info'] = $this->statusInformation;
        }

        return $jsonArray;
    }

    private function validateStatusInformation(): void
    {
        if (is_object($this->statusInformation) && !($this->statusInformation instanceof JsonSerializable)) {
            throw new InvalidArgumentException(sprintf('Status information must be JSON serializable and must therefore implement %s.', JsonSerializable::class), 1642411760);
        }

        try {
            json_encode($this->statusInformation, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(sprintf('Status information must be JSON serializable: %s', $exception->getMessage()), 1623144955);
        }
    }
}

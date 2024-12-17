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

namespace CPSIT\Monitoring\Result;

use JsonSerializable;

/**
 * MonitoringResult.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 *
 * @phpstan-import-type JsonResult from MonitoringProviderResult
 */
class MonitoringResult implements JsonSerializable
{
    /**
     * @var array<string, MonitoringProviderResult>
     */
    protected array $providerResults = [];

    public function isHealthy(): bool
    {
        foreach ($this->providerResults as $providerResult) {
            if (!$providerResult->isHealthy()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return MonitoringProviderResult[]
     */
    public function getProviderResults(): array
    {
        return array_values($this->providerResults);
    }

    public function addProviderResult(MonitoringProviderResult $providerResult): self
    {
        $this->providerResults[spl_object_hash($providerResult)] = $providerResult;

        return $this;
    }

    public function removeProviderResult(MonitoringProviderResult $providerResult): self
    {
        unset($this->providerResults[spl_object_hash($providerResult)]);

        return $this;
    }

    public function getStatus(): MonitoringStatus
    {
        return $this->isHealthy() ? MonitoringStatus::Ok : MonitoringStatus::Error;
    }

    /**
     * @return array{
     *     status: MonitoringStatus,
     *     services?: non-empty-array<non-empty-string, JsonResult>,
     * }
     */
    public function jsonSerialize(): array
    {
        $jsonArray = [
            'status' => $this->getStatus(),
        ];

        foreach ($this->providerResults as $providerResult) {
            $jsonArray['services'][$providerResult->getName()] = $providerResult->jsonSerialize();
        }

        return $jsonArray;
    }
}

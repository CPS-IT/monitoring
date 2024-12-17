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

namespace CPSIT\Monitoring;

use CPSIT\Monitoring\Provider\ExceptionAwareMonitoringProvider;
use CPSIT\Monitoring\Provider\MonitoringProvider;
use CPSIT\Monitoring\Provider\StatusInformationAwareMonitoringProvider;
use CPSIT\Monitoring\Result\MonitoringProviderResult;
use CPSIT\Monitoring\Result\MonitoringResult;
use GuzzleHttp\Exception\RequestException;

/**
 * Monitoring.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
final class Monitoring
{
    private const ERROR_UNKNOWN = 'unknown';

    /**
     * @param MonitoringProvider[] $providers
     */
    public function checkHealth(array $providers): MonitoringResult
    {
        $result = new MonitoringResult();

        // Perform health checks
        foreach ($providers as $provider) {
            $name = $provider->getName();
            $healthy = $provider->isHealthy();

            $providerResult = new MonitoringProviderResult($name, $healthy);
            $result->addProviderResult($providerResult);

            if (!$healthy) {
                if ($provider instanceof ExceptionAwareMonitoringProvider) {
                    $providerResult->setErrorMessage($this->getErrorMessage($provider));
                    $providerResult->setErrorCode($this->getErrorCode($provider));
                }
            } elseif ($provider instanceof StatusInformationAwareMonitoringProvider) {
                $providerResult->setStatusInformation($provider->getStatusInformation());
            }
        }

        return $result;
    }

    private function getErrorMessage(ExceptionAwareMonitoringProvider $monitoringProvider): string
    {
        $lastException = $monitoringProvider->getLastException();

        return null !== $lastException ? $lastException->getMessage() : self::ERROR_UNKNOWN;
    }

    private function getErrorCode(ExceptionAwareMonitoringProvider $monitoringProvider): int|string
    {
        $lastException = $monitoringProvider->getLastException();

        if (null === $lastException) {
            return self::ERROR_UNKNOWN;
        }

        if ($lastException instanceof RequestException && ($response = $lastException->getResponse()) !== null) {
            return $response->getStatusCode();
        }

        $exceptionCode = $lastException->getCode();

        if (is_numeric($exceptionCode)) {
            return $exceptionCode;
        }

        return self::ERROR_UNKNOWN;
    }
}

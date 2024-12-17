<div align="center">

# Monitoring

[![Coverage](https://img.shields.io/coverallsCoverage/github/CPS-IT/monitoring?logo=coveralls)](https://coveralls.io/github/CPS-IT/monitoring)
[![Maintainability](https://img.shields.io/codeclimate/maintainability/CPS-IT/monitoring?logo=codeclimate)](https://codeclimate.com/github/CPS-IT/monitoring/maintainability)
[![CGL](https://img.shields.io/github/actions/workflow/status/CPS-IT/monitoring/cgl.yaml?label=cgl&logo=github)](https://github.com/CPS-IT/monitoring/actions/workflows/cgl.yaml)
[![Tests](https://img.shields.io/github/actions/workflow/status/CPS-IT/monitoring/tests.yaml?label=tests&logo=github)](https://github.com/CPS-IT/monitoring/actions/workflows/tests.yaml)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/cpsit/monitoring/php?logo=php)](https://packagist.org/packages/cpsit/monitoring)

</div>

This package provides a generic monitoring solution for web applications. It can be used to
monitor various parts of your application by implementing a set of monitoring providers and
exposing their health state by using a dedicated monitoring route. The package is highly
customizable in terms of implementing custom monitoring providers and authorization solutions.

## 🔥 Installation

[![Packagist](https://img.shields.io/packagist/v/cpsit/monitoring?label=version&logo=packagist)](https://packagist.org/packages/cpsit/monitoring)
[![Packagist Downloads](https://img.shields.io/packagist/dt/cpsit/monitoring?color=brightgreen)](https://packagist.org/packages/cpsit/monitoring)

```bash
composer require cpsit/monitoring
```

## ⚡ Usage

### Monitoring service

The package ships a [`Monitoring`](src/Monitoring.php) class which can be used to check health
of various services.

Services can be defined using the [`MonitoringProvider`](src/Provider/MonitoringProvider.php)
interface. Each provider can reveal the health state of the underlain service using the `isHealthy()`
method.

```php
namespace My\Vendor\Monitoring\Provider;

use CPSIT\Monitoring\Provider\MonitoringProvider;
use My\Vendor\Service\ApiService;

final class ApiMonitoringProvider implements MonitoringProvider
{
    public function __construct(
        private readonly ApiService $apiService,
    ) {}

    public function getName(): string
    {
        return 'api';
    }

    public function isHealthy(): bool
    {
        try {
            $response = $this->apiService->request('/health', 'HEAD');
            return $response->getStatusCode() < 400;
        } catch (\Exception) {
            return false;
        }
    }
}
```

### Report errors

In addition to the normal health state, providers might also be able to report errors which
occurred during health check. For this, an
[`ExceptionAwareMonitoringProvider`](src/Provider/ExceptionAwareMonitoringProvider.php)
interface exists. It allows to fetch errors using the `getLastException()` method.

```diff
 namespace My\Vendor\Monitoring\Provider;

-use CPSIT\Monitoring\Provider\MonitoringProvider;
+use CPSIT\Monitoring\Provider\ExceptionAwareMonitoringProvider;
 use My\Vendor\Service\ApiService;

-final class ApiMonitoringProvider implements MonitoringProvider
+final class ApiMonitoringProvider implements ExceptionAwareMonitoringProvider
 {
+    private ?\Throwable $lastException = null;
+
     public function __construct(
         private readonly ApiService $apiService,
     ) {}

     public function getName(): string
     {
         return 'api';
     }

     public function isHealthy(): bool
     {
         try {
             $response = $this->apiService->request('/health', 'HEAD');
             return $response->getStatusCode() < 400;
-        } catch (\Exception) {
+        } catch (\Exception $exception) {
+            $this->lastException = $exception;
             return false;
         }
     }
+
+    public function getLastException(): ?\Throwable
+    {
+        return $this->lastException;
+    }
 }
```

### Provide individual status information

Each provider can be extended to return individual status information. This is possible once the
[`StatusInformationAwareMonitoringProvider`](src/Provider/StatusInformationAwareMonitoringProvider.php)
interface is implemented within a concrete provider.

```diff
 namespace My\Vendor\Monitoring\Provider;

-use CPSIT\Monitoring\Provider\MonitoringProvider;
+use CPSIT\Monitoring\Provider\StatusInformationAwareMonitoringProvider;
 use My\Vendor\Service\ApiService;

-final class ApiMonitoringProvider implements MonitoringProvider
+final class ApiMonitoringProvider implements StatusInformationAwareMonitoringProvider
 {
     public function __construct(
         private readonly ApiService $apiService,
     ) {}

     public function getName(): string
     {
         return 'api';
     }

     public function isHealthy(): bool
     {
         try {
             $response = $this->apiService->request('/health', 'HEAD');
             return $response->getStatusCode() < 400;
         } catch (\Exception) {
             return false;
         }
     }
+
+    /**
+     * @return array<string, string>
+     */
+    public function getStatusInformation(): array
+    {
+        $lastProcessDate = $this->apiService->getLastProcessDate();
+
+        return [
+            'last_process_date' => $lastProcessDate->format(\DateTimeInterface::RFC2822),
+        ];
+    }
 }
```

### Middleware

Additionally, a middleware [`MonitoringMiddleware`](src/Middleware/MonitoringMiddleware.php) exists.
It can be used to make health checks available using the middleware stack of a web application. A set
of monitoring providers needs to be provided when constructing the middleware. This can be best
achieved with a PSR-11 service container, e.g. within a Symfony or Symfony-based application.

In case the monitoring result is healthy, a `200 OK` response will be returned, otherwise the response
is `424 Failed Dependency`. All responses are in JSON format and contain the serialized monitoring result.
If an internal error occurs (such as failed JSON encoding), the response is `500 Internal Server Error`
and the response body contains the error message in JSON format.

#### Validators

The middleware acts only on valid requests. The decision as to whether a request is valid is in the hands
of so called _validators_. Each validator must implement the
[`Validator`](src/Validation/Validator.php) interface.

The package already provides the [`RouteValidator`](src/Validation/RouteValidator.php). It can be
configured to allow only requests to a given route, which is `/monitor/health` by default. In case this
route is matched, the request is valid and therefore the monitoring process will be triggered.

#### Authorizers

Requests handled by the provided middleware can be secured using a list of _authorizers_. Authorizers
are classes which implement the [`Authorizer`](src/Authorization/Authorizer.php) interface.
Each authorizer must implement the `isAuthorized()` method. In case any authorizer returns `true`, the
request will be processed as is. If no authorizer is able to give the appropriate authorization, a
`401 Unauthorized` response will be returned.

Authorizers can be prioritized by defining an explicit priority using the `getPriority()` method.
Authorizers with higher priority will be executed first.

### Dependency injection

The package already provides a ready-made container configuration for dependency injection based on
[Symfony dependency injection](https://symfony.com/doc/current/components/dependency_injection.html).
This is particularly helpful if several monitoring providers are to be automatically configured on
the middleware.

For this purpose, it is necessary to install the following packages via Composer:

```bash
composer require symfony/config symfony/dependency-injection symfony/yaml
```

> [!NOTE]
> The dependency injection configuration is an **optional component** of this package. Therefore,
> required Composer packages are not explicitly required, but only suggested. You must install them
> **by your own**.

The next step is to load the configuration into the container. For this, the package provides a
helper class [`ServiceConfigurator`](src/DependencyInjection/ServiceConfigurator.php):

```php
use CPSIT\Monitoring\DependencyInjection\ServiceConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerBuilder $container): void {
    ServiceConfigurator::configure($container);
};
```

All classes that are configured in the service container and implement the
`MonitoringProvider` interface are now automatically tagged with `monitoring.provider`. The
[`MonitoringProviderCompilerPass`](src/DependencyInjection/MonitoringProviderCompilerPass.php)
then takes care of the autoconfiguration of all tagged monitoring providers at the
`MonitoringMiddleware` class.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 3.0 (or later)](LICENSE).

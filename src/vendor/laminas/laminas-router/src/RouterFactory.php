<?php

declare(strict_types=1);

namespace Laminas\Router;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * @internal
 *
 * @final
 */
class RouterFactory implements FactoryInterface
{
    /**
     * Create and return the router
     *
     * Delegates to the HttpRouter service.
     *
     * @param  string $name
     * @param  null|array $options
     * @return RouteStackInterface
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $name, ?array $options = null)
    {
        return $container->get('HttpRouter');
    }

    /**
     * Create and return RouteStackInterface instance
     *
     * For use with laminas-servicemanager v2; proxies to __invoke().
     *
     * @deprecated Since 3.6.0 - This component is no longer compatible with Service Manager v2
     *
     * @param null|string $normalizedName
     * @param null|string $requestedName
     * @return RouteStackInterface
     */
    public function createService(ServiceLocatorInterface $container, $normalizedName = null, $requestedName = null)
    {
        $requestedName ??= 'Router';
        return $this($container, $requestedName);
    }
}

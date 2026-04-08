<?php

declare(strict_types=1);

namespace Laminas\Router\Http;

/**
 * @deprecated Use HttpRouteInterface instead; this will be removed in v4.0
 */
interface RouteInterface extends HttpRouteInterface
{
    /**
     * Get a list of parameters used while assembling.
     *
     * @return array
     */
    public function getAssembledParams();
}

<?php

declare(strict_types=1);

namespace Laminas\Router\Http;

use Laminas\Router\RouteMatch as BaseRouteMatch;
use Override;

use function array_merge;

/**
 * Part route match.
 *
 * @deprecated This class will become an interface in version 4 with
 *              `Laminas\Router\HttpRouteMatch` as a final implementation
 */
class RouteMatch extends BaseRouteMatch
{
    /**
     * Create a part RouteMatch with given parameters and length.
     *
     * @param  int $length
     */
    public function __construct(
        array $params,
        /**
         * Length of the matched path.
         */
        protected $length = 0
    ) {
        parent::__construct($params);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setMatchedRouteName($name)
    {
        if ($this->matchedRouteName === null) {
            $this->matchedRouteName = $name;
        } else {
            $this->matchedRouteName = $name . '/' . $this->matchedRouteName;
        }

        return $this;
    }

    /**
     * Merge parameters from another match.
     *
     * @return RouteMatch
     */
    public function merge(RouteMatch $match)
    {
        $this->params  = array_merge($this->params, $match->getParams());
        $this->length += $match->getLength();

        $this->matchedRouteName = $match->getMatchedRouteName();

        return $this;
    }

    /**
     * Get the matched path length.
     *
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }
}

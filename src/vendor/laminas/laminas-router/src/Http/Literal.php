<?php

declare(strict_types=1);

namespace Laminas\Router\Http;

use Laminas\Router\Exception;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface;
use Override;
use Traversable;

use function is_array;
use function method_exists;
use function sprintf;
use function strlen;
use function strpos;

/**
 * Literal route.
 *
 * @final
 */
class Literal implements HttpRouteInterface
{
    /**
     * Default values.
     *
     * @var array
     */
    protected $defaults;

    /**
     * @internal
     * @deprecated Since 3.9.0 This property will be removed or made private in version 4.0
     *
     * @var int|null
     */
    public $priority;

    /**
     * Create a new literal route.
     *
     * @param  string $route
     */
    public function __construct(
        /**
         * RouteInterface to match.
         */
        protected $route,
        array $defaults = []
    ) {
        $this->defaults = $defaults;
    }

    /**
     * @inheritDoc
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public static function factory($options = [])
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable set of options',
                __METHOD__
            ));
        }

        if (! isset($options['route'])) {
            throw new Exception\InvalidArgumentException('Missing "route" in options array');
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['route'], $options['defaults']);
    }

    /**
     * @inheritDoc
     * @param int|null $pathOffset
     */
    #[Override]
    public function match(RequestInterface $request, $pathOffset = null)
    {
        if (! method_exists($request, 'getUri')) {
            return null;
        }

        $uri  = $request->getUri();
        $path = $uri->getPath();

        if ($pathOffset !== null) {
            if ($pathOffset >= 0 && strlen((string) $path) >= $pathOffset && ! empty($this->route)) {
                if (strpos($path, $this->route, $pathOffset) === $pathOffset) {
                    return new HttpRouteMatch($this->defaults, strlen($this->route));
                }
            }

            return null;
        }

        if ($path === $this->route) {
            return new HttpRouteMatch($this->defaults, strlen($this->route));
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function assemble(array $params = [], array $options = [])
    {
        return $this->route;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAssembledParams()
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace Laminas\Router\Http;

use Laminas\Router\Exception;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface;
use Override;
use Traversable;

use function is_array;
use function sprintf;

/**
 * Placeholder route.
 *
 * @final
 */
class Placeholder implements HttpRouteInterface
{
    /**
     * @internal
     * @deprecated Since 3.9.0 This property will be removed or made private in version 4.0
     *
     * @var int|null
     */
    public $priority;

    public function __construct(private readonly array $defaults)
    {
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
        }

        if (! is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable set of options',
                __METHOD__
            ));
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        if (! is_array($options['defaults'])) {
            throw new Exception\InvalidArgumentException('options[defaults] expected to be an array if set');
        }

        return new static($options['defaults']);
    }

    /**
     * @inheritDoc
     * @param int|null $pathOffset
     */
    #[Override]
    public function match(RequestInterface $request, $pathOffset = null)
    {
        return new HttpRouteMatch($this->defaults);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function assemble(array $params = [], array $options = [])
    {
        return '';
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

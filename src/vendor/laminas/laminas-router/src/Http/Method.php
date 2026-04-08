<?php

declare(strict_types=1);

namespace Laminas\Router\Http;

use Laminas\Router\Exception;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface;
use Override;
use Traversable;

use function array_map;
use function explode;
use function in_array;
use function is_array;
use function method_exists;
use function sprintf;
use function strtoupper;

/**
 * Method route.
 *
 * @final
 */
class Method implements HttpRouteInterface
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
     * Create a new method route.
     *
     * @param  string $verb
     */
    public function __construct(
        /**
         * Verb to match.
         */
        protected $verb,
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

        if (! isset($options['verb'])) {
            throw new Exception\InvalidArgumentException('Missing "verb" in options array');
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['verb'], $options['defaults']);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function match(RequestInterface $request)
    {
        if (! method_exists($request, 'getMethod')) {
            return null;
        }

        $requestVerb = strtoupper($request->getMethod());
        $matchVerbs  = explode(',', strtoupper($this->verb));
        $matchVerbs  = array_map('trim', $matchVerbs);

        if (in_array($requestVerb, $matchVerbs)) {
            return new HttpRouteMatch($this->defaults);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function assemble(array $params = [], array $options = [])
    {
        // The request method does not contribute to the path, thus nothing is returned.
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

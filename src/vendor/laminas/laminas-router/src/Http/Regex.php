<?php

declare(strict_types=1);

namespace Laminas\Router\Http;

use Laminas\Router\Exception;
use Laminas\Router\Exception\InvalidArgumentException;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface;
use Override;
use Traversable;

use function array_merge;
use function is_array;
use function is_int;
use function is_numeric;
use function method_exists;
use function preg_match;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;

/**
 * Regex route.
 *
 * @final
 */
class Regex implements HttpRouteInterface
{
    /**
     * Default values.
     *
     * @var array
     */
    protected $defaults;

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    /**
     * @internal
     * @deprecated Since 3.9.0 This property will be removed or made private in version 4.0
     *
     * @var int|null
     */
    public $priority;

    /**
     * Create a new regex route.
     *
     * @param  string $regex
     * @param  string $spec
     */
    public function __construct(
        /**
         * Regex to match.
         */
        protected $regex,
        /**
         * Specification for URL assembly.
         *
         * Parameters accepting substitutions should be denoted as "%key%"
         */
        protected $spec,
        array $defaults = []
    ) {
        $this->defaults = $defaults;
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
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

        if (! isset($options['regex'])) {
            throw new Exception\InvalidArgumentException('Missing "regex" in options array');
        }

        if (! isset($options['spec'])) {
            throw new Exception\InvalidArgumentException('Missing "spec" in options array');
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['regex'], $options['spec'], $options['defaults']);
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
            $result = preg_match('(\G' . $this->regex . ')', $path, $matches, 0, $pathOffset);
        } else {
            $result = preg_match('(^' . $this->regex . '$)', $path, $matches);
        }

        if (! $result) {
            return null;
        }

        $matchedLength = strlen($matches[0]);

        foreach ($matches as $key => $value) {
            if (is_numeric($key) || is_int($key) || $value === '') {
                unset($matches[$key]);
            } else {
                $matches[$key] = rawurldecode($value);
            }
        }

        return new HttpRouteMatch(array_merge($this->defaults, $matches), $matchedLength);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function assemble(array $params = [], array $options = [])
    {
        $url                   = $this->spec;
        $mergedParams          = array_merge($this->defaults, $params);
        $this->assembledParams = [];

        foreach ($mergedParams as $key => $value) {
            $spec = '%' . $key . '%';

            if (str_contains($url, $spec)) {
                $url = str_replace($spec, rawurlencode((string) $value), $url);

                $this->assembledParams[] = $key;
            }
        }

        return $url;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAssembledParams()
    {
        return $this->assembledParams;
    }
}

<?php

declare(strict_types=1);

namespace Laminas\Router;

use Laminas\Stdlib\PriorityList as StdlibPriorityList;

/**
 * @internal
 *
 * @template TKey of string
 * @template TValue of RouteInterface
 * @template-extends StdlibPriorityList<TKey, TValue>
 * @final
 */
class PriorityList extends StdlibPriorityList
{
}

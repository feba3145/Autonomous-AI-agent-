<?php
namespace Magento\ConfigurableProduct\Pricing\Price\FinalPriceResolver;

/**
 * Interceptor class for @see \Magento\ConfigurableProduct\Pricing\Price\FinalPriceResolver
 */
class Interceptor extends \Magento\ConfigurableProduct\Pricing\Price\FinalPriceResolver implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct()
    {
        $this->___init();
    }

    /**
     * {@inheritdoc}
     */
    public function resolvePrice(\Magento\Framework\Pricing\SaleableInterface $product)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'resolvePrice');
        return $pluginInfo ? $this->___callPlugins('resolvePrice', func_get_args(), $pluginInfo) : parent::resolvePrice($product);
    }
}

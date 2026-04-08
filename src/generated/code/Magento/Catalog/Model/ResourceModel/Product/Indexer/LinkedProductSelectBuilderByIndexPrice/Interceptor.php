<?php
namespace Magento\Catalog\Model\ResourceModel\Product\Indexer\LinkedProductSelectBuilderByIndexPrice;

/**
 * Interceptor class for @see \Magento\Catalog\Model\ResourceModel\Product\Indexer\LinkedProductSelectBuilderByIndexPrice
 */
class Interceptor extends \Magento\Catalog\Model\ResourceModel\Product\Indexer\LinkedProductSelectBuilderByIndexPrice implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Store\Model\StoreManagerInterface $storeManager, \Magento\Framework\App\ResourceConnection $resourceConnection, \Magento\Customer\Model\Session $customerSession, \Magento\Framework\EntityManager\MetadataPool $metadataPool, ?\Magento\Catalog\Model\ResourceModel\Product\BaseSelectProcessorInterface $baseSelectProcessor = null, ?\Magento\Framework\Search\Request\IndexScopeResolverInterface $priceTableResolver = null, ?\Magento\Framework\Indexer\DimensionFactory $dimensionFactory = null)
    {
        $this->___init();
        parent::__construct($storeManager, $resourceConnection, $customerSession, $metadataPool, $baseSelectProcessor, $priceTableResolver, $dimensionFactory);
    }

    /**
     * {@inheritdoc}
     */
    public function build(int $productId, int $storeId): array
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'build');
        return $pluginInfo ? $this->___callPlugins('build', func_get_args(), $pluginInfo) : parent::build($productId, $storeId);
    }
}

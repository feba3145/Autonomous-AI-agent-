<?php
namespace Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;

/**
 * Interceptor class for @see \Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory
 */
class Interceptor extends \Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManagerInterface, array $collections = [])
    {
        $this->___init();
        parent::__construct($objectManagerInterface, $collections);
    }

    /**
     * {@inheritdoc}
     */
    public function getReport($requestName)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getReport');
        return $pluginInfo ? $this->___callPlugins('getReport', func_get_args(), $pluginInfo) : parent::getReport($requestName);
    }
}

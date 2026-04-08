<?php
namespace Magento\Config\Console\Command\ConfigSet\ProcessorFacade;

/**
 * Interceptor class for @see \Magento\Config\Console\Command\ConfigSet\ProcessorFacade
 */
class Interceptor extends \Magento\Config\Console\Command\ConfigSet\ProcessorFacade implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Framework\App\Scope\ValidatorInterface $scopeValidator, \Magento\Config\Model\Config\PathValidator $pathValidator, \Magento\Config\Console\Command\ConfigSet\ConfigSetProcessorFactory $configSetProcessorFactory, \Magento\Deploy\Model\DeploymentConfig\Hash $hash, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->___init();
        parent::__construct($scopeValidator, $pathValidator, $configSetProcessorFactory, $hash, $scopeConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function process($path, $value, $scope, $scopeCode, $lock)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'process');
        return $pluginInfo ? $this->___callPlugins('process', func_get_args(), $pluginInfo) : parent::process($path, $value, $scope, $scopeCode, $lock);
    }

    /**
     * {@inheritdoc}
     */
    public function processWithLockTarget($path, $value, $scope, $scopeCode, $lock, $lockTarget = 'app_env')
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'processWithLockTarget');
        return $pluginInfo ? $this->___callPlugins('processWithLockTarget', func_get_args(), $pluginInfo) : parent::processWithLockTarget($path, $value, $scope, $scopeCode, $lock, $lockTarget);
    }
}

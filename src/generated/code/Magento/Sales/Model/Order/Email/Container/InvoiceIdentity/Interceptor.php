<?php
namespace Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;

/**
 * Interceptor class for @see \Magento\Sales\Model\Order\Email\Container\InvoiceIdentity
 */
class Interceptor extends \Magento\Sales\Model\Order\Email\Container\InvoiceIdentity implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, \Magento\Store\Model\StoreManagerInterface $storeManager)
    {
        $this->___init();
        parent::__construct($scopeConfig, $storeManager);
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'isEnabled');
        return $pluginInfo ? $this->___callPlugins('isEnabled', func_get_args(), $pluginInfo) : parent::isEnabled();
    }

    /**
     * {@inheritdoc}
     */
    public function getEmailCopyTo()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getEmailCopyTo');
        return $pluginInfo ? $this->___callPlugins('getEmailCopyTo', func_get_args(), $pluginInfo) : parent::getEmailCopyTo();
    }

    /**
     * {@inheritdoc}
     */
    public function getCopyMethod()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getCopyMethod');
        return $pluginInfo ? $this->___callPlugins('getCopyMethod', func_get_args(), $pluginInfo) : parent::getCopyMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getGuestTemplateId()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getGuestTemplateId');
        return $pluginInfo ? $this->___callPlugins('getGuestTemplateId', func_get_args(), $pluginInfo) : parent::getGuestTemplateId();
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateId()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getTemplateId');
        return $pluginInfo ? $this->___callPlugins('getTemplateId', func_get_args(), $pluginInfo) : parent::getTemplateId();
    }

    /**
     * {@inheritdoc}
     */
    public function getEmailIdentity()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getEmailIdentity');
        return $pluginInfo ? $this->___callPlugins('getEmailIdentity', func_get_args(), $pluginInfo) : parent::getEmailIdentity();
    }

    /**
     * {@inheritdoc}
     */
    public function setStore(\Magento\Store\Model\Store $store)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'setStore');
        return $pluginInfo ? $this->___callPlugins('setStore', func_get_args(), $pluginInfo) : parent::setStore($store);
    }

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getStore');
        return $pluginInfo ? $this->___callPlugins('getStore', func_get_args(), $pluginInfo) : parent::getStore();
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerName($name)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'setCustomerName');
        return $pluginInfo ? $this->___callPlugins('setCustomerName', func_get_args(), $pluginInfo) : parent::setCustomerName($name);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerEmail($email)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'setCustomerEmail');
        return $pluginInfo ? $this->___callPlugins('setCustomerEmail', func_get_args(), $pluginInfo) : parent::setCustomerEmail($email);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerName()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getCustomerName');
        return $pluginInfo ? $this->___callPlugins('getCustomerName', func_get_args(), $pluginInfo) : parent::getCustomerName();
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerEmail()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getCustomerEmail');
        return $pluginInfo ? $this->___callPlugins('getCustomerEmail', func_get_args(), $pluginInfo) : parent::getCustomerEmail();
    }
}

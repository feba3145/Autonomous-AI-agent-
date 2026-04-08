<?php
/************************************************************************
 *
 * Copyright 2020 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 * ************************************************************************
 */
declare(strict_types=1);

namespace Magento\PaymentServicesDashboard\Block\Adminhtml;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\PaymentServicesBase\Model\Config;
use Magento\PaymentServicesPaypal\Model\Config as PaymentsConfig;

/**
 * @api
 */
class Index extends Template
{
    /**
     * Config path used for frontend url
     */
    private const FRONTEND_URL_PATH = 'payment_services_dashboard/frontend_url';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var PaymentsConfig
     */
    private $paymentsConfig;

    /**
     * @var Session
     */
    private $adminSession;

    /**
     * @param Context $context
     * @param Config $config
     * @param TimezoneInterface $timezone
     * @param PaymentsConfig $paymentsConfig
     * @param Session $adminSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        TimezoneInterface $timezone,
        PaymentsConfig $paymentsConfig,
        Session $adminSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->timezone = $timezone;
        $this->paymentsConfig = $paymentsConfig;
        $this->adminSession = $adminSession;
    }

    /**
     * Returns config for frontend url
     *
     * @return string
     */
    public function getFrontendUrl(): string
    {
        return (string) $this->_scopeConfig->getValue(
            self::FRONTEND_URL_PATH,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Return a JSON map of endpoints path
     *
     * @return string
     */
    public function getConfigJson() : string
    {
        $config = [
            'endpoints' => [
                'config' => $this->getUrl('services/config/index'),
                'websites' => $this->getUrl('services/config/websites'),
                'servicesProxy'=> $this->getUrl('services/service/proxy'),
                'genericRedirect' => $this->getUrl('services/url/redirect')
            ],
            'configurationStatus' => [
                'magentoServicesConfigured' => [
                    'production' => $this->config->isServicesConnectorConfigured('production'),
                    'sandbox' => $this->config->isServicesConnectorConfigured('sandbox'),
                ],
                'paymentEnvironmentType' => $this->config->getEnvironmentTypeAcrossWebsites()
            ],
            'userDetails' => [
                'locale' => $this->adminSession->getUser()->getInterfaceLocale()
            ],
            'extensionVersion' => $this->config->getVersion()
        ];
        return json_encode($config);
    }
}

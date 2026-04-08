<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Request;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Gateway\Config\Vault\Config as VaultConfig;
use PayPal\Braintree\Gateway\Helper\SubjectReader;

class ThreeDSecureVaultDataBuilder extends ThreeDSecureDataBuilder
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var VaultConfig
     */
    private VaultConfig $vaultConfig;

    /**
     * ThreeDSecureVaultDataBuilder constructor.
     * @param Config $config
     * @param SubjectReader $subjectReader
     * @param RequestInterface $request
     * @param VaultConfig $vaultConfig
     */
    public function __construct(
        Config $config,
        SubjectReader $subjectReader,
        RequestInterface $request,
        VaultConfig $vaultConfig
    ) {
        parent::__construct($config, $subjectReader);
        $this->request = $request;
        $this->vaultConfig = $vaultConfig;
    }

    /**
     * Check if 3d secure is enabled
     *
     * @param OrderAdapterInterface $order
     * @param float $amount
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     */
    protected function is3DSecureEnabled(OrderAdapterInterface $order, $amount): bool
    {
        if ($this->request->isSecure() && $this->vaultConfig->isCvvVerifyEnabled()) {
            return false;
        }

        return parent::is3DSecureEnabled($order, $amount);
    }
}

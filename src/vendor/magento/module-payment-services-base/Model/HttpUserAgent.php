<?php
/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2025 Adobe
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
 */
declare(strict_types=1);

namespace Magento\PaymentServicesBase\Model;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

/**
 * Payment Services user agent to use when making HTTP requests.
 */
class HttpUserAgent
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @param Config $config
     * @param State $appState
     */
    public function __construct(
        Config $config,
        State $appState,
    ) {
        $this->config = $config;
        $this->appState = $appState;
    }

    /**
     * @throws LocalizedException
     */
    public function get(): string
    {
        return sprintf(
            'PaymentServices/%s/%s',
            $this->appState->getAreaCode(),
            $this->config->getVersion(),
        );
    }
}

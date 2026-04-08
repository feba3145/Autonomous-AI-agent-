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

namespace Magento\PaymentServicesBase\Controller\GetSdk;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\PaymentServicesBase\Model\SdkContent\Provider as SdkContentProvider;
use Psr\Log\LoggerInterface;

/**
 * Processes request to PaymentSDK.js and returns content as result
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param ResultFactory $resultFactory
     * @param SdkContentProvider $sdkContentProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly SdkContentProvider $sdkContentProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Generates PaymentSDK.js data and returns it as result
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            return $this->resultFactory->create(ResultFactory::TYPE_RAW)
                ->setHeader('Content-Type', 'application/javascript')
                ->setContents($this->sdkContentProvider->get())
                ->setStatusHeader(200);
        } catch (\Throwable $e) {
            $this->logger->error('Error generating \'PaymentSDK.js\'.', ['exception' => $e->getMessage()]);
            return $this->resultFactory->create(ResultFactory::TYPE_JSON)
                ->setData(['message' => 'An unexpected error occurred while generating \'PaymentSDK.js\''])
                ->setHttpResponseCode(500);
        }
    }
}

<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Model\Ui\Adminhtml\PayPal;

use PayPal\Braintree\Gateway\Config\PayPal\Config;
use PayPal\Braintree\Model\Ui\Adminhtml\PayPal\TokenUiComponentProvider;
use Magento\Framework\UrlInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

/**
 * Contains methods to test PayPal token Ui component provider
 */
class TokenUiComponentProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var TokenUiComponentInterfaceFactory|MockObject
     */
    private TokenUiComponentInterfaceFactory|MockObject $componentFactory;

    /**
     * @var UrlInterface|MockObject
     */
    private UrlInterface|MockObject $urlBuilder;

    /**
     * @var Config|MockObject
     */
    private MockObject|Config $config;

    /**
     * @var TokenUiComponentProvider
     */
    private TokenUiComponentProvider $tokenUiComponentProvider;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->componentFactory = $this->getMockBuilder(TokenUiComponentInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayPalIcon'])
            ->getMock();

        $this->tokenUiComponentProvider = new TokenUiComponentProvider(
            $this->componentFactory,
            $this->urlBuilder,
            $this->config
        );
    }

    /**
     * @covers \PayPal\Braintree\Model\Ui\Adminhtml\PayPal\TokenUiComponentProvider::getComponentForToken
     * @throws Exception
     */
    public function testGetComponentForToken()
    {
        $nonceUrl = 'https://payment/adminhtml/nonce/url';
        $payerEmail = 'john.doe@test.com';
        $icon = [
            'url' => 'https://payment/adminhtml/icon.png',
            'width' => 48,
            'height' => 32
        ];

        $expected = [
            'code' => 'vault',
            'nonceUrl' => $nonceUrl,
            'details' => [
                'payerEmail' => $payerEmail,
                'icon' => $icon
            ],
            'template' => 'vault.phtml'
        ];

        $this->config->expects(static::once())
            ->method('getPayPalIcon')
            ->willReturn($icon);

        $paymentToken = $this->createMock(PaymentTokenInterface::class);
        $paymentToken->expects(static::once())
            ->method('getTokenDetails')
            ->willReturn('{"payerEmail":" ' . $payerEmail . '"}');
        $paymentToken->expects(static::once())
            ->method('getPublicHash')
            ->willReturn('cmk32dl21l');

        $this->urlBuilder->expects(static::once())
            ->method('getUrl')
            ->willReturn($nonceUrl);

        $tokenComponent = $this->createMock(TokenUiComponentInterface::class);
        $tokenComponent->expects(static::once())
            ->method('getConfig')
            ->willReturn($expected);

        $this->componentFactory->expects(static::once())
            ->method('create')
            ->willReturn($tokenComponent);

        $component = $this->tokenUiComponentProvider->getComponentForToken($paymentToken);
        static::assertEquals($tokenComponent, $component);
        static::assertEquals($expected, $component->getConfig());
    }
}

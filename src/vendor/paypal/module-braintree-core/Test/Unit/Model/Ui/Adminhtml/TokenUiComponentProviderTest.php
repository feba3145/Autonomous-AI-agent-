<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Model\Ui\Adminhtml;

use PayPal\Braintree\Model\Ui\Adminhtml\TokenUiComponentProvider;
use Magento\Framework\UrlInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject as MockObject;

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

        $this->tokenUiComponentProvider = new TokenUiComponentProvider(
            $this->componentFactory,
            $this->urlBuilder
        );
    }

    /**
     * @covers \PayPal\Braintree\Model\Ui\Adminhtml\TokenUiComponentProvider::getComponentForToken
     * @throws Exception
     */
    public function testGetComponentForToken()
    {
        $nonceUrl = 'https://payment/adminhtml/nonce/url';
        $type = 'VI';
        $maskedCC = '1111';
        $expirationDate = '12/2015';

        $expected = [
            'code' => 'vault',
            'nonceUrl' => $nonceUrl,
            'details' => [
                'type' => $type,
                'maskedCC' => $maskedCC,
                'expirationDate' => $expirationDate
            ],
            'template' => 'vault.phtml'
        ];

        $paymentToken = $this->createMock(PaymentTokenInterface::class);
        $paymentToken->expects(static::once())
            ->method('getTokenDetails')
            ->willReturn('{"type":"VI","maskedCC":"1111","expirationDate":"12\/2015"}');
        $paymentToken->expects(static::once())
            ->method('getPublicHash')
            ->willReturn('37du7ir5ed');

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

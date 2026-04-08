<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Test\Unit\Gateway\Config;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Config\Config as PaymentConfig;
use PayPal\Braintree\Gateway\Config\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use PayPal\Braintree\Model\StoreConfigResolver;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private const METHOD_CODE = 'braintree';

    /**
     * @var Config
     */
    private Config $model;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private ScopeConfigInterface|MockObject $scopeConfigMock;

    /**
     * @var Json|MockObject
     */
    private Json|MockObject $serializerMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $storeConfigResolverMock = $this->createMock(StoreConfigResolver::class);
        $this->serializerMock = $this->createMock(Json::class);

        $this->model = new Config(
            $this->scopeConfigMock,
            $storeConfigResolverMock,
            self::METHOD_CODE,
            PaymentConfig::DEFAULT_PATH_PATTERN,
            $this->serializerMock
        );
    }

    /**
     * Test get country specific card type config
     *
     * @param string $encodedValue
     * @param array|string $value
     * @param array $expected
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     * @dataProvider getCountrySpecificCardTypeConfigDataProvider
     */
    public function testGetCountrySpecificCardTypeConfig(
        string $encodedValue,
        array|string $value,
        array $expected
    ) {
        $this->scopeConfigMock->expects(static::once())
            ->method('getValue')
            ->with(
                $this->getPath(Config::KEY_COUNTRY_CREDIT_CARD),
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn($encodedValue);

        $this->serializerMock->expects($this->once())
            ->method('unserialize')
            ->with($encodedValue)
            ->willReturn($value);

        static::assertEquals(
            $expected,
            $this->model->getCountrySpecificCardTypeConfig()
        );
    }

    /**
     * get country specific card type data provider
     *
     * @return array
     */
    public static function getCountrySpecificCardTypeConfigDataProvider(): array
    {
        return [
            'valid data' => [
                '{"GB":["VI","AE"],"US":["DI","JCB"]}',
                ['GB' => ['VI', 'AE'], 'US' => ['DI', 'JCB']],
                ['GB' => ['VI', 'AE'], 'US' => ['DI', 'JCB']]
            ],
            'non-array value' => [
                '""',
                '',
                []
            ]
        ];
    }

    /**
     * Test get available card types
     *
     * @param string $value
     * @param array $expected
     * @throws InputException
     * @throws NoSuchEntityException
     * @dataProvider getAvailableCardTypesDataProvider
     */
    public function testGetAvailableCardTypes(string $value, array $expected)
    {
        $this->scopeConfigMock->expects(static::once())
            ->method('getValue')
            ->with($this->getPath(Config::KEY_CC_TYPES), ScopeInterface::SCOPE_STORE, null)
            ->willReturn($value);

        static::assertEquals(
            $expected,
            $this->model->getAvailableCardTypes()
        );
    }

    /**
     * Get available card types data provider
     *
     * @return array
     */
    public static function getAvailableCardTypesDataProvider(): array
    {
        return [
            [
                'AE,VI,MC,DI,JCB',
                ['AE', 'VI', 'MC', 'DI', 'JCB']
            ],
            [
                '',
                []
            ]
        ];
    }

    /**
     * Test get cc types mapper
     *
     * @param string $value
     * @param array $expected
     * @throws InputException
     * @throws NoSuchEntityException
     * @dataProvider getCcTypesMapperDataProvider
     */
    public function testGetCcTypesMapper(string $value, array $expected)
    {
        $this->scopeConfigMock->expects(static::once())
            ->method('getValue')
            ->with(
                $this->getPath(Config::KEY_CC_TYPES_BRAINTREE_MAPPER),
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn($value);

        static::assertEquals(
            $expected,
            $this->model->getCctypesMapper()
        );
    }

    /**
     * Get cc types mapper data provider
     *
     * @return array
     */
    public static function getCcTypesMapperDataProvider(): array
    {
        return [
            [
                '{"visa":"VI","american-express":"AE"}',
                ['visa' => 'VI', 'american-express' => 'AE']
            ],
            [
                '{invalid json}',
                []
            ],
            [
                '',
                []
            ]
        ];
    }

    /**
     * Test country available card types
     *
     * @param string $encodedData
     * @param array|string $data
     * @param array $countryData
     * @throws InputException
     * @throws NoSuchEntityException
     * @covers \PayPal\Braintree\Gateway\Config\Config::getCountryAvailableCardTypes
     * @dataProvider getCountrySpecificCardTypeConfigDataProvider
     */
    public function testCountryAvailableCardTypes(
        string $encodedData,
        array|string $data,
        array $countryData
    ) {
        $this->scopeConfigMock->expects(static::any())
            ->method('getValue')
            ->with($this->getPath(Config::KEY_COUNTRY_CREDIT_CARD), ScopeInterface::SCOPE_STORE, null)
            ->willReturn($encodedData);

        $this->serializerMock->expects($this->any())
            ->method('unserialize')
            ->with($encodedData)
            ->willReturn($data);

        foreach ($countryData as $countryId => $types) {
            $result = $this->model->getCountryAvailableCardTypes($countryId);
            static::assertEquals($types, $result);
        }

        if (empty($countryData)) {
            static::assertEquals("", $data);
        }
    }

    /**
     * Test use cvv
     *
     * @covers \PayPal\Braintree\Gateway\Config\Config::isCvvEnabled
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function testUseCvv()
    {
        $this->scopeConfigMock->expects(static::any())
            ->method('getValue')
            ->with($this->getPath(Config::KEY_USE_CVV), ScopeInterface::SCOPE_STORE, null)
            ->willReturn(1);

        static::assertTrue($this->model->isCvvEnabled());
    }

    /**
     * Test 3D secure enabled
     *
     * @param bool|int|string $data
     * @param boolean $expected
     * @throws InputException
     * @throws NoSuchEntityException
     * @covers \PayPal\Braintree\Gateway\Config\Config::isVerify3DSecure
     * @dataProvider verify3DSecureDataProvider
     */
    public function testIsVerify3DSecure(bool|int|string $data, bool $expected)
    {
        $this->scopeConfigMock->expects(static::any())
            ->method('getValue')
            ->with($this->getPath(Config::KEY_VERIFY_3DSECURE), ScopeInterface::SCOPE_STORE, null)
            ->willReturn($data);
        static::assertEquals($expected, $this->model->isVerify3DSecure());
    }

    /**
     * Get items to verify 3d secure testing
     *
     * @return array
     */
    public static function verify3DSecureDataProvider(): array
    {
        return [
            ['data' => 1, 'expected' => true],
            ['data' => true, 'expected' => true],
            ['data' => '1', 'expected' => true],
            ['data' => 0, 'expected' => false],
            ['data' => '0', 'expected' => false],
            ['data' => false, 'expected' => false],
        ];
    }

    /**
     * Test to get threshold amount
     *
     * @param $data
     * @param $expected
     * @throws InputException
     * @throws NoSuchEntityException
     * @covers \PayPal\Braintree\Gateway\Config\Config::getThresholdAmount
     * @dataProvider thresholdAmountDataProvider
     */
    public function testGetThresholdAmount($data, $expected)
    {
        $this->scopeConfigMock->expects(static::any())
            ->method('getValue')
            ->with(
                $this->getPath(Config::KEY_THRESHOLD_AMOUNT),
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn($data);
        static::assertEquals($expected, $this->model->getThresholdAmount());
    }

    /**
     * Get items for testing threshold amount
     *
     * @return array
     */
    public static function thresholdAmountDataProvider(): array
    {
        return [
            ['data' => '23.01', 'expected' => 23.01],
            ['data' => -1.02, 'expected' => -1.02],
            ['data' => true, 'expected' => 1],
            ['data' => 'true', 'expected' => 0],
            ['data' => 'abc', 'expected' => 0],
            ['data' => false, 'expected' => 0],
            ['data' => 'false', 'expected' => 0],
            ['data' => 1, 'expected' => 1],
        ];
    }

    /**
     * @param $value
     * @param array $expected
     * @throws InputException
     * @throws NoSuchEntityException
     * @covers \PayPal\Braintree\Gateway\Config\Config::get3DSecureSpecificCountries
     * @dataProvider threeDSecureSpecificCountriesDataProvider
     */
    public function testGet3DSecureSpecificCountries($value, array $expected)
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnCallback(function ($path) use ($value) {
                if ($path === $this->getPath(Config::KEY_VERIFY_ALLOW_SPECIFIC)) {
                    return $value;
                } elseif ($path === $this->getPath(Config::KEY_VERIFY_SPECIFIC)) {
                    return 'GB,US';
                }
                return null; // Default case if needed
            });
        static::assertEquals($expected, $this->model->get3DSecureSpecificCountries());
    }

    /**
     * Get variations to test specific countries for 3d secure
     * @return array
     */
    public static function threeDSecureSpecificCountriesDataProvider(): array
    {
        return [
            ['configValue' => 0, 'expected' => []],
            ['configValue' => 1, 'expected' => ['GB', 'US']],
        ];
    }

    /**
     * @covers \PayPal\Braintree\Gateway\Config\Config::getDynamicDescriptors
     * @param $name
     * @param $phone
     * @param $url
     * @param array $expected
     * @dataProvider descriptorsDataProvider
     */
    public function testGetDynamicDescriptors($name, $phone, $url, array $expected)
    {
        $map = [
            [$this->getPath('descriptor_name'), ScopeInterface::SCOPE_STORE, null, $name],
            [$this->getPath('descriptor_phone'), ScopeInterface::SCOPE_STORE, null, $phone],
            [$this->getPath('descriptor_url'), ScopeInterface::SCOPE_STORE, null, $url]
        ];

        $this->scopeConfigMock->method('getValue')
            ->willReturnMap($map);

        $actual = $this->model->getDynamicDescriptors();
        static::assertEquals($expected, $actual);
    }

    /**
     * Get variations to test dynamic descriptors
     * @return array
     */
    public static function descriptorsDataProvider(): array
    {
        $name = 'company * product';
        $phone = '333-22-22-333';
        $url = 'https://test.url.mage.com';
        return [
            [
                $name, $phone, $url,
                'expected' => [
                    'name' => $name, 'phone' => $phone, 'url' => $url
                ]
            ],
            [
                $name, null, null,
                'expected' => [
                    'name' => $name
                ]
            ],
            [
                null, null, $url,
                'expected' => [
                    'url' => $url
                ]
            ],
            [
                null, null, null,
                'expected' => []
            ]
        ];
    }

    /**
     * Return config path
     *
     * @param string $field
     * @return string
     */
    private function getPath(string $field): string
    {
        return sprintf(PaymentConfig::DEFAULT_PATH_PATTERN, self::METHOD_CODE, $field);
    }
}

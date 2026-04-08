<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Test\Unit\Gateway\Command;

use Braintree\IsNode;
use Braintree\MultipleValueNode;
use Braintree\ResourceCollection;
use Braintree\TextNode;
use Braintree\Transaction;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use PayPal\Braintree\Gateway\Command\CaptureStrategyCommand;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\GatewayCommand;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Model\Adapter\BraintreeSearchAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[CoversClass(CaptureStrategyCommand::class)]
#[CoversFunction('execute')]
class CaptureStrategyCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CaptureStrategyCommand
     */
    private CaptureStrategyCommand $strategyCommand;

    /**
     * @var CommandPoolInterface|MockObject
     */
    private CommandPoolInterface|MockObject $commandPool;

    /**
     * @var TransactionRepositoryInterface|MockObject
     */
    private TransactionRepositoryInterface|MockObject $transactionRepository;

    /**
     * @var FilterBuilder|MockObject
     */
    private FilterBuilder|MockObject $filterBuilder;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private SearchCriteriaBuilder|MockObject $searchCriteriaBuilder;

    /**
     * @var Payment|MockObject
     */
    private Payment|MockObject $payment;

    /**
     * @var GatewayCommand|MockObject
     */
    private GatewayCommand|MockObject $command;

    /**
     * @var SubjectReader|MockObject
     */
    private SubjectReader|MockObject $subjectReaderMock;

    /**
     * @var BraintreeAdapter|MockObject
     */
    private BraintreeAdapter|MockObject $braintreeAdapter;

    /**
     * @var BraintreeSearchAdapter
     */
    private BraintreeSearchAdapter $braintreeSearchAdapter;

    protected function setUp(): void
    {
        $this->commandPool = $this->getMockBuilder(CommandPoolInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->addMethods(['__wakeup'])
            ->getMockForAbstractClass();

        $this->subjectReaderMock = $this->getMockBuilder(SubjectReader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->initCommandMock();
        $this->initTransactionRepositoryMock();
        $this->initFilterBuilderMock();
        $this->initSearchCriteriaBuilderMock();

        $this->braintreeAdapter = $this->getMockBuilder(BraintreeAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->braintreeSearchAdapter = new BraintreeSearchAdapter();

        $this->strategyCommand = new CaptureStrategyCommand(
            $this->commandPool,
            $this->transactionRepository,
            $this->filterBuilder,
            $this->searchCriteriaBuilder,
            $this->subjectReaderMock,
            $this->braintreeAdapter,
            $this->braintreeSearchAdapter
        );
    }

    public function testSaleExecute()
    {
        $paymentData = $this->getPaymentDataObjectMock();
        $subject['payment'] = $paymentData;
        $subject['amount'] = 100;

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($paymentData);

        $this->payment->expects(static::once())
            ->method('getAuthorizationTransaction')
            ->willReturn(false);

        $this->payment->expects(static::once())
            ->method('getId')
            ->willReturn(1);

        $this->buildSearchCriteria();

        $this->transactionRepository->expects(static::once())
            ->method('getTotalCount')
            ->willReturn(0);

        $this->commandPool->expects(static::once())
            ->method('get')
            ->with(CaptureStrategyCommand::SALE)
            ->willReturn($this->command);

        $this->strategyCommand->execute($subject);
    }

    /**
     * @throws NotFoundException
     * @throws CommandException
     */
    public function testCaptureExecute()
    {
        $paymentData = $this->getPaymentDataObjectMock();
        $subject['payment'] = $paymentData;
        $subject['amount'] = 100;
        $lastTransId = 'txnds';

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($paymentData);

        $this->payment->expects(static::once())
            ->method('getAuthorizationTransaction')
            ->willReturn(true);
        $this->payment->expects(static::once())
            ->method('getLastTransId')
            ->willReturn($lastTransId);

        $this->payment->expects(static::once())
            ->method('getId')
            ->willReturn(1);

        $this->buildSearchCriteria();

        $this->transactionRepository->expects(static::once())
            ->method('getTotalCount')
            ->willReturn(0);

        // authorization transaction was not expired
        $collection = $this->getNotExpiredExpectedCollection($lastTransId);
        $collection->expects(static::once())
            ->method('maximumCount')
            ->willReturn(0);

        $this->commandPool->expects(static::once())
            ->method('get')
            ->with(CaptureStrategyCommand::CAPTURE)
            ->willReturn($this->command);

        $this->strategyCommand->execute($subject);
    }

    /**
     * @param string $lastTransactionId
     * @return ResourceCollection|MockObject
     */
    private function getNotExpiredExpectedCollection(string $lastTransactionId): MockObject|ResourceCollection
    {
        $isExpectations = [
            'id' => ['is' => $lastTransactionId],
            'status' => [Transaction::AUTHORIZATION_EXPIRED]
        ];

        $collection = $this->getMockBuilder(ResourceCollection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->braintreeAdapter->expects(static::once())
            ->method('search')
            ->with(
                static::callback(
                    function (array $filters) use ($isExpectations) {
                        foreach ($filters as $filter) {
                            /** @var IsNode $filter */
                            if (!isset($isExpectations[$filter->name])) {
                                return false;
                            }

                            if ($isExpectations[$filter->name] !== $filter->toParam()) {
                                return false;
                            }
                        }

                        return true;
                    }
                )
            )
            ->willReturn($collection);

        return $collection;
    }

    /**
     * @throws NotFoundException
     * @throws CommandException
     */
    public function testExpiredAuthorizationPerformVaultCaptureExecute()
    {
        $paymentData = $this->getPaymentDataObjectMock();
        $subject['payment'] = $paymentData;
        $subject['amount'] = 100;
        $lastTransId = 'txnds';

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($paymentData);

        $this->payment->expects(static::once())
            ->method('getAuthorizationTransaction')
            ->willReturn(true);

        $this->payment->expects(static::once())
            ->method('getLastTransId')
            ->willReturn($lastTransId);

        $this->payment->expects(static::once())
            ->method('getId')
            ->willReturn(1);

        $this->buildSearchCriteria();

        $this->transactionRepository->expects(static::once())
            ->method('getTotalCount')
            ->willReturn(0);

        // authorization transaction was expired
        $collection = $this->getNotExpiredExpectedCollection($lastTransId);
        $collection->expects(static::once())
            ->method('maximumCount')
            ->willReturn(1);

        $this->commandPool->expects(static::once())
            ->method('get')
            ->with(CaptureStrategyCommand::VAULT_CAPTURE)
            ->willReturn($this->command);

        $this->strategyCommand->execute($subject);
    }

    /**
     * @throws NotFoundException
     * @throws CommandException
     */
    public function testVaultCaptureExecute()
    {
        $paymentData = $this->getPaymentDataObjectMock();
        $subject['payment'] = $paymentData;
        $subject['amount'] = 100;

        $this->subjectReaderMock->expects(self::once())
            ->method('readPayment')
            ->with($subject)
            ->willReturn($paymentData);

        $this->payment->expects(static::once())
            ->method('getId')
            ->willReturn(1);

        $this->buildSearchCriteria();

        $this->transactionRepository->expects(static::once())
            ->method('getTotalCount')
            ->willReturn(1);

        $this->commandPool->expects(static::once())
            ->method('get')
            ->with(CaptureStrategyCommand::VAULT_CAPTURE)
            ->willReturn($this->command);

        $this->strategyCommand->execute($subject);
    }

    /**
     * Create mock for payment data object and order payment
     *
     * @return MockObject
     */
    private function getPaymentDataObjectMock(): MockObject
    {
        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock = $this->getMockBuilder(PaymentDataObject::class)
            ->onlyMethods(['getPayment'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects(static::once())
            ->method('getPayment')
            ->willReturn($this->payment);

        return $mock;
    }

    /**
     * Create mock for gateway command object
     */
    private function initCommandMock(): void
    {
        $this->command = $this->getMockBuilder(GatewayCommand::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();

        $this->command->expects(static::once())
            ->method('execute')
            ->willReturn([]);
    }

    /**
     * Create mock for filter object
     *
     * @return void
     */
    private function initFilterBuilderMock(): void
    {
        $this->filterBuilder = $this->getMockBuilder(FilterBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setField', 'setValue', 'create'])
            ->addMethods(['__wakeup'])
            ->getMock();
    }

    /**
     * Build search criteria
     *
     * @return void
     */
    private function buildSearchCriteria(): void
    {
        $this->filterBuilder->expects(static::exactly(2))
            ->method('setField')
            ->willReturnSelf();
        $this->filterBuilder->expects(static::exactly(2))
            ->method('setValue')
            ->willReturnSelf();

        $searchCriteria = new SearchCriteria();
        $this->searchCriteriaBuilder->expects(static::exactly(2))
            ->method('addFilters')
            ->willReturnSelf();
        $this->searchCriteriaBuilder->expects(static::once())
            ->method('create')
            ->willReturn($searchCriteria);

        $this->transactionRepository->expects(static::once())
            ->method('getList')
            ->with($searchCriteria)
            ->willReturnSelf();
    }

    /**
     * Create mock for search criteria object
     *
     * @return void
     */
    private function initSearchCriteriaBuilderMock(): void
    {
        $this->searchCriteriaBuilder = $this->getMockBuilder(SearchCriteriaBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFilters', 'create'])
            ->addMethods(['__wakeup'])
            ->getMock();
    }

    /**
     * Create mock for transaction repository
     *
     * @return void
     */
    private function initTransactionRepositoryMock(): void
    {
        $this->transactionRepository = $this->getMockBuilder(TransactionRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getList', 'delete', 'get', 'save', 'create'])
            ->addMethods(['getTotalCount', '__wakeup'])
            ->getMockForAbstractClass();
    }
}

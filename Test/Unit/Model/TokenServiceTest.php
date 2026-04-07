<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Muon\MultiFactorLogin\Api\Data\TokenInterface;
use Muon\MultiFactorLogin\Api\RateLimitServiceInterface;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\ResourceModel\Token as TokenResource;
use Muon\MultiFactorLogin\Model\ResourceModel\Token\Collection;
use Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory;
use Muon\MultiFactorLogin\Model\Token;
use Muon\MultiFactorLogin\Model\TokenFactory;
use Muon\MultiFactorLogin\Model\TokenService;
use Muon\MultiFactorLogin\Service\EmailService;
use Muon\MultiFactorLogin\Service\SmsService;
use ArrayIterator;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Muon\MultiFactorLogin\Model\TokenService
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * Test class — high coupling is inherent to mocking all TokenService dependencies.
 */
class TokenServiceTest extends TestCase
{
    /**
     * @var \Muon\MultiFactorLogin\Model\Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private Config $config;

    /**
     * @var \Muon\MultiFactorLogin\Model\TokenFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private TokenFactory $tokenFactory;

    /**
     * @var \Muon\MultiFactorLogin\Model\ResourceModel\Token|\PHPUnit\Framework\MockObject\MockObject
     */
    private TokenResource $tokenResource;

    /**
     * @var \Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var \Muon\MultiFactorLogin\Api\RateLimitServiceInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private RateLimitServiceInterface $rateLimitService;

    /**
     * @var \Muon\MultiFactorLogin\Service\EmailService|\PHPUnit\Framework\MockObject\MockObject
     */
    private EmailService $emailService;

    /**
     * @var \Muon\MultiFactorLogin\Service\SmsService|\PHPUnit\Framework\MockObject\MockObject
     */
    private SmsService $smsService;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime|\PHPUnit\Framework\MockObject\MockObject
     */
    private DateTime $dateTime;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private AdapterInterface $connection;

    /**
     * @var \Muon\MultiFactorLogin\Model\TokenService
     */
    private TokenService $service;

    protected function setUp(): void
    {
        $this->config            = $this->createMock(Config::class);
        $this->tokenFactory      = $this->createMock(TokenFactory::class);
        $this->tokenResource     = $this->createMock(TokenResource::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->rateLimitService  = $this->createMock(RateLimitServiceInterface::class);
        $this->emailService      = $this->createMock(EmailService::class);
        $this->smsService        = $this->createMock(SmsService::class);
        $this->dateTime          = $this->createMock(DateTime::class);
        $this->connection        = $this->createMock(AdapterInterface::class);

        $this->tokenResource->method('getConnection')->willReturn($this->connection);
        $this->tokenResource->method('getMainTable')->willReturn('muon_mfa_token');

        $this->service = new TokenService(
            $this->config,
            $this->tokenFactory,
            $this->tokenResource,
            $this->collectionFactory,
            $this->rateLimitService,
            $this->emailService,
            $this->smsService,
            $this->dateTime,
        );
    }

    public function testCreateAndSendThrowsWhenRateLimited(): void
    {
        $this->rateLimitService->method('isRequestAllowed')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Too many verification code requests');

        $this->service->createAndSend(1, TokenInterface::METHOD_EMAIL);
    }

    public function testCreateAndSendDispatchesEmailForEmailMethod(): void
    {
        $this->rateLimitService->method('isRequestAllowed')->willReturn(true);
        $this->config->method('getTokenLength')->willReturn(6);
        $this->config->method('getTokenCharacters')->willReturn('0123456789');
        $this->config->method('getTokenLifetime')->willReturn(10);

        $this->dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $this->connection->method('update');

        $emptyCollection = $this->createMock(Collection::class);
        $emptyCollection->method('addFieldToFilter')->willReturnSelf();
        $emptyCollection->method('setOrder')->willReturnSelf();
        $emptyCollection->method('setPageSize')->willReturnSelf();

        $token = $this->createMock(Token::class);
        $token->method('getTokenId')->willReturn(null);
        $emptyCollection->method('getFirstItem')->willReturn($token);

        $this->collectionFactory->method('create')->willReturn($emptyCollection);
        $this->tokenFactory->method('create')->willReturn($this->createMock(Token::class));
        $this->tokenResource->expects($this->once())->method('save');
        $this->emailService->expects($this->once())->method('send');
        $this->smsService->expects($this->never())->method('send');

        $this->service->createAndSend(1, TokenInterface::METHOD_EMAIL);
    }

    public function testCreateAndSendDispatchesSmsForSmsMethod(): void
    {
        $this->rateLimitService->method('isRequestAllowed')->willReturn(true);
        $this->config->method('getTokenLength')->willReturn(6);
        $this->config->method('getTokenCharacters')->willReturn('0123456789');
        $this->config->method('getTokenLifetime')->willReturn(10);

        $this->dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $this->connection->method('update');

        $emptyCollection = $this->createMock(Collection::class);
        $emptyCollection->method('addFieldToFilter')->willReturnSelf();
        $emptyCollection->method('setOrder')->willReturnSelf();
        $emptyCollection->method('setPageSize')->willReturnSelf();

        $token = $this->createMock(Token::class);
        $token->method('getTokenId')->willReturn(null);
        $emptyCollection->method('getFirstItem')->willReturn($token);

        $this->collectionFactory->method('create')->willReturn($emptyCollection);
        $this->tokenFactory->method('create')->willReturn($this->createMock(Token::class));
        $this->tokenResource->expects($this->once())->method('save');
        $this->smsService->expects($this->once())->method('send');
        $this->emailService->expects($this->never())->method('send');

        $this->service->createAndSend(1, TokenInterface::METHOD_SMS);
    }

    public function testVerifyReturnsTrueForCorrectToken(): void
    {
        $rawToken = '123456';

        $token = $this->createMock(Token::class);
        $token->method('getTokenId')->willReturn(42);
        $token->method('getExpiresAt')->willReturn('2026-12-31 23:59:59');
        $token->method('getVerifyAttempts')->willReturn(0);
        $token->method('getToken')->willReturn(hash('sha256', $rawToken));

        $this->dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $this->config->method('getMaxVerifyAttempts')->willReturn(5);

        $token->expects($this->once())->method('setIsUsed')->with(true);
        $this->tokenResource->expects($this->once())->method('save');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($token);
        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->service->verify(1, $rawToken);

        $this->assertTrue($result);
    }

    public function testVerifyReturnsFalseAndIncrementsAttemptsForWrongToken(): void
    {
        $token = $this->createMock(Token::class);
        $token->method('getTokenId')->willReturn(42);
        $token->method('getExpiresAt')->willReturn('2026-12-31 23:59:59');
        $token->method('getVerifyAttempts')->willReturn(1);
        $token->method('getToken')->willReturn(hash('sha256', 'correct'));

        $this->dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $this->config->method('getMaxVerifyAttempts')->willReturn(5);

        $token->expects($this->once())->method('setVerifyAttempts')->with(2);
        $this->tokenResource->expects($this->once())->method('save');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($token);
        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->service->verify(1, 'wrong');

        $this->assertFalse($result);
    }

    public function testVerifyThrowsWhenNoActiveToken(): void
    {
        $token = $this->createMock(Token::class);
        $token->method('getTokenId')->willReturn(null);

        $this->dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($token);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No active verification code found');

        $this->service->verify(1, '123456');
    }

    public function testVerifyThrowsAndInvalidatesTokenWhenMaxAttemptsReached(): void
    {
        $token = $this->createMock(Token::class);
        $token->method('getTokenId')->willReturn(42);
        $token->method('getExpiresAt')->willReturn('2026-12-31 23:59:59');
        $token->method('getVerifyAttempts')->willReturn(5);

        $this->dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $this->config->method('getMaxVerifyAttempts')->willReturn(5);

        $token->expects($this->once())->method('setIsUsed')->with(true);
        $this->tokenResource->expects($this->once())->method('save');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($token);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Too many incorrect attempts');

        $this->service->verify(1, '123456');
    }
}

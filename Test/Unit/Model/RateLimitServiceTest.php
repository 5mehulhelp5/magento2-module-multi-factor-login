<?php

declare(strict_types=1);

namespace Muon\MultiFactorLogin\Test\Unit\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Muon\MultiFactorLogin\Model\Config;
use Muon\MultiFactorLogin\Model\RateLimitService;
use Muon\MultiFactorLogin\Model\ResourceModel\Token\Collection;
use Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Muon\MultiFactorLogin\Model\RateLimitService
 */
class RateLimitServiceTest extends TestCase
{
    /**
     * @var \Muon\MultiFactorLogin\Model\Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private Config $config;

    /**
     * @var \Muon\MultiFactorLogin\Model\ResourceModel\Token\CollectionFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime|\PHPUnit\Framework\MockObject\MockObject
     */
    private DateTime $dateTime;

    /**
     * @var \Muon\MultiFactorLogin\Model\RateLimitService
     */
    private RateLimitService $service;

    protected function setUp(): void
    {
        $this->config            = $this->createMock(Config::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->dateTime          = $this->createMock(DateTime::class);

        $this->service = new RateLimitService(
            $this->config,
            $this->collectionFactory,
            $this->dateTime,
        );
    }

    public function testIsRequestAllowedReturnsTrueWhenUnderLimit(): void
    {
        $this->config->method('getRateLimitWindowMinutes')->willReturn(60);
        $this->config->method('getMaxRequests')->willReturn(3);
        $this->dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(2);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertTrue($this->service->isRequestAllowed(1));
    }

    public function testIsRequestAllowedReturnsFalseWhenAtLimit(): void
    {
        $this->config->method('getRateLimitWindowMinutes')->willReturn(60);
        $this->config->method('getMaxRequests')->willReturn(3);
        $this->dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(3);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertFalse($this->service->isRequestAllowed(1));
    }
}

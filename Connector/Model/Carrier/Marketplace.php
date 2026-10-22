<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Mercur\Connector\Model\Shipping\MarketplaceItems;
use Psr\Log\LoggerInterface;

/**
 * The Magento shipping method for a cart that holds only seller lines. It
 * costs nothing here: what each seller charges is in the marketplace shipping total.
 */
class Marketplace extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'mercur';

    protected $_code = self::CODE;

    protected $_isFixed = true;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request): Result|bool
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        [$own, $marketplace] = MarketplaceItems::split($request->getAllItems() ?: []);
        if ($marketplace === [] || $own !== []) {
            return false;
        }

        $method = $this->rateMethodFactory->create();
        $method->setCarrier(self::CODE);
        $method->setCarrierTitle((string) $this->getConfigData('title'));
        $method->setMethod(self::CODE);
        $method->setMethodTitle((string) $this->getConfigData('name'));
        $method->setPrice(0);
        $method->setCost(0);

        $result = $this->rateResultFactory->create();
        $result->append($method);

        return $result;
    }

    public function getAllowedMethods(): array
    {
        return [self::CODE => (string) $this->getConfigData('name')];
    }
}

<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\PlaceOrder;
use Mercur\Connector\Model\Checkout\ShippingErrorGuard;

/**
 * A cart whose seller shipping could not be quoted must not reach payment:
 * the shopper would otherwise pay without the sellers' shipping charge.
 * Guest checkout goes through BlockGuestCheckoutOnShippingError.
 */
class BlockCheckoutOnShippingError
{
    public function __construct(private readonly ShippingErrorGuard $guard)
    {
    }

    /**
     * Totals are collected while the address is saved, so the quote is only inspected afterwards.
     */
    public function afterSaveAddressInformation(
        ShippingInformationManagement $subject,
        PaymentDetailsInterface $result,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        $this->guard->assertQuoteId((int) $cartId);

        return $result;
    }

    public function beforeSavePaymentInformationAndPlaceOrder(PaymentInformationManagement $subject, $cartId): void
    {
        $this->guard->assertQuoteId((int) $cartId);
    }

    public function beforeSavePaymentInformation(PaymentInformationManagement $subject, $cartId): void
    {
        $this->guard->assertQuoteId((int) $cartId);
    }

    public function beforeResolve(PlaceOrder $subject, Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): void
    {
        $cartId = (string) ($args['input']['cart_id'] ?? '');
        if ($cartId !== '') {
            $this->guard->assertMaskedId($cartId);
        }
    }
}

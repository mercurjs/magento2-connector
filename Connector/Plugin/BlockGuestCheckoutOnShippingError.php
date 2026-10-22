<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin;

use Magento\Checkout\Model\GuestPaymentInformationManagement;
use Mercur\Connector\Model\Checkout\ShippingErrorGuard;

class BlockGuestCheckoutOnShippingError
{
    public function __construct(private readonly ShippingErrorGuard $guard)
    {
    }

    public function beforeSavePaymentInformationAndPlaceOrder(GuestPaymentInformationManagement $subject, $cartId): void
    {
        $this->guard->assertMaskedId((string) $cartId);
    }

    public function beforeSavePaymentInformation(GuestPaymentInformationManagement $subject, $cartId): void
    {
        $this->guard->assertMaskedId((string) $cartId);
    }
}

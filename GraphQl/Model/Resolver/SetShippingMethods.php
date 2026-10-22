<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Mercur\Connector\Model\Checkout\ShadowCart;

class SetShippingMethods implements ResolverInterface
{
    public function __construct(
        private readonly GetCartForUser $getCartForUser,
        private readonly ShadowCart $shadowCart,
        private readonly CartRepositoryInterface $quotes
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $input = $args['input'] ?? [];
        if (empty($input['cart_id']) || empty($input['shipping_methods'])) {
            throw new GraphQlInputException(__('cart_id and shipping_methods are required.'));
        }

        $store = $context->getExtensionAttributes()->getStore();
        $quote = $this->getCartForUser->execute((string) $input['cart_id'], $context->getUserId(), (int) $store->getId());

        $offered = $this->shadowCart->shippingOptions($quote);
        foreach ($input['shipping_methods'] as $method) {
            $sellerId = (string) $method['seller_id'];
            $optionId = (string) $method['option_id'];
            $ids = array_map(static fn ($option) => (string) $option['id'], $offered[$sellerId] ?? []);
            if (!in_array($optionId, $ids, true)) {
                throw new GraphQlInputException(__('Shipping option %1 is not offered by seller %2.', $optionId, $sellerId));
            }
            $this->shadowCart->chooseShippingMethod($quote, $sellerId, $optionId);
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->quotes->save($quote);

        return ['cart' => ['model' => $quote]];
    }
}

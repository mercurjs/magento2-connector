<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Taxonomy;

use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeManagementInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Mercur\Catalog\Model\CatalogConfig;

/**
 * Creates the Magento attribute behind a Mercur variant axis, so the variants
 * of a seller product can be grouped under a configurable.
 */
class AttributePuller
{
    public function __construct(
        private readonly ProductAttributeInterfaceFactory $attributeFactory,
        private readonly AttributeOptionInterfaceFactory $optionFactory,
        private readonly ProductAttributeRepositoryInterface $attributes,
        private readonly ProductAttributeManagementInterface $management,
        private readonly AttributeGroup $group,
        private readonly CatalogConfig $config
    ) {
    }

    /**
     * @param array $axis one entry of a Mercur product's `attributes`
     */
    public function pull(array $axis): ProductAttributeInterface
    {
        $code = (string) $axis['handle'];

        $options = [];
        foreach ($axis['all_values'] ?? [] as $rank => $value) {
            $option = $this->optionFactory->create();
            $option->setLabel((string) $value['name']);
            $option->setSortOrder((int) ($value['rank'] ?? $rank));
            $options[] = $option;
        }

        $attribute = $this->attributeFactory->create();
        $attribute->setAttributeCode($code);
        $attribute->setDefaultFrontendLabel((string) ($axis['name'] ?? $code));
        // A configurable axis has to be a dropdown, although Mercur types its axes as multi_select.
        $attribute->setFrontendInput('select');
        $attribute->setScope(EavAttributeInterface::SCOPE_GLOBAL_TEXT);
        $attribute->setIsVisible(true);
        $attribute->setIsUserDefined(true);
        $attribute->setIsRequired(false);
        $attribute->setOptions($options);

        $saved = $this->attributes->save($attribute);
        $this->management->assign($this->config->getAttributeSetId(), $this->group->ensure(), $code, 0);

        return $saved;
    }
}

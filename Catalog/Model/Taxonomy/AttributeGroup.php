<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Taxonomy;

use Magento\Catalog\Api\ProductAttributeGroupRepositoryInterface;
use Magento\Eav\Api\Data\AttributeGroupInterfaceFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;
use Mercur\Catalog\Model\CatalogConfig;

/**
 * The attribute group, in the configured attribute set, that holds the
 * marketplace attributes.
 */
class AttributeGroup
{
    public function __construct(
        private readonly GroupCollectionFactory $groups,
        private readonly AttributeGroupInterfaceFactory $groupFactory,
        private readonly ProductAttributeGroupRepositoryInterface $groupRepository,
        private readonly CatalogConfig $config
    ) {
    }

    public function find(): ?int
    {
        $group = $this->groups->create()
            ->addFieldToFilter('attribute_set_id', $this->config->getAttributeSetId())
            ->addFieldToFilter('attribute_group_name', $this->config->getAttributeGroup())
            ->getFirstItem();

        return $group->getId() ? (int) $group->getId() : null;
    }

    public function ensure(): int
    {
        $id = $this->find();
        if ($id !== null) {
            return $id;
        }

        $group = $this->groupFactory->create();
        $group->setAttributeSetId($this->config->getAttributeSetId());
        $group->setAttributeGroupName($this->config->getAttributeGroup());

        return (int) $this->groupRepository->save($group)->getAttributeGroupId();
    }
}

<?php

declare(strict_types=1);

namespace Mercur\Catalog\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddMercurProductAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $setup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eav = $this->eavSetupFactory->create(['setup' => $this->setup]);

        $eav->addAttribute(Product::ENTITY, 'mercur_product', [
            'type' => 'int',
            'label' => 'Imported from Mercur',
            'input' => 'boolean',
            'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'default' => 0,
            'group' => 'Marketplace',
            'used_in_product_listing' => true,
        ]);
        $eav->addAttribute(Product::ENTITY, 'mercur_product_id', [
            'type' => 'varchar',
            'label' => 'Mercur product ID',
            'input' => 'text',
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'group' => 'Marketplace',
            'used_in_product_listing' => true,
        ]);
        $eav->addAttribute(Product::ENTITY, 'mercur_variant_id', [
            'type' => 'varchar',
            'label' => 'Mercur variant ID',
            'input' => 'text',
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'group' => 'Marketplace',
        ]);
        $eav->addAttribute(Product::ENTITY, 'mercur_thumbnail', [
            'type' => 'varchar',
            'label' => 'Mercur thumbnail URL',
            'input' => 'text',
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'group' => 'Marketplace',
        ]);
        $eav->addAttribute(Product::ENTITY, 'mercur_scoped_attributes', [
            'type' => 'text',
            'label' => 'Seller attributes',
            'input' => 'textarea',
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'group' => 'Marketplace',
            'used_in_product_listing' => true,
        ]);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}

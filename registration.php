<?php

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

$modules = [
    'Mercur_Core' => 'Core',
    'Mercur_Process' => 'Process',
    'Mercur_Connector' => 'Connector',
    'Mercur_OfferIndexer' => 'OfferIndexer',
    'Mercur_Catalog' => 'Catalog',
    'Mercur_Settlement' => 'Settlement',
    'Mercur_Frontend' => 'Frontend',
    'Mercur_GraphQl' => 'GraphQl',
];

foreach ($modules as $name => $dir) {
    ComponentRegistrar::register(ComponentRegistrar::MODULE, $name, __DIR__ . '/' . $dir);
}

<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Stock;

use Magento\Framework\DB\Select;
use Mercur\Connector\Model\ResourceModel\Offer\IndexedSkus;
use Mercur\Core\Model\Config;
use Mercur\Core\Model\Scope\WebsiteScope;

/**
 * The `OR e.sku IN (...)` tail the stock plugins append so a product the
 * operator has run out of stays in listings while a seller offers it.
 */
class OfferedSkuFilter
{
    public function __construct(
        private readonly IndexedSkus $indexedSkus,
        private readonly WebsiteScope $websites,
        private readonly Config $config
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function offeredSkus(): Select
    {
        return $this->indexedSkus->select($this->websites->currentWebsiteId());
    }

    /**
     * Rewrites the join named $alias to a LEFT join and ORs the offered SKUs
     * into every WHERE part that mentions the alias. Used where the join and
     * filter are built by code the plugin cannot reproduce (MSI).
     */
    public function widen(Select $select, string $alias, string $entityAlias = 'e'): void
    {
        $from = $select->getPart(Select::FROM);
        if (isset($from[$alias]) && $from[$alias]['joinType'] === Select::INNER_JOIN) {
            $from[$alias]['joinType'] = Select::LEFT_JOIN;
            $select->setPart(Select::FROM, $from);
        }

        $where = $select->getPart(Select::WHERE);
        $changed = false;
        foreach ($where as $i => $part) {
            if (!is_string($part) || !str_contains($part, $alias . '.')) {
                continue;
            }
            $prefix = '';
            $condition = $part;
            foreach ([Select::SQL_AND . ' ', Select::SQL_OR . ' '] as $keyword) {
                if (str_starts_with($part, $keyword)) {
                    $prefix = $keyword;
                    $condition = substr($part, strlen($keyword));
                    break;
                }
            }
            $where[$i] = $prefix . '(' . $condition . ' OR ' . $this->skuCondition($select, $entityAlias) . ')';
            $changed = true;
        }
        if ($changed) {
            $select->setPart(Select::WHERE, $where);
        }
    }

    public function skuCondition(Select $select, string $entityAlias = 'e'): string
    {
        return $select->getConnection()->quoteInto($entityAlias . '.sku IN ?', $this->offeredSkus());
    }
}

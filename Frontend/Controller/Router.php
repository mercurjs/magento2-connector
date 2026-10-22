<?php

declare(strict_types=1);

namespace Mercur\Frontend\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Mercur\Connector\Model\SellerUrl;

/**
 * Serves /seller/{handle} as mercur/seller/view.
 */
class Router implements RouterInterface
{
    public function __construct(private readonly ActionFactory $actionFactory)
    {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$request instanceof Http) {
            return null;
        }
        $path = trim((string) $request->getPathInfo(), '/');
        if (!preg_match('#^' . SellerUrl::PATH_PREFIX . '/([^/]+)$#', $path, $match)) {
            return null;
        }

        $request->setModuleName('mercur')
            ->setControllerName('seller')
            ->setActionName('view')
            ->setParam('handle', rawurldecode($match[1]));

        return $this->actionFactory->create(Forward::class);
    }
}

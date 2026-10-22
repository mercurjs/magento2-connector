<?php

declare(strict_types=1);

namespace Mercur\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestConnection extends Field
{
    protected $_template = 'Mercur_Core::system/config/test-connection.phtml';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('mercur/connection/test', [
            'website' => $this->getRequest()->getParam('website'),
            'store' => $this->getRequest()->getParam('store'),
        ]);
    }

    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setData(['id' => 'mercur_test_connection', 'label' => __('Test connection')]);

        return $button->toHtml();
    }
}

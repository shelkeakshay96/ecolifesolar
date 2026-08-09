<?php

declare(strict_types=1);

namespace EcoLife\Backend\Block\Adminhtml;

use EcoLife\Core\View\Element\AbstractBlock;

final class Login extends AbstractBlock
{
    public function getPostUrl(): string
    {
        return $this->getUrl('auth/login');
    }
}

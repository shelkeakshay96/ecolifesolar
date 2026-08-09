<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Controller\Index;

use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Gallery\Block\Grid;

final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        return $this->resultPage()
            ->setTitle('Recent installations | Eco Life')
            ->setMetaDescription('Rooftop solar systems installed by Eco Life across Satara district.')
            ->setBodyClass('page-gallery')
            ->setContent(Grid::class, 'EcoLife_Gallery::gallery.phtml');
    }
}

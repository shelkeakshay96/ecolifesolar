<?php

declare(strict_types=1);

namespace EcoLife\Calculator\Controller\Index;

use EcoLife\Calculator\Block\Calculator;
use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;

final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        return $this->resultPage()
            ->setTitle('Solar savings calculator | Eco Life')
            ->setMetaDescription(
                'Estimate what rooftop solar could save you each month in Satara, '
                . 'based on your electricity bill and roof type.'
            )
            ->setBodyClass('page-calculator')
            ->setContent(Calculator::class, 'EcoLife_Calculator::calculator.phtml');
    }
}

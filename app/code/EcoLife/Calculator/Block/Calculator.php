<?php

declare(strict_types=1);

namespace EcoLife\Calculator\Block;

use EcoLife\Calculator\Model\Estimator;
use EcoLife\Core\View\Element\AbstractBlock;

final class Calculator extends AbstractBlock
{
    private Estimator $estimator;

    protected function construct(): void
    {
        $this->estimator = new Estimator();
    }

    public function getEstimateUrl(): string
    {
        return $this->getFrontendUrl('calculator/estimate');
    }

    /** @return array<string, string> */
    public function getRoofTypes(): array
    {
        return $this->estimator->getRoofTypes();
    }

    /** @return array<string, string> */
    public function getSystemTypes(): array
    {
        return $this->estimator->getSystemTypes();
    }

    public function getDisclaimer(): string
    {
        return $this->estimator->getDisclaimer();
    }

    /**
     * Rendered on first paint so the page is useful before any JavaScript runs
     * and before the visitor has touched anything.
     *
     * @return array<string, mixed>
     */
    public function getInitialEstimate(): array
    {
        return $this->estimator->estimate(3000.0, 'rcc', 'panels');
    }
}

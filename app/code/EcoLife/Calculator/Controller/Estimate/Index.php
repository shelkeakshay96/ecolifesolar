<?php

declare(strict_types=1);

namespace EcoLife\Calculator\Controller\Estimate;

use EcoLife\Calculator\Model\Estimator;
use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;

/**
 * JSON endpoint behind the calculator.
 *
 * Accepts GET as well as POST: this reads nothing and changes nothing, so
 * requiring a form key would only mean the calculator stops working for anyone
 * whose session expired while they were reading the page.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $request   = $this->getRequest();
        $estimator = new Estimator();

        $bill = $request->getParam('monthly_bill', $request->getParam('bill', 0));

        if (!is_numeric($bill) || (float) $bill <= 0) {
            return $this->resultJson()->setError('Please enter your average monthly electricity bill.', [
                'monthly_bill' => 'Enter an amount in rupees.',
            ], 422);
        }

        $roofType = (string) $request->getParam('roof_type', 'rcc');
        if (!$estimator->isValidRoofType($roofType)) {
            $roofType = 'rcc';
        }

        $systemType = (string) $request->getParam('system_type', 'panels');
        if (!$estimator->isValidSystemType($systemType)) {
            $systemType = 'panels';
        }

        return $this->resultJson()->setData([
            'success'  => true,
            'estimate' => $estimator->estimate((float) $bill, $roofType, $systemType),
        ]);
    }
}

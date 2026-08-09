<?php

declare(strict_types=1);

namespace EcoLife\Calculator\Model;

/**
 * The savings estimate, computed server-side.
 *
 * Every input is clamped rather than rejected: someone typing 9999999 into the
 * bill field should see a sensible number at the top of the range, not an
 * error message that ends their interest in the page.
 */
final class Estimator
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__) . '/etc/calculator.php';
    }

    /**
     * @return array{
     *     monthly_saving: float, annual_saving: float, system_size_kw: float,
     *     system_cost: float, payback_years: float, twenty_five_year_saving: float,
     *     roof_type: string, system_type: string, monthly_bill: float, disclaimer: string
     * }
     */
    public function estimate(float $monthlyBill, string $roofType, string $systemType): array
    {
        $limits = $this->config['limits'];
        $sizing = $this->config['sizing'];

        $bill = max((float) $limits['min_bill'], min((float) $limits['max_bill'], $monthlyBill));

        $roofFactor = $this->config['roof_factor'][$roofType] ?? $this->config['roof_factor']['other'];
        $isHeater   = $systemType === 'water_heater';

        $offset = $isHeater ? $this->config['offset']['water_heater'] : $this->config['offset']['panels'];

        $monthlySaving = $bill * $offset * $roofFactor;
        $annualSaving  = $monthlySaving * 12;

        $sizeKw = max(
            (float) $sizing['min_kw'],
            min(
                (float) $sizing['max_kw'],
                (float) round(
                    ($bill / 1000) * $sizing['kw_per_1000_rupees'] * $roofFactor
                    * ($isHeater ? $sizing['water_heater_factor'] : 1)
                )
            )
        );

        $systemCost = $isHeater
            ? $this->config['water_heater_cost'] * $roofFactor
            : $sizeKw * $this->config['cost_per_kw'] * $roofFactor;

        // Guard the division: a zero annual saving is only reachable with an
        // absurd configuration, but a division by zero here would be an
        // exception on a marketing page.
        $payback = $annualSaving > 0 ? $systemCost / $annualSaving : 0.0;

        return [
            'monthly_bill'            => round($bill, 2),
            'monthly_saving'          => round($monthlySaving, 2),
            'annual_saving'           => round($annualSaving, 2),
            'system_size_kw'          => $sizeKw,
            'system_cost'             => round($systemCost, 2),
            'payback_years'           => round($payback, 1),
            'twenty_five_year_saving' => round(($annualSaving * 25) - $systemCost, 2),
            'roof_type'               => $roofType,
            'system_type'             => $systemType,
            'disclaimer'              => $this->config['disclaimer'],
        ];
    }

    /** @return array<string, string> */
    public function getRoofTypes(): array
    {
        return [
            'rcc'         => 'RCC / concrete',
            'metal_sheet' => 'Metal sheet',
            'tiled'       => 'Tiled',
            'other'       => 'Other',
        ];
    }

    /** @return array<string, string> */
    public function getSystemTypes(): array
    {
        return [
            'panels'       => 'Solar panels',
            'water_heater' => 'Solar water heater',
        ];
    }

    public function isValidRoofType(string $roofType): bool
    {
        return array_key_exists($roofType, $this->config['roof_factor']);
    }

    public function isValidSystemType(string $systemType): bool
    {
        return array_key_exists($systemType, $this->getSystemTypes());
    }

    public function getDisclaimer(): string
    {
        return $this->config['disclaimer'];
    }
}

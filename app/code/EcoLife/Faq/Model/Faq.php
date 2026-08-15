<?php

declare(strict_types=1);

namespace EcoLife\Faq\Model;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Faq\Model\ResourceModel\Faq as FaqResource;

final class Faq extends AbstractModel
{
    /**
     * Tokens an admin may type into an answer, and what they mean.
     *
     * Surfaced on the admin form from this constant so the help text and the
     * substitution cannot describe different things.
     */
    public const TOKENS = [
        '{cost_per_kw}' => 'Installed cost per kW, from the calculator (e.g. 55,000)',
        '{cost_3kw}'    => 'Cost of a 3 kW system, from the calculator (e.g. 1,65,000)',
    ];

    protected string $idFieldName = 'faq_id';

    public function getResource(): AbstractResource
    {
        return $this->resource ??= new FaqResource();
    }

    public function getQuestion(): string
    {
        return (string) $this->getData('question');
    }

    /**
     * The answer, with price tokens replaced by live figures.
     *
     * The substitution is the reason those figures are not simply typed into
     * the text. The same price appears to a visitor twice -- as prose here and
     * as the calculator's output -- and a site quoting two different prices for
     * the same thing has a credibility problem no amount of SEO repairs. Storing
     * a token means correcting the rate in one config file corrects both.
     *
     * An answer with no token is returned untouched, so this costs nothing for
     * the questions that never mention money.
     */
    public function getAnswer(): string
    {
        $answer = (string) $this->getData('answer');

        if (!str_contains($answer, '{')) {
            return $answer;
        }

        $cost = self::costPerKw();

        return strtr($answer, [
            '{cost_per_kw}' => self::money($cost),
            '{cost_3kw}'    => self::money($cost * 3),
        ]);
    }

    /** The answer exactly as stored, tokens intact, for the admin edit form. */
    public function getRawAnswer(): string
    {
        return (string) $this->getData('answer');
    }

    public function getLinkUrl(): string
    {
        return (string) $this->getData('link_url', '');
    }

    public function getLinkLabel(): string
    {
        return (string) $this->getData('link_label', '');
    }

    /** A link is only rendered when both halves are present. */
    public function hasLink(): bool
    {
        return $this->getLinkUrl() !== '' && $this->getLinkLabel() !== '';
    }

    public function isActive(): bool
    {
        return (int) $this->getData('is_active') === 1;
    }

    /**
     * Installed cost per kW, read from the calculator's own configuration.
     *
     * The require is guarded rather than declared as a module dependency:
     * EcoLife_Faq sequences after Core, Theme and Backend, and a missing or
     * disabled Calculator should cost a fallback figure rather than the page.
     * The fallback matches the committed value in that file.
     */
    private static function costPerKw(): int
    {
        static $cost = null;

        if ($cost !== null) {
            return $cost;
        }

        $file = BP . '/app/code/EcoLife/Calculator/etc/calculator.php';

        if (is_file($file)) {
            $config = require $file;

            if (is_array($config) && isset($config['cost_per_kw']) && is_numeric($config['cost_per_kw'])) {
                return $cost = (int) $config['cost_per_kw'];
            }
        }

        return $cost = 55000;
    }

    /**
     * Indian digit grouping: 1,65,000 rather than 165,000.
     *
     * The same rule AbstractBlock::formatCurrency applies. It is repeated here
     * rather than reached for because a model has no block to call, and four
     * lines of string handling is a better trade than giving this class a view
     * dependency purely to place two commas.
     */
    private static function money(int $amount): string
    {
        $digits = (string) $amount;

        if (strlen($digits) <= 3) {
            return $digits;
        }

        $last  = substr($digits, -3);
        $rest  = substr($digits, 0, -3);
        $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;

        return $rest . ',' . $last;
    }
}

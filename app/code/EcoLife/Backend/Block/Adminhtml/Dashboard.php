<?php

declare(strict_types=1);

namespace EcoLife\Backend\Block\Adminhtml;

use EcoLife\Backend\Model\Auth;
use EcoLife\Backend\Model\AdminUser;
use EcoLife\Core\Model\Db;
use EcoLife\Core\Model\Settings;
use EcoLife\Core\View\Element\AbstractBlock;
use Throwable;

/**
 * Dashboard view model.
 *
 * Counts are read with plain aggregate queries rather than through collections:
 * loading four hundred lead models to count them would be the wrong shape, and
 * these numbers are the first thing on the screen.
 */
final class Dashboard extends AbstractBlock
{
    public function getCurrentUser(): ?AdminUser
    {
        return (new Auth($this->context))->getUser();
    }

    /** @return array{total: int, new: int, week: int, converted: int} */
    public function getLeadCounts(): array
    {
        $empty = ['total' => 0, 'new' => 0, 'week' => 0, 'converted' => 0];

        try {
            $pdo   = Db::instance();
            $table = Db::quoteIdentifier('lead');

            return [
                'total'     => (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(),
                'new'       => (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'new'")->fetchColumn(),
                'week'      => (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn(),
                'converted' => (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'converted'")->fetchColumn(),
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    /** @return list<array<string, mixed>> */
    public function getRecentLeads(int $limit = 8): array
    {
        try {
            $table     = Db::quoteIdentifier('lead');
            $statement = Db::instance()->prepare(
                "SELECT lead_id, name, phone, city, lead_type, status, created_at
                 FROM {$table} ORDER BY created_at DESC LIMIT ?"
            );
            $statement->bindValue(1, $limit, \PDO::PARAM_INT);
            $statement->execute();

            return $statement->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /** Tailwind classes for a lead status pill. */
    public function getStatusClass(string $status): string
    {
        return match ($status) {
            'new'            => 'bg-brand-50 text-brand-600',
            'converted'      => 'bg-emerald-50 text-emerald-700',
            'not_interested',
            'junk'           => 'bg-slate-100 text-slate-500',
            default          => 'bg-slate-100 text-slate-700',
        };
    }

    public function getStatusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    /**
     * Contact details still carrying the seeded PLACEHOLDER text. Surfaced on
     * the dashboard because these are visible on every page of the public site,
     * and nobody will find them buried in a settings screen.
     *
     * @return list<string>
     */
    public function getPlaceholderSettings(): array
    {
        $paths = [
            'general/contact/phone'    => 'Phone number',
            'general/contact/whatsapp' => 'WhatsApp number',
            'general/contact/email'    => 'Email address',
            'general/contact/address'  => 'Postal address',
            'general/business/gstin'   => 'GSTIN',
        ];

        $outstanding = [];
        foreach ($paths as $path => $label) {
            if (Settings::isPlaceholder($path)) {
                $outstanding[] = $label;
            }
        }

        return $outstanding;
    }
}

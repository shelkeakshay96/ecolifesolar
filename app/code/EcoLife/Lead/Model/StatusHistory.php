<?php

declare(strict_types=1);

namespace EcoLife\Lead\Model;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\Db;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Lead\Model\ResourceModel\StatusHistory as StatusHistoryResource;

/**
 * One recorded status change.
 *
 * from_status and to_status are VARCHAR rather than ENUM: history is a
 * permanent record and has to stay readable after the lead status ENUM gains or
 * loses a value.
 */
final class StatusHistory extends AbstractModel
{
    protected string $idFieldName = 'history_id';

    public function getResource(): AbstractResource
    {
        return $this->resource ??= new StatusHistoryResource();
    }

    public static function record(
        int $leadId,
        ?string $fromStatus,
        string $toStatus,
        ?int $adminUserId,
        ?string $comment = null
    ): void {
        (new self())->addData([
            'lead_id'       => $leadId,
            'admin_user_id' => $adminUserId,
            'from_status'   => $fromStatus,
            'to_status'     => $toStatus,
            'comment'       => $comment,
        ])->save();
    }

    public function getFromLabel(): string
    {
        $from = (string) $this->getData('from_status', '');
        return $from === '' ? '—' : (Lead::STATUSES[$from] ?? $from);
    }

    public function getToLabel(): string
    {
        $to = (string) $this->getData('to_status', '');
        return Lead::STATUSES[$to] ?? $to;
    }

    /** Username of whoever made the change, or "system" for the initial row. */
    public function getActorName(): string
    {
        $userId = $this->getData('admin_user_id');

        if ($userId === null) {
            return 'system';
        }

        $statement = Db::instance()->prepare('SELECT username FROM admin_user WHERE user_id = ?');
        $statement->execute([$userId]);

        return (string) ($statement->fetchColumn() ?: 'deleted user');
    }
}

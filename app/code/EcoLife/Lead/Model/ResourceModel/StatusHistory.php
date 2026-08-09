<?php

declare(strict_types=1);

namespace EcoLife\Lead\Model\ResourceModel;

use EcoLife\Core\Model\ResourceModel\AbstractResource;

final class StatusHistory extends AbstractResource
{
    protected string $table = 'lead_status_history';
    protected string $idFieldName = 'history_id';

    protected array $fields = [
        'history_id', 'lead_id', 'admin_user_id',
        'from_status', 'to_status', 'comment', 'created_at',
    ];
}

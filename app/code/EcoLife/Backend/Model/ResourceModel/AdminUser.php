<?php

declare(strict_types=1);

namespace EcoLife\Backend\Model\ResourceModel;

use EcoLife\Core\Model\ResourceModel\AbstractResource;

final class AdminUser extends AbstractResource
{
    protected string $table = 'admin_user';
    protected string $idFieldName = 'user_id';

    protected array $fields = [
        'user_id', 'username', 'email', 'password_hash', 'first_name', 'last_name',
        'role', 'is_active', 'failures_num', 'first_failure', 'lock_expires',
        'last_login_at', 'last_login_ip', 'created_at', 'updated_at',
    ];
}

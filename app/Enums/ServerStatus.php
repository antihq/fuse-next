<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Provisioned = 'provisioned';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Provisioning => 'Provisioning',
            self::Provisioned => 'Provisioned',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Provisioning => 'amber',
            self::Provisioned => 'emerald',
            self::Failed => 'red',
        };
    }
}

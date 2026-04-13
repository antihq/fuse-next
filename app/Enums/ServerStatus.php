<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Provisioning = 'provisioning';
    case Provisioned = 'provisioned';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Connected => 'Connected',
            self::Provisioning => 'Provisioning',
            self::Provisioned => 'Provisioned',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Connected => 'blue',
            self::Provisioning => 'amber',
            self::Provisioned => 'emerald',
            self::Failed => 'red',
        };
    }
}

<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Pending = 'pending';
    case Deploying = 'deploying';
    case Deployed = 'deployed';
    case Failed = 'failed';
    case Deleting = 'deleting';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Deploying => 'Deploying',
            self::Deployed => 'Deployed',
            self::Failed => 'Failed',
            self::Deleting => 'Deleting',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Deploying => 'amber',
            self::Deployed => 'emerald',
            self::Failed => 'red',
            self::Deleting => 'rose',
        };
    }
}

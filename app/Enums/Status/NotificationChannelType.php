<?php

namespace App\Enums\Status;

enum NotificationChannelType: string
{
    case Mail = 'mail';
    case Webhook = 'webhook';
    case Telegram = 'telegram';
    case Discord = 'discord';
    case Slack = 'slack';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::Webhook => 'Webhook',
            self::Telegram => 'Telegram',
            self::Discord => 'Discord',
            self::Slack => 'Slack',
        };
    }

    public function implemented(): bool
    {
        return in_array($this, [self::Mail, self::Webhook], true);
    }
}

<?php

namespace App\Enums;

enum DestroyMode: string
{
    case OnRead = 'on_read';
    case AfterViews = 'after_views';
    case AtDate = 'at_date';
    case Never = 'never';

    public function viewLimit(?int $maxViews): ?int
    {
        return match ($this) {
            self::OnRead => 1,
            self::AfterViews => $maxViews,
            self::AtDate, self::Never => null,
        };
    }

    public function reasonWhenViewsExhausted(): ?DestroyReason
    {
        return match ($this) {
            self::OnRead => DestroyReason::ReadOnce,
            self::AfterViews => DestroyReason::ViewLimitReached,
            self::AtDate, self::Never => null,
        };
    }
}

<?php

namespace App\Filament\Resources\Master\UserResource\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case AGENT_JUNIOR = 'agent_junior';
    case AGENT_MID = 'agent_mid';
    case AGENT_SENIOR = 'agent_senior';

    case ACCOUNTANT_JUNIOR = 'accountant_junior';
    case ACCOUNTANT_MID = 'accountant_mid';
    case ACCOUNTANT_SENIOR = 'accountant_senior';

    case MANAGER_JUNIOR = 'manager_junior';
    case MANAGER_MID = 'manager_mid';
    case MANAGER_SENIOR = 'manager_senior';

    case PARTNER_JUNIOR = 'partner_junior';
    case PARTNER_MID = 'partner_mid';
    case PARTNER_SENIOR = 'partner_senior';

    case ADMIN_JUNIOR = 'admin_junior';
    case ADMIN_MID = 'admin_mid';
    case ADMIN_SENIOR = 'admin_senior';

    public function getLabel(): ?string
    {
        $agent = __('resources/user/strings.general.options.agent');
        $accountant = __('resources/user/strings.general.options.accountant');
        $manager = __('resources/user/strings.general.options.manager');
        $partner = __('resources/user/strings.general.options.partner');
        $admin = __('resources/user/strings.general.options.admin');

        return match ($this) {
            self::AGENT_JUNIOR => "⭐ {$agent}",
            self::AGENT_MID => "⭐⭐ {$agent}",
            self::AGENT_SENIOR => "⭐⭐⭐ {$agent}",

            self::ACCOUNTANT_JUNIOR => "⭐ {$accountant}",
            self::ACCOUNTANT_MID => "⭐⭐ {$accountant}",
            self::ACCOUNTANT_SENIOR => "⭐⭐⭐ {$accountant}",

            self::MANAGER_JUNIOR => "⭐ {$manager}",
            self::MANAGER_MID => "⭐⭐ {$manager}",
            self::MANAGER_SENIOR => "⭐⭐⭐ {$manager}",

            self::PARTNER_JUNIOR => "⭐ {$partner}",
            self::PARTNER_MID => "⭐⭐ {$partner}",
            self::PARTNER_SENIOR => "⭐⭐⭐ {$partner}",

            self::ADMIN_JUNIOR => "⭐ {$admin}",
            self::ADMIN_MID => "⭐⭐ {$admin}",
            self::ADMIN_SENIOR => "⭐⭐⭐ {$admin}",
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AGENT_JUNIOR, self::AGENT_MID, self::AGENT_SENIOR => 'primary',

            self::ACCOUNTANT_JUNIOR, self::ACCOUNTANT_MID, self::ACCOUNTANT_SENIOR => 'warning',

            self::MANAGER_JUNIOR, self::MANAGER_MID, self::MANAGER_SENIOR => 'success',

            self::PARTNER_JUNIOR, self::PARTNER_MID, self::PARTNER_SENIOR => 'info',

            self::ADMIN_JUNIOR, self::ADMIN_MID, self::ADMIN_SENIOR => 'danger',
        };
    }
}

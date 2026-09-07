<?php

namespace ThriveDesk\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the API key on file has authenticated against ThriveDesk.
 *
 * Owns the option so the outbound HTTP layer can record a rejection without
 * reaching into the admin screen that used to hold these accessors: nothing
 * under src/Services/ should have to load ThriveDesk\Admin to answer a portal
 * request.
 */
final class ConnectionState
{
    public const OPTION = 'td_helpdesk_verified';

    public static function verified(): bool
    {
        return (bool) get_option(self::OPTION, false);
    }

    public static function set(bool $verified): void
    {
        update_option(self::OPTION, $verified);
    }
}

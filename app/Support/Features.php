<?php

namespace App\Support;

/*
 * Optional extras that are not part of every client's plan: the mobile app (install on the phone, bottom bar, Scan page) and the camera scan buttons.
 *   PWA_ENABLED=false           switches them off for everybody.
 *   PWA_USERS=demo,aman         switches them on only for these usernames (comma separated). Everybody else does not see them.
 *   (PWA_USERS empty)           everybody who is signed in sees them.
 */
class Features
{
    /* For the signed-in user. */
    public static function mobile(): bool
    {
        if (!config('rentstock.pwa')) {
            return false;
        }
        $only = (array) config('rentstock.pwa_users', []);
        if (!$only) {
            return true;
        }
        $u = auth()->user();
        return $u && in_array(strtolower((string) $u->username), $only, true);
    }

    /* Before anybody has signed in (the login page): the install file is offered only when it is for everybody. */
    public static function mobileForAll(): bool
    {
        return (bool) config('rentstock.pwa') && !config('rentstock.pwa_users');
    }
}

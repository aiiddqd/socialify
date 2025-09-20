<?php

namespace Socialify;

use Exception;

defined('ABSPATH') || die();

abstract class AbstractProvider
{

    // Unique key of provider - used for saving settings and other things
    public static $key;

    /**
     * Redirect URI - after auth on provider side user will be redirected to this URI
     *
     * @var string
     */
    // protected static $redirect_uri = '';

    /**
     * The init
     */
    abstract public static function init(): void;

    public static function saveDataToUserMeta($user_id, $data)
    {
        try {
            $data = (array) $data;
            update_user_meta($user_id, 'socialify_'.static::getProviderKey(), $data);
            update_user_meta($user_id, 'socialify_telegram', $data);
            update_user_meta($user_id, 'socialify_telegram_id_'.$data['identifier'], strtotime('now'));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the logo URL
     */
    abstract public static function getLogoUrl(): string;
    abstract public static function getProviderKey(): string;
    abstract public static function getProviderName(): string;

    
    abstract public static function getUrlToConnect(): string;

    abstract public static function getAuthStartUrl(): string;
    abstract public static function is_enabled(): bool;
}

<?php 

namespace Socialify;

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
    abstract public static function init() : void;

    /**
     * Get the logo URL
     */
    abstract public static function getLogoUrl() : string;
    
    abstract public static function getAuthStartUrl() : string;
}
    
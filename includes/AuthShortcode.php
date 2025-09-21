<?php 

namespace Socialify;

defined('ABSPATH') || die();

AuthShortcode::init();

final class AuthShortcode
{

    //init
    public static function init()
    {
        add_shortcode('socialify_auth', [self::class, 'render']);
    }
    public static function render($args)
    {
        $user_id = get_current_user_id();
        if ($user_id) {
            return '';
        }

        $items = [];
        foreach (Plugin::get_providers() as $provider) {
            if ($provider::isEnabled()) {
                $items[$provider::$key] = [
                    'actionUrl' => $provider::getUrlToAuth(),
                    'logo_url' => $provider::getUrlToLogo(),
                    'name' => $provider::getProviderName(),
                    'key' => $provider::getProviderKey(),
                ];
            }
        }

        $items = apply_filters('socialify_auth_items', $items);

        if (empty($items)) {
            return '';
        }

        ob_start();
        include __DIR__.'/../templates/auth-actions.php';
        return ob_get_clean();
    }
}
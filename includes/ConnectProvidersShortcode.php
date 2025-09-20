<?php 

namespace Socialify;

defined('ABSPATH') || die();

ConnectProvidersShortcode::init();

final class ConnectProvidersShortcode
{

    //init
    public static function init()
    {
        add_shortcode('socialify_connect_providers', [self::class, 'render']);
    }
    public static function render($args)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return __('You must be logged in to connect providers.', 'socialify');
        }

        $items = [];
        foreach (Plugin::get_providers() as $provider) {
            if ($provider::is_enabled()) {
                $items[$provider::$key] = [
                    'url' => $provider::getAuthStartUrl(),
                    'logo_url' => $provider::getLogoUrl(),
                    'name' => $provider::getProviderName(),
                    'key' => $provider::getProviderKey(),
                ];
            }
        }

        if (empty($items)) {
            return '';
        }

        ob_start();
        include __DIR__.'/../templates/provider-connector.php';
        return ob_get_clean();
    }
}
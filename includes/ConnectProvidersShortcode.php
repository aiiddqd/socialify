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

        add_action('woocommerce_account_dashboard', [self::class, 'add_to_my_account_page_for_woocommerce'], 33);
    }

    public static function add_to_my_account_page_for_woocommerce()
    {   
        echo do_shortcode('[socialify_connect_providers]');
    }

    public static function render($args)
    {
        global $wp;
        $user_id = get_current_user_id();
        if (!$user_id) {
            return __('You must be logged in to connect providers.', 'socialify');
        }

        $nonce = wp_create_nonce('socialify_connect_providers');
        set_transient('socialify_connect_providers_nonce_'.$nonce, $user_id, 15 * MINUTE_IN_SECONDS);

        $items = [];
        foreach (Plugin::get_providers() as $provider) {
            if ($provider::isEnabled()) {
                $url = add_query_arg('nonce', $nonce, $provider::getUrlToConnect());
                $url = add_query_arg('_redirect_to', site_url($wp->request), $url);

                $meta = $provider::getProviderDataFromUserMeta($user_id);
                $isConnected = !empty($meta);
                $items[$provider::$key] = [
                    'url' => $url,
                    'logo_url' => $provider::getUrlToLogo(),
                    'name' => $provider::getProviderName(),
                    'key' => $provider::getProviderKey(),
                    'meta' => $provider::getProviderDataFromUserMeta($user_id),
                    'is_connected' => $isConnected,
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
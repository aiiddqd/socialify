<?php


// function htmxer_url($route)
// {
//     $route = sanitize_text_field($route);
//     return home_url(user_trailingslashit('htmxer/'.ltrim($route, '/')));
// }

// function htmxer_hook($route)
// {
//     return 'htmxer/ep/'.esc_url($route);
// }

namespace Socialify;

Endpoints::init();

class Endpoints
{
    public static function init()
    {
        add_action('init', [self::class, 'addEndpoint']);
        add_filter('query_vars', [self::class, 'addQueryVars']);
        add_action('wp', [self::class, 'handleEndpoint']);
    }

    public static function getUrl($path){
        return home_url(user_trailingslashit(Plugin::$slug) . $path);
    }

    public static function getHook($path){
        return "socialify/endpoint/$path";
    }

    public static function handleEndpoint($wp)
    {

        $path = get_query_var(Plugin::$slug, false);
        if ($path === false) {
            return;
        }


        header('Cache-Control: private, no-cache, must-revalidate, max-age=0');

        $path = sanitize_text_field($path);
        do_action('socialify/endpoint', $path, $wp);
        do_action("socialify/endpoint/$path", $wp);
        exit;
    }

    public static function addQueryVars($vars)
    {
        $vars[] = Plugin::$slug;
        return $vars;
    }

    public static function addEndpoint()
    {
        // Add rewrite endpoint for 'socialify' at the root
        add_rewrite_endpoint(Plugin::$slug, EP_ROOT);

        /**
         * hack for reset rewrite rules
         */
        $rules = get_option('rewrite_rules');
        $key = sprintf('%s(/(.*))?/?$', Plugin::$slug);
        if (! isset($rules[$key])) {
            flush_rewrite_rules($hard = false);
        }
    }
}



<?php
/**
 * Plugin Name:  Socialify
 * Description:  Social Login for WordPress based the OAuth2 and HybridAuth
 * Plugin URI:   https://github.com/uptimizt/socialify
 * Author:       uptimizt
 * Author URI:   https://github.com/uptimizt
 * Text Domain:  socialify
 * Domain Path:  /languages/
 * GitHub Plugin URI: https://github.com/uptimizt/socialify
 * Requires PHP: 8.0
 * Version:      0.9.230415
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

namespace Socialify;

defined('ABSPATH') || die();

require_once __DIR__ . '/vendor/autoload.php';

$files = glob(__DIR__ . '/includes/*.php');
foreach ($files as $file) {
  require_once $file;
}

require_once __DIR__ . '/Google/GoogleLogin.php';

Main::init();

class Main {
    public static function init (){
        add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), function($links ) {
            $settings_link = sprintf( '<a href="%s">%s</a>', admin_url('admin.php?page=socialify'), __('Settings', 'socialify') );
            array_unshift($links, $settings_link);
            return $links;
        } );
    }
}


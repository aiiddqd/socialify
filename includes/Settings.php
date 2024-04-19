<?php 

namespace Socialify;

class Settings {

    public static $settings_group = 'socialify_login_settings';

    public static function init(){
        add_action('admin_menu', function(){
            add_options_page(
                $page_title = 'Socialify Settings',
                $menu_title = 'Socialify',
                $capability = 'administrator',
                $menu_slug = 'socialify-settings',
                $callback = [__CLASS__, 'render_settings']
            );
        });
    }


    /**
     * Add settings
     */
    public static function render_settings(){
        ?>
        <div class="wrap">
            <h1><?= __('Socialify Settings', 'socialify') ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( self::$settings_group ); ?>
                <?php do_settings_sections( self::$settings_group ); ?>
                <?php submit_button(); ?>

            </form>
        </div>
        <?php
    }

}

Settings::init();
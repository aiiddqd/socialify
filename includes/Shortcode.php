<?php 

namespace Socialify\Shortcode;

add_shortcode('socialify', function($atts){
    ob_start(); ?>
<div class="socialify-btns">
    <div class="socialify-btns-block">
        <?php do_action('socialify_btns') ?>
    </div>
    <div class="socialify-btns-icns">
        <?php do_action('socialify_btns_icn') ?>
    </div>
</div>
<?php return ob_get_clean();
});

// add_action('admin_init', __NAMESPACE__ . '\\add_settings');
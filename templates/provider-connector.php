<?php

defined('ABSPATH') || die();

dd_web($items);

if (empty($items)) {
    return 'No providers are enabled';
}

?>

<div class="socialify-connect-providers">
    <?php foreach ($items as $key => $item) : ?>
        <div
            class="wp-block-group is-content-justification-space-between is-nowrap is-layout-flex wp-block-group-is-layout-flex">
            <div
                class="wp-block-group is-nowrap is-layout-flex wp-block-group-is-layout-flex">
                <figure class="wp-block-image size-full is-resized">
                    <img decoding="async" width="64" height="64" src="<?php echo esc_url($item['logo_url']); ?>"
                        alt="<?php echo esc_attr(ucfirst($key)); ?>" />
                </figure>
                <p>Connect with <span><?php echo esc_html($item['name']); ?></span></p>
            </div>

            <div class="wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex">
                <div class="wp-block-button">
                    <a href="<?php echo esc_url($item['url']); ?>" class="wp-block-button__link wp-element-button socialify-provider-<?php echo esc_attr($key); ?>">
                        <span><?= __('Connect', 'socialify') ?></span>
                    </a>
                </div>
            </div>
        </div>
        <div>



        </div>
    <?php endforeach; ?>
</div>
<?php
//includes/AuthShortcode.php

defined('ABSPATH') || die();

if (empty($items)) {
    return '';
}

?>

<div class="socialify-connect-providers">
    <?php foreach ($items as $key => $item) : ?>
        <div
            class="wp-block-group is-content-justification-space-between is-nowrap is-layout-flex wp-block-group-is-layout-flex">
            <div class="wp-block-group is-nowrap is-layout-flex wp-block-group-is-layout-flex">
                <figure class="wp-block-image size-full is-resized">
                    <img decoding="async" width="64" height="64" alt="<?php echo esc_attr(ucfirst($key)); ?>"
                        src="<?php echo esc_url($item['logo_url']); ?>" />
                </figure>
                <p>Продолжить с <span><?php echo esc_html($item['name']); ?></span></p>
            </div>

            <div class="wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex">
                <div class="wp-block-button">
                    <a href="<?php echo esc_url($item['actionUrl']); ?>"
                        class="wp-block-button__link wp-element-button socialify-provider-<?php echo esc_attr($key); ?>">
                        <span><?= __('Вход', 'socialify') ?></span>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php
//includes/AuthShortcode.php

defined('ABSPATH') || die();

if (empty($items)) {
    return '';
}

?>

<div class="socialify-auth-providers">
    <?php foreach ($items as $key => $item) : ?>
        <a class="socialify-btn" href="<?php echo esc_url($item['actionUrl']); ?>">
            <figure >
                <img width="16" height="16" decoding="async" alt="<?php echo esc_attr(ucfirst($key)); ?>"
                    src="<?php echo esc_url($item['logo_url']); ?>" />
            </figure>
            <span>Продолжить с <span><?php echo esc_html($item['name']); ?></span></span>
        </a>
    <?php endforeach; ?>
</div>
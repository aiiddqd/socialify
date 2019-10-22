<?php

if(empty($data)){
  return;
}

do_action('socialify_shortcode_before');
?>
<div class="socialify_shortcode_login">
  <?php foreach ($data['login_items'] as $key => $item):
    if(empty($item['inner_html'])){
      $item['inner_html'] = sprintf('<img src="%s" alt="">', $item['ico_url']);
    }
    ?>
    <a href="<?= $item['url'] ?>" class="<?= implode(' ', $item['class_array']) ?>">
      <?= $item['inner_html'] ?>
    </a>
  <?php endforeach; ?>
</div>
<?php
do_action('socialify_shortcode_after');

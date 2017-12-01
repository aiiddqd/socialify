<?php

/**
 * Actions for login form
 */
class HAWP_Login_Form_Ext {

  function __construct() {
    add_action( "login_form", array( $this, "add_actions_to_form" ) );
  }

  //	$message = apply_filters( 'login_message', $message );
  function chg_login_message(){
    //Написать что то с инструкцией о входе
  }


  //	do_action( 'login_form' );
  function add_actions_to_form(){

    $url_google = site_url('/ha-sign/Google');
    $url_yandex = site_url('/ha-sign/Yandex');

    if(isset($_GET['redirect_to'])){
      $url_google = add_query_arg('redirect_url', $_GET['redirect_to'], $url_google);
      $url_yandex = add_query_arg('redirect_url', $_GET['redirect_to'], $url_yandex);
    }
    //Добавляем кнопки
    ?>
      <div class="hawp_google">
        <style type="text/css" scoped>
          .button.btn-hawp{
            margin: 0px 0px 15px;
          }
        </style>
        <p><strong>Быстрый вход без пароля:</strong></p>
        <?php printf('<a href="%s" class="button btn-hawp">Google</a>', $url_google); ?>
        <?php printf('<a href="%s" class="button btn-hawp">Яндекс</a>', $url_yandex); ?>
      </div>
    <?php
  }

} new HAWP_Login_Form_Ext;

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

    $url = site_url('/ha-sign/Google');

    if(isset($_GET['redirect_url'])){
      $url = add_query_arg('redirect_url', $_GET['redirect_url'], $url);
    }
    //Добавляем кнопки
    ?>
      <div class="hawp_google">
        <style type="text/css" scoped>
          .button.btn-hawp{
            margin: 0px 0px 15px;
          }
        </style>
        <?php printf('<a href="%s" class="button btn-hawp">Быстрый вход через Google</a>', $url); ?>
      </div>
    <?php
  }

} new HAWP_Login_Form_Ext;

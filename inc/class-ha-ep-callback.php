<?php

/**
 * Add Endpoint for URL Callback OAuth2
 * /hawp/
 */
class HAWP_Endpoint_Callback {

  function __construct() {
    add_action( 'init', array($this, 'add_endpoint') );
    add_action( 'wp_loaded', array($this, 'flush_rewrite_rules_hack') );
    add_action( "template_redirect", array( $this, "endpoint_call" ) );

  }


  function flush_rewrite_rules_hack(){
    $rules = get_option( 'rewrite_rules' );

    if ( ! isset( $rules['hawp(/(.*))?/?$'] ) ) {
        flush_rewrite_rules( $hard = false );
    }
  }

  function add_endpoint() {
      add_rewrite_endpoint( 'hawp', EP_ROOT );
  }


  function endpoint_call(){

    $check = get_query_var('hawp', false);
    if($check === false){
      return;
    }

    require_once (plugin_dir_path( __FILE__ ) . '/inc/hybridauth/Hybrid/Auth.php');
    require_once (plugin_dir_path( __FILE__ ) . '/inc/hybridauth/Hybrid/Endpoint.php');

    Hybrid_Endpoint::process();

  }

}
new HAWP_Endpoint_Callback;

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

  /*
  * Small hack for check and reset rewrite rules
  */
  function flush_rewrite_rules_hack(){
    $rules = get_option( 'rewrite_rules' );

    if ( ! isset( $rules['hawp(/(.*))?/?$'] ) ) {
        flush_rewrite_rules( $hard = false );
    }
  }

  /*
  * Add endpoint /hawp/ for app
  */
  function add_endpoint() {
    add_rewrite_endpoint( 'hawp', EP_ROOT );
  }

  /*
  * Call endpoint - start HA process
  */
  function endpoint_call(){

    $check = get_query_var('hawp', false);
    if($check === false){
      return;
    }

    // @TODO remove logger
    do_action("u7logger", ['test2', $_REQUEST]);


    // require_once (plugin_dir_path( __FILE__ ) . 'hybridauth/Hybrid/Auth.php');
    // require_once (plugin_dir_path( __FILE__ ) . 'hybridauth/Hybrid/Endpoint.php');
    include plugin_dir_path( __FILE__ ) . 'ha2/autoload.php';

    Hybrid_Endpoint::process();

  }

}
new HAWP_Endpoint_Callback;

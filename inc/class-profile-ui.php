<?php

/**
 * UI for profiles
 */

class HAWP_Profile_UI {

  function __construct() {
    // add_action( 'edit_user_profile', [$this, 'display_current_profiles'] );
    add_action( 'show_user_profile', [$this, 'display_current_profiles'] );

  }

  function display_current_profiles($user){
    $user_id =  $user->data->ID;
    ?>
    <h1>HybridAuth</h1>
    <?php


    $profiles = $this->get_profiles($user);

    if(is_array($profiles)){
      // $this->display_list_profiles($meta);
    } else {
      echo "<p>No profiles</p>";
    }


  }

  function get_profiles($user){

    $data = [];

    $user_id =  $user->data->ID;

    $meta = get_user_meta( $user_id);


      // var_dump($meta);

    return $data;
  }
}
new HAWP_Profile_UI;

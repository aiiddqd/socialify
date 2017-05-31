<?php

/**
 * White List for HAWP
 *
 */
class HAWP_White_List{

  function __construct()
  {
    add_filter('hawp_get_user_id', [$this, 'add_user_for_domain'], 10, 3);
  }

  /**
   * Add user for domain
   *
   * Use hook apply_filters('hawp_get_user_id', 0, $profile, $provider_id);
   *
   * @return $user_id or Exception
   */
  public function add_user_for_domain($user_id, $profile, $provider_id){



    return $user_id;
  }

}
new HAWP_White_List;

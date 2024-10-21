<?php

namespace UCSS;

/**
 * Handles authentication for the REST API
 *   
 * **How to test**
 * - Check that routes in /wp-json/dh-functions/ are not accessible to non-logged in users
 *
 * @package UCSS
 */
class Auth {

  /**
   * Checks if the current user has access to the REST API
   *
   * @return bool
   */
  public static function is_allowed()  {

    $current_user = wp_get_current_user();
    $allowed_roles = ['administrator'];

    // Only allow access to the REST API for users with the specified roles
    if (!array_intersect($current_user->roles, $allowed_roles)) {
      return false;
    }

    return true;

  }

}

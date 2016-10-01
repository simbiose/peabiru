<?php namespace resources;

/**
 * users resource
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

class Users extends \libs\Resourceful {

  /**
   * construct
   *
   * @param string      $action
   * @param ArrayObject $params
   */

  function __construct ($action, $params) {
    debug(' users->__construct ', $action, $params);
  }

  /**
   * list users
   */

  protected function index () {
    debug(' users->index ');
    $users = [];

    $users[] = ['id'=>1, 'nick'=>'xico',   'slug'=>'xico',   'email'=>'xico@simbio.se'];
    $users[] = ['id'=>2, 'nick'=>'boneca', 'slug'=>'boneca', 'email'=>'boneca@osm.org'];
    $users[] = ['id'=>3, 'nick'=>'naoliv', 'slug'=>'naoliv', 'email'=>'naoliv@gmail.com'];

    return $users;
  }

  /**
   * show user information / load logged user data into session, proxy to user creation ...
   *
   * @param ArrayObject $params
   * @param mixed       $id
   * @param string      $strategy
   */

  protected function show ($params, $id, $strategy=null) {
    debug(' users->show ');
    return $this->code(404);
  }

  /**
   * create user through oauth or oauth2 strategies
   *
   * @param ArrayObject $params
   * @param string      $login
   * @param string      $strategy
   */

  protected function create ($params, $login, $strategy=null) {
    debug(' users->create ');
    if ($login != 'login') $strategy = $login;

    $params = $this->params(['name', 'email', 'nick']);

    if (empty($params))
      exit(header('Location: /login'));

    return $params;
  }

  /**
   * update user data
   *
   * @param ArrayObject $params
   * @param mixed       $id
   */

  protected function update ($params, $id) {
    debug(' users->update ');
    return;
  }

  /**
   * delete user / logout
   *
   * @param ArrayObject $params
   * @param mixed       $id
   */

  protected function destroy ($params, $id=null) {
    debug(' users->destroy ');
    $this->code(500);
  }
}

?>

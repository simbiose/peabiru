<?php namespace resources;

/**
 * paths resource
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

class Paths extends \libs\Resourceful {

  /**
   * contructor
   *
   * @param string      $action
   * @param ArrayObject $params
   */

  function __construct ($action, $params) {
    debug(' paths->__construct ', $action, $params);
  }

  /**
   * list paths
   */

  protected function index () {
    debug(' paths->index ');
    return $this->code(200);
  }

  /**
   * show path
   *
   * @param  ArrayObject $params
   * @param  integer     $id
   */

  protected function show ($params, $id) {
    debug(' paths->show ');
    return ['path' => 'unknown'];
  }

  /**
   * create path
   *
   * @param ArrayObject $params
   */

  protected function create ($params) {
    debug(' paths->create ');
    return;
  }

  /**
   * update path
   *
   * @param ArrayObject $params
   * @param integer     $id
   */

  protected function update ($params, $id) {
    debug(' paths->update ');
    return $params;
  }

  /**
   * destroy path
   *
   * @param ArrayObject $params
   * @param integer     $id
   */

  protected function destroy ($params, $id) {
    debug(' paths->destroy ');
    return $this->code(500);
  }

  /**
   *
   *
   *
   */

  private function generate_path (&$error, $id=null) {
    // steps
    //
    // 1) check minimun distance between points
    //
    // 2) call mapbox api
    //
    // 3) exists?
    //   3.a) yes, update! - check path against current one, matches?
    //     3.a.a) yes, nothing to be done
    //     3.a.b) continue.
    //   3.b) no, create! - continue
    //
    // 4) parse path polyline into array of points and
    //  generate unique hashs for each point
    //

/*
    if (
      !$id && $this->distance() < 5000 &&
      ($error = 'too close')
    ) return false;

    if
*/
  }

  /**
   *
   *
   *
   */

  private function directions_api ($api) {
    // ?
  }
}

?>

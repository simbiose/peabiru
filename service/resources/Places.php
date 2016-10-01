<?php namespace resources;

/**
 * places resource
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

class Places extends \libs\Resourceful {

  /**
   * construct
   *
   * @param string      $action
   * @param ArrayObject $params
   */

  function __construct ($action, $params) {
    debug(' places->__construct ', $action, $params);
  }

  /**
   * list places by geohash
   *
   * @param ArrayObject $params
   */

  protected function index ($params) {
    debug(' places->index ');
    $places = [];
    return $places;
  }

  /**
   * show place
   *
   * @param ArrayObject $params
   * @param integer     $place
   */

  protected function show ($params, $place) {
    debug(' places->show ');
    return;
  }

  /**
   * create place
   *
   * @param  ArrayObject $params
   * @return mixed
   */

  protected function create ($params) {
    debug(' places->create ', $params);
    return $this->code(500)->finish(['error'=>'failed to create place']);
  }

  /**
   * update place, subscribe to
   *
   * @param  ArrayObject $params
   * @param  integer     $id
   * @return mixed
   */

  protected function update ($params, $id) {
    debug(' places->update ', $id, $params);
    return $this->code(500)->finish(['error'=>'failed to update place']);
  }

  /**
   * delete place
   *
   */

  protected function destroy () {
    debug(' places->destroy ');
    return $this->code(403);
  }
}

?>

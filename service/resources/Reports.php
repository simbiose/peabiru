<?php namespace resources;

/**
 * reports resource
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

class Reports extends \libs\Resourceful {

  /**
   * construct
   *
   * @param string      $action
   * @param ArrayObject $params
   */

  function __construct ($action, $params) {
    debug(' reports->__construct ', $action, $params);
  }

  /**
   * list reports
   */

  protected function index () {
    debug(' reports->index ');
    return [[]];
  }

  /**
   * show report
   *
   * @param ArrayObject $params
   * @param integer     $id
   */

  protected function show ($params, $id) {
    debug(' reports->show ');
    return [];
  }

  /**
   * create report
   *
   * @param ArrayObject $params
   */

  protected function create ($params) {
    debug(' reports->create ');
    return $this->code(204);
  }

  /**
   * update report
   *
   * @param ArrayObject $params
   * @param integer     $id
   */

  protected function update ($params, $id) {
    debug(' reports->update ');
    return $this->code(404);
  }
}

?>

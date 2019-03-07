<?php namespace libs;

/**
 * check credentials, delegate action
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

trait Permissible {

  public $allow = [];
  public $none  = false;

  /**
   * delegate action calls
   *
   * @param string $name
   * @param array  $args
   *
   */

  function __call ($name, $args) {
    debug($name);
    debug($args);
    if (!method_exists($this, $name)) {
      debug(' raised exception with '. $name .' does not contains '. get_class($this));
      throw new \Exception('Method '.$name.' does not exists in resource: '. get_class($this));
    }

    if ((!empty($this->allow) && !in_array($name, $this->allow)) || $this->none)
      if (method_exists($this, 'authenticated') && !$this->authenticated())
        return $this->code(401)->finish(['status' => 'Unauthorized'], true);

    return $this->{$name}(...$args);
  }
}

?>

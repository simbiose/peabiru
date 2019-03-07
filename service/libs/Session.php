<?php namespace libs;

/**
 * simple session
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

trait Session {

  public $session = null;

  function session (...$args) {

    debug(' call session with: ', $args);

    if (!$this->session) $this->session = new class() extends \ArrayObject {

        private $_sid = null;

        function __construct () {
          $last = (empty($this->_sid = $_COOKIE['peabiru']) ?
            [] : apcu_fetch('peabiru-'. $this->_sid) ?: []);
          debug(' should construct session, last session: ', $last);
          parent::__construct($last); //, self::STD_PROP_LIST);
        }

        function __destruct () {
          debug(' ['. $_SERVER['REQUEST_URI'] .'] should save session and send cookies, SID: ', $this->_sid);
          if (!$this->_sid) $this->_sid = uniqid(true);
          // name, value, expire, path, domain, secure, httponly
          setcookie('peabiru', $this->_sid, time()+3600, '/', '.'. $_SERVER['HTTP_HOST'], false, false);//true);
          apcu_store('peabiru-'. $this->_sid, (array) $this->getArrayCopy(), 60*60*24);
        }
      };

    if (count($args) == 2) return $this->session[$args[0]] = $args[1];
    if (count($args) != 1) return;

    if (is_string($args[0])) return $this->session[$args[0]];
    if (array_keys($args[0]) !== range(0, count($args[0])-1)) {
      error_log(' should iterate ... ');
      foreach ($args[0] as $k => $v) $this->session[$k] = $v;
      return $args[0];
    }
    if (is_array($args[0]))
      return array_intersect_key($this->session->getArrayCopy(), array_flip($args[0]));
  }
}

?>

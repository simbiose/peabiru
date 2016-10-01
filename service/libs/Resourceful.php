<?php namespace libs;

/**
 * resources base
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

class Resourceful {
  use Permissible;

  private $_headers     = [];
  private $_params      = [];
  public  $_out_headers = [];
  private $_body        = '';
  public  $_code        = 0;
  public  $_method      = '';
  public  $action       = '';

  /**
   * validates params
   *
   * @param  array  $params
   * @return Validator
   */

  function validates ($params=null) {
    return new \Valitron\Validator($params ?: $this->params);
  }

  /**
   * magic access to private properties
   *
   * xhr, method, headers, params, body
   */

  function __get ($property) {
    debug(' __get ', $property);
    switch ((string) $property) {
      case 'xhr':
        return isset($this->headers['x-requested-with']) &&
          strtolower($this->_headers['x-requested-with']) == 'xmlhttprequest';
      case 'method':
        if (!empty($this->_method)) return $this->_method;
        if ($this->_method = strtolower($_SERVER['REQUEST_METHOD']) && $this->_method != 'post')
          return $this->_method;
        if (isset($this->headers['x-http-method-override'])) {
          return $this->_method = strtolower($this->_headers['x-http-method-override']);
        } elseif (isset($this->params['_method'])) {
          return $this->_method = strtolower($this->_params['_method']);
        }

        return $this->_method;
      case 'headers':
        if (empty($this->_headers))
          foreach ($_SERVER as $k => $v)
            if (substr($k, 0, 5) == 'HTTP_')
              $this->_headers[str_replace('_', '-', strtolower(substr($k, 5)))] = $v;

        return $this->_headers;
      case 'params':
        if (!empty($this->_params)) return $this->_params;
        if (
          ($this->headers('content-type', 'text/html') == 'application/json' ||
            strpos($_SERVER['REQUEST_URI'], '.json') !== false) &&
          ($json = json_decode($this->body))
        )
          foreach ($json as $k => $v) $this->_params[$k] = (array) $v;

        return $this->_params ?: $this->_params = array_merge($this->_params, (array)$_REQUEST);
      case 'body':
        return $this->_body ?: $this->_body = @file_get_contents('php://input');
      default:
        return null;
    }
  }

  /**
   * assert properties
   */

  function __isset ($property) {
    debug(' __isset ', $property);
    switch ((string) $property) {
      case 'xhr':     return true;
      case 'method':  return $this->method && empty($this->_method);
      case 'headers': return $this->headers && empty($this->_headers);
      case 'params':  return $this->params && empty($this->_params);
      case 'body':    return $this->body && empty($this->_body);
      default: return false;
    }
  }

  /**
   * set http code
   *
   * @param  integer $status
   * @return $this
   */

  function code ($status=null) {
    if ($status) $this->_code = $status;
    return $this;
  }

  /**
   * add response header
   *
   * @array  $key
   * @string $val
   * @return $this
   */

  function header (...$args) {
    if (isset($args[0]) && is_array($args[0])) {
      foreach ($args[0] as $k => &$v) if (is_numeric($k)) unset($args[0][$k]);
      $args[0] = array_change_key_case($args[0]);
    }

    $this->_out_headers = array_merge(
      $this->_out_headers, (count($args) == 2 ? [strtolower($args[0]) => $args[1]] : $args)
    );

    return $this;
  }

  /**
   * finish
   *
   * @param  object  $content
   * @param  boolean $dies
   * @return object
   */

  function finish ($content, $dies=false) {
    if (!$dies) return $content;

    $code = &$this->_code;

    if ($content instanceof self) $content = null;
    if (!$code && $this->action == 'create') $code = 201;
    if (!$code && empty($result) && in_array($this->action, ['update', 'destroy']))
      $code = 204;

    http_response_code($code ?: 200);
    foreach ($this->_out_headers as $k => $v)
      header(is_numeric($k) ? $v : $k.': '.$v);

    if (
      $this->headers('content-type', 'text/html') == 'application/json' ||
      strpos($_SERVER['REQUEST_URI'], '.json') !== false
    ) {
      if (!isset($this->_out_headers['content-type']))
        header('Content-Type: application/json; charset=utf-8');
      exit((!$content && !is_array($content)) ? '' : json_encode($content));
    }

    exit((string) $content);
  }

  /**
   * get param
   *
   * @param  string $key
   * @param  object $default
   * @return object
   */

  function param ($key, $default=null) {
    return $this->params[$key] ?: $default;
  }

  /**
   * get params
   *
   * @param  string $required
   * @param  array  $permit
   * @return mixed
   */

  function params (...$args) {
    debug(' params() ', $args);
    if (empty($args)) return $this->params;
    \Koine\Parameters::$throwExceptions = false;
    if (count($args) == 1) return new \ArrayObject(
        (new \Koine\Parameters($this->params))->permit(...$args)->toArray(),
        \ArrayObject::ARRAY_AS_PROPS
      );
    return new \ArrayObject((new \Koine\Parameters($this->params))
      ->requireParam($args[0])->permit(...array_slice($args, 1))->toArray(),
      \ArrayObject::ARRAY_AS_PROPS
    );
  }

  /**
   * get request headers, single (default) or filter many
   *
   * @param  mixed  $header
   * @param  string $default
   * @return mixed
   */

  function headers (...$args) {
    if (empty($args)) return $this->headers;
    if (count($args) == 1) {
      if (!is_array($args[0])) return $this->headers[$args[0]];
      return array_intersect_key($this->headers, array_flip($args[0]));
    }
    return $this->headers[$args[0]] ?: $args[1];
  }

  /**
   * check against http method
   *
   * @param  array  $args
   * @return boolean
   */

  function method (...$args) {
    return in_array($this->method, $args);
  }

  /**
   *
   *
   *
   */

  function back () {
  }

  /**
   *
   *
   *
   */

  function to () {
  }
}

?>

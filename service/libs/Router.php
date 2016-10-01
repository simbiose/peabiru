<?php namespace libs;

/**
 * router, implements fastrouter
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */


class Router {

  private $_routes  = [];
  private $_id      = ['show' => true, 'update' => true, 'destroy' => true];
  private $_actions = [
    'index'  => 'GET',  'show'    => 'GET', 'update' => 'PUT',
    'create' => 'POST', 'destroy' => 'DELETE'
  ];

  /**
   * maps resources actions
   *
   * @param  string $root
   * @param  array  $actions
   * @param  string $id
   * @param  string $format
   * @param  string $resource
   * @return $this
   */

  function resources (
    $root, $actions=[], $id='{id:\d+}', $format='[.{format:csv|xml|json|html}]', $resource=null
  ) {
    if (!$resource && class_exists('resources\\'. ucfirst($root)))
      $resource = 'resources\\'. ucfirst($root);
    if (!$resource)
      throw new Exception('Could not find resource: '. ucfirst($root));

    $root = $root[0] == '/' ? $root : '/'.$root;

    foreach ($this->_actions as $action => $verb) {
      if (!method_exists($resource, $action)) continue;
      if (!($actions && isset($actions[$action]))) {
        $this->_routes[] = [
          $verb, $root .($this->_id[$action] ? '/'.$id : '') .$format, [$resource, $action]
        ];
        continue;
      }

      $paths = is_array($actions[$action]) ? $actions[$action] : [$actions[$action]];
      foreach ($paths as $path) $this->_routes[] = [
        $verb,
        ($path[0] == '/' ? '' : $root. (($path[1] == '.' || $path[0] == '.') ? '' : '/')). $path,
        [$resource, $action]
      ];
    }
    //debug(' -------- ', $this->_routes);
    return $this;
  }

  /**
   * dispatch routes
   *
   * @param  boolean $debug
   * @return $this
   */

  function dispatch ($debug=false) {
    $routes     = &$this->_routes;
    $uri        = $_SERVER['REQUEST_URI'];
    $method     = $_SERVER['REQUEST_METHOD'];
    $json       = strtolower($_SERVER['HTTP_CONTENT_TYPE']) == 'application/json' ||
      strpos($uri, '.json') !== false;
    $dispatcher = \FastRoute\simpleDispatcher(
      function (\FastRoute\RouteCollector $rc) use ($routes, $debug) {
        foreach ($routes as $route) {
          if ($debug) debug(' add route ');
          $rc->addRoute(...$route);
        }
    });

    if (false !== $pos = strpos($uri, '?')) $uri = substr($uri, 0, $pos);
    if (
      'POST' == $method &&
      (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) || isset($_REQUEST['_method']))
    ) $method = strtoupper(isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ?
      $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : $_REQUEST['_method']);

    list($status, $handler, $vars) = $dispatcher->dispatch($method, rawurldecode($uri));

    switch ($status) {
      case \FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        header($json ? 'application/json' : 'text/html');
        exit($json ? '{"status":"Not found"}' : 'Not found');
      case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED: // $handler ($allowed_method)
        $implemented = in_array($method, array_values($this->_actions));
        $status      = $implemented ? 'Method not allowed' : 'Not implemented';

        http_response_code($implemented ? 405 : 501);
        header($json ? 'application/json' : 'text/html');
        exit($json ? '{"status":"'. $status .'"}' : $status);
      case \FastRoute\Dispatcher::FOUND:
        try {
          $vars             = new \ArrayObject($vars, \ArrayObject::ARRAY_AS_PROPS);
          $instance         = new $handler[0]($handler[1], $vars);
          $instance->action = $handler[1];

          if ($instance->_code > 200) $instance->finish(null, true);
          $instance->finish(
            $instance->{$handler[1]}($vars, ...array_values($vars->getArrayCopy())), true
          );
        } catch (Exception $e) {
          log(' raised exception with ', $e->getMessage());

          http_response_code(500);
          header($json ? 'application/json' : 'text/html');
          exit($json ? '{"status":"Server error"}' : 'Server error');
        }
    }
  }
}

?>

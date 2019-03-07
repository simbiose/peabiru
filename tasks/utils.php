<?php

/**
 *
 *
 *
 *
 */

require_once __DIR__ .'/../vendor/autoload.php';

set_time_limit(0);

define('DEV',   getenv('ENV') == 'development');
define('DEBUG', (($opts = getopt('d')) && isset($opts['d'])));

echo PHP_EOL. strftime('%m/%d %T  ', time()) .'started script ' .$argv[0]. PHP_EOL;

/**
 * info
 *
 * @param  mixed   ...
 * @return boolean
 */

function info (...$args) {
  $contents = [];
  foreach ($args as $arg) $contents[] = (
    (is_array($arg) || is_object($arg)) ? json_encode($arg) :
      (is_string($arg) ? $arg : var_export($arg, true)));

  echo strftime('%m/%d %T  ', time()). join(', ', $contents) .PHP_EOL;

  return true;
}

/**
 *  debug
 *
 *  @param  mixed   ...
 *  @return boolean
 */

function debug (...$args) {
  if (DEBUG) return info (...$args);
  return true;
}

/**
 * @see request
 */

function get (...$args) {
  array_splice($args, 1, 0, false);
  return request(...$args);
}

/**
 * @see request
 */

function post (...$args) {
  return request(...$args);
}

/**
 *
 *
 */

function parser (&$body, &$rheaders, &$bsize, $fhandler) {
  $is_body = false;
  return function ($res, $part) use (&$is_body, &$body, &$rheaders, &$bsize, $fhandler) {
    $len = strlen($part);
    if ($is_body) {
      $bsize += $len;
      if ($fhandler) return fwrite($fhandler, $part);
      $body .= $part;
      return $len;
    }

    if ($is_body = ($part == "\r\n")) return $len;
    if (substr($part, 0, 7) == 'HTTP/1.') return $len;

    list($k, $v) = explode(': ', $part);
    if (($k = strtolower($k)) && ($v = trim($v)) && isset($rheaders[$k]))
      $rheaders[$k] = is_array($rheaders[$k]) ? $rheaders[$k] + [$v] : [$rheaders[$k], $v];
    else
      $rheaders[$k] = $v;

    return $len;
  };
}

/**
 * regular request, multiple requests
 *
 * @param  mixed   $url
 * @param  mixed   $post
 * @param  array   $headers
 * @param  array   $opts
 * @param  string  $filename
 * @param  boolean $stats
 * @return string
 */

function request ($url, $post=false, $headers=[], $opts=[], $fname=null, $stats=false) {
  if (empty($url)) throw new Exception('url is empty');

  $active   = null;
  $mrc      = null;
  $rstart   = microtime(true);
  $load     = 0;
  $fhandler = false;
  $version  = 'curl-php/'. curl_version()['version'];
  $bsize    = [];
  $body     = [];
  $rheaders = [];
  $handlers = [];
  $codes    = [];
  $results  = [];
  $url      = flatten([$url]);

  if ($fname && ($fhandler = fopen($fname, 'w+'))) $opts = $opts + [
      CURLOPT_NOPROGRESS       => false,
      CURLOPT_PROGRESSFUNCTION => function (...$n) use (&$load) { $load = $n[2]; }
    ];

  $multi = curl_multi_init();
  $total = count($url);

  for ($i = 0; $i < count($url); ++$i) {
    if ($stats)
      echo strftime('%m/%d %T  ', time()). (($post ? 'POST' : 'GET') .' '. (strlen($url[$i]) > 100 ?
        substr($url[$i], 0, 80). ' [...] '.substr($url[$i], -20) : $url[$i])) .($i == count($url)-1 ? '' : PHP_EOL);

    $body[$i]     = '';
    $bsize[$i]    = 0;
    $rheaders[$i] = [];
    $handlers[$i] = curl_init($url[$i]);

    curl_setopt_array($handlers[$i], [
      CURLOPT_ENCODING             => '',
      CURLOPT_RETURNTRANSFER       => true,
      CURLOPT_HEADER               => true,
      CURLOPT_FOLLOWLOCATION       => true,
      CURLOPT_BUFFERSIZE           => 8192,
      CURLOPT_MAX_RECV_SPEED_LARGE => 65536,
      CURLOPT_WRITEFUNCTION        => parser($body[$i], $rheaders[$i], $bsize[$i], $fhandler),
      CURLOPT_HTTPHEADER           => array_merge([
        'Accept: application/json', 'User-Agent: '.$version, 'Expect:'], $headers
      )] + $opts + ($post ? [CURLOPT_POSTFIELDS =>
        (is_string($post) ? $post : http_build_query($post))] : [])
    );

    curl_multi_add_handle($multi, $handlers[$i]);
  }

  do {
    if (curl_multi_select($multi) == -1) usleep(1);
    do
      $mrc = curl_multi_exec($multi, $active);
    while ($mrc == CURLM_CALL_MULTI_PERFORM);
  } while ($active && $mrc == CURLM_OK);

  foreach ($handlers as $i => $handler) {
    $codes[$i] = curl_getinfo($handler, CURLINFO_HTTP_CODE);
    curl_multi_remove_handle($multi, $handler);
  }

  curl_multi_close($multi);

  if ($fname) {
    fclose($fhandler);
    if ($stats) info(sprintf(
      PHP_EOL. 'finished - total size %.1f KiB, payload %.1f KiB, ratio %.2f in %s',
      $bsize[0]/1024, $load/1024, $bsize[0]/$load, htime(microtime(true)-$rstart)
    ));
  } else
      if ($stats) echo (sprintf(' - finished in %s', htime(microtime(true)-$rstart))) .PHP_EOL;

  for ($i = 0; $i < $total; ++$i)
    array_push($results, [
      $codes[$i],
      (substr($rheaders[$i]['content-type'] ?: '', 0, 16) == 'application/json') ? json_decode($body[$i]) : $body,
      $rheaders[$i]
    ]);

  unset($handlers);
  unset($rheaders);
  unset($body);
  unset($bsize);
  unset($url);
  unset($codes);

  return $total > 1 ? $results : flatten($results);
}

/**
 * humanize time
 *
 * @param  integer $seconds
 * @return string
 */

function htime ($seconds) {
  $h = ($seconds > 3599 ? floor($seconds / 3600) : 0);
  $m = ($seconds > 59 ? floor(($seconds / 60) % 60) : 0);
  $s = $seconds % 60;

  return $h > 0 ? sprintf('%dh%dm%ds', $h, $m, $s) : ($m > 0 ?
    sprintf('%sm%ds', $m, $s) : sprintf('%ds', $s));
}

/**
 * gauge time and progress
 *
 * @param  integer $from
 * @param  integer $to
 * @return function
 */

function gauge ($from=0, $to=0) {
  $start = microtime(true);
  $index = -1;
  return function ($step=0) use ($from, $to, $start, &$index) {
    if ($step == 0 && $from == 0 && $to == 0)
      print('finished in '. htime(microtime(true) - $start). PHP_EOL);
    elseif ($index == -1 && (++$index > -1))
      print(' ');
    elseif (($current = (int) ((100 * $step) / $to)) && $index < $current) {
      print(str_repeat('*', $current - $index));
      $index = $current;
      if ($index == 100) print(' - took '. htime(microtime(true) - $start). PHP_EOL);
    }
    return true;
  };
}

/**
 * except keys
 *
 * @param  array $array
 * @param  array $keys
 * @return array
 */

function except ($array, $keys) {
  return array_diff_key((array) $array, array_flip((array) $keys));
}

/**
 * array diff recursive
 *
 * @param  array $st_array
 * @param  array $nd_array
 * @return array
 */

function array_rec_diff ($st_array, $nd_array) {
  $a_return = [];

  foreach ($st_array as $key => $value) {
    if (array_key_exists($key, $nd_array)) {
      if (is_array($value) && count($value) != count($value, COUNT_RECURSIVE)) {
        if (($diff = array_rec_diff($value, $nd_array[$key])) && count($diff))
          $a_return[$key] = $diff;
      } else {
        if ($value != $nd_array[$key])
          $a_return[$key] = $value;
      }
    } else {
      $a_return[$key] = $value;
    }
  }
  return $a_return;
}

/**
 * flatten array
 *
 * @param  array $array
 * @return array
 */

function flatten (array $array) {
  $return = [];
  array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
  return $return;
}

?>

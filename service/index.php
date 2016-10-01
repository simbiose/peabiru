<?php

/**
 * main script, routing
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

require_once __DIR__ .'/../vendor/autoload.php';

/**
 * debug
 *
 * @param mixed ...
 */

function debug (...$args) {
  $content = '';
  foreach ($args as $arg) $content .= PHP_EOL .
    ((is_array($arg) || is_object($arg)) ? json_encode($arg) : var_export($arg, true));
  error_log($content . PHP_EOL);
}

/**
 * pretty stack trace
 */

function stackTrace () {
  $stack    = debug_backtrace();
  $output   = '';
  $stackLen = count($stack);

  for ($i = 1; $i < $stackLen; $i++) {
    $entry = $stack[$i];
    $func  = $entry['function'] . '(';
    $argsLen = count($entry['args']);
    for ($j = 0; $j < $argsLen; $j++) {
      $my_entry = $entry['args'][$j];
      if (is_string($my_entry)) $func .= $my_entry;
      if ($j < $argsLen - 1)    $func .= ', ';
    }

    $func .= ')';

    $entry_file = 'NO_FILE';
    $entry_line = 'NO_LINE';

    if (array_key_exists('file', $entry)) $entry_file = $entry['file'];
    if (array_key_exists('line', $entry)) $entry_line = $entry['line'];

    $output .= $entry_file . ':' . $entry_line . ' - ' . $func . PHP_EOL;
  }
  return $output;
}

/**
 * routes
 */

(new libs\Router())
  ->resources('users', [
    'create' => '/{action:login}/{strategy:[a-z]+}[.{format:json|}]',
    'show'   => [
      '/{action:login}/{strategy:[a-z]+}[.{format:json|}]', '{user:[0-9a-z\-_]+}[.{format:json}]'
    ],
    'destroy' => ['/logout[.{format:json}]', '{user:[0-9a-z\-_]+}[.{format:json}]']
  ], '{user:[0-9a-z\-_]+}')
  ->resources('places', [
    'show'  => ['{id:\d+}[.{format:json}]', '{id:\d+}/{version:\d+}[.{format:json}]'],
    'index' => [
      '[.{format:json}]', '/users/{user:[0-9a-z\-_]+}/places[.{format:json}]',
      'g/{geohash:[0-9a-zA-Z]{2,5}}[.{format:json}]',
      'g/{from:[0-9a-zA-Z]{2,5}}-{to:[0-9a-zA-Z]{1,5}}[.{format:json}]'
    ]])
  ->resources('paths', [
      'show'  => ['{id:\d+}[.{format:json}]', '{id:\d+}/{version:\d+}[.{format:json}]'],
      'index' => [
        '[.{format:json}]', '/users/{user:[0-9a-z\-\_]+}/paths[.{format:json}]',
        'g/{geohash:[0-9a-zA-Z]{2,5}}[.{format:json}]',
        'g/{from:[0-9a-zA-Z]{2,5}}-{to:[0-9a-zA-Z]{1,5}}[.{format:json}]'
      ]
    ])
/*
  ->resources(
    'reports', [
      'index' => ['[.{format:json}]', '/users/{user:[0-9a-z\-_]+}/reports[.{format:json}]']
    ])
*/
  ->dispatch();

?>

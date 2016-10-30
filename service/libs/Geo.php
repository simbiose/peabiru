<?php namespace libs;

 /**
  * geo methods
  *
  * @author    xico@simbio.se
  * @copyright Simbiose
  * @license   LGPL version 3.0, see LICENSE
  */

trait Geo {

  private $b32_index = [
    '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'j',
    'k', 'm', 'n', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
  ];
  private $b32_hash  = [
    '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',     'b'=>10, 'c'=>11, 'd'=>12, 'e'=>13,
    'f'=>14, 'g'=>15, 'h'=>16, 'j'=>17, 'k'=>18, 'm'=>19, 'n'=>20, 'p'=>21, 'q'=>22, 'r'=>23,
    's'=>24, 't'=>25, 'u'=>26, 'v'=>27, 'w'=>28, 'x'=>29, 'y'=>30, 'z'=>31
  ];

  /**
   * geohash encode, see: https://en.wikipedia.org/wiki/Geohash
   *
   * @param  mixed   $lat
   * @param  float   $lon
   * @param  integer $depth
   * @param  boolean $output_hash
   * @return mixed
   */

  private function geohash_encode ($lat, $lon=null, $depth=50, $expect_hash=true) {
    if (is_string($lat) && $lon == null) {
      $i     = $combined = 0;
      $shift = strlen($lat) * 5;
      while (($shift -= 5) > -1) $combined |= $this->b32_hash[$lat[$i++]] << $shift;
      return $combined;
    }

    if (is_numeric($lat) && $lat > 90 && $lon == null) {
      $hash = '';
      do $hash = $this->b32_index[$lat % 0x20] .$hash; while ($lat >>= 5);
      return $hash;
    }

    if ($expect_hash) $depth *= 5;
    if (!(
      is_numeric($lat) && is_numeric($lon) &&
      ($lat >= -90.0 && $lat <= 90.0) && ($lon >= -180.0 && $lon <= 180.0)
    )) throw new \Exception('lon and lat should be numeric and within range');

    $total = $mid = $combined = $even = 0;
    $_lat  = [-90.0, 90.0];
    $_lon  = [-180.0, 180.0];
    $depth = $depth - ($depth % 5);

    do
      if (($even = !$even))
        $_lon[($lon > ($mid = ($_lon[0] + $_lon[1]) / 2) && $combined += 1) ? 0 : 1] = $mid;
      else
        $_lat[($lat > ($mid = ($_lat[0] + $_lat[1]) / 2) && $combined += 1) ? 0 : 1] = $mid;
    while (++$total < $depth && ($combined *= 2) > -1);

    return $expect_hash ? $this->geohash_encode($combined) : $combined;
  }
}

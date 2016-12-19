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
  private $_box = [[
      'p', 'r', 'x', 'z',
      'n', 'q', 'w', 'y',
      'j', 'm', 't', 'v',
      'h', 'k', 's', 'u',
      '5', '7', 'e', 'g',
      '4', '6', 'd', 'f',
      '1', '3', '9', 'c',
      '0', '2', '8', 'b'
    ], [
      'b', 'c', 'f', 'g', 'u', 'v', 'y', 'z',
      '8', '9', 'd', 'e', 's', 't', 'w', 'x',
      '2', '3', '6', '7', 'k', 'm', 'q', 'r',
      '0', '1', '4', '5', 'h', 'j', 'n', 'p'
  ]];

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

  /**
   * get range recursively using 4x8 and 8x4 tables
   *
   * @param array   &$hashes
   * @param array   &$positions
   * @param array   $limits
   * @param string  $parent
   * @param boolean $even
   * @param integer $steps
   */

  private function range_recursive (
    &$hashes, &$positions, $limits, $parent, $even, $steps
  ) {

    list($_x, $_y, $_x2, $_y2, $last) = $positions[strlen($parent)];

    $v   = $h = 0;
    $x   = !$limits[0] ? -1 : $_x;
    $y   = !$limits[1] ? -1 : $_y;
    $x2  = !$limits[2] ? ($even ? 7 : 3) : $_x2;
    $y2  = !$limits[3] ? ($even ? 3 : 7) : $_y2;
    $box = &$this->_box[(int) $even];

    do
      do
        if ($v > $y && $v <= $y2 && $h > $x && $h <= $x2)
          if ($last)
            $hashes[] = $parent . $box[($v * $steps) + $h];
          else
            $this->range_recursive(
              $hashes, $positions,
              [$_x == $h - 1, $_y == $v - 1, $_x2 == $h, $_y2 == $v],
              $parent . $box[($v * $steps) + $h], !$even, $even ? 4 : 8
            );
      while (++$h < $steps);
    while (32 > (++$v * $steps) && ($h = 0) > -1);
  }

  /**
   * get range of geohashes, example: 6u4-gx (6u4 -> 6gx), 6u5-752
   *
   * @param string $from
   * @param string $to
   * @return array
   */

  private function geohash_range ($from, $to) {
    // match from and to lengths
    if (($len = strlen($from)) > ($to_len = strlen($to)))
      $to = substr($from, 0, -($to_len)) .$to;

    $i = $even = 0;
    $hashes = $pos = [];

    while (
      $i < $len && ($box = &$this->_box[(int) $even = !$even]) && ($steps = $even ? 8 : 4)
    ) $pos[] = [
        (($begin = array_search($from[$i], $box)) % $steps) -1, (($begin / $steps) << 0) -1,
        (($end = array_search($to[$i], $box)) % $steps), (($end / $steps) << 0), ++$i == $len
      ];

    $this->range_recursive($hashes, $pos, [true, true, true, true], '', true, 8);

    return $hashes;
  }
}

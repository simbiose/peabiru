<?php namespace libs;

 /**
  * geohash database methods
  *
  * @author    xico@simbio.se
  * @copyright Simbiose
  * @license   LGPL version 3.0, see LICENSE
  */

trait Hashable {

  /**
   * generate geohashs for place/path given latitude, longitude
   *
   * @param  float   $lat
   * @param  float   $lon
   * @param  integer $id
   * @param  boolean $is_place
   * @return array
   */

  private function hash_it ($lat, $lon, $id, $is_place = true) {
    $hash      = $this->geohash_encode($lat, $lon, 5);
    $hashs     = [$hash, substr($hash, 0, -1), substr($hash, 0, -2), substr($hash, 0, -3)];
    $condition = 'fk_hashs = ? AND '. ($is_place ? 'fk_places = ?' : 'fk_paths = ?');

    for ($i = 0; $i < count($hashs); ++$i)
      if (($exists = Norm::hashs()->where('hash = ?', $hashs[$i])->fetch()))
        $hashs[$i] = $exists;
      else
        $hashs[$i] = Norm::hashs()->insert(['hash' => $hashs[$i], 'len' => strlen($hashs[$i])]);

    if ($is_place)
      for ($i = 0; $i < count($hashs); ++$i)
        if (!Norm::hash_places()->where($condition, $hashs[$i]['id'], $id)->fetch())
          Norm::hash_places()->insert(['fk_hashs' => $hashs[$i]['id'], 'fk_places' => $id]);
    else
      for ($i = 0; $i < count($hashs); ++$i)
        if (!Norm::hash_paths()->where($condition, $hashs[$i]['id'], $id)->fetch())
          Norm::hash_paths()->insert(['fk_hashs' => $hashs[$i]['id'], 'fk_paths' => $id]);

    return $hashs;
  }

  /**
   * find places/paths in list of hashs
   *
   * @param  object  $query
   * @param  array   $hashs
   * @param  boolean $is_place
   * @return object
   */

  private function in_hashs ($query, $hashs, $is_place = true) {
    if (empty($hashs)) return $query;
  }
}

?>

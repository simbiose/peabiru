<?php namespace resources;

/**
 * places resource
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

use \libs\Norm;

class Places extends \libs\Resourceful {
  use \libs\Geo;
  use \libs\Hashable;

  /**
   * construct
   *
   * @param string      $action
   * @param ArrayObject $params
   */

  function __construct ($action, $params) {
    debug(' places->__construct ', $action, $params);
    if (in_array($action, ['show', 'update', 'destroy'])) {
      $query = ['id = ?', (int)$params->id];
      if (isset($params->version))
        $query = ['id = ? AND fk_places = ?', (int)$params->id, $params->version];
      if (false === $this->place = Norm::places()->where(...$query)->select('*')->fetch())
        return $this->code(404);
    }

    if (in_array($action, ['create', 'update'])) {
      $validator = $this->validates();
      $validator->rules([
        'integer'=>'place.node', 'float'=>['place.lat', 'place.lon']
      ]);
      $validator->rule('in', 'place.place', [
        'city', 'farm', 'hamlet', 'isolated_dwelling', 'suburb', 'town', 'village'
      ]);
      if ($action == 'create')
        $validator->rule('required', ['place.node', 'place.lat', 'place.lon']);

      if (!$validator->validate())
        return $this->code(400)->finish($validator->errors(), true);
    }
  }

  /**
   * list places by geohash
   *
   * @param ArrayObject $params
   */

  protected function index ($params) {
    $places  = [];
    $limit   = empty($this->param('limit')) ? 1000 : (int) $this->param('limit');
    $query   = Norm::hash_places();
    $cluster = false;

    if ($params->user && !($limit = false))
      if ((
        $uid = Norm::users()
          ->where('(users.id = ? OR users.slug = ?)', (int) $params->user, $params->user)
      )) $query->where('user_places.fk_users = ?', $uid);

    if (
      !empty($time = $this->param('time') ?: $this->param('created') ?: $this->param('updated'))
      && is_numeric($time) && ($time = (int) $time)
    ) $query->where(sprintf(
        '(places.%s BETWEEN to_timestamp(%d) AND to_timestamp(%d))',
        (empty($this->param('created')) ? 'updated_at' : 'created_at'), ($time - 86400), $time
      ));

    if (!empty($p = $this->param('page')) || ($limit && ($p = 1)))
      $query->limit($limit, ($p - 1) * 200);

    if (!empty($search = $this->param('search')))
      $query->where('name ILIKE ? OR node = ?', "%$search%", (int) $search);

    if ((
      $hashs = isset($params->geohash) ? [$params->geohash] : (isset($params->from) ?
        $this->geohash_range($params->from, $params->to) : null)
    ) && ($cluster = strlen($hashs[0]) < 5) !== null)
      $query->where('hashs.len = ?', strlen($hashs[0]))->where('hash', $hashs);
    else
      $query->where('hashs.len = 5')->group('places.id, hashs.hash');

    if ($cluster)
      foreach (
        $query->select('AVG(lat) AS lat, AVG(lon) AS lon, COUNT(places.*) AS count, hashs.hash')
          ->group('hash') as $place
      ) $places[] = $place;
    else
      foreach (
        $query->select('places.*, hashs.hash') as $place
      ) $places[] = $place;

    return $places;
  }

  /**
   * show place
   *
   * @param ArrayObject $params
   * @param integer     $place
   */

  protected function show ($params, $place) {
    debug(' places->show ');
    return $this->place;
  }

  /**
   * create place
   *
   * @param  ArrayObject $params
   * @return mixed
   */

  protected function create ($params) {
    $place = $this->params('place', ['lat', 'lon', 'name', 'node', 'place']);

    if (($exists = Norm::places()->select('id, name')->where(
      '(lat = ? AND lon = ?) OR node = ?', round($place->lat, 5),
      round($place->lon, 5), ($place->node ?: 0)
    )->fetch())) return $this->code(422)->finish(['error'=>"already exists with id:$exists[id]"]);

    if (($current = Norm::places()->insert((array) $place))) {
      if (!$this->hash_it($place->lat, $place->lon, $current['id']))
        return $this->code(500)->finish(['error'=>'failed to hash place']);

      if (($uid = $this->session('id')))
        if (!Norm::user_places()->insert(['fk_users'=>$uid, 'fk_places'=>$current['id']]))
          return $this->code(500)->finish(['error'=>'failed to associate user']);

      return $current;
    }

    return $this->code(500)->finish(['error'=>'failed to create place']);
  }

  /**
   * update place, subscribe to
   *
   * @param  ArrayObject $params
   * @param  integer     $id
   * @return mixed
   */

  protected function update ($params, $id) {
    $place = $this->params('place', ['lat', 'lon', 'name', 'node', 'place']);
    $hash  = '';

    if (
      (isset($place->lat) && isset($place->lon)) &&
      ($place->lat = round($place->lat, 5)) && ($place->lon = round($place->lon, 5))
    )
      $hash = $this->geohash_encode($place->lat, $place->lon, 5);
    else
      unset($place->lat, $place->lon);

    $changed = ($place->name && $place->name != $this->place->name) ?: $changed;

    if ($this->place->update((array) $place)) {
      if(!$this->place->hashes()->update([
        'h2' => substr($hash, 0, -3), 'h3' => substr($hash, 0, -2),
        'h4' => substr($hash, 0, -1), 'h5' => $hash
      ])) return $this->code(500)->finish(['error'=>'failed to hash place']);

      if (!$this->place->user_places(['fk_users'=>$this->session('id')])->count()) {
        if (!Norm::user_places()->insert(['fk_places'=>$id, 'fk_users'=>$this->session['id']]))
          return $this->code(500)->finish(['error'=>'failed to associate user']);
        // versionate here ...

      }

      return;
    }

    return $this->code(500)->finish(['error'=>'failed to update place']);
  }

  /**
   * delete place
   *
   */

  protected function destroy () {
    if (!$this->place->delete()) return $this->code(500);
  }
}

?>

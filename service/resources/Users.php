<?php namespace resources;

/**
 * users resource
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

use \libs\Norm;

class Users extends \libs\Resourceful {
  use \libs\Authenticable;

  /**
   * construct
   *
   * @param string      $action
   * @param ArrayObject $params
   */

  function __construct ($action, $params) {
    debug(' users->__construct ', $action, $params);

    $this->is_user = ($id && $this->current_user($id));

    if (
      in_array($action, ['show', 'update', 'destroy']) &&
      (!isset($params->strategy) && !($params->action ?: 'nothing' == 'login'))
    )
      if (false === $this->user = Norm::users()->where(
          '(id = ? OR slug = ?) AND enabled = ?', (int)$params->user, $params->user, true
        )->select(
          'id, slug, nick, created_at'. ($this->is_user ? ', email, digest' : '')
        )->fetch()
      ) return $this->code(404);

    if ($action == 'update') {
      $validator = $this->validates()->rule(
        'required', ['user.nick', 'user.email']
      );

      if (!$validator->validate())
        return $this->code(400)->finish($validator->errors(), true);
    }
  }

  /**
   * list users
   */

  protected function index () {
    $users = [];

    foreach (
      Norm::users()->select('id, nick, slug, email')->where('enabled', true) as $user
    ) $users[] = $this->gravatar($user->jsonSerialize(), true);

    return $users;
  }

  /**
   * show user information / load logged user data into session, proxy to user creation ...
   *
   * @param ArrayObject $params
   * @param mixed       $id
   * @param string      $strategy
   */

  protected function show ($params, $id, $strategy=null) {
    if ($id == 'login' && !empty($strategy))
      return $this->create(null, $strategy);

    $user   = $this->gravatar($this->user->jsonSerialize(), !$this->is_user);
    $result = array_merge(
      $user, [
      'strategies' => array_keys($this->user->strategies()->fetchPairs('strategy')),
      'places'     => Norm::to_a($this->user->user_places()->where('places.fk_places = ?', 0)
        ->select('places.id, name, lat, lon')->limit(20)),
      'paths'      => Norm::to_a(Norm::paths()->where(
          'paths.fk_paths = ? AND user_paths.fk_users = ?', 0, $user['id']
        )->select('paths.id, fk_end, fk_start, places.id AS pid, name, lat, lon')->limit(20*2)),
      'reports'    => Norm::to_a( Norm::reports()->where(
          'report_users.fk_users = ? OR reports.fk_users = ?', $user['id'], $user['id']
        )->select(
          'reports.id, reports.fk_users, reports.fk_places, reports.fk_paths, report, ' .
          'places.id AS plid, paths.id AS pid, lat, lon, name, users.nick, email, users.slug'
        )->limit(20))
    ]);

    return $this->code(empty($result) ? 404 : 200)->finish($result);
  }

  /**
   * create user through oauth or oauth2 strategies
   *
   * @param ArrayObject $params
   * @param string      $login
   * @param string      $strategy
   */

  protected function create ($params, $login, $strategy=null) {
    if ($login != 'login') $strategy = $login;

    $user     = [];
    $provider = '';
    $params   = $this->params(['oauth_token', 'code', 'state', 'error']);

    debug(' users->create ', $params, $strategy);

    if (!(
      ($user = $this->oauth($strategy, $params['oauth_token'])) || ($user =
        $this->oauth2($strategy, $params['code'], $params['state']))
    )) return $this->code(404)->finish(['status'=>'Not found']);

    // check user is logged in, associates
    if ($this->session('id')) {
      Norm::strategies()
        ->where('fk_users = ? AND strategy = ?', [$this->session['id'], $strategy])->delete();
      Norm::strategies()->insert([
        'fk_users'=>$this->session['id'], 'strategy'=>$strategy, 'identifier'=>$user->id
      ]);

      exit(header('Location: /users/'. $this->session['slug']));
    }

    // check identifier exists, login
    foreach (
      Norm::users()->select(
        'users.slug, users.id AS uid, email, identifier AS si, strategies.strategy AS st'
      )->where('identifier = ? OR email = ?', $user->id, $user->email) as $item
    ) {
      $provider = $user->email == $item['email'] ? $item['st'] : $provider;
      if ($item['si'] == $user->id) {
        $this->session('id', $item['uid']);
        exit(header('Location: /users/'. $item['slug']));
      }
    }

    if (empty($provider) && ($slug = $this->slugify($user->name))) {
      if (Norm::users()->where('slug LIKE ?', $slug.'%')->count())
        $slug .= '-'.uniqid()[rand(0,13)].uniqid()[rand(0,13)].uniqid()[rand(0,13)];

      if (($last = Norm::users()->insert([
          'slug'=>$slug, 'nick'=>$user->name, 'email'=>$user->email
        ])) && Norm::strategies()->insert([
          'fk_users'=>$last['id'], 'strategy'=>$strategy, 'identifier'=>$user->id
        ]) &&
        $this->session('id', $last['id'])
      ) exit(header('Location: /users/'. $last['slug']));

      $this->session['error'] = 'failed to authenticate user using '. $strategy;
    } else {
      $this->session['error'] = 'user already exists associated with '. $provider;
    }

    exit(header('Location: /login'));
  }

  /**
   * update user data
   *
   * @param ArrayObject $params
   * @param mixed       $id
   */

  protected function update ($params, $id) {
    $strategies   = $this->params('user', ['strategies' => []]);
    $user         = $this->params('user', ['nick', 'email', 'digest']);
    $user['slug'] = $this->slugify($user['nick']);

    if (!$this->exists($user, $id) && !$this->user->update($user))
      return $this->code(500)->finish(['status' => 'Failed to update user']);

    if (
      is_array($strategies['strategies']) && !empty($dels = array_diff(array_keys(
        $this->user->strategies()->fetchPairs('strategy')), $strategies['strategies']))
    ) if (!$this->user->strategies()->where('strategy', $dels)->delete())
      return $this->code(500)->finish(['status' => 'Failed to update user']);

    return;
  }

  /**
   * delete user / logout
   *
   * @param ArrayObject $params
   * @param mixed       $id
   */

  protected function destroy ($params, $id=null) {
    // if (!$this->session('id')) 403?
    if (!$id) debug(' should log out ');
    if (!$this->user->delete()) return $this->code(500);
  }

  /**
   * check if user exists
   *
   * @array  $data
   * @string $id
   * @return boolean
   */

  private function exists ($data, $id=null) {
    foreach (
      Norm::users()->select('email, nick, '.(is_numeric($id) ? 'id' : 'slug').' AS pk')
        ->where('email = ? OR nick = ?', $data['email'], $data['nick']) as $user
    ) {
      if ($user['email'] == $data['email'] && (!$id || $id != $user['pk']))
        return $this->code(409)->finish(['user.email' => 'already exists'], true);
      if ($user['nick'] == $data['nick'] && (!$id || $id != $user['pk']))
        return $this->code(409)->finish(['user.nick' => 'already exists'], true);
    }
    return false;
  }

  /**
   * set gravatar
   *
   * @param  array   $user
   * @param  boolean $mailless
   * @return array
   */

  private function gravatar ($user=[], $mailless=false) {
    $url = 'https://www.gravatar.com/avatar/%s?s=80&d=mm&r=g';
    if ($user && isset($user['email'])) $user = array_merge(
      $user, ['avatar' => sprintf($url, md5(strtolower(trim($user['email']))))]);
    if ($mailless) unset($user['email']);
    return $user;
  }

  /**
   * slugify user nick
   *
   * @param  string $nick
   * @param  string $separator
   * @return string
   */

  private function slugify ($nick, $separator='-') {
    $nick = trim(preg_replace('~[^\\pL\d\s]+~u', $separator ?: '-', $nick));
    $subs = [
      'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'AE', 'Ç'=>'C', 'È'=>'E',
      'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ð'=>'D', 'Ñ'=>'N',
      'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U',
      'Ü'=>'U', 'Ý'=>'Y', 'ß'=>'s', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a',
      'æ'=>'ae', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
      'ï'=>'i', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
      'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'ÿ'=>'y', 'Ā'=>'A', 'ā'=>'a', 'Ă'=>'A', 'ă'=>'a',
      'Ą'=>'A', 'ą'=>'a', 'Ć'=>'C', 'ć'=>'c', 'Ĉ'=>'C', 'ĉ'=>'c', 'Ċ'=>'C', 'ċ'=>'c', 'Č'=>'C',
      'č'=>'c', 'Ď'=>'D', 'ď'=>'d', 'Đ'=>'D', 'đ'=>'d', 'Ē'=>'E', 'ē'=>'e', 'Ĕ'=>'E', 'ĕ'=>'e',
      'Ė'=>'E', 'ė'=>'e', 'Ę'=>'E', 'ę'=>'e', 'Ě'=>'E', 'ě'=>'e', 'Ĝ'=>'G', 'ĝ'=>'g', 'Ğ'=>'G',
      'ğ'=>'g', 'Ġ'=>'G', 'ġ'=>'g', 'Ģ'=>'G', 'ģ'=>'g', 'Ĥ'=>'H', 'ĥ'=>'h', 'Ħ'=>'H', 'ħ'=>'h',
      'Ĩ'=>'I', 'ĩ'=>'i', 'Ī'=>'I', 'ī'=>'i', 'Ĭ'=>'I', 'ĭ'=>'i', 'Į'=>'I', 'į'=>'i', 'İ'=>'I',
      'ı'=>'i', 'Ĳ'=>'IJ', 'ĳ'=>'ij', 'Ĵ'=>'J', 'ĵ'=>'j', 'Ķ'=>'K', 'ķ'=>'k', 'Ĺ'=>'L', 'ĺ'=>'l',
      'Ļ'=>'L', 'ļ'=>'l', 'Ľ'=>'L', 'ľ'=>'l', 'Ŀ'=>'L', 'ŀ'=>'l', 'Ł'=>'l', 'ł'=>'l', 'Ń'=>'N',
      'ń'=>'n', 'Ņ'=>'N', 'ņ'=>'n', 'Ň'=>'N', 'ň'=>'n', 'ŉ'=>'n', 'Ō'=>'O', 'ō'=>'o', 'Ŏ'=>'O',
      'ŏ'=>'o', 'Ő'=>'O', 'ő'=>'o', 'Œ'=>'OE', 'œ'=>'oe', 'Ŕ'=>'R', 'ŕ'=>'r', 'Ŗ'=>'R', 'ŗ'=>'r',
      'Ř'=>'R', 'ř'=>'r', 'Ś'=>'S', 'ś'=>'s', 'Ŝ'=>'S', 'ŝ'=>'s', 'Ş'=>'S', 'ş'=>'s', 'Š'=>'S',
      'š'=>'s', 'Ţ'=>'T', 'ţ'=>'t', 'Ť'=>'T', 'ť'=>'t', 'Ŧ'=>'T', 'ŧ'=>'t', 'Ũ'=>'U', 'ũ'=>'u',
      'Ū'=>'U', 'ū'=>'u', 'Ŭ'=>'U', 'ŭ'=>'u', 'Ů'=>'U', 'ů'=>'u', 'Ű'=>'U', 'ű'=>'u', 'Ų'=>'U',
      'ų'=>'u', 'Ŵ'=>'W', 'ŵ'=>'w', 'Ŷ'=>'Y', 'ŷ'=>'y', 'Ÿ'=>'Y', 'Ź'=>'Z', 'ź'=>'z', 'Ż'=>'Z',
      'ż'=>'z', 'Ž'=>'Z', 'ž'=>'z', 'ſ'=>'s', 'ƒ'=>'f', 'Ơ'=>'O', 'ơ'=>'o', 'Ư'=>'U', 'ư'=>'u',
      'Ǎ'=>'A', 'ǎ'=>'a', 'Ǐ'=>'I', 'ǐ'=>'i', 'Ǒ'=>'O', 'ǒ'=>'o', 'Ǔ'=>'U', 'ǔ'=>'u', 'Ǖ'=>'U',
      'ǖ'=>'u', 'Ǘ'=>'U', 'ǘ'=>'u', 'Ǚ'=>'U', 'ǚ'=>'u', 'Ǜ'=>'U', 'ǜ'=>'u', 'Ǻ'=>'A', 'ǻ'=>'a',
      'Ǽ'=>'AE', 'ǽ'=>'ae', 'Ǿ'=>'O', 'ǿ'=>'o'
    ];
    $nick = strtr($nick, $subs);
    return preg_replace('~[^-\w]+~', $separator ?: '-', strtolower($nick));
  }
}

?>

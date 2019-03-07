<?php namespace libs;

/**
 * authentication, oauth strategies
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 *
 */

use OAuth;

trait Authenticable {

  private $strategies = [
    /* oauth */
    'osm' => [
      //'base' => ('http://api06.'. (DEV ? 'dev.' : '') .'openstreetmap.org/'), 'method' => 'oauth'
      'base' => ('https://master.apis.'. (DEV ? 'dev.' : '') .'openstreetmap.org/'), 'method' => 'oauth'
    ],
    'twitter' => [
      'base' => 'https://api.twitter.com/', 'method' => 'oauth'
    ],
    /* oauth2 */
    'github' => [
      'authorization_url' => 'https://github.com/login/oauth/authorize',
      'token_url'         => 'https://github.com/login/oauth/access_token',
      'user_info'         => 'https://api.github.com/user', 'scope' => 'user', 'method' => 'oauth2'
    ],
    'google' => [
      'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth', 'method' => 'oauth2',
      'token_url'         => 'https://www.googleapis.com/oauth2/v4/token',
      'user_info'         => 'https://www.googleapis.com/oauth2/v1/userinfo',
      'scope'             => 'https://www.googleapis.com/auth/userinfo.profile'
    ],
    'facebook' => [
      'authorization_url' => 'https://www.facebook.com/dialog/oauth',
      'token_url'         => 'https://graph.facebook.com/v2.3/oauth/access_token',
      'user_info'         => 'https://graph.facebook.com/me',
      'scope'             => 'public_profile,email', 'method' => 'oauth2'
    ]
  ];

  /**
   * provides oauth strategy
   *
   * @param  string $strategy
   * @param  string $oauth_token
   * @return mixed
   */

  function oauth ($strategy, $oauth_token) {
    if (
      empty(getenv(strtoupper($strategy) .'_KEY')) ||
      !(isset($this->strategies[$strategy]) && $this->strategies[$strategy]['method'] == 'oauth')
    ) return false;

    $base     = $this->strategies[$strategy]['base'];
    $strategy = strtoupper($strategy);
    $client   = new OAuth(
      getenv($strategy .'_KEY'), getenv($strategy .'_SECRET'),
      OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI
    );

    debug(
      '[strategy]  [base]                     [key]                 [secret]',
      $strategy .'  '. $base .'  '. getenv($strategy .'_KEY') .'  '. getenv($strategy .'_SECRET')
    );

    if (empty($oauth_token)) {
      debug(' oauth_token is empty ');
      $info = $client->getRequestToken($base .'oauth/request_token');
      debug(' token request response ... ', $info);
      $this->session($info);
      exit(header("Location: ${base}oauth/authorize?oauth_token=$info[oauth_token]"));
    }

    debug(' last tokens: ', $this->session(['oauth_token', 'oauth_token_secret']));

    $client->setToken($this->session('oauth_token'), $this->session('oauth_token_secret'));
    $info = $client->getAccessToken($base .'oauth/access_token');

    debug(' user tokens: ', $info);

    if (isset($info['user_id']) && isset($info['screen_name']))
      return (object) ['id' => $info['user_id'], 'name' => $info['screen_name'], 'email' => ''];

    $client->setToken($info['oauth_token'], $info['oauth_token_secret']);
    $client->fetch($base .'api/0.6/user/details');

    if (
      preg_match_all('#([^ =]+) *= *[\'"]?([^<\'"]+)[\'"]?#', $client->getLastResponse(), $attrs)
        && $attrs[1] = array_flip($attrs[1])
    ) return (object) [
        'id'    => $attrs[2][$attrs[1]['id']] ?: '',
        'name'  => $attrs[2][$attrs[1]['display_name']] ?: '',
        'email' => ''
      ];

    return false;
  }

  /**
   * provides oauth2 strategy
   *
   * @param  string $strategy
   * @param  string $code
   * @param  string $state
   * @return mixed
   */

  function oauth2 ($strategy, $code = '', $state = '') {

    if (
      empty(getenv(strtoupper($strategy) .'_ID')) ||
      !(isset($this->strategies[$strategy]) && $this->strategies[$strategy]['method'] == 'oauth2')
    ) return false;

    if (!empty($this->session('access_token')))
      return $this->api_request($this->strategies[$strategy]['user_info']);

    $secret = getenv(strtoupper($strategy) .'_SECRET');
    $id     = getenv(strtoupper($strategy) .'_ID');
    $itself = 'http'. ($_SERVER['https'] ? 's' : '') .
      '://'. $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');

    if (!empty($code)) {
      debug(' code: '. $code .', state: '. $state);
      if (empty($state) || $this->session('state') != $state)
        exit(header("Location: $_SERVER[REQUEST_URI]"));

      $token_url = $this->strategies[$strategy]['token_url'];
      $token     = $this->api_request($token_url, [
        'state'        => $state,  'code'      => $code, 'grant_type'    => 'authorization_code',
        'redirect_uri' => $itself, 'client_id' => $id,   'client_secret' => $secret
      ]);
      $this->session('access_token', $token->access_token);

      exit(header("Location: $_SERVER[REQUEST_URI]")); // exit here ???
    }

    //if ($error)
    $scope    = $this->strategies[$strategy]['scope'];
    $auth_url = $this->strategies[$strategy]['authorization_url'];
    $state    = $this->session(
      'state', hash('sha256', microtime(true).rand().$scope.$auth_url.$itself)
    );
    unset($this->session['access_token']);

    debug(' [oauth2] should redirect ');

    exit(header('Location: '. $auth_url .'?'. http_build_query([
      'scope'        => $scope,  'state'     => $state, 'response_type' => 'code',
      'redirect_uri' => $itself, 'client_id' => $id
    ])));
  }

  /**
   * should validates current user
   *
   * @param  object $user
   * @return boolean
   */

  protected function current_user ($id=null) {
    if ($id) return true;
  }

  /**
   * api request
   *
   * @param  string $url
   * @param  mixed  $post
   * @param  array  $headers
   * @return object
   */

  private function api_request ($url, $post = false, $headers = []) {
    if (empty($url)) throw new Exception('url is empty');

    debug(' [url]                    [fields]', "$url  $post");
    $version = 'curl-php/'. curl_version()['version'];
    $handler = curl_init($url);

    curl_setopt_array($handler, [
      CURLOPT_ENCODING       => '',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => array_merge([
        'Accept: application/json', 'User-Agent: '.$version], ($this->session('access_token') ?
          ['Authorization: Bearer '. $this->session['access_token']] : [])
      )] + ($post ? [CURLOPT_POSTFIELDS => http_build_query($post)] : [])
    );

    $response = curl_exec($handler);
    curl_close($handler);

    if ($this->session['access_token']) unset($this->session['access_token']);

    debug(' response  ', $response);
    return json_decode($response);
  }
}

?>

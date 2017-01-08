<header>
  <h1>Peabiru</h1>
  <h2>Help connecting places now!</h2>
  <div class='login-btns'>
    {{#if env.OSM_KEY}}
      <a href='#login/osm' class='icon-osm-circled' title='Open Street Map'></a>
    {{/if}}
    {{#if env.FACEBOOK_ID}}
      <a href='#login/facebook' class='icon-facebook-circled' title='Facebook'></a>
    {{/if}}
    {{#if env.GITHUB_ID}}
      <a href='#login/github' class='icon-github-circled' title='Github'></a>
    {{/if}}
    {{#if env.TWITTER_KEY}}
      <a href='#login/twitter' class='icon-twitter-circled' title='Twitter'></a>
    {{/if}}
    {{#if env.GOOGLE_ID}}
      <a href='#login/google' class='icon-gplus-circled' title='Google'></a>
    {{/if}}
  </div>
</header>

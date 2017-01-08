var noop  = window.noop = function () {},
   slice  = window.slice = Array.prototype.slice,
 isRetina = window.isRetina = function () {
    if (window.matchMedia) {
      var mq = window.matchMedia(
        "only screen and (min--moz-device-pixel-ratio: 1.3), only screen and " +
        "(-o-min-device-pixel-ratio: 2.6/2), only screen and " +
        "(-webkit-min-device-pixel-ratio: 1.3), only screen and (min-device-pixel-ratio: 1.3)," +
        " only screen and (min-resolution: 1.3dppx)"
      );
      return (mq && mq.matches || (window.devicePixelRatio > 1));
    }
  };

if (!window.console) window.console = {log:noop, error:noop};

var riot = window.riot = require('libs/riot'),
  Router = require('libs/router'),
     Api = require('libs/api'),
   Zepto = require('libs/zepto'),
   tags  = require('tags');

var router = window.router = new Router();
var app    = riot.mount('body', 'app', {events: Api.events})[0];

router.add({
  '/': function () {
    app.update({home: true});
  },
  '/places': function () {
    app.update({home: false, places: true});
  },
  '/isolated': function () {
    app.update({home: false, isolated: true});
  }
});

router.start();

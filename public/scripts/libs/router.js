/**
 * simple router
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 */

function Router () {
  this.__routes = [];
  this.isReady  = ['interactive', 'complete'];
  this.__first  = false;
  this.history  = [];
}

Router.prototype = (function () {

  function subPart (part) {
    if ((part[0] === ':' || part.indexOf('(') > -1))
      return (part = part.replace(/:?\w*/, '')) !== '' ? part : '((?:(?!\\/).)+?)';
    return part;
  }

  function regexTransfer (path) {
    if (path == '' || path == '/') return new RegExp('^\\/?$', 'i');
    var parts = path.split('/'), regexParts = [], part;

    for (var i = 0; i < parts.length; i++)
      if ((part = parts[i]) && part.length > 0)
        regexParts.push(subPart(part));

    return new RegExp('^\\/'+ (regexParts.join('\\/')) +'\\/?$', 'i');
  }

  return {
    constructor: Router,

    add: function () {
      if (arguments.length < 1) return;
      if (arguments.length == 2) {
        if (typeof arguments[0] == 'string' && typeof arguments[1] == 'function')
          this.__routes.push([regexTransfer(arguments[0]), arguments[1]]);
      } else if (!(arguments[0] instanceof Array)) {
        for (var key in arguments[0])
          if (typeof key == 'string' && typeof arguments[0][key] == 'function')
            this.__routes.push([regexTransfer(key), arguments[0][key]]);
      }
    },

    exec: function (path) {
      console.log(' exec ', path);
      path = path.split('?')[0].split('#')[0];
      if (this.__routes.length === 0) { console.log('no routes'); return; }
      var matches;
      for (var i = 0; i < this.__routes.length; i++)
        if ((matches = path.match(this.__routes[i][0])) !== null) {
          console.log(' matched ');
          window.dispatchEvent(new Event('load'));
          return this.__routes[i][1].apply(null, matches.slice(1));
        }
    },

    emit: function (path) {
      console.log(' emit ', path);
      if (Object.keys(this.history).length > 0) {
        var key;
        for (key in this.history);
        history.replaceState({path: this.history[key]}, '', this.history[key]);
        delete this.history[key];
      }

      this.exec(location.pathname + location.search + location.hash);
    },

    start: function () {
      var self = this;
      // readiness
      document.addEventListener('readystatechange', function (e) {
        if (self.isReady.indexOf(document.readyState) > -1 && !self._first)
          self._first = !!setTimeout(function () {
            window.addEventListener('popstate', self.emit.bind(self), false);
            self.go(location.pathname + location.search + location.hash);
          }, 100);
      });

      // capture clicks
      document.body.addEventListener('click', function (e) {
        e          = e || event;
        var l      = window.location;
        var domain = l.protocol +'//'+ l.hostname +
          ((l.port != '' && l.port != 80) ? ':'+l.port : ''), anchor;

        if (e.target && (anchor = e.target)) do {
            if (anchor.nodeName.toLowerCase() == 'a') break;
          } while (anchor = anchor.parentNode);

        if (!(
          anchor && anchor.href.indexOf(domain) > -1 &&
          (anchor.target == '' || anchor.target != '_self')
        )) return;

        e.preventDefault();
        self.go(anchor.href.replace(domain, ''));
      });
    },

    stop: function () {
      window.removeEventListener('popstate', this.emit.bind(this), false);
    },

    go: function (path) {
      console.log(' go to ', path);
      var part = /([^\?\#]*)(\??[^#]*)(\#?.*)/.exec(path);

      if (Object.keys(this.history).length == 0 && (this.history[part[1] + part[2]] = path))
        history.pushState({path: path}, document.title, path);
      else
        if (!this.history[part[1] + part[2]] && (this.history[part[1] + part[2]] = path))
          history.pushState({path: path}, document.title, path);
        else
          history.replaceState({path: path}, document.title, path);

      this.exec(path);
    },

    back: function () {
      history.back();
    }
  };
}());

module.exports = Router

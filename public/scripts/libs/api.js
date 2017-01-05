/**
 * api abstraction
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 */

var geohash   = require('./geohash');
var waterfall = require('./waterfall');
var fragments = window.fragments = function (value) {
  var hash   = decodeURIComponent(value.substring(1)),
   variables = hash.split('&'),
     results = {};

  for (i = 0; i < variables.length; ++i) {
    param = variables[i].split('=');
    if (param.length == 2)
      results[param[0]] = param[1];
    else
      results[Object.keys(results).length] = param[0];
  }

  return results;
}

var Api = {
  map: null,
  context: null,
  loadInterval: 0,
  circleMarkers: {},
  pinMarkers: {},
  encode: geohash.encode.bind(geohash),
  events: riot.observable(),
  markers: new L.FeatureGroup(),

  start: function (map) {
    var self = this;
    this.map = map;

    this.map.addLayer(this.markers);
    this.map.on('dragstart zoomstart movestart', this.loading.bind(this));
    this.map.on('dragend zoomend moveend',       this.bounds.bind(this));

    Zepto(window).on('load', function (e) {
      console.log(' on load -> zepto ');
      if (!history.state || location.pathname != history.state.last.pathname)
        self.context = location.pathname.substring(1);

      if (
        (!history.state || location.hash != history.state.last.hash)
        && (hash = fragments(location.hash))
      ) {
        if (hash && hash[0] && !isNaN(hash[0])) // if first is numeric -> goToPlace
          return self.goToPlace.call(self, parseInt(hash[0]));

        // if first is composed gh -> load places with context
        if (hash && hash[0] && hash[0].indexOf('-') > -1)
          return self.loadPlaces.apply(self, hash[0].split('-'));
      }

      if (!history.state || location.search != history.state.last.search)
        console.log(' search changed !!! ? ');
    });
  },

  loadPlaces: function (from, to, zoom) {
    options = {method: 'GET', url: '/places/g/'+ from +(to.length == 0 ? '' : '-'+to)+ '.json'};
    if (this.context == 'isolated') options.data = {isolated: true};
    Zepto.ajax(options).then(
      (zoom || 0) > 12 ? this.addPins.bind(this) : this.addCircles.bind(this)
    );
  },

  goToPlace: function (id) {
    waterfall([
      function (cb) {
        if (window.places && window.places[id]) return cb(null, window.places[id]);
        if (!window.places) window.places = [];
        Zepto.ajax({
          method: 'GET', url: '/places/'+ id +'.json'
        }).then(function (data) {
          cb(null, (window.places[id] = data));
        });
      }
    ], function (err, data) {
      console.log(' should pan to: ', data);
      this.map.panTo([data.lat, data.lon], {animate:true, duration:1.6, easeLinearity:0.4});
    }.bind(this));
  },

  loading: function (e) {
    this.events.trigger('loading')
  },

  bounds: function (e) {
    console.log(' should get bounds ', e);
    var bounds   = this.map.getBounds(),
         topLeft = bounds.getNorthWest(),
     bottomRight = bounds.getSouthEast(),
            zoom = this.map.getZoom();

    var size = 8 > zoom ? 2 : (10 > zoom ? 3 : (12 > zoom ? 4 : 5));
    var from = this.encode(topLeft.lat, topLeft.lng, size),
          to = this.encode(bottomRight.lat, bottomRight.lng, size);

    for (var i = 0; i < from.length; ++i)
      if (from[i] != to[i] && (to = to.substring(i)) !== null) break;

    if (e.type == 'zoomend') {
      console.log(' should clear layers ');
      this.markers.clearLayers();
      this.pinMarkers = this.circleMarkers = {};
    }

    console.log(' clear old loadPlaces ... do again! ');
    clearTimeout(this.loadInterval);
    this.loadInterval = setTimeout(this.loadPlaces.bind(this, from, to, zoom), 250);
  },

  addCircles: function (data) {
    for (var i = 0; i < data.length; ++i)
      if (!this.circleMarkers[data[i].hash] && (this.circleMarkers[data[i].hash] = true))
        this.markers.addLayer(L.circle(
          [data[i].lat, data[i].lon],
          ((x = (50 * (data[i].count || 2))) < 200 ? 200 : x),
          {color: 'red', fillColor: 'red'}
        ));

    this.events.trigger('loaded');
  },

  addPins: function (data) {
    var icon = L.divIcon({className: 'icon-location'});
    for (var i = 0; i < data.length; ++i)
      if (!this.pinMarkers[data[i].id] && (this.pinMarkers[data[i].id] = true)) {
        this.markers.addLayer(L.marker([data[i].lat, data[i].lon], {icon: icon}));
      }

    this.events.trigger('loaded');
  }
}

module.exports = window.Api = Api

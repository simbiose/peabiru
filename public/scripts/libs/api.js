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

  for (i = 0; i < variables.length; ++i)
    if ((param = variables[i].split('=')) && param.length == 2)
      results[param[0]] = param[1];
    else
      results[Object.keys(results).length] = param[0];

  return results;
}
var months = ['Jan', 'Fev', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dec'];
var fmtDate = function (strdate) {
  var date = new Date(Date.parse(strdate));
  return date.getDate() +'/'+ months[date.getMonth()] +'/'+ ('0'+ date.getFullYear()).substr(-2)
    +' '+ ('0'+ date.getHours()).substr(-2) +':'+ ('0'+ date.getMinutes()).substr(-2)
    +':'+ ('0'+ date.getSeconds()).substr(-2);
};

var Api = {
  map: null,
  context: null,
  mapElement: null,
  lockUser: false,
  userInteraction: false,
  loadInterval: 0,
  circleMarkers: {},
  pinMarkers: {},
  pinIcons: {},
  panOptions: { animate:true, duration:1.6, easeLinearity:0.4 },
  viewOptions: { pan: this.panOptions, zoom: {animate:true}, animate:true },
  placeTypes: {
    city: true, town: true, village: true, hamlet: true,
    suburb: true, farm: true, isolated_dwelling: true
  },
  events: riot.observable(),
  markers: {},
  nonIsolatedMarkers: [],
  decode: geohash.decode.bind(geohash),
  encode: function (lat, lon, size, ref) {
    var result = geohash.encode(lat, lon, size);

    if (ref)
      for (var i = 0; i < ref.length; ++i)
        if (ref[i] != result[i] && (result = result.substring(i)) !== null)
          break;

    return result;
  },

  start: function (map) {
    var self = this;
    this.map = map;

    this.mapElement = Zepto('#bg-map');

    console.log(' loading ... ... ');

    for (key in this.placeTypes) {
      this.markers[key]    = new L.FeatureGroup();
      this.pinMarkers[key] = [];

      this.map.addLayer(this.markers[key]);
    }

    this.markers.circles = new L.FeatureGroup();
    this.markers.user    = new L.FeatureGroup();

    this.map.addLayer(this.markers.circles)
      .addLayer(this.markers.user);

    var _types = Object.keys(this.placeTypes),
        total = _types.length;

    for (var i = 0; i < total * 2; ++i)
      this.pinIcons[_types[i % total] + (i >= total ? '-red' : '')] = L.divIcon({
       className: 'icon-location pin-'+ _types[i % total] + (i >= total ? ' pin-reddish': '')
      });

    this.map.on('dragstart zoomstart movestart', this.loading.bind(this));
    this.map.on('dragend zoomend moveend',       this.bounds.bind(this));

    Zepto(window).on('load', function (e) {
      console.log(' on load -> zepto ', self.context);
      var contextChanged = false;

//      if (
//        self.context != null && self.context != '' && self.context != location.pathname.substring(1)
//      ) contextChanged = true;
//
      if (self.context == null || self.context != location.pathname.substring(1))
        contextChanged = true;

      console.log( '  context changed? '+ (contextChanged ? ' yeah ' : ' no ') );

      self.context = location.pathname.substring(1) || 'places';

      if (location.hash != '' && (hash = fragments(location.hash))) {
        if (hash[0] && !isNaN(hash[0]))
          return self.goToPlace.call(self, parseInt(hash[0]));

        console.log(' here ');

        if (hash[0] && hash[0].indexOf('-') > -1 && (parts = hash[0].split('-'))) {

          console.log(' here 2 ', self.userInteraction);

          if (!self.userInteraction && !self.isAt(parts[0], parts[1], parts[2])) {

            console.log(' here 3 ');

            if ((changes = self.filterChanged()) || (contextChanged && self.context == 'isolated')) {

              console.log(' here changed ');

              self.clearMarkers(changes, contextChanged);

              clearTimeout(self.loadInterval);
              self.loadInterval = setTimeout(
                self.loadPlaces.bind(self, parts[0], parts[1], parts[2]), 150
              );
            }

            setTimeout(function () {
              self.map.setView(
                self.getCenter(parts[0], parts[1]), parseInt(parts[2]) || 13, self.viewOptions
              );
            }, 100);

            self.lockUser = true;
            self.map.once('moveend zoomend', self.unlockUser.bind(self));

            return;
          } else {

            console.log(' here 4 ');

            if ((changes = self.filterChanged()) || (contextChanged && self.context == 'isolated'))
              self.clearMarkers(changes, contextChanged);

            clearTimeout(self.loadInterval);

            return self.loadInterval = setTimeout(
              self.loadPlaces.bind(self, parts[0], parts[1], parts[2]), 150
            );
          }
        }
      }

      if (contextChanged) {
        clearTimeout(self.loadInterval);

        return self.loadInterval = setTimeout(self.loadPlaces.bind(self), 150);
      }

    });
  },

  loadPlaces: function (from, to, zoom) {
    if (!from)
      [from, to, zoom] = this.getBounds();

    var options =
      { method: 'GET', url: '/places/g/'+ from +(to.length == 0 ? '' : '-'+to)+ '.json', data: {} },
        types   = JSON.parse(localStorage.getItem('peabiru-types')) || [];

    if (types.length < 7 && types.length > 0) options.data.types    = types.join(',');
    if (this.context == 'isolated')           options.data.isolated = true;

    Zepto.ajax(options).then(
      (zoom || 0) > 10 ? this.addPins.bind(this) : this.addCircles.bind(this)
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
      setTimeout(
        this.map.setView.bind(this.map, [data.lat, data.lon], 13, this.viewOptions), 150
      );
    }.bind(this));
  },

  loading: function (e) {

    console.log( e.type, ' [loading] ' );

    if (this.lockUser) return;

    if (!this.userInteraction && e.type == 'dragstart')
      this.userInteraction = true;

    if (e.type == 'dragstart')
      this.mapElement.css('cursor', 'move');

    this.userInteraction = true;
    this.events.trigger('loading')
  },

  bounds: function (e) {
    if (this.lockUser) return;

    console.log(' bounds [] [] ');

    var bounds = this.getBounds();

    this.userInteraction = false;

    if (e.type == 'zoomend')
      this.clearMarkers();

    if (e.type == 'dragend' || e.type == 'zoomend') {
      this.mapElement.css('cursor', 'pointer');

      router.go(
        location.pathname + location.search +'#'+ bounds[0] +'-'+ bounds[1] +'-'+ bounds[2]
      );
    }
/*
    clearTimeout(this.loadInterval);
    //this.loadInterval = setTimeout(this.loadPlaces.bind(this, from, to, zoom), 150);
    this.loadInterval = setTimeout(this.loadPlaces.bind(this, bounds[0], bounds[1], bounds[2]), 150);
*/
  },

  getBounds: function (size) {
    var bounds  = this.map.getBounds(),
        topLeft = bounds.getNorthWest(),
    bottomRight = bounds.getSouthEast(),
           zoom = this.map.getZoom();

    size = size || (8 > zoom ? 2 : (10 > zoom ? 3 : (12 > zoom ? 4 : 5)));

    return [
      (from = this.encode(topLeft.lat, topLeft.lng, size)),
      this.encode(bottomRight.lat, bottomRight.lng, size, from),
      zoom
    ];
  },

  clearMarkers: function (types, switchIsolated) {
    console.log(' cleaning markers ', types);

    types = types || (keys = Object.keys(this.placeTypes)).push('circles') && keys;

/*    if (switchIsolated)
      for (var i = 0; i < types.length; ++i)
        if (this.pinMarkers[types[i]])
          for (var
*/
    for (var i = 0; i < types.length; ++i) {
//      this.markers[types[i]].clearLayers();

      if (this.pinMarkers[types[i]])
        for (var j = 0, pins = this.pinMarkers[types[i]]; j < pins.length; ++j)
          if (this.pinMarkers[pins[j]]) {
            if (switchIsolated && this.nonIsolatedMarkers.indexOf(pins[j]) > -1)
              this.markers[types[i]].removeLayer(this.pinMarkers[pins[j]]);

            delete this.pinMarkers[pins[j]];
          }

      if (!switchIsolated) {
        this.markers[types[i]].clearLayers();
        this.pinMarkers[types[i]] = [];
      }
    }

    if (types.length) this.circleMarkers = {};
  },

  addCircles: function (data) {
    var zoom = this.map.getZoom(),
       delta = (zoom < 5 ? 4000 : (zoom < 8 ? 2000 : (zoom < 11 ? 1000 : 100))) * 4,
       color = (this.context == 'isolated' ? 'tomato' : '#ff6200');

    for (var i = 0; i < data.length; ++i)
      if (!this.circleMarkers[data[i].hash] && (this.circleMarkers[data[i].hash] = true))
        this.markers.circles.addLayer(L.circle(
          [data[i].lat, data[i].lon],
          ((x = (100 * (data[i].count || 2))) < delta ? delta : x),
          {color: color, fillColor: color, stroke: false, fillOpacity: 0.6}
        ));

    this.events.trigger('loaded');
  },

  addPins: function (data) {

    for (var i = 0; i < data.length; ++i)
      if (
        !this.pinMarkers[data[i].id] && (item = data[i]) &&
        (this.pinMarkers[item.place].push(item.id))
      ) {
        if (!item.isolated)
          this.nonIsolatedMarkers.push(item.id);

        this.markers[item.place].addLayer((
          this.pinMarkers[item.id] = L.marker(
            [ item.lat, item.lon ],
            { icon: this.pinIcons[item.place + (!!item.isolated ? '-red' : '')] }
          ).bindPopup(
            ' <a href="https://www.openstreetmap.org/node/'+ item.node +'" target="_blank">'+
            item.name + '</a> <i>'+ item.place +'</i> <p>criado em: ' +
            fmtDate(item.created_at) + '</p>'
          )
        ));
      }

    if (
      location.hash != '' && (hash = fragments(location.hash)) &&
      (hash[0] && !isNaN(hash[0])) && (marker = this.pinMarkers[parseInt(hash[0])])
    ) marker.openPopup();

    this.events.trigger('loaded');
  },

  isAt: function (from, to, zoom) {
    var bounds = this.getBounds(from.length);

    if (from.length == to.length)
      for (var i = 0; i < from.length; ++i)
        if (from[i] != to[i] && (to = to.substring(i)) !== null) break;

    return zoom == bounds[2] && from == bounds[0] && to == bounds[1];
  },

  getCenter: function (from, to) {
    if (to == '') return this.decode(from);
    to = from.substring(0, from.length - to.length) + to;
    var _from = this.decode(from), _to = this.decode(to);

    return this.midpoint(_from.lat, _from.lon, _to.lat, _to.lon);
  },

  midpoint: function (lat1, lng1, lat2, lng2) {
    var lat1 = this.deg2rad(lat1),
        lng1 = this.deg2rad(lng1),
        lat2 = this.deg2rad(lat2),
        lng2 = this.deg2rad(lng2);

    var dlng = lng2 - lng1,
        Bx   = Math.cos(lat2) * Math.cos(dlng),
        By   = Math.cos(lat2) * Math.sin(dlng),
        lat3 = Math.atan2(
          Math.sin(lat1) + Math.sin(lat2),
          Math.sqrt((Math.cos(lat1) + Bx) * (Math.cos(lat1) + Bx) + By * By)
        );

    lng3 = lng1 + Math.atan2(By, (Math.cos(lat1) + Bx));

    return [(lat3 * 180) / Math.PI, (lng3 * 180) / Math.PI];
  },

  filterChanged: function () {
    var types = JSON.parse(localStorage.getItem('peabiru-types')) || [],
      keys    = Object.keys(this.placeTypes),
      total   = keys.filter(function (key) {
        return this.placeTypes[key]; }.bind(this)
      ),
      diff    = keys.filter(function (key) {
        return this.placeTypes[key] && types.indexOf(key) == -1 }.bind(this)
      );

    for (var i = 0; i < keys.length; ++i)
      this.placeTypes[keys[i]] = types.indexOf(keys[i]) > -1;

    return total.length == types.length ? false : diff;
  },

  unlockUser: function () {
    this.lockUser = false;
  },

  deg2rad: function (degrees) {
    return degrees * Math.PI / 180;
  }
}

module.exports = window.Api = Api

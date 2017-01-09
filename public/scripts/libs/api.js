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
  panOptions: {animate:true, duration:1.6, easeLinearity:0.4},
  viewOptions: {pan: this.panOptions, zoom: {animate:true}, animate:true},
  events: riot.observable(),
  markers: new L.FeatureGroup(),
  decode: geohash.decode.bind(geohash),
  encode: function (lat, lon, size, ref) {
    var result = geohash.encode(lat, lon, size);

    if (ref)
      for (var i = 0; i < ref.length; ++i)
        if (ref[i] != result[i] && (result = result.substring(i)) !== null) break;

    return result;
  },

  start: function (map) {
    var self = this;
    this.map = map;

    this.mapElement = Zepto('#bg-map');

    this.map.addLayer(this.markers);
    this.map.on('dragstart zoomstart movestart', this.loading.bind(this));
    this.map.on('dragend zoomend moveend',       this.bounds.bind(this));

    Zepto(window).on('load', function (e) {
      console.log(' on load -> zepto ');

      self.context = location.pathname.substring(1);

      if (location.hash != '' && (hash = fragments(location.hash))) {
        if (hash[0] && !isNaN(hash[0]))
          return self.goToPlace.call(self, parseInt(hash[0]));

        if (hash[0] && hash[0].indexOf('-') > -1 && (parts = hash[0].split('-'))) {
          if (!self.userInteraction && !self.isAt(parts[0], parts[1], parts[2])) {
            setTimeout(function () {
              self.map.setView(
                self.getCenter(parts[0], parts[1]), parseInt(parts[2]) || 13, self.viewOptions
              );
            }, 100);
            self.lockUser = true;
            self.map.once('moveend zoomend', self.unlockUser.bind(self));
          } else {
            clearTimeout(self.loadInterval);
            self.loadInterval = setTimeout(
              self.loadPlaces.bind(self, parts[0], parts[1], parts[2]), 150
            );
          }
        }
      }
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
      setTimeout(
        this.map.setView.bind(this.map, [data.lat, data.lon], 13, this.viewOptions), 150
      );
    }.bind(this));
  },

  loading: function (e) {
    if (this.lockUser) return;
    if (e.type == 'dragstart') this.mapElement.css('cursor', 'move');
    this.userInteraction = true;
    this.events.trigger('loading')
  },

  bounds: function (e) {
    if (this.lockUser) return;

    var bounds   = this.map.getBounds(),
         topLeft = bounds.getNorthWest(),
     bottomRight = bounds.getSouthEast(),
            zoom = this.map.getZoom();

    var size = 8 > zoom ? 2 : (10 > zoom ? 3 : (12 > zoom ? 4 : 5));
    var from = this.encode(topLeft.lat, topLeft.lng, size),
          to = this.encode(bottomRight.lat, bottomRight.lng, size, from);

    this.userInteraction = false;

    if (e.type == 'zoomend') {
      this.markers.clearLayers();
      this.pinMarkers = this.circleMarkers = {};
    }

    if (e.type == 'dragend' || e.type == 'zoomend') {
      this.mapElement.css('cursor', 'pointer');
      router.go(
        location.pathname + location.search +'#'+ from +'-'+ to +'-'+ zoom
      );
    }

    clearTimeout(this.loadInterval);
    this.loadInterval = setTimeout(this.loadPlaces.bind(this, from, to, zoom), 150);
  },

  addCircles: function (data) {
    var zoom = this.map.getZoom(),
       delta = (zoom < 5 ? 4000 : (zoom < 8 ? 2000 : (zoom < 11 ? 1000 : 100))) * 4,
       color = (this.context == 'isolated' ? 'tomato' : '#ff6200');

    for (var i = 0; i < data.length; ++i)
      if (!this.circleMarkers[data[i].hash] && (this.circleMarkers[data[i].hash] = true))
        this.markers.addLayer(L.circle(
          [data[i].lat, data[i].lon],
          ((x = (100 * (data[i].count || 2))) < delta ? delta : x),
          {color: color, fillColor: color, stroke: false, fillOpacity: 0.6}
        ));

    this.events.trigger('loaded');
  },

  addPins: function (data) {
    var icon = L.divIcon({
      className: 'icon-location' + (this.context == 'isolated' ? ' reddish' : '')
    });
    for (var i = 0; i < data.length; ++i)
      if (!this.pinMarkers[data[i].id])
        this.markers.addLayer((
          this.pinMarkers[data[i].id] = L.marker(
            [data[i].lat, data[i].lon], {icon: icon}
          ).bindPopup(
            ' <a href="https://www.openstreetmap.org/node/'+ data[i].node +'" target="_blank">'+
            data[i].name + '</a> <i>'+ data[i].place +'</i> <p>criado em: ' +
            fmtDate(data[i].created_at) + '</p>'
          )
        ));

    if (
      location.hash != '' && (hash = fragments(location.hash)) &&
      (hash[0] && !isNaN(hash[0])) && (marker = this.pinMarkers[parseInt(hash[0])])
    ) marker.openPopup();

    this.events.trigger('loaded');
  },

  isAt: function (from, to, zoom) {
    var bounds   = this.map.getBounds(),
         topLeft = bounds.getNorthWest(),
     bottomRight = bounds.getSouthEast(),
            size = from.length,
           _zoom = this.map.getZoom();

    var _from = this.encode(topLeft.lat, topLeft.lng, size),
          _to = this.encode(bottomRight.lat, bottomRight.lng, size, _from);

    if (from.length == to.length)
      for (var i = 0; i < from.length; ++i)
        if (from[i] != to[i] && (to = to.substring(i)) !== null) break;

    return zoom == _zoom && from == _from && to == _to;
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

  unlockUser: function () {
    this.lockUser = false;
  },

  deg2rad: function (degrees) {
    return degrees * Math.PI / 180;
  }
}

module.exports = window.Api = Api

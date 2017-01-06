<map>
  <div id='bg-map'></div>
  <script>
    var path = [
      [-23.88884, -46.78596], [-23.83986, -46.72554], [-23.79463, -46.76537],
      [-23.69155, -46.82991], [-23.52293, -46.82854], [-23.47634, -46.90407],
      [-23.2696,  -46.84227], [-23.11433, -46.92055], [-22.99429, -47.06886],
      [-23.03094, -47.1801],  [-23.24436, -47.31194], [-23.40704, -47.37373],
      [-23.50782, -47.53304], [-23.36418, -47.85713], [-23.23553, -47.97386],
      [-23.05369, -47.9821],  [-22.95383, -48.19771], [-22.88426, -48.48473],
      [-22.69815, -48.43803], [-22.55618, -48.12904], [-22.54857, -47.76924],
      [-22.37978, -47.57698], [-22.00977, -47.90382], [-21.75107, -48.2032],
      [-21.26556, -48.41194], [-20.5613,  -48.54515], [-20.66027, -48.98735],
      [-20.86958, -49.3705],  [-20.37088, -49.96239], [-20.22018, -50.52818],
      [-20.65513, -51.05553], [-20.39276, -51.43593]
    ];

    var speed   = 2000 * 0.277778, // km/h -> m/s ~ 20 times naoliv's traffic ticket
      index     = 0,
      duration  = 0,
      direction = 1,
      interval  = 0,
      map       = null;

    travel () {
      direction = index > path.length -2 ? -1 : (index < 1 ? 1 : direction);
      duration  = Math.ceil(map.getCenter().distanceTo(path[index += direction * 1]) / speed);
      console.log(
        ' going to ('+ path[index].join(', ') +'), duration '+ duration +'s at '+ speed +'m/s'
      );
      map.panTo(path[index], {animate:true, duration:duration, easeLinearity:1.0});
      interval = setTimeout(this.travel, duration * 1000);
    }

    this.on('mount', function () {
      console.log(' map on load ');
      map = L.map('bg-map', {
        reuseTiles: true, unloadInvisibleTiles: true, zoomControl: false,
        dragging: false, touchZoom: false, doubleClickZoom: false, scrollWheelZoom: false,
        maxZoom: 14, minZoom: 4
      }).setView(path[index], 13);

      L.tileLayer(
        'http://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}.png',
        {attribution: '&nbsp;', maxZoom: 14}
      ).addTo(map);

      Api.start(map);
    });

    this.on('update', function () {
      if (opts.traveler) {
        interval = setTimeout(this.travel, duration);
        if (map.options.dragging) {
          map.dragging.disable();
          map.touchZoom.disable();
          map.doubleClickZoom.disable();
          map.scrollWheelZoom.disable();
        }
      } else {
        if (interval) {
          clearTimeout(interval);
          map.stop();
          interval = 0;
        }
        if (!map.options.dragging) {
          map.dragging.enable();
          map.touchZoom.enable();
          map.doubleClickZoom.enable();
          map.scrollWheelZoom.enable();
        }
      }
    });
  </script>
</map>

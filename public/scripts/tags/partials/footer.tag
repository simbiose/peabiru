<footer>
  <p if={ opts.home }>
    made with <span class='heart' title='love'></span> by
    <a href='https://www.openstreetmap.org/user/xxleite'>@xxleite</a>&nbsp;&nbsp;|&nbsp;
    source at <a href='https://github.com/simbiose/peabiru'>github</a>
  </p>
  &copy; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors,
  &copy; <a href='https://www.mapbox.com/about/maps/'>Mapbox</a>,
  <a href='http://leafletjs.com/'>Leaflet</a>,
  <a href='https://github.com/simbiose/peabiru'>Peabiru</a>
  <script>
    this.on('update', function () {
      console.log('footer updated', opts, this);
    });
  </script>
</footer>

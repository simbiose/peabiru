<loading>
  <span class="icon-spinner spinner" if={ loading }></span>
  <script>
    spin (yes) {
      console.log(' should spin? ');
      this.loading = yes;
      this.update();
    }

    opts.events.on('loading', this.spin.bind(this, true));
    opts.events.on('loaded',  this.spin.bind(this, false));
  </script>
</loading>

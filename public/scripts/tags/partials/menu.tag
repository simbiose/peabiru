<menu>
  <button class="toleft hamburger hamburger--squeeze" type="button" onclick={ toggleMenu }>
    <span class="hamburger-box">
      <span class="hamburger-inner"></span>
    </span>
  </button>

  <form action="/places.json" method="GET" class="toleft" onsubmit={ ajaxSearch }>
    <div>
      <span class="icon-search"></span>
      <input type="text" name="search" autocomplete="off" onkeypress={ ajaxSearch }>
    </div>
  </form>

  <div if={ logged } >
    <img src="" />
  </div>

  <submenu></submenu>

  <script>

    var searchTimeout = 0;

    toggleMenu (data) {
      var menu = Zepto('.hamburger'),
        active = menu.hasClass('is-active');
            up = false;

      if (data && data instanceof Array) {
        up = data.length == 0;

        if (!window.places) window.places = [];
        for (var i = 0; i < data.length; ++i)
          window.places[data[i].id] = data[i];

        this.tags.submenu.update({slideDown:!up, slideUp:up, results:data});
        if (active && !up) return;
      } else {
        this.tags.submenu.update({slideDown:!active, slideUp:active});
      }
      menu.toggleClass('is-active');
    }

    doSearch (input, value) {
      if (input.length == 0 || input.val() != value || input.val().length < 3) return;
      Zepto.ajax({
        method: 'GET',
        url:    'places.json',
        data:   {'search': input.val(), 'limit': 3}
      }).then(this.toggleMenu);
    }

    ajaxSearch (e) {
      if (e.target.nodeName != 'INPUT') {
        console.log(' was a form ');
        e.preventDefault();
        return false
      }
      if ((e.which || e.keyCode) != 13 && Zepto(e.target).val().length > 2) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(this.doSearch.bind(
          this, Zepto(e.target), Zepto(e.target).val()+String.fromCharCode(e.which || e.keyCode)
        ), 350);
      }
      return true
    }

    this.on('mount', function () {
      console.log(' mount menu ');
    });
  </script>
</menu>

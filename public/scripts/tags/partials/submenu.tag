<submenu class={ slidedown: slideDown, slideup: slideUp, toleft: true }>
  <ul if={ slideDown && results } class="highlights">
    <li each={ results }>
      <a href="javascript:void(0);" onclick={ moveToPlace } data-id={ id }>
        { name } <i>{ place }</i>
      </a>
    </li>
  </ul>

  <ul if={ slideDown && !results } class="submenu">
    <li>
      <a href="/isolated" onclick={ moveToPlace }>Lugares isolados</a>
    </li>
    <li>
      <a href="/places" onclick={ moveToPlace }>Lugares importados</a>
    </li>
  </ul>

  <script>
    moveToPlace (e) {
      var item = Zepto(e.target);
      this.parent.toggleMenu();
      if (item.data('id'))
        router.go(
          '/'+ (location.pathname.indexOf('isolated') > -1 ? 'isolated' : 'places') +
          '#'+ item.data('id')
        );
    }

    this.on('update', function () {
      console.log(' update submenu ');
    });

    this.on('mount', function () {
      console.log(' mounted submenu ');
    });
  </script>
</submenu>

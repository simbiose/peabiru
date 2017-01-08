export default {
  parsers: {
    html: {
      handlebars: (html, opts, url) => require('handlebars').create().compile(html)({env: process.env})
    }
  }
};

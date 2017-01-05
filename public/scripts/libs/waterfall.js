/**
 * waterfall method
 *
 * @author    xico@simbio.se
 * @copyright Simbiose
 * @license   LGPL version 3.0, see LICENSE
 */

var once = window.once = function (fn, stoppable) {
  var stop = function () { fn = null };
  return function () {
    if (stoppable) (arguments[0] && arguments[0] instanceof Event) ?
      arguments[0].stop = stop : arguments[arguments.length] = stop;
    if (fn) fn.apply(this, arguments);
    return stoppable ? void 0 : stop();
  };
};

function Iterator (tasks) {
  var callback = function (index) {
    var fn = function () {
      if (tasks.length) tasks[index].apply(null, arguments);
      return fn.next();
    };
    fn.next = function () {
      return (index < tasks.length - 1) ? callback(index + 1) : null;
    };
    return fn;
  };
  return callback(0);
}

function waterfall (tasks, callback) {
  callback = once(callback || noop);
  if (!Array.isArray(tasks))
    return callback(new Error('First argument to waterfall must be an array of functions'));
  if (!tasks.length)
    return callback();

  function wrap (iterator) {
    return function (err) {
      if (err) {
        callback.apply(null, arguments);
      } else {
        var args = Array.prototype.slice.call(arguments, 1);
        var next = iterator.next();
        args.push(next ? wrap(next) : callback);
        setTimeout(function () { iterator.apply(null, args); }, 0);
      }
    }
  }

  wrap(Iterator(tasks))();
}

module.exports = window.waterfall = waterfall

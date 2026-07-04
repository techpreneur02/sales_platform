(function () {
  'use strict';

  var THRESHOLD_MS = 30;
  var IDLE_FINALIZE_MS = 90;
  var MIN_SCAN_LENGTH = 4;

  var buffer = '';
  var intervals = [];
  var lastKeyAt = 0;
  var scanTimer = null;

  function getScanEndpoint() {
    var base = typeof admin_url !== 'undefined' ? admin_url : '/admin/';
    if (base.slice(-1) !== '/') {
      base += '/';
    }
    return base + 'omnipos/pos/scan_item';
  }

  function resetScanState() {
    buffer = '';
    intervals = [];
    lastKeyAt = 0;
    if (scanTimer) {
      clearTimeout(scanTimer);
      scanTimer = null;
    }
  }

  function isFastCluster() {
    if (intervals.length === 0) {
      return false;
    }

    for (var i = 0; i < intervals.length; i += 1) {
      if (intervals[i] > THRESHOLD_MS) {
        return false;
      }
    }

    return true;
  }

  function finalizeScan(triggerEvent) {
    if (buffer.length < MIN_SCAN_LENGTH || !isFastCluster()) {
      resetScanState();
      return;
    }

    if (triggerEvent && typeof triggerEvent.preventDefault === 'function') {
      triggerEvent.preventDefault();
      triggerEvent.stopPropagation();
    }

    var postData = {
      barcode: buffer
    };

    if (typeof csrfData !== 'undefined') {
      postData[csrfData.token_name] = csrfData.hash;
    }

    if (typeof $ !== 'undefined' && typeof $.ajax === 'function') {
      $.ajax({
        url: getScanEndpoint(),
        type: 'POST',
        dataType: 'json',
        data: postData,
        success: function (response) {
          window.dispatchEvent(
            new CustomEvent('omnipos:scan', {
              detail: {
                barcode: buffer,
                response: response
              }
            })
          );
        },
        error: function () {
          window.dispatchEvent(
            new CustomEvent('omnipos:scan_error', {
              detail: {
                barcode: buffer
              }
            })
          );
        }
      });
    }

    resetScanState();
  }

  window.addEventListener('keydown', function (event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
      return;
    }

    var key = event.key;
    var now = Date.now();

    if (key === 'Enter') {
      finalizeScan(event);
      return;
    }

    if (!key || key.length !== 1) {
      return;
    }

    var delta = lastKeyAt > 0 ? now - lastKeyAt : 0;

    if (lastKeyAt > 0 && delta <= THRESHOLD_MS) {
      intervals.push(delta);
      buffer += key;
      event.preventDefault();
      event.stopPropagation();
    } else {
      buffer = key;
      intervals = [];
    }

    lastKeyAt = now;

    if (scanTimer) {
      clearTimeout(scanTimer);
    }

    scanTimer = setTimeout(function () {
      finalizeScan(event);
    }, IDLE_FINALIZE_MS);
  }, true);
})();

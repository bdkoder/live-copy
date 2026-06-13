(function ($) {
  'use strict';

  window.addEventListener('paste', function (event) {
    if (!window.elementor || !window.elementor.config) {
      return;
    }

    var clipboardData = event.clipboardData || window.clipboardData;
    if (!clipboardData) {
      return;
    }

    var dataText = clipboardData.getData('text/plain');
    if (!dataText) {
      return;
    }

    try {
      var data = JSON.parse(dataText);
      // Check if it's an Elementor cross-domain paste payload
      if (data.type === 'elementor' && Array.isArray(data.elements)) {
        var missingWidgets = [];
        var registeredWidgets = elementor.config.widgets;

        var checkElements = function (elements) {
          elements.forEach(function (el) {
            if (el.elType === 'widget' && el.widgetType) {
              var wType = el.widgetType;
              if (!registeredWidgets[wType]) {
                if (missingWidgets.indexOf(wType) === -1) {
                  missingWidgets.push(wType);
                }
              }
            }
            if (el.elements && Array.isArray(el.elements)) {
              checkElements(el.elements);
            }
          });
        };

        checkElements(data.elements);

        if (missingWidgets.length > 0) {
          // Block Elementor from processing the paste and throwing console errors
          event.preventDefault();
          event.stopPropagation();

          var message = 'You are trying to paste content that uses widgets not installed or active on this site:<br><br><b>' +
            missingWidgets.join(', ') + '</b><br><br>Please install and activate the missing widgets before pasting to avoid errors.';

          if (window.elementorCommon && window.elementorCommon.dialogsManager) {
            elementorCommon.dialogsManager.createWidget('alert', {
              id: 'live-copy-missing-widget-alert',
              headerMessage: 'Missing Widgets Detected',
              message: message,
              strings: {
                confirm: 'OK'
              }
            }).show();
          } else {
            // Fallback
            alert('Missing Widgets Detected\n\n' + message.replace(/<br>/g, '\n').replace(/<\/?b>/g, ''));
          }
        }
      }
    } catch (e) {
      // Not JSON, ignore
    }
  }, true); // Use capture phase to intercept before Elementor does

})(jQuery);

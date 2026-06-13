(function ($) {
  'use strict';

  if (!ElLiveCopyData || !ElLiveCopyData.enable) {
    return;
  }

  // Skip inside the Elementor editor preview.
  if (document.body.classList.contains('elementor-editor-active')) {
    return;
  }

  // Mobile guard — exit before any DOM work.
  if (ElLiveCopyData.disable_mobile && (window.innerWidth <= 768 || navigator.maxTouchPoints > 0)) {
    return;
  }

  // ── Inline SVG icons ────────────────────────────────────────────────────────
  var ICONS = {
    copy:
      '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<rect x="9" y="9" width="11" height="11" rx="2.5"/>' +
      '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
    download:
      '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>' +
      '<polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
    info:
      '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    check:
      '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<polyline points="20 6 9 17 4 12"/></svg>',
  };

  // ── i18n (localized, with English fallbacks) ────────────────────────────────
  var I18N = ElLiveCopyData.i18n || {};
  function t(key, fallback) {
    return I18N[key] || fallback;
  }

  // ── Cookie helpers ────────────────────────────────────────────────────────
  function getCookie(name) {
    var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return m ? decodeURIComponent(m.pop()) : '';
  }
  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + days * 864e5);
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }

  // Resolve help URL with a version stamp so admin URL changes take effect
  // immediately. Cookie value is "ver|url"; a version mismatch re-seeds it.
  function resolveHelpUrl() {
    var ver   = ElLiveCopyData.help_ver || '';
    var raw   = getCookie('ellc_help_url');
    var parts = raw ? raw.split('|') : [];
    var cookieVer = parts.length > 1 ? parts[0] : '';
    var cookieUrl = parts.length > 1 ? parts.slice(1).join('|') : '';

    // Valid cached copy only when versions match.
    if (cookieUrl && ver && cookieVer === ver) {
      return cookieUrl;
    }
    // Re-seed from the freshly localized value.
    if (ElLiveCopyData.help_url) {
      setCookie('ellc_help_url', ver + '|' + ElLiveCopyData.help_url, 30);
      return ElLiveCopyData.help_url;
    }
    return '';
  }

  var El_Live_Copy = {

    _helpUrl: '',

    globalSelector: function () {
      this._document = $(document);
      var $roots = this._document.find('[data-elementor-type="wp-page"], [data-elementor-type="wp-post"]');

      // Include nested containers + inner sections (fixes missing buttons deeper in the tree).
      this._elItem = $roots.find('.e-con, .elementor-section');

      if (this._elItem.length === 0) {
        this._elItem = this._document.find('.elementor-section.elementor-top-section');
      }
    },

    buildPanel: function () {
      var showCopy     = ElLiveCopyData.show_copy     !== false;
      var showDownload = ElLiveCopyData.show_download !== false;
      var help         = this._helpUrl;

      var tCopy = t('live_copy', 'Live Copy');
      var tDl   = t('download', 'Download JSON');
      var tHow  = t('how', 'How it works');

      var html = '<div class="ellc-magic-copy-wrapper" role="group" aria-label="Live Copy actions">';
      if (showCopy) {
        html += '<a href="#" role="button" class="ellc-btn ellc-copy-btn" data-tip="' + tCopy + '" aria-label="' + tCopy + '">' + ICONS.copy + '</a>';
      }
      if (showDownload) {
        html += '<a href="#" role="button" class="ellc-btn ellc-download-btn" data-tip="' + tDl + '" aria-label="' + tDl + '">' + ICONS.download + '</a>';
      }
      var infoAttrs = help ? ' href="' + help + '" target="_blank" rel="noopener noreferrer"' : ' href="#"';
      html += '<a class="ellc-btn ellc-info-btn" data-tip="' + tHow + '"' + infoAttrs + ' aria-label="' + tHow + '">' + ICONS.info + '</a>';
      html += '</div>';
      return html;
    },

    attach: function () {
      var showCopy     = ElLiveCopyData.show_copy     !== false;
      var showDownload = ElLiveCopyData.show_download !== false;
      if (!showCopy && !showDownload) {
        return;
      }

      var specific  = ElLiveCopyData.specific_mode === true;
      var panelHtml = this.buildPanel();

      this._elItem.each(function () {
        var $el  = $(this);
        var $doc = $el.closest('[data-elementor-type="wp-page"], [data-elementor-type="wp-post"]');

        if ($doc.length === 0 || $doc.hasClass('magic-button-disabled-yes')) {
          return;
        }
        // Specific Section Mode: only elements opted-in via the Elementor editor.
        if (specific && !$el.hasClass('ellc-enabled')) {
          return;
        }
        // attr (not .data) — keeps all-digit Elementor ids as strings, uncached.
        if (!$el.attr('data-id')) {
          return;
        }
        // Skip empty wrappers.
        var hasContent = $.trim($el.text()).length > 0 ||
          $el.find('.elementor-widget, .e-con, .elementor-section, img, svg, video').length > 0;
        if (!hasContent) {
          return;
        }
        // Avoid double-attach.
        if ($el.children('.ellc-magic-copy-wrapper').length > 0) {
          return;
        }

        // Anchor the absolute panel correctly even when Elementor leaves position:static.
        if ($el.css('position') === 'static') {
          $el.css('position', 'relative');
        }

        $el.addClass('ellc-copy-target').append(panelHtml);
      });
    },

    // Show only the innermost hovered target's panel (prevents ancestor stacking).
    // No stopPropagation — we pick the innermost by matching the real hovered
    // node, so other scripts' mouseover listeners are never disrupted.
    bindHover: function () {
      this._document.on('mouseover', '.ellc-copy-target', function (e) {
        var innermost = $(e.target).closest('.ellc-copy-target').get(0);
        if (innermost !== this) {
          return;
        }
        $('.ellc-copy-target.ellc-active').removeClass('ellc-active');
        $(this).addClass('ellc-active');
      });
      this._document.on('mouseleave', '.ellc-copy-target', function () {
        $(this).removeClass('ellc-active');
      });
    },

    flashTip: function ($btn, msg, success) {
      var orig = $btn.attr('data-tip');
      var iconHtml = $btn.html();
      $btn.attr('data-tip', msg).addClass('ellc-tip-show');
      if (success) {
        $btn.addClass('ellc-tip-success').data('icon', iconHtml).html(ICONS.check);
      }
      setTimeout(function () {
        $btn.removeClass('ellc-tip-show ellc-tip-success').attr('data-tip', orig);
        if (success) {
          $btn.html($btn.data('icon'));
        }
      }, 1700);
    },

    // Fetch a fresh nonce from REST (handles cached-page staleness), then cb(ok).
    refreshNonce: function (cb) {
      if (!ElLiveCopyData.rest_nonce_url) { cb(false); return; }
      $.ajax({ url: ElLiveCopyData.rest_nonce_url, type: 'GET', cache: false })
        .done(function (r) {
          if (r && r.nonce) { ElLiveCopyData.nonce = r.nonce; cb(true); }
          else { cb(false); }
        })
        .fail(function () { cb(false); });
    },

    fetchData: function (widget_id, action_type, $btn, onData, isRetry) {
      var retryWithFreshNonce = function () {
        if (isRetry) { El_Live_Copy.flashTip($btn, t('failed', 'Failed'), false); return; }
        El_Live_Copy.refreshNonce(function (ok) {
          if (ok) {
            El_Live_Copy.fetchData(widget_id, action_type, $btn, onData, true);
          } else {
            El_Live_Copy.flashTip($btn, t('failed', 'Failed'), false);
          }
        });
      };

      $.ajax({
        url:  ElLiveCopyData.ajax_url,
        type: 'POST',
        data: {
          action:      'ellc_get_data',
          widget_id:   widget_id,
          post_id:     ElLiveCopyData.post_id,
          _wp_nonce:   ElLiveCopyData.nonce,
          action_type: action_type,
        },
        beforeSend: function () {
          $btn.addClass('ellc-loading');
        },
      }).done(function (response) {
        $btn.removeClass('ellc-loading');
        if (response && response.success) {
          onData(response.data.widget);
          return;
        }
        // Stale nonce on a cached page → refresh + retry once.
        if (response && response.data && response.data.code === 'invalid_nonce') {
          $btn.addClass('ellc-loading');
          retryWithFreshNonce();
          return;
        }
        El_Live_Copy.flashTip($btn, t('failed', 'Failed'), false);
      }).fail(function (jqXHR) {
        $btn.removeClass('ellc-loading');
        // 403 = nonce rejected (commonly a cached page) → refresh + retry once.
        if (jqXHR && jqXHR.status === 403) {
          $btn.addClass('ellc-loading');
          retryWithFreshNonce();
          return;
        }
        El_Live_Copy.flashTip($btn, t('failed', 'Failed'), false);
      });
    },

    bindCopy: function () {
      this._document.on('click', '.ellc-copy-btn', function (e) {
        e.preventDefault();
        var $btn      = $(this);
        var widget_id = $btn.closest('.ellc-copy-target').attr('data-id');
        if ($btn.hasClass('ellc-loading')) { return; }

        El_Live_Copy.fetchData(widget_id, 'copy', $btn, function (widget) {
          var textarea = document.createElement('textarea');
          textarea.value = JSON.stringify(widget);
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.select();
          try { document.execCommand('copy'); } catch (err) {}
          document.body.removeChild(textarea);
          El_Live_Copy.flashTip($btn, t('copied', 'Copied!'), true);
        });
      });
    },

    bindDownload: function () {
      this._document.on('click', '.ellc-download-btn', function (e) {
        e.preventDefault();
        var $btn      = $(this);
        var widget_id = $btn.closest('.ellc-copy-target').attr('data-id');
        if ($btn.hasClass('ellc-loading')) { return; }

        El_Live_Copy.fetchData(widget_id, 'download', $btn, function (widget) {
          var blob = new Blob([JSON.stringify(widget, null, 2)], { type: 'application/json' });
          var url  = URL.createObjectURL(blob);
          var link = document.createElement('a');
          link.href     = url;
          link.download = 'live-copy-' + widget_id + '.json';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
          El_Live_Copy.flashTip($btn, t('downloaded', 'Downloaded!'), true);
        });
      });
    },

    bindInfo: function () {
      this._document.on('click', '.ellc-info-btn', function (e) {
        if (!El_Live_Copy._helpUrl) {
          e.preventDefault();
        }
      });
    },

    // Re-scan + attach to any sections/containers added after load.
    refresh: function () {
      this.globalSelector();
      this.attach();
    },

    // Watch for popups / AJAX-loaded / lazy content and attach to new sections.
    observe: function () {
      if (typeof MutationObserver === 'undefined') {
        return;
      }
      var self = this;
      var timer = null;

      var observer = new MutationObserver(function (mutations) {
        // Ignore mutations that are only our own panel insertions.
        var relevant = false;
        for (var i = 0; i < mutations.length; i++) {
          var added = mutations[i].addedNodes;
          for (var j = 0; j < added.length; j++) {
            var node = added[j];
            if (node.nodeType === 1 && !$(node).hasClass('ellc-magic-copy-wrapper')) {
              relevant = true;
              break;
            }
          }
          if (relevant) { break; }
        }
        if (!relevant) { return; }

        clearTimeout(timer);
        timer = setTimeout(function () { self.refresh(); }, 250);
      });

      observer.observe(document.body, { childList: true, subtree: true });
    },

    init: function () {
      this._helpUrl = resolveHelpUrl();
      this.globalSelector();
      this.attach();
      this.bindHover();
      this.bindCopy();
      this.bindDownload();
      this.bindInfo();
      this.observe();
    },
  };

  $(document).ready(function () {
    El_Live_Copy.init();
  });

})(jQuery);

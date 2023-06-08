(function ($) {
    'use strict';

    if (!ElLiveCopyData.enable) {
        return;
    }



    var El_Live_Copy = {
        globalSelector: function (e) {
            this._document = $(document);
            this._elItem = this._document.find('.elementor-section.elementor-top-section');
            if (this._elItem.length === 0) {
                this._elItem = this._document.find('.elementor-element.e-con');
            }
        },
        copyBtn: function () {
            $(this._elItem).each(function () {
                if ($.trim($(this).find('.elementor-widget-wrap').html()) || $.trim($(this).find('.e-con-inner').html())) {
                    $(this).append(
                        '<div class="ellc-magic-copy-wrapper"><a aria-label="Click live copy button to copy this block." data-microtip-position="left" role="tooltip" class="ellc-copy-btn">Live Copy</a><a href="javascript:void(0)" class="ellc-magic-copy-info">?</a></div>'
                    );
                }

                $($(this).find('.ellc-magic-copy-item')).hover(
                    function () {
                        $(this).parent().addClass('ellc-copy-selected');
                    },
                    function () {
                        // on mouseout, reset the background colour
                        $(this).parent().removeClass('ellc-copy-selected');
                    }
                );
            });
        },
        copyData: function () {
            this._document.on('click', 'a.ellc-copy-btn', function (e) {
                e.preventDefault();
                var parentSelector = $(this).closest(El_Live_Copy._elItem),
                    _this = $(this),
                    widget_id = parentSelector.data('id'),
                    post_id = ElLiveCopyData.post_id,
                    ajax_url = ElLiveCopyData.ajax_url,
                    ajax_nonce = ElLiveCopyData.nonce;

                $.ajax({
                    url: ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ellc_copy_data',
                        widget_id: widget_id,
                        post_id: post_id,
                        _wp_nonce: ajax_nonce,
                    },
                    beforeSend: function () {
                        _this.text('Copying...');
                    }
                }).done(function (response) {
                    _this.text('Copied');
                    setTimeout(function () {
                        _this.text('Live Copy');
                    }, 2000);
                    if (response.success) {
                        console.log(response.data.widget);
                        // Assuming you have received the JSON object in the 'result' variable from your AJAX call

                        // Create a temporary textarea element
                        var textarea = document.createElement('textarea');
                        // Set the value of the textarea to the JSON object
                        textarea.value = JSON.stringify(response.data.widget);
                        // Append the textarea element to the document body
                        document.body.appendChild(textarea);
                        // Select the text in the textarea
                        textarea.select();
                        // Copy the selected text to the clipboard
                        document.execCommand('copy');
                        // Remove the temporary textarea element
                        document.body.removeChild(textarea);

                        // new ClipboardJS({
                        //     text: response.data.widget
                        // });
                    }
                }).fail(function (response) {
                    _this.text('Failed!');
                });
            });
        },
        init: function () {
            El_Live_Copy.globalSelector();
            El_Live_Copy.copyBtn();
            El_Live_Copy.copyData();

        }
    }

    $(document).ready(function () {
        El_Live_Copy.init();
    });

})(jQuery);
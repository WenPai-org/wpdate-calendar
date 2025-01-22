jQuery(document).ready(function($) {
    function updatePreview() {
        var format = $('input[name="wplc_display_format"]').val();
        var displayItems = {
            year: $('input[name="wplc_display_items[year]"]').is(':checked') ? 1 : 0,
            month: $('input[name="wplc_display_items[month]"]').is(':checked') ? 1 : 0,
            day: $('input[name="wplc_display_items[day]"]').is(':checked') ? 1 : 0,
            zodiac: $('input[name="wplc_display_items[zodiac]"]').is(':checked') ? 1 : 0,
            festival: $('input[name="wplc_display_items[festival]"]').is(':checked') ? 1 : 0,
            solar_term: $('input[name="wplc_display_items[solar_term]"]').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: wplcAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'lunar_date_preview',
                nonce: wplcAdmin.nonce,
                format: format,
                display_items: displayItems
            },
            success: function(response) {
                if (response.success) {
                    $('#lunar_date_preview').html(response.data.preview);
                } else {
                    $('#lunar_date_preview').html('<div class="error">预览失败，请重试。</div>');
                }
            },
            error: function() {
                $('#lunar_date_preview').html('<div class="error">请求失败，请检查网络连接。</div>');
            }
        });
    }

    // 绑定事件
    $('input[name="wplc_display_format"], input[name^="wplc_display_items"]').on('input change', function() {
        updatePreview();
    });

    // 初始化预览
    updatePreview();
});
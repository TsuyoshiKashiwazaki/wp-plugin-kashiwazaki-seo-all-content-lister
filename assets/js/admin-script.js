/**
 * Kashiwazaki SEO All Content Lister - Admin Script
 */

(function($) {
    'use strict';

    var STORAGE_KEY = 'kashiwazaki_seo_all_content_lister_columns';

    function getColumnSettings() {
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            try {
                return JSON.parse(saved);
            } catch (e) {
                return {};
            }
        }
        return {};
    }

    function saveColumnSettings(settings) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    }

    function toggleColumn(columnName, visible) {
        var $th = $('.kashiwazaki-seo-all-content-lister-table th[data-column="' + columnName + '"]');
        var $td = $('.kashiwazaki-seo-all-content-lister-table td[data-column="' + columnName + '"]');

        if (visible) {
            $th.removeClass('column-hidden');
            $td.removeClass('column-hidden');
        } else {
            $th.addClass('column-hidden');
            $td.addClass('column-hidden');
        }
    }

    function initColumnToggles() {
        var settings = getColumnSettings();

        $('.column-toggle-checkbox').each(function() {
            var $checkbox = $(this);
            var columnName = $checkbox.data('column');
            var $label = $checkbox.closest('.column-toggle');

            // 保存された設定を適用
            if (settings.hasOwnProperty(columnName)) {
                $checkbox.prop('checked', settings[columnName]);
                toggleColumn(columnName, settings[columnName]);
                if (!settings[columnName]) {
                    $label.addClass('disabled');
                }
            }

            // チェンジイベント
            $checkbox.on('change', function() {
                var isChecked = $(this).is(':checked');
                var settings = getColumnSettings();
                settings[columnName] = isChecked;
                saveColumnSettings(settings);
                toggleColumn(columnName, isChecked);

                if (isChecked) {
                    $label.removeClass('disabled');
                } else {
                    $label.addClass('disabled');
                }
            });
        });
    }

    function initCsvExport() {
        var $encodingSelect = $('#csv-encoding');
        var $downloadBtn = $('#csv-download-btn');
        var baseUrl = $downloadBtn.attr('href');

        function updateDownloadUrl() {
            var encoding = $encodingSelect.val();
            var url = new URL(baseUrl, window.location.origin);
            url.searchParams.set('encoding', encoding);
            $downloadBtn.attr('href', url.toString());
        }

        // 初期ロード時にもURLを更新
        updateDownloadUrl();

        $encodingSelect.on('change', updateDownloadUrl);
    }

    $(document).ready(function() {
        initColumnToggles();
        initCsvExport();
    });

})(jQuery);

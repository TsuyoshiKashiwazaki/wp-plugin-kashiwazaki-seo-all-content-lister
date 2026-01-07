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

    function initLinkScan() {
        var $startBtn = $('#start-link-scan-btn');
        var $status = $('#link-scan-status');
        var $progress = $('#link-scan-progress');
        var $progressText = $progress.find('.progress-text');
        var $progressBar = $('#link-scan-progress-bar');
        var isScanning = false;

        console.log('initLinkScan called', {
            btnFound: $startBtn.length,
            configExists: typeof kashiwazakiLinkScan !== 'undefined'
        });

        if (!$startBtn.length || typeof kashiwazakiLinkScan === 'undefined') {
            console.warn('Link scan init skipped: button or config not found');
            return;
        }

        function startScan() {
            if (isScanning) {
                return;
            }

            isScanning = true;
            $startBtn.prop('disabled', true).text('スキャン中...').addClass('updating-message');
            $status.html('<span class="scan-running">準備中...</span>');
            $progress.show();
            $progressText.text('初期化中...');
            $progressBar.val(0);

            console.log('被リンクスキャン開始');

            $.ajax({
                url: kashiwazakiLinkScan.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kashiwazaki_start_link_scan',
                    nonce: kashiwazakiLinkScan.nonce
                },
                success: function(response) {
                    console.log('スキャン開始レスポンス:', response);
                    if (response.success) {
                        $progressBar.attr('max', response.data.total);
                        $progressText.text('0 / ' + response.data.total);
                        processBatch();
                    } else {
                        handleError(response.data || 'エラーが発生しました');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('スキャン開始エラー:', status, error, xhr.responseText);
                    handleError('通信エラーが発生しました: ' + error);
                }
            });
        }

        function processBatch() {
            console.log('バッチ処理実行中...');
            $.ajax({
                url: kashiwazakiLinkScan.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kashiwazaki_process_link_scan',
                    nonce: kashiwazakiLinkScan.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $progressBar.val(data.progress);
                        $progressText.text(data.progress + ' / ' + data.total);

                        if (data.status === 'completed') {
                            completeScanning(data.last_scan);
                        } else {
                            // 次のバッチを処理
                            setTimeout(processBatch, 100);
                        }
                    } else {
                        handleError(response.data || 'エラーが発生しました');
                    }
                },
                error: function() {
                    handleError('通信エラーが発生しました');
                }
            });
        }

        function completeScanning(lastScan) {
            isScanning = false;
            $startBtn.prop('disabled', false).text('被リンク調査を実行');
            $status.html('<span class="scan-completed">' + lastScan + ' に生成しました</span>');
            $progress.hide();

            // ページをリロードして結果を反映
            setTimeout(function() {
                location.reload();
            }, 1000);
        }

        function handleError(message) {
            isScanning = false;
            $startBtn.prop('disabled', false).text('被リンク調査を実行');
            $status.html('<span class="scan-error">' + message + '</span>');
            $progress.hide();
        }

        $startBtn.on('click', function(e) {
            e.preventDefault();
            console.log('被リンク調査ボタンがクリックされました');
            startScan();
        });

        // ページロード時にスキャン中かチェック
        $.ajax({
            url: kashiwazakiLinkScan.ajaxurl,
            type: 'POST',
            data: {
                action: 'kashiwazaki_check_scan_status',
                nonce: kashiwazakiLinkScan.nonce
            },
            success: function(response) {
                if (response.success && response.data.status === 'running') {
                    isScanning = true;
                    $startBtn.prop('disabled', true).text('スキャン中...');
                    $progress.show();
                    $progressBar.attr('max', response.data.total);
                    $progressBar.val(response.data.progress);
                    $progressText.text(response.data.progress + ' / ' + response.data.total);
                    processBatch();
                }
            }
        });
    }

    function initIncomingLinksToggle() {
        $(document).on('click', '.incoming-links-toggle', function() {
            var $btn = $(this);
            var $detail = $btn.siblings('.incoming-links-detail');

            if ($detail.is(':visible')) {
                $detail.slideUp(200);
                $btn.text($btn.text().replace('閉じる', '詳細を見る'));
            } else {
                $detail.slideDown(200);
                $btn.text($btn.text().replace('詳細を見る', '閉じる'));
            }
        });
    }

    $(document).ready(function() {
        initColumnToggles();
        initCsvExport();
        initLinkScan();
        initIncomingLinksToggle();
    });

})(jQuery);

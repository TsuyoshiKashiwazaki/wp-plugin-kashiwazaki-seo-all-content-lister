<?php
/**
 * Plugin Name: Kashiwazaki SEO All Content Lister
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: 全ての投稿・固定ページ・カスタム投稿タイプを一覧表示し、ソート・フィルター機能を提供する管理ツール
 * Version: 1.0.1
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashiwazaki-seo-all-content-lister
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kashiwazaki_SEO_All_Content_Lister {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'kashiwazaki_incoming_links';

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        add_action('init', array($this, 'handle_csv_download'));

        // Ajax handlers
        add_action('wp_ajax_kashiwazaki_start_link_scan', array($this, 'ajax_start_link_scan'));
        add_action('wp_ajax_kashiwazaki_check_scan_status', array($this, 'ajax_check_scan_status'));
        add_action('wp_ajax_kashiwazaki_process_link_scan', array($this, 'ajax_process_link_scan'));
        add_action('wp_ajax_kashiwazaki_get_link_map_data', array($this, 'ajax_get_link_map_data'));
    }

    public static function activate() {
        $instance = self::get_instance();
        $instance->create_table();
    }

    private function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            incoming_count int(11) NOT NULL DEFAULT 0,
            incoming_post_ids longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY incoming_count (incoming_count)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=kashiwazaki-seo-all-content-lister')) . '">コンテンツ一覧</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Kashiwazaki SEO All Content Lister',
            'Kashiwazaki SEO All Content Lister',
            'edit_posts',
            'kashiwazaki-seo-all-content-lister',
            array($this, 'render_admin_page'),
            'dashicons-list-view',
            81
        );

        add_submenu_page(
            'kashiwazaki-seo-all-content-lister',
            'コンテンツ一覧',
            'コンテンツ一覧',
            'edit_posts',
            'kashiwazaki-seo-all-content-lister',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'kashiwazaki-seo-all-content-lister',
            'リンクマップ',
            'リンクマップ',
            'edit_posts',
            'kashiwazaki-seo-link-map',
            array($this, 'render_link_map_page')
        );
    }

    public function enqueue_admin_assets($hook) {
        // デバッグ用
        // error_log('Current hook: ' . $hook);

        // コンテンツ一覧ページ
        if ($hook === 'toplevel_page_kashiwazaki-seo-all-content-lister') {
            wp_enqueue_style(
                'kashiwazaki-seo-all-content-lister-style',
                plugin_dir_url(__FILE__) . 'assets/css/admin-style.css',
                array(),
                '1.0.0'
            );

            wp_enqueue_script(
                'kashiwazaki-seo-all-content-lister-script',
                plugin_dir_url(__FILE__) . 'assets/js/admin-script.js',
                array('jquery'),
                '1.0.0',
                true
            );

            wp_localize_script(
                'kashiwazaki-seo-all-content-lister-script',
                'kashiwazakiLinkScan',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('kashiwazaki_link_scan_nonce'),
                )
            );
        }

        // リンクマップページ
        if (strpos($hook, 'kashiwazaki-seo-link-map') !== false) {
            wp_enqueue_style(
                'kashiwazaki-seo-link-map-style',
                plugin_dir_url(__FILE__) . 'assets/css/link-map-style.css',
                array(),
                '1.0.0'
            );

            wp_enqueue_script(
                'd3js',
                'https://d3js.org/d3.v7.min.js',
                array(),
                '7',
                true
            );

            wp_enqueue_script(
                'kashiwazaki-seo-link-map-script',
                plugin_dir_url(__FILE__) . 'assets/js/link-map-script.js',
                array('d3js'),
                '1.0.0',
                true
            );

            $initial_post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

            wp_localize_script(
                'kashiwazaki-seo-link-map-script',
                'kashiwazakiLinkMap',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('kashiwazaki_link_map_nonce'),
                    'initialPostId' => $initial_post_id,
                )
            );
        }
    }

    private function get_columns() {
        return array(
            'ID' => 'ID',
            'title' => 'タイトル',
            'permalink' => 'URL',
            'post_type' => '投稿タイプ',
            'post_status' => 'ステータス',
            'categories' => 'カテゴリ',
            'keywords' => 'キーワード',
            'description' => 'ディスクリプション',
            'date' => '公開日',
            'modified' => '更新日',
            'post_name' => 'スラッグ',
            'incoming_links' => '被リンク元',
        );
    }

    private function get_meta_description($post_id) {
        // Yoast SEO
        $desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (!empty($desc)) return $desc;

        // All in One SEO
        $desc = get_post_meta($post_id, '_aioseo_description', true);
        if (!empty($desc)) return $desc;

        // Rank Math
        $desc = get_post_meta($post_id, 'rank_math_description', true);
        if (!empty($desc)) return $desc;

        // SEOPress
        $desc = get_post_meta($post_id, '_seopress_titles_desc', true);
        if (!empty($desc)) return $desc;

        // Kashiwazaki SEO Auto Description
        $desc = get_post_meta($post_id, '_kashiwazaki_seo_description', true);
        if (!empty($desc)) return $desc;

        return '';
    }

    private function get_meta_keywords($post_id) {
        // Yoast SEO (focuskw)
        $kw = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (!empty($kw)) return $kw;

        // All in One SEO
        $kw = get_post_meta($post_id, '_aioseo_keywords', true);
        if (!empty($kw)) return $kw;

        // Rank Math
        $kw = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!empty($kw)) return $kw;

        // SEOPress
        $kw = get_post_meta($post_id, '_seopress_analysis_target_kw', true);
        if (!empty($kw)) return $kw;

        // Kashiwazaki SEO Auto Keywords
        $kw = get_post_meta($post_id, '_kashiwazaki_seo_keywords', true);
        if (!empty($kw)) return $kw;

        return '';
    }

    private function get_post_categories($post_id, $post_type) {
        $categories = array();

        // 投稿のカテゴリ
        if ($post_type === 'post') {
            $cats = get_the_category($post_id);
            if ($cats) {
                foreach ($cats as $cat) {
                    $categories[] = $cat->name;
                }
            }
        }

        // カスタムタクソノミー
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $tax_slug => $tax_obj) {
            if ($tax_slug === 'category' || $tax_slug === 'post_tag' || !$tax_obj->hierarchical) {
                continue;
            }
            $terms = get_the_terms($post_id, $tax_slug);
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $categories[] = $term->name;
                }
            }
        }

        return $categories;
    }

    private function get_incoming_internal_links($post_id) {
        global $wpdb;

        $permalink = get_permalink($post_id);
        $site_url = home_url();

        // URLからドメインを除いたパス部分を取得
        $path = str_replace($site_url, '', $permalink);

        // 検索パターン: パーマリンク、相対パス、?p=ID、?page_id=ID
        $patterns = array(
            '%' . $wpdb->esc_like($permalink) . '%',
            '%href="' . $wpdb->esc_like($path) . '"%',
            '%?p=' . $post_id . '%',
            '%?page_id=' . $post_id . '%',
        );

        $where_clauses = array();
        foreach ($patterns as $pattern) {
            $where_clauses[] = $wpdb->prepare('post_content LIKE %s', $pattern);
        }

        $where = implode(' OR ', $where_clauses);

        $results = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE ({$where})
             AND ID != {$post_id}
             AND post_status IN ('publish', 'pending', 'draft', 'future', 'private')
             AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
             ORDER BY post_title ASC"
        );

        $incoming_links = array();
        if ($results) {
            foreach ($results as $row) {
                $incoming_links[] = array(
                    'id' => $row->ID,
                    'title' => $row->post_title ?: '(タイトルなし)',
                );
            }
        }

        return $incoming_links;
    }

    public function add_incoming_links_sort($clauses, $query) {
        global $wpdb;

        $order = isset($this->incoming_links_order) ? $this->incoming_links_order : 'DESC';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // 中間テーブルをLEFT JOINして被リンク数でソート
        $clauses['fields'] .= ", COALESCE(kil.incoming_count, 0) AS incoming_links_count";
        $clauses['join'] .= " LEFT JOIN {$this->table_name} kil ON {$wpdb->posts}.ID = kil.post_id";
        $clauses['orderby'] = "incoming_links_count {$order}";

        return $clauses;
    }

    public function handle_csv_download() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'kashiwazaki-seo-all-content-lister') {
            return;
        }

        if (!isset($_GET['action']) || $_GET['action'] !== 'csv_download') {
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_die('権限がありません。');
        }

        // Nonce確認
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'csv_download_nonce')) {
            wp_die('不正なリクエストです。');
        }

        $encoding = isset($_GET['encoding']) ? sanitize_text_field($_GET['encoding']) : 'utf8';
        $post_type_filter = isset($_GET['post_type_filter']) ? sanitize_text_field($_GET['post_type_filter']) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

        $post_types = get_post_types(array('public' => true), 'objects');
        $private_post_types = get_post_types(array('public' => false, 'show_ui' => true), 'objects');
        $post_types = array_merge($post_types, $private_post_types);

        $args = array(
            'post_type' => $post_type_filter ? $post_type_filter : array_keys($post_types),
            'posts_per_page' => -1,
            'post_status' => $status_filter ? $status_filter : array('publish', 'pending', 'draft', 'future', 'private', 'trash'),
        );

        $query = new WP_Query($args);

        $filename = 'content-list-' . date('Y-m-d-His') . '.csv';

        // 出力バッファをクリア
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=' . ($encoding === 'sjis' ? 'Shift_JIS' : 'UTF-8'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM（UTF-8の場合のみ）
        if ($encoding === 'utf8') {
            fwrite($output, "\xEF\xBB\xBF");
        }

        // ヘッダー行
        $headers = array('ID', 'タイトル', 'URL', '投稿タイプ', 'ステータス', 'カテゴリ', 'キーワード', 'ディスクリプション', '公開日', '更新日', 'スラッグ', '被リンク元');
        if ($encoding === 'sjis') {
            $headers = array_map(function($h) {
                return mb_convert_encoding($h, 'SJIS-win', 'UTF-8');
            }, $headers);
        }
        fputcsv($output, $headers);

        // データ行
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_obj = get_post($post_id);
                $post_type_obj = get_post_type_object($post_obj->post_type);
                $categories = $this->get_post_categories($post_id, $post_obj->post_type);

                // 被リンク元を取得（中間テーブルから）
                $incoming_links_data = $this->get_incoming_links_from_cache($post_id);
                $incoming_links_text = '';
                if (!$incoming_links_data['exists']) {
                    $incoming_links_text = '未調査';
                } elseif (!empty($incoming_links_data['links'])) {
                    $link_titles = array_map(function($link) {
                        return $link['title'] . ' (ID:' . $link['id'] . ')';
                    }, $incoming_links_data['links']);
                    $incoming_links_text = implode(' | ', $link_titles);
                }

                $row = array(
                    $post_id,
                    get_the_title() ?: '(タイトルなし)',
                    get_permalink($post_id),
                    $post_type_obj ? $post_type_obj->labels->singular_name : $post_obj->post_type,
                    $this->get_status_label($post_obj->post_status),
                    implode(', ', $categories),
                    $this->get_meta_keywords($post_id),
                    $this->get_meta_description($post_id),
                    get_the_date('Y/m/d H:i'),
                    get_the_modified_date('Y/m/d H:i'),
                    $post_obj->post_name,
                    $incoming_links_text,
                );

                if ($encoding === 'sjis') {
                    $row = array_map(function($v) {
                        return mb_convert_encoding($v, 'SJIS-win', 'UTF-8');
                    }, $row);
                }

                fputcsv($output, $row);
            }
            wp_reset_postdata();
        }

        fclose($output);
        exit;
    }

    public function render_admin_page() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $private_post_types = get_post_types(array('public' => false, 'show_ui' => true), 'objects');
        $post_types = array_merge($post_types, $private_post_types);

        $current_post_type = isset($_GET['post_type_filter']) ? sanitize_text_field($_GET['post_type_filter']) : '';
        $current_status = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 50;

        $args = array(
            'post_type' => $current_post_type ? $current_post_type : array_keys($post_types),
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => $orderby,
            'order' => $order,
            'post_status' => $current_status ? $current_status : array('publish', 'pending', 'draft', 'future', 'private', 'trash'),
        );

        // 被リンク元でソートする場合はフィルターを追加
        if ($orderby === 'incoming_links') {
            $this->incoming_links_order = $order;
            add_filter('posts_clauses', array($this, 'add_incoming_links_sort'), 10, 2);
        }

        $query = new WP_Query($args);

        // フィルターを削除
        if ($orderby === 'incoming_links') {
            remove_filter('posts_clauses', array($this, 'add_incoming_links_sort'), 10);
        }
        $total_posts = $query->found_posts;
        $total_pages = ceil($total_posts / $per_page);
        $columns = $this->get_columns();

        // CSVダウンロード用URL（デフォルトUTF-8）
        $csv_download_url = wp_nonce_url(
            add_query_arg(array(
                'page' => 'kashiwazaki-seo-all-content-lister',
                'action' => 'csv_download',
                'encoding' => 'utf8',
                'post_type_filter' => $current_post_type,
                'status_filter' => $current_status,
            ), admin_url('admin.php')),
            'csv_download_nonce'
        );

        ?>
        <div class="wrap kashiwazaki-seo-all-content-lister-wrap">
            <h1>Kashiwazaki SEO All Content Lister</h1>

            <div class="kashiwazaki-seo-all-content-lister-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="kashiwazaki-seo-all-content-lister">

                    <label for="post_type_filter">投稿タイプ:</label>
                    <select name="post_type_filter" id="post_type_filter">
                        <option value="">全て</option>
                        <?php foreach ($post_types as $type_slug => $type_obj): ?>
                            <option value="<?php echo esc_attr($type_slug); ?>" <?php selected($current_post_type, $type_slug); ?>>
                                <?php echo esc_html($type_obj->labels->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="status_filter">ステータス:</label>
                    <select name="status_filter" id="status_filter">
                        <option value="">全て</option>
                        <option value="publish" <?php selected($current_status, 'publish'); ?>>公開済み</option>
                        <option value="pending" <?php selected($current_status, 'pending'); ?>>レビュー待ち</option>
                        <option value="draft" <?php selected($current_status, 'draft'); ?>>下書き</option>
                        <option value="future" <?php selected($current_status, 'future'); ?>>予約済み</option>
                        <option value="private" <?php selected($current_status, 'private'); ?>>非公開</option>
                        <option value="trash" <?php selected($current_status, 'trash'); ?>>ゴミ箱</option>
                    </select>

                    <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
                    <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">

                    <button type="submit" class="button">フィルター適用</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kashiwazaki-seo-all-content-lister')); ?>" class="button">リセット</a>
                </form>
            </div>

            <div class="kashiwazaki-seo-all-content-lister-csv-export">
                <span class="export-label">CSVエクスポート:</span>
                <select id="csv-encoding">
                    <option value="utf8" selected>UTF-8</option>
                    <option value="sjis">Shift-JIS</option>
                </select>
                <a href="<?php echo esc_url($csv_download_url); ?>" id="csv-download-btn" class="button button-primary">CSVダウンロード</a>
            </div>

            <div class="kashiwazaki-seo-all-content-lister-link-scan">
                <?php
                $last_scan = get_option('kashiwazaki_link_scan_completed', '');
                $scan_status = get_option('kashiwazaki_link_scan_status', 'idle');
                ?>
                <span class="scan-label">被リンク調査:</span>
                <button type="button" id="start-link-scan-btn" class="button button-secondary" <?php echo $scan_status === 'running' ? 'disabled' : ''; ?>>
                    被リンク調査を実行
                </button>
                <span id="link-scan-status">
                    <?php if ($scan_status === 'running'): ?>
                        <span class="scan-running">スキャン中...</span>
                    <?php elseif (!empty($last_scan)): ?>
                        <span class="scan-completed"><?php echo esc_html($last_scan); ?> に生成しました</span>
                    <?php else: ?>
                        <span class="scan-none">未実行</span>
                    <?php endif; ?>
                </span>
                <span id="link-scan-progress" style="display: none;">
                    <span class="progress-text">0 / 0</span>
                    <progress id="link-scan-progress-bar" value="0" max="100"></progress>
                </span>
            </div>

            <div class="kashiwazaki-seo-all-content-lister-column-toggles">
                <span class="toggle-label">表示カラム:</span>
                <?php foreach ($columns as $column_key => $column_label): ?>
                    <label class="column-toggle">
                        <input type="checkbox" class="column-toggle-checkbox" data-column="<?php echo esc_attr($column_key); ?>" checked>
                        <?php echo esc_html($column_label); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="kashiwazaki-seo-all-content-lister-info">
                <p>全 <?php echo number_format($total_posts); ?> 件中 <?php echo number_format(($paged - 1) * $per_page + 1); ?> - <?php echo number_format(min($paged * $per_page, $total_posts)); ?> 件を表示</p>
            </div>

            <table class="wp-list-table widefat fixed striped kashiwazaki-seo-all-content-lister-table">
                <thead>
                    <tr>
                        <?php foreach ($columns as $column_key => $column_label):
                            $sort_url = add_query_arg(array(
                                'orderby' => $column_key,
                                'order' => ($orderby === $column_key && $order === 'ASC') ? 'DESC' : 'ASC',
                            ));
                            $is_sorted = ($orderby === $column_key);
                            $sort_dir = ($is_sorted && $order === 'ASC') ? 'asc' : 'desc';
                        ?>
                            <th scope="col" class="column-<?php echo esc_attr($column_key); ?> <?php echo $is_sorted ? 'sorted' : 'sortable'; ?>" data-column="<?php echo esc_attr($column_key); ?>">
                                <a href="<?php echo esc_url($sort_url); ?>">
                                    <?php echo esc_html($column_label); ?>
                                    <span class="sort-icon"><?php echo $is_sorted ? ($order === 'ASC' ? '▲' : '▼') : ''; ?></span>
                                </a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->have_posts()): ?>
                        <?php while ($query->have_posts()): $query->the_post(); ?>
                            <?php
                            $post_id = get_the_ID();
                            $post_obj = get_post($post_id);
                            $post_type_obj = get_post_type_object($post_obj->post_type);
                            $categories = $this->get_post_categories($post_id, $post_obj->post_type);
                            $keywords = $this->get_meta_keywords($post_id);
                            $description = $this->get_meta_description($post_id);
                            $incoming_links = $this->get_incoming_links_from_cache($post_id);
                            ?>
                            <tr>
                                <td class="column-ID" data-column="ID"><?php echo esc_html($post_id); ?></td>
                                <td class="column-title" data-column="title">
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">
                                            <?php echo esc_html(get_the_title() ?: '(タイトルなし)'); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>">編集</a> |
                                        </span>
                                        <?php if ($post_obj->post_status !== 'trash'): ?>
                                        <span class="view">
                                            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank">表示</a> |
                                        </span>
                                        <?php endif; ?>
                                        <span class="link-map">
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=kashiwazaki-seo-link-map&post_id=' . $post_id)); ?>">リンクマップ</a>
                                        </span>
                                    </div>
                                </td>
                                <td class="column-permalink" data-column="permalink">
                                    <?php $permalink = get_permalink($post_id); ?>
                                    <a href="<?php echo esc_url($permalink); ?>" target="_blank" class="permalink-link" title="<?php echo esc_attr($permalink); ?>">
                                        <?php echo esc_html($permalink); ?>
                                    </a>
                                </td>
                                <td class="column-post_type" data-column="post_type">
                                    <span class="post-type-badge post-type-<?php echo esc_attr($post_obj->post_type); ?>">
                                        <?php echo esc_html($post_type_obj ? $post_type_obj->labels->singular_name : $post_obj->post_type); ?>
                                    </span>
                                </td>
                                <td class="column-post_status" data-column="post_status">
                                    <span class="status-badge status-<?php echo esc_attr($post_obj->post_status); ?>">
                                        <?php echo esc_html($this->get_status_label($post_obj->post_status)); ?>
                                    </span>
                                </td>
                                <td class="column-categories" data-column="categories">
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <span class="category-badge"><?php echo esc_html($cat); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="no-data">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-keywords" data-column="keywords">
                                    <?php if (!empty($keywords)): ?>
                                        <span class="keywords-text"><?php echo esc_html($keywords); ?></span>
                                    <?php else: ?>
                                        <span class="no-data">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-description" data-column="description">
                                    <?php if (!empty($description)): ?>
                                        <span class="description-text" title="<?php echo esc_attr($description); ?>"><?php echo esc_html(mb_substr($description, 0, 50)); ?><?php echo mb_strlen($description) > 50 ? '...' : ''; ?></span>
                                    <?php else: ?>
                                        <span class="no-data">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-date" data-column="date">
                                    <?php echo esc_html(get_the_date('Y/m/d H:i')); ?>
                                </td>
                                <td class="column-modified" data-column="modified">
                                    <?php echo esc_html(get_the_modified_date('Y/m/d H:i')); ?>
                                </td>
                                <td class="column-post_name" data-column="post_name">
                                    <code><?php echo esc_html($post_obj->post_name); ?></code>
                                </td>
                                <td class="column-incoming_links" data-column="incoming_links">
                                    <?php if (!$incoming_links['exists']): ?>
                                        <span class="not-scanned">未調査</span>
                                    <?php elseif (!empty($incoming_links['links'])): ?>
                                        <?php
                                        $links_count = count($incoming_links['links']);
                                        $first_link = $incoming_links['links'][0];
                                        ?>
                                        <div class="incoming-links-summary">
                                            <a href="<?php echo esc_url(get_edit_post_link($first_link['id'])); ?>" class="incoming-link-first" title="<?php echo esc_attr($first_link['title']); ?>">
                                                <?php echo esc_html(mb_substr($first_link['title'], 0, 15)); ?><?php echo mb_strlen($first_link['title']) > 15 ? '...' : ''; ?>
                                            </a>
                                            <?php if ($links_count > 1): ?>
                                                <span class="incoming-links-more">他<?php echo $links_count - 1; ?>件</span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="incoming-links-toggle button-link"><?php echo $links_count; ?>件の詳細を見る</button>
                                        <div class="incoming-links-detail" style="display: none;">
                                            <?php foreach ($incoming_links['links'] as $link): ?>
                                                <a href="<?php echo esc_url(get_edit_post_link($link['id'])); ?>" class="incoming-link-item" title="<?php echo esc_attr($link['title']); ?>">
                                                    <?php echo esc_html(mb_substr($link['title'], 0, 25)); ?><?php echo mb_strlen($link['title']) > 25 ? '...' : ''; ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="no-data">0件</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php wp_reset_postdata(); ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo count($columns); ?>">コンテンツが見つかりませんでした。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="kashiwazaki-seo-all-content-lister-pagination">
                <?php
                $pagination_args = array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo; 前へ',
                    'next_text' => '次へ &raquo;',
                    'total' => $total_pages,
                    'current' => $paged,
                );
                echo paginate_links($pagination_args);
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_status_label($status) {
        $labels = array(
            'publish' => '公開済み',
            'pending' => 'レビュー待ち',
            'draft' => '下書き',
            'future' => '予約済み',
            'private' => '非公開',
            'trash' => 'ゴミ箱',
            'auto-draft' => '自動下書き',
            'inherit' => '継承',
        );
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    public function render_link_map_page() {
        $last_scan = get_option('kashiwazaki_link_scan_completed', '');
        ?>
        <div class="wrap kashiwazaki-seo-link-map-wrap">
            <h1>リンクマップ</h1>

            <?php if (empty($last_scan)): ?>
                <div class="notice notice-warning">
                    <p>被リンク調査が未実行です。<a href="<?php echo esc_url(admin_url('admin.php?page=kashiwazaki-seo-all-content-lister')); ?>">コンテンツ一覧</a>から「被リンク調査を実行」してください。</p>
                </div>
            <?php else: ?>
                <p class="description">最終調査: <?php echo esc_html($last_scan); ?></p>
            <?php endif; ?>

            <div class="link-map-controls">
                <label for="center-post-select">中心にする記事:</label>
                <select id="center-post-select">
                    <option value="">選択してください</option>
                </select>
                <button type="button" id="reset-view-btn" class="button">表示をリセット</button>
            </div>

            <div class="link-map-legend">
                <span class="legend-item"><span class="legend-dot center"></span> 選択中</span>
                <span class="legend-item"><span class="legend-dot linked-from"></span> リンク元（この記事にリンクしている）</span>
                <span class="legend-item"><span class="legend-dot linked-to"></span> リンク先（この記事からリンクしている）</span>
            </div>

            <div id="link-map-container">
                <div id="link-map-loading">読み込み中...</div>
                <svg id="link-map-svg"></svg>
            </div>

            <div id="link-map-info" style="display: none;">
                <h3 id="info-title"></h3>
                <p><a id="info-edit-link" href="#" target="_blank">編集画面を開く</a></p>
                <div id="info-links"></div>
            </div>
        </div>
        <?php
    }

    public function ajax_get_link_map_data() {
        check_ajax_referer('kashiwazaki_link_map_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません。');
        }

        global $wpdb;

        // テーブルが存在するか確認
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        if (!$table_exists) {
            // テーブルがなければ作成
            $this->create_table();
        }

        $center_post_id = isset($_POST['center_post_id']) ? absint($_POST['center_post_id']) : 0;

        // 全投稿のリンク情報を取得
        $results = $wpdb->get_results(
            "SELECT post_id, incoming_count, incoming_post_ids FROM {$this->table_name}"
        );

        $nodes = array();
        $links = array();
        $node_ids = array();

        // まず全投稿をノードとして追加
        $post_types = get_post_types(array('public' => true), 'names');
        $private_post_types = get_post_types(array('public' => false, 'show_ui' => true), 'names');
        $all_types = array_merge($post_types, $private_post_types);

        $posts = get_posts(array(
            'post_type' => $all_types,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
        ));

        foreach ($posts as $post) {
            $nodes[] = array(
                'id' => $post->ID,
                'title' => $post->post_title ?: '(タイトルなし)',
                'type' => $post->post_type,
                'url' => get_edit_post_link($post->ID, 'raw'),
            );
            $node_ids[$post->ID] = true;
        }

        // リンク関係を構築
        foreach ($results as $row) {
            $target_id = $row->post_id;
            $source_ids = json_decode($row->incoming_post_ids, true);

            if (!is_array($source_ids)) {
                continue;
            }

            foreach ($source_ids as $source_id) {
                if (isset($node_ids[$source_id]) && isset($node_ids[$target_id])) {
                    $links[] = array(
                        'source' => $source_id,
                        'target' => $target_id,
                    );
                }
            }
        }

        wp_send_json_success(array(
            'nodes' => $nodes,
            'links' => $links,
            'center_post_id' => $center_post_id,
        ));
    }

    // 被リンクスキャン開始
    public function ajax_start_link_scan() {
        check_ajax_referer('kashiwazaki_link_scan_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません。');
        }

        // テーブルが存在しない場合は作成
        $this->create_table();

        // スキャン状態をリセット
        delete_option('kashiwazaki_link_scan_progress');
        delete_option('kashiwazaki_link_scan_total');

        // 全投稿数を取得
        $post_types = get_post_types(array('public' => true), 'names');
        $private_post_types = get_post_types(array('public' => false, 'show_ui' => true), 'names');
        $post_types = array_merge($post_types, $private_post_types);

        $args = array(
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
            'fields' => 'ids',
        );
        $all_posts = get_posts($args);
        $total = count($all_posts);

        update_option('kashiwazaki_link_scan_total', $total);
        update_option('kashiwazaki_link_scan_progress', 0);
        update_option('kashiwazaki_link_scan_status', 'running');
        update_option('kashiwazaki_link_scan_post_ids', $all_posts);

        wp_send_json_success(array(
            'total' => $total,
            'message' => 'スキャンを開始しました。',
        ));
    }

    // スキャン状態確認
    public function ajax_check_scan_status() {
        check_ajax_referer('kashiwazaki_link_scan_nonce', 'nonce');

        $status = get_option('kashiwazaki_link_scan_status', 'idle');
        $progress = get_option('kashiwazaki_link_scan_progress', 0);
        $total = get_option('kashiwazaki_link_scan_total', 0);
        $last_scan = get_option('kashiwazaki_link_scan_completed', '');

        wp_send_json_success(array(
            'status' => $status,
            'progress' => $progress,
            'total' => $total,
            'last_scan' => $last_scan,
        ));
    }

    // バッチ処理
    public function ajax_process_link_scan() {
        check_ajax_referer('kashiwazaki_link_scan_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません。');
        }

        $status = get_option('kashiwazaki_link_scan_status', 'idle');
        if ($status !== 'running') {
            wp_send_json_error('スキャンが実行されていません。');
        }

        $post_ids = get_option('kashiwazaki_link_scan_post_ids', array());
        $progress = get_option('kashiwazaki_link_scan_progress', 0);
        $total = get_option('kashiwazaki_link_scan_total', 0);

        // 1回のリクエストで処理する件数
        $batch_size = 10;
        $processed = 0;

        global $wpdb;

        for ($i = $progress; $i < min($progress + $batch_size, $total); $i++) {
            if (!isset($post_ids[$i])) {
                continue;
            }

            $post_id = $post_ids[$i];
            $incoming_links = $this->scan_incoming_links_for_post($post_id);

            // 中間テーブルに保存
            $wpdb->replace(
                $this->table_name,
                array(
                    'post_id' => $post_id,
                    'incoming_count' => count($incoming_links),
                    'incoming_post_ids' => json_encode($incoming_links),
                    'updated_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s')
            );

            $processed++;
        }

        $new_progress = $progress + $processed;
        update_option('kashiwazaki_link_scan_progress', $new_progress);

        // 完了チェック
        if ($new_progress >= $total) {
            update_option('kashiwazaki_link_scan_status', 'completed');
            update_option('kashiwazaki_link_scan_completed', current_time('Y/m/d H:i'));
            delete_option('kashiwazaki_link_scan_post_ids');

            wp_send_json_success(array(
                'status' => 'completed',
                'progress' => $new_progress,
                'total' => $total,
                'last_scan' => get_option('kashiwazaki_link_scan_completed'),
            ));
        } else {
            wp_send_json_success(array(
                'status' => 'running',
                'progress' => $new_progress,
                'total' => $total,
            ));
        }
    }

    // 個別投稿の被リンク元をスキャン
    private function scan_incoming_links_for_post($post_id) {
        global $wpdb;

        $permalink = get_permalink($post_id);
        $site_url = home_url();
        $path = str_replace($site_url, '', $permalink);

        $patterns = array(
            '%' . $wpdb->esc_like($permalink) . '%',
            '%href="' . $wpdb->esc_like($path) . '"%',
            '%?p=' . $post_id . '%',
            '%?page_id=' . $post_id . '%',
        );

        $where_clauses = array();
        foreach ($patterns as $pattern) {
            $where_clauses[] = $wpdb->prepare('post_content LIKE %s', $pattern);
        }

        $where = implode(' OR ', $where_clauses);

        $results = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE ({$where})
             AND ID != {$post_id}
             AND post_status IN ('publish', 'pending', 'draft', 'future', 'private')
             AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
             ORDER BY ID ASC"
        );

        return $results ? array_map('intval', $results) : array();
    }

    // 中間テーブルから被リンク元を取得
    // 戻り値: array('exists' => bool, 'links' => array)
    private function get_incoming_links_from_cache($post_id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT incoming_count, incoming_post_ids FROM {$this->table_name} WHERE post_id = %d",
                $post_id
            )
        );

        if (!$row) {
            // 中間テーブルにデータがない = 未調査
            return array('exists' => false, 'links' => array());
        }

        $incoming_post_ids = json_decode($row->incoming_post_ids, true);
        if (!is_array($incoming_post_ids)) {
            $incoming_post_ids = array();
        }

        $incoming_links = array();
        foreach ($incoming_post_ids as $source_id) {
            $title = get_the_title($source_id);
            $incoming_links[] = array(
                'id' => $source_id,
                'title' => $title ?: '(タイトルなし)',
            );
        }

        return array('exists' => true, 'links' => $incoming_links);
    }
}

register_activation_hook(__FILE__, array('Kashiwazaki_SEO_All_Content_Lister', 'activate'));
Kashiwazaki_SEO_All_Content_Lister::get_instance();

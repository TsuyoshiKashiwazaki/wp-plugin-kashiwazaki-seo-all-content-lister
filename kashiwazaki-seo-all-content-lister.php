<?php
/**
 * Plugin Name: Kashiwazaki SEO All Content Lister
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: 全ての投稿・固定ページ・カスタム投稿タイプを一覧表示し、ソート・フィルター機能を提供する管理ツール
 * Version: 1.0.0
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

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        add_action('init', array($this, 'handle_csv_download'));
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
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_kashiwazaki-seo-all-content-lister') {
            return;
        }

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
        $headers = array('ID', 'タイトル', 'URL', '投稿タイプ', 'ステータス', 'カテゴリ', 'キーワード', 'ディスクリプション', '公開日', '更新日', 'スラッグ');
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

        $query = new WP_Query($args);
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
                                            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank">表示</a>
                                        </span>
                                        <?php endif; ?>
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
}

Kashiwazaki_SEO_All_Content_Lister::get_instance();

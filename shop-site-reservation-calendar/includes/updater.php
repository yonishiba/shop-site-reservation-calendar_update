<?php
/**
 * 自動更新機能
 * 
 * Cloudflare Workers経由でGitHub Releasesから更新情報を取得し、
 * WordPressの自動更新機能と連携します。
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 更新情報APIのURL（Cloudflare Workers）
 */
define('RCAL_UPDATE_URL', 'https://license-server.yoshinori-nishibayashi.workers.dev/update.json');

/**
 * プラグインのメインファイル相対パス
 */
define('RCAL_PLUGIN_FILE', 'shop-site-reservation-calendar/shop_site_reservation_calendar.php');

/**
 * プラグインスラッグ
 */
define('RCAL_PLUGIN_SLUG', 'shop-site-reservation-calendar');

/**
 * 現在のプラグインバージョンを取得
 * 
 * @return string バージョン番号
 */
function rcal_get_current_version() {
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_file = WP_PLUGIN_DIR . '/' . RCAL_PLUGIN_FILE;
    $plugin_data = get_plugin_data($plugin_file, false, false);
    return isset($plugin_data['Version']) ? trim($plugin_data['Version']) : '0.0.0';
}

/**
 * Cloudflare Workersから更新情報を取得
 * 
 * @return array|null 更新情報、または取得失敗時はnull
 */
function rcal_fetch_update_info() {
    $response = wp_remote_get(RCAL_UPDATE_URL, array(
        'timeout'   => 10,
        'sslverify' => true,
        'headers'   => array(
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version'),
        ),
    ));

    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[RCAL Update] Fetch failed: ' . $response->get_error_message());
        }
        return null;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[RCAL Update] HTTP ' . $code . ' response');
        }
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data)) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[RCAL Update] Invalid JSON response');
        }
        return null;
    }

    // 必須フィールドの検証
    $required = array('latest_version', 'download_url', 'requires', 'tested');
    foreach ($required as $field) {
        if (!array_key_exists($field, $data)) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[RCAL Update] Missing required field: ' . $field);
            }
            return null;
        }
    }

    // download_urlはhttps必須
    if (stripos($data['download_url'], 'https://') !== 0) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[RCAL Update] Invalid download URL (not HTTPS)');
        }
        return null;
    }

    return $data;
}

/**
 * バージョン比較（新しいバージョンかチェック）
 * 
 * @param string $remote リモートバージョン
 * @param string $local ローカルバージョン
 * @return bool リモートが新しい場合true
 */
function rcal_is_newer_version($remote, $local) {
    // "v1.2.3" のような先頭の 'v' を除去
    $remote_norm = ltrim($remote, 'vV');
    $local_norm = ltrim($local, 'vV');
    return version_compare($remote_norm, $local_norm, '>');
}

/**
 * 更新チェックフック：WordPressに新しいバージョンがあることを通知
 * 
 * @param object $transient update_pluginsのトランジェント
 * @return object 更新情報を追加したトランジェント
 */
add_filter('site_transient_update_plugins', function($transient) {
    if (!is_object($transient)) {
        $transient = new stdClass();
    }
    
    // WordPress標準の更新チェックでない場合はスキップ
    if (empty($transient->checked)) {
        return $transient;
    }

    // 現在のバージョンを取得
    $current_version = rcal_get_current_version();
    
    // 更新情報を取得
    $info = rcal_fetch_update_info();
    if (!$info) {
        return $transient; // 取得失敗時はスキップ
    }

    // バージョン比較
    if (!rcal_is_newer_version($info['latest_version'], $current_version)) {
        return $transient; // 新しくない場合はスキップ
    }

    // 更新データをセット
    $plugin_update = (object) array(
        'slug'        => RCAL_PLUGIN_SLUG,
        'plugin'      => RCAL_PLUGIN_FILE,
        'new_version' => ltrim($info['latest_version'], 'vV'),
        'url'         => 'https://github.com/yonishiba/shop-site-reservation-calendar_update',
        'package'     => $info['download_url'],
        'tested'      => $info['tested'],
        'requires_php' => isset($info['requires_php']) ? $info['requires_php'] : '8.0',
    );

    $transient->response[RCAL_PLUGIN_FILE] = $plugin_update;

    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[RCAL Update] New version available: ' . $info['latest_version']);
    }

    return $transient;
}, 10, 1);

/**
 * プラグイン詳細情報フック：「詳細を表示」ポップアップ用
 * 
 * @param false|object|array $result プラグイン情報
 * @param string $action 実行するアクション
 * @param object $args リクエスト引数
 * @return false|object|array プラグイン情報
 */
add_filter('plugins_api', function($result, $action, $args) {
    if ($action !== 'plugin_information') {
        return $result;
    }
    
    if (!isset($args->slug) || $args->slug !== RCAL_PLUGIN_SLUG) {
        return $result;
    }

    // 更新情報を取得
    $info = rcal_fetch_update_info();
    if (!$info) {
        return new WP_Error('plugins_api_failed', '更新情報の取得に失敗しました。', array('status' => 502));
    }

    // 説明文とチェンジログの整形
    $description = isset($info['description']) 
        ? wp_kses_post($info['description']) 
        : 'WordPress向け来店予約カレンダープラグイン（シングルサイト仕様）。';
    
    $changelog = isset($info['changelog']) && !empty($info['changelog'])
        ? '<pre style="white-space: pre-wrap; word-wrap: break-word;">' . esc_html($info['changelog']) . '</pre>'
        : '変更履歴はGitHubリリースページをご確認ください。';

    // プラグイン情報オブジェクトを構築
    $plugin_info = (object) array(
        'name'          => 'Shop Site Reservation Calendar',
        'slug'          => RCAL_PLUGIN_SLUG,
        'version'       => ltrim($info['latest_version'], 'vV'),
        'author'        => '<a href="https://votra.jp">VOTRA Co., Ltd.</a>',
        'homepage'      => 'https://github.com/yonishiba/shop-site-reservation-calendar_update',
        'requires'      => $info['requires'],
        'tested'        => $info['tested'],
        'requires_php'  => isset($info['requires_php']) ? $info['requires_php'] : '8.0',
        'download_link' => $info['download_url'],
        'sections'      => array(
            'description' => $description,
            'changelog'   => $changelog,
        ),
        'banners'       => array(),
        'icons'         => array(),
    );

    return $plugin_info;
}, 10, 3);


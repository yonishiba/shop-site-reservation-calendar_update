<?php
/**
 * ライセンス管理機能
 * 
 * Cloudflare Workers経由でライセンスキーを検証し、
 * 有効性をTransient APIでキャッシュします。
 */

if (!defined('ABSPATH')) exit;

// ライセンスキー保存用オプションキー
define('RCAL_LICENSE_OPTION_KEY', 'rcal_license_key');

// ライセンス検証結果キャッシュ用Transientキー
define('RCAL_LICENSE_CACHE_TRANSIENT', 'rcal_license_cache');

// インストールUUID保存用オプションキー
define('RCAL_LICENSE_UUID_KEY', 'rcal_install_uuid');

// Cloudflare Workers検証エンドポイント
define('RCAL_LICENSE_ENDPOINT', 'https://license-server.yoshinori-nishibayashi.workers.dev/license/verify');

/**
 * ライセンスキーの伏字化
 * 
 * @param string $key ライセンスキー
 * @return string 伏字化されたキー
 */
function rcal_mask_key($key) {
    if (!$key) return '';
    $len = strlen($key);
    if ($len <= 6) return str_repeat('•', $len);
    return substr($key, 0, 3) . str_repeat('•', max(0, $len - 6)) . substr($key, -3);
}

/**
 * エラー理由の日本語変換
 * 
 * @param string $reason エラー理由コード
 * @return string 日本語のエラーメッセージ
 */
function rcal_translate_license_error($reason) {
    $messages = array(
        'bad_request'       => 'リクエストパラメータに問題があります。サポートにお問い合わせください。',
        'not_found'         => 'ライセンスキーが見つかりません。キーを確認してください。',
        'disabled'          => 'このライセンスキーは無効化されています。',
        'expired'           => 'ライセンスキーの有効期限が切れています。',
        'domain_mismatch'   => 'このドメインではライセンスキーを使用できません。',
        'activation_limit'  => 'アクティベーション上限に達しています。別のサイトで使用されている可能性があります。',
        'network_error'     => 'ネットワークエラーが発生しました。時間をおいて再度お試しください。',
        'invalid_response'  => 'サーバーからの応答が不正です。',
        'unknown'           => '不明なエラーが発生しました。',
    );
    return isset($messages[$reason]) ? $messages[$reason] : $messages['unknown'];
}

/**
 * ライセンス検証（APIリクエスト + キャッシュ管理）
 * 
 * @param bool $force trueの場合、キャッシュを無視して必ず検証APIを呼ぶ
 * @return array ['valid' => bool, 'reason' => string|null, 'data' => array|null]
 */
function rcal_verify_license($force = false) {
    $key = get_option(RCAL_LICENSE_OPTION_KEY);
    if (!$key) {
        delete_transient(RCAL_LICENSE_CACHE_TRANSIENT);
        return array('valid' => false, 'reason' => 'not_found', 'data' => null);
    }

    // キャッシュチェック（force=falseの場合のみ）
    if (!$force) {
        $cache = get_transient(RCAL_LICENSE_CACHE_TRANSIENT);
        if ($cache && is_array($cache)) {
            // 有効なキャッシュがある場合
            if (!empty($cache['valid'])) {
                // 期限チェック
                $expires_at = isset($cache['expires_at']) ? $cache['expires_at'] : null;
                if ($expires_at === null) {
                    // 無期限
                    return array('valid' => true, 'reason' => null, 'data' => $cache);
                }
                $expires_ts = strtotime($expires_at);
                if ($expires_ts !== false && time() <= $expires_ts) {
                    // 期限内
                    return array('valid' => true, 'reason' => null, 'data' => $cache);
                }
                // 期限切れ → APIで再検証
            }
            // 無効キャッシュがある → APIで再検証
        }
    }

    // ドメイン取得
    $domain = isset($_SERVER['HTTP_HOST']) 
        ? $_SERVER['HTTP_HOST'] 
        : parse_url(home_url(), PHP_URL_HOST);
    
    // UUID取得（未生成の場合は自動生成）
    $uuid = get_option(RCAL_LICENSE_UUID_KEY);
    if (!$uuid) {
        // UUIDが未生成の場合は新規生成
        $uuid = wp_generate_uuid4();
        update_option(RCAL_LICENSE_UUID_KEY, $uuid, false);
    }

    // リクエストボディ準備
    $request_body = array(
        'key'    => $key,
        'domain' => $domain,
        'uuid'   => $uuid,
    );
    
    // デバッグログ（WP_DEBUG有効時）
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[RCAL License] Request to ' . RCAL_LICENSE_ENDPOINT);
        error_log('[RCAL License] Body: ' . wp_json_encode($request_body));
    }
    
    // APIへリクエスト
    $resp = wp_remote_post(RCAL_LICENSE_ENDPOINT, array(
        'timeout' => 10,
        'headers' => array(
            'User-Agent'   => 'WordPress/' . get_bloginfo('version'),
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode($request_body),
    ));

    // ネットワークエラー処理
    if (is_wp_error($resp)) {
        // 最後の有効キャッシュを確認
        $cache = get_transient(RCAL_LICENSE_CACHE_TRANSIENT);
        if ($cache && !empty($cache['valid'])) {
            $expires_at = isset($cache['expires_at']) ? $cache['expires_at'] : null;
            if ($expires_at === null) {
                // 無期限なら継続許可
                return array('valid' => true, 'reason' => null, 'data' => $cache);
            }
            $expires_ts = strtotime($expires_at);
            if ($expires_ts !== false && time() <= $expires_ts) {
                // 期限内なら継続許可
                return array('valid' => true, 'reason' => null, 'data' => $cache);
            }
        }
        // キャッシュがない、または期限切れ
        return array('valid' => false, 'reason' => 'network_error', 'data' => null);
    }

    // レスポンス処理
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $data = json_decode($body, true);
    
    // デバッグログ（WP_DEBUG有効時）
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[RCAL License] Response Code: ' . $code);
        error_log('[RCAL License] Response Body: ' . $body);
    }

    // JSON解析失敗
    if (!is_array($data)) {
        return array('valid' => false, 'reason' => 'invalid_response', 'data' => null);
    }

    // 200 OK
    if ($code === 200) {
        $valid = !empty($data['valid']);
        
        if ($valid) {
            // 追加の期限チェック（expires_at）
            $expires_at = isset($data['expires_at']) ? $data['expires_at'] : null;
            if ($expires_at !== null) {
                $expires_ts = strtotime($expires_at);
                if ($expires_ts !== false && time() > $expires_ts) {
                    $valid = false;
                    $data['valid'] = false;
                    $data['reason'] = 'expired';
                }
            }
        }

        // キャッシュに保存（成功・失敗どちらも）
        $cache_data = array(
            'valid'      => $valid,
            'plan'       => isset($data['plan']) ? $data['plan'] : null,
            'domain'     => isset($data['domain']) ? $data['domain'] : null,
            'expires_at' => isset($data['expires_at']) ? $data['expires_at'] : null,
            'features'   => isset($data['features']) ? $data['features'] : array(),
            'reason'     => $valid ? null : (isset($data['reason']) ? $data['reason'] : 'unknown'),
            'status'     => isset($data['status']) ? $data['status'] : 'active', // サブスクステータス（active/canceled/expired）
        );

        // TTLの決定（ttl_hintがあればそれを使用、なければ12時間）
        $ttl = isset($data['ttl_hint']) ? absint($data['ttl_hint']) : (12 * HOUR_IN_SECONDS);
        set_transient(RCAL_LICENSE_CACHE_TRANSIENT, $cache_data, $ttl);

        $reason = $valid ? null : $cache_data['reason'];
        return array('valid' => $valid, 'reason' => $reason, 'data' => $cache_data);
    }

    // 400 Bad Request
    if ($code === 400) {
        $reason = isset($data['reason']) ? $data['reason'] : 'bad_request';
        return array('valid' => false, 'reason' => $reason, 'data' => null);
    }

    // その他のステータスコード
    return array('valid' => false, 'reason' => 'unknown', 'data' => null);
}

/**
 * ライセンス有効性判定（高速版・キャッシュのみ参照）
 * 
 * 機能ゲートで使用する軽量な判定関数。
 * API呼び出しは行わず、キャッシュのみを参照します。
 * 
 * @return bool ライセンスが有効ならtrue
 */
function rcal_is_licensed() {
    $cache = get_transient(RCAL_LICENSE_CACHE_TRANSIENT);
    if (!$cache || !is_array($cache)) {
        return false;
    }
    
    if (empty($cache['valid'])) {
        return false;
    }
    
    // 期限チェック
    $expires_at = isset($cache['expires_at']) ? $cache['expires_at'] : null;
    if ($expires_at === null) {
        // 無期限
        return true;
    }
    
    $expires_ts = strtotime($expires_at);
    if ($expires_ts === false) {
        return false;
    }
    
    return time() <= $expires_ts;
}

/**
 * ライセンス情報の取得（キャッシュから）
 * 
 * @return array|null ライセンス情報、またはnull
 */
function rcal_get_license_info() {
    $cache = get_transient(RCAL_LICENSE_CACHE_TRANSIENT);
    if (!$cache || !is_array($cache)) {
        return null;
    }
    return $cache;
}

/**
 * プラン名の日本語変換
 * 
 * @param string $plan プランID
 * @return string 日本語のプラン名
 */
function rcal_translate_plan_name($plan) {
    $plan_names = array(
        'standard'         => 'スタンダード（月額）',
        'standard_monthly' => 'スタンダード（月額）',
        'standard_annual'  => 'スタンダード（年額）',
    );
    return isset($plan_names[$plan]) ? $plan_names[$plan] : $plan;
}

/**
 * WP-Cron: 定期的なライセンス再検証
 */
add_action('rcal_license_reverify_event', function() {
    rcal_verify_license(true);
});


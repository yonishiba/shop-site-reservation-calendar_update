<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* ------------------------------------------------------------------------------ */
global $wp_version;
global $rcal_tableName;
global $rcal_db_version_optionKey;
global $rcal_db_version;
global $rcal_status;

/* ------------------------------------------------------------------------------ */
/**
 * 【管理画面】専用ページ追加発動用
 */
add_action('admin_menu', 'rcal_add_admin_optionpages');

function rcal_add_admin_optionpages(){
	// NOTE: author 権限のユーザにのみメニュー表示
	add_menu_page(
        '予約カレンダー',
        '予約カレンダー',
        'author',
        'rcal-reservation-calendar',
        '_rcal_admin_calendar_optionpage_contents',
        'dashicons-calendar-alt',
        '98'
    );
}

/**
 * 【管理画面】CSS/JS読み込み発動用
 */
add_action( 'admin_enqueue_scripts', 'rcal_style_script_enqueue' );
function rcal_style_script_enqueue($hook_suffix) {
    switch($hook_suffix) {
        case 'toplevel_page_rcal-reservation-calendar':
			wp_enqueue_script('rcal_admin_js', RCAL_URL . '/js/admin/calendarSettings.js');
            wp_enqueue_style('rcal_admin_css', RCAL_URL . '/css/admin/calendarSettings.css');
            // 管理画面にもアクセントカラー適用（必要時）
            $accent = get_option('rcal_accent_color');
            $accent = sanitize_hex_color($accent) ?: '#90edc7';
            $on_accent = get_option('rcal_on_accent_color');
            $on_accent = sanitize_hex_color($on_accent) ?: '#111111';
            if (!empty($accent)) {
                $inline = ":root{--rcal-accent: {$accent}; --rcal-on-accent: {$on_accent};}";
                wp_add_inline_style('rcal_admin_css', $inline);
            }
            break;
        case 'settings_page_rcal-master-settings':
            // マスター設定ページ用のカラー・ピッカー
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
			// メディアアップローダ
			if(function_exists('wp_enqueue_media')){ wp_enqueue_media(); }
            break;
        case 'profile.php':
        case 'user-edit.php':
            wp_enqueue_style('rcal_admin_profile_css', RCAL_URL . '/css/admin/userProfile.css');
            $accent = get_option('rcal_accent_color');
            $accent = sanitize_hex_color($accent) ?: '#90edc7';
            $on_accent = get_option('rcal_on_accent_color');
            $on_accent = sanitize_hex_color($on_accent) ?: '#111111';
            if (!empty($accent)) {
                $inline = ":root{--rcal-accent: {$accent}; --rcal-on-accent: {$on_accent};}";
                wp_add_inline_style('rcal_admin_profile_css', $inline);
            }
            break;
    }
}

/* ------------------------------------------------------------------------------ */
/**
 * 【管理画面】マスター設定（テーマカラー）
 */
add_action('admin_menu', 'rcal_add_master_settings_page');
function rcal_add_master_settings_page(){
    add_options_page(
        '予約カレンダー設定',
        '予約カレンダー設定',
        'manage_options',
        'rcal-master-settings',
        '_rcal_master_settings_page'
    );
}

/**
 * プラグイン一覧に「設定」リンクを追加
 */
add_filter('plugin_action_links_shop-site-reservation-calendar/shop_site_reservation_calendar.php', 'rcal_add_settings_link');
function rcal_add_settings_link($links){
    $settings_link = '<a href="' . admin_url('options-general.php?page=rcal-master-settings') . '">設定</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function _rcal_master_settings_page(){
    if(!current_user_can('manage_options')){ return; }
    
    // ライセンスキー保存・検証処理
    $license_message = '';
    $license_message_type = '';
    if(isset($_POST['rcal_license_submit'])){
        if(!isset($_POST['_rcal_license_nonce']) || !wp_verify_nonce($_POST['_rcal_license_nonce'], 'rcal_license_save')){
            $license_message = '不正なリクエストです。';
            $license_message_type = 'error';
        } else {
            $key = isset($_POST['rcal_license_key']) ? sanitize_text_field($_POST['rcal_license_key']) : '';
            if($key === ''){
                // ライセンスキー削除
                delete_option(RCAL_LICENSE_OPTION_KEY);
                delete_transient(RCAL_LICENSE_CACHE_TRANSIENT);
                $license_message = 'ライセンスキーを削除しました。';
                $license_message_type = 'updated';
            } else {
                // ライセンスキー保存
                update_option(RCAL_LICENSE_OPTION_KEY, $key, false);
                // 即時検証
                $result = rcal_verify_license(true);
                if($result['valid']){
                    $license_message = 'ライセンス検証に成功しました。';
                    $license_message_type = 'updated';
                } else {
                    $reason = isset($result['reason']) ? $result['reason'] : 'unknown';
                    $error_msg = rcal_translate_license_error($reason);
                    $license_message = 'ライセンス検証に失敗しました。' . $error_msg;
                    
                    // デバッグ情報の追加（開発環境用）
                    if(defined('WP_DEBUG') && WP_DEBUG){
                        $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : parse_url(home_url(), PHP_URL_HOST);
                        $uuid = get_option('rcal_install_uuid');
                        $license_message .= '<br><small>デバッグ情報: ドメイン=' . esc_html($domain) . ', UUID=' . esc_html($uuid) . ', 理由=' . esc_html($reason) . '</small>';
                    }
                    
                    $license_message_type = 'error';
                }
            }
        }
    }
    
    // ライセンス再検証処理
    if(isset($_POST['rcal_license_recheck'])){
        if(!isset($_POST['_rcal_license_recheck_nonce']) || !wp_verify_nonce($_POST['_rcal_license_recheck_nonce'], 'rcal_license_recheck')){
            $license_message = '不正なリクエストです。';
            $license_message_type = 'error';
        } else {
            $result = rcal_verify_license(true);
            if($result['valid']){
                $license_message = '再検証に成功しました。';
                $license_message_type = 'updated';
            } else {
                $reason = isset($result['reason']) ? $result['reason'] : 'unknown';
                $error_msg = rcal_translate_license_error($reason);
                $license_message = '再検証に失敗しました。' . $error_msg;
                
                // デバッグ情報の追加（開発環境用）
                if(defined('WP_DEBUG') && WP_DEBUG){
                    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : parse_url(home_url(), PHP_URL_HOST);
                    $uuid = get_option('rcal_install_uuid');
                    $license_message .= '<br><small>デバッグ情報: ドメイン=' . esc_html($domain) . ', UUID=' . esc_html($uuid) . ', 理由=' . esc_html($reason) . '</small>';
                }
                
                $license_message_type = 'error';
            }
        }
    }
    
    if(isset($_POST['rcal_master_submit'])){
        check_admin_referer('rcal_master_settings');
        $color = isset($_POST['rcal_accent_color']) ? sanitize_hex_color($_POST['rcal_accent_color']) : '';
        if(empty($color)) { $color = '#90edc7'; }
        update_option('rcal_accent_color', $color);
        $on_color = isset($_POST['rcal_on_accent_color']) ? sanitize_hex_color($_POST['rcal_on_accent_color']) : '';
        if(empty($on_color)) { $on_color = '#111111'; }
        update_option('rcal_on_accent_color', $on_color);
        // プライバシーURL
        $privacy = isset($_POST['rcal_privacy_url']) ? esc_url_raw($_POST['rcal_privacy_url']) : '';
        update_option('rcal_privacy_url', $privacy);
        // メール挨拶文
        $greeting = isset($_POST['rcal_email_greeting']) ? sanitize_textarea_field($_POST['rcal_email_greeting']) : '';
        update_option('rcal_email_greeting', $greeting);
        // メール署名
        $signature = isset($_POST['rcal_email_signature']) ? sanitize_textarea_field($_POST['rcal_email_signature']) : '';
        update_option('rcal_email_signature', $signature);
        // 予約完了メッセージ
        $success_msg = isset($_POST['rcal_success_message']) ? sanitize_textarea_field($_POST['rcal_success_message']) : '';
        update_option('rcal_success_message', $success_msg);
        // フォーム注意事項
        $form_notice = isset($_POST['rcal_form_notice']) ? sanitize_textarea_field($_POST['rcal_form_notice']) : '';
        update_option('rcal_form_notice', $form_notice);
        // 予約ボタン文言・アイコン
        $open_btn_text = isset($_POST['rcal_open_button_text']) ? sanitize_text_field($_POST['rcal_open_button_text']) : '';
        update_option('rcal_open_button_text', $open_btn_text);
        $open_btn_icon = isset($_POST['rcal_open_button_icon']) ? esc_url_raw($_POST['rcal_open_button_icon']) : '';
        update_option('rcal_open_button_icon', $open_btn_icon);
        echo '<div class="updated"><p>設定を保存しました。</p></div>';
    }
    $current = get_option('rcal_accent_color');
    $current = sanitize_hex_color($current) ?: '#90edc7';
    $on_current = get_option('rcal_on_accent_color');
    $on_current = sanitize_hex_color($on_current) ?: '#111111';
    $privacy_current = get_option('rcal_privacy_url');
    $privacy_current = !empty($privacy_current) ? esc_url($privacy_current) : '';
    $greeting_current = get_option('rcal_email_greeting');
    $greeting_current = !empty($greeting_current) ? esc_textarea($greeting_current) : '';
    $signature_current = get_option('rcal_email_signature');
    $signature_current = !empty($signature_current) ? esc_textarea($signature_current) : '';
    $success_message_current = get_option('rcal_success_message');
    $success_message_current = !empty($success_message_current) ? esc_textarea($success_message_current) : '';
    $form_notice_current = get_option('rcal_form_notice');
    $form_notice_current = !empty($form_notice_current) ? esc_textarea($form_notice_current) : '';
    $open_btn_text_current = get_option('rcal_open_button_text');
    $open_btn_text_current = !empty($open_btn_text_current) ? esc_attr($open_btn_text_current) : '';
    $open_btn_icon_current = get_option('rcal_open_button_icon');
    $open_btn_icon_current = !empty($open_btn_icon_current) ? esc_url($open_btn_icon_current) : '';
    
    // ライセンス情報取得
    $license_key_raw = get_option(RCAL_LICENSE_OPTION_KEY, '');
    $license_key_masked = rcal_mask_key($license_key_raw);
    $license_info = rcal_get_license_info();
    $is_licensed = rcal_is_licensed();
    ?>
    <div class="wrap">
        <h1>予約カレンダー設定（マスター）</h1>
        
        <?php if($license_message): ?>
            <div class="notice notice-<?php echo esc_attr($license_message_type); ?> is-dismissible">
                <p><?php echo esc_html($license_message); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- ライセンスキー設定セクション -->
        <h2>ライセンスキー</h2>
        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px;">
            <form method="post" style="margin-bottom: 15px;">
                <?php wp_nonce_field('rcal_license_save', '_rcal_license_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="rcal_license_key">ライセンスキー</label></th>
                        <td>
                            <input type="text" id="rcal_license_key" name="rcal_license_key" value="" placeholder="キーを入力" style="width: 30rem;" class="regular-text" />
                            <?php if($license_key_masked): ?>
                                <p class="description">現在のキー: <code><?php echo esc_html($license_key_masked); ?></code></p>
                            <?php else: ?>
                                <p class="description">まだキーが設定されていません。</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">状態</th>
                        <td>
                            <?php if($is_licensed && $license_info): ?>
                                <span style="color: green; font-weight: bold;">✓ 有効</span>
                                <?php if(!empty($license_info['plan'])): ?>
                                    / プラン: <strong><?php echo esc_html($license_info['plan']); ?></strong>
                                <?php endif; ?>
                                <?php if(!empty($license_info['expires_at'])): ?>
                                    / 期限: <?php echo esc_html($license_info['expires_at']); ?>
                                <?php else: ?>
                                    / 期限: 無期限
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #d63638; font-weight: bold;">✗ 無効</span>
                                <?php if($license_info && !empty($license_info['reason'])): ?>
                                    — <?php echo esc_html(rcal_translate_license_error($license_info['reason'])); ?>
                                <?php else: ?>
                                    — ライセンスキーを入力してください。
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="rcal_license_submit" class="button button-primary">保存＆検証</button>
                </p>
            </form>
            
            <form method="post">
                <?php wp_nonce_field('rcal_license_recheck', '_rcal_license_recheck_nonce'); ?>
                <button type="submit" name="rcal_license_recheck" class="button">接続テスト（再検証）</button>
            </form>
        </div>
        
        <!-- 既存のマスター設定フォーム（ライセンス有効時のみ表示） -->
        <?php if($is_licensed): ?>
        <h2>サイト設定</h2>
        <form method="post">
            <?php wp_nonce_field('rcal_master_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="rcal_accent_color">テーマカラー</label></th>
                    <td>
                        <input type="text" id="rcal_accent_color" name="rcal_accent_color" class="rcal-color-field" value="<?php echo esc_attr($current); ?>" data-default-color="#90edc7" />
                        <p class="description">予約ボタン、アクティブ状態、hover状態などに適用されます。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rcal_on_accent_color">アクセント上の文字色</label></th>
                    <td>
                        <input type="text" id="rcal_on_accent_color" name="rcal_on_accent_color" class="rcal-color-field" value="<?php echo esc_attr($on_current); ?>" data-default-color="#111111" />
                        <p class="description">アクセント背景上のテキスト/アイコンの色（読みやすさを確保）。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rcal_open_button_text">予約ボタンのテキスト</label></th>
                    <td>
                        <input type="text" id="rcal_open_button_text" name="rcal_open_button_text" class="regular-text" value="<?php echo $open_btn_text_current; ?>" placeholder="ご予約はこちら" />
                        <p class="description">モーダルを開くボタンの表示テキスト（未入力時は「ご予約はこちら」）。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rcal_open_button_icon">予約ボタンのアイコンURL</label></th>
                    <td>
                        <input type="url" id="rcal_open_button_icon" name="rcal_open_button_icon" class="regular-text" value="<?php echo $open_btn_icon_current; ?>" placeholder="https://example.com/path/to/icon.png" />
                        <button type="button" class="button" id="rcal_open_button_icon_select">メディアから選択</button>
                        <button type="button" class="button" id="rcal_open_button_icon_clear">クリア</button>
                        <p class="description">未入力時はプラグイン同梱アイコンが使用されます。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rcal_form_notice">フォームに掲載する注意事項</label></th>
                    <td>
                        <textarea id="rcal_form_notice" name="rcal_form_notice" class="large-text" rows="4" placeholder="予約フォームの注意事項・伝達事項をご記入ください。">
<?php echo esc_textarea($form_notice_current); ?></textarea>
                        <p class="description">予約フォームの上部に表示される注意事項です。未入力時は表示されません。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rcal_privacy_url">プライバシーポリシーURL</label></th>
                    <td>
                        <input type="url" id="rcal_privacy_url" name="rcal_privacy_url" class="regular-text" value="<?php echo esc_attr($privacy_current); ?>" placeholder="https://example.com/privacy" />
                        <p class="description">予約フォーム内の同意リンクとして表示されます。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rcal_email_greeting">自動返信メール：挨拶文</label></th>
                    <td>
                        <textarea id="rcal_email_greeting" name="rcal_email_greeting" class="large-text" rows="4" placeholder="この度はご予約いただきありがとうございます。"><?php echo esc_textarea($greeting_current); ?></textarea>
                        <p class="description">お客様への自動返信メールの冒頭に表示されます。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rcal_email_signature">自動返信メール：署名</label></th>
                    <td>
                        <textarea id="rcal_email_signature" name="rcal_email_signature" class="large-text" rows="6" placeholder="ご不明点がございましたら、お気軽にお問い合わせください。&#10;&#10;株式会社〇〇&#10;TEL: 03-xxxx-xxxx"><?php echo esc_textarea($signature_current); ?></textarea>
                        <p class="description">お客様への自動返信メールの末尾に表示されます。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rcal_success_message">予約完了メッセージ</label></th>
                    <td>
                        <textarea id="rcal_success_message" name="rcal_success_message" class="large-text" rows="3" placeholder="予約を受け付けました。後ほど担当者よりご連絡いたします。">
<?php echo esc_textarea($success_message_current); ?></textarea>
                        <p class="description">完了画面に表示されるメッセージです。未入力時はデフォルト文言（予約を受け付けました。）を表示します。</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('変更を保存', 'primary', 'rcal_master_submit'); ?>
        </form>
        <script>
    (function($){
        $(function(){
            if ($.fn.wpColorPicker) {
                $('.rcal-color-field').wpColorPicker();
            }
            // メディア選択
            var frame;
            $('#rcal_open_button_icon_select').on('click', function(e){
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: 'アイコン画像を選択',
                    button: { text: 'この画像を使用' },
                    multiple: false
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#rcal_open_button_icon').val(attachment.url);
                });
                frame.open();
            });
            $('#rcal_open_button_icon_clear').on('click', function(){
                $('#rcal_open_button_icon').val('');
            });
        });
    })(jQuery);
    </script>
    <?php endif; // ライセンス有効時のみサイト設定を表示 ?>
    
    </div><!-- .wrap -->
    <?php
}

/* ------------------------------------------------------------------------------ */
/**
 * 【管理画面】データDB登録 / 更新
 */
function _rcal_set_database_calendar($data = NULL) {
	global $wpdb;
	global $rcal_tableName;

	// テーブルの接頭辞と名前を指定
	$table_name = $wpdb->prefix . $rcal_tableName;
	// POST値チェック用 ステータスの接頭辞
	$statusPrefix = 'status_';
	// 現在のログインユーザ
	$userId = get_current_user_id();

	if($data){
		// 古いデータを削除するため、1番目（1番最初の月）のキーを取得
		$firstDateKey = key(array_slice($data, 0, 1, true));
		if($firstDateKey){
			$firstDate = explode('_',$firstDateKey);
			$firstDate = $firstDate[1];
			_rcal_delete_database_past($firstDate);
		}

		foreach($data as $k => $v){
			// キーが「$statusPrefix」で始まるものだったら、ステータス用のPOST値
			if(preg_match("/^".$statusPrefix."/",$k)){
				$keyOrigin = $k;
				$keyExplode = explode('_',$keyOrigin);
				$query = "SELECT * FROM {$table_name} WHERE date = '{$keyExplode[1]}' AND time = {$keyExplode[2]} AND user_id = {$userId};";
				$checkResult = $wpdb->get_results($query);

				// レコードがあるか確認
				if(!$checkResult){
					// レコードが無い => INSERT
					$wpdb->insert(
						$table_name,
						array(
							'user_id' => $userId, // insert値
							'date' => $keyExplode[1], // insert値
							'time' => $keyExplode[2], // insert値
							'status' => $v // insert値
						),
						array(
							'%d', // user_id のフォーマット
							'%s', // date のフォーマット
							'%d', // time のフォーマット
							'%d'  // status のフォーマット
						)
					);
				}else{
					// レコード有り => UPDATE
					$wpdb->update(
						$table_name,
						array(
							'status' => $v // update値
						),
						array(
							'user_id' => $userId, // where句
							'date' => $keyExplode[1], // where句
							'time' => $keyExplode[2]  // where句
						),
						array(
							'%d' // status のフォーマット
						),
						array(
							'%d', // user_id のフォーマット
							'%s', // date のフォーマット
							'%d'  // time のフォーマット
						)
					);
				}
			}
		}
	}
}

/**
 * 【管理画面】古い日付のデータはDBから自動削除
 */
function _rcal_delete_database_past($date = NULL) {
	global $wpdb;
	global $rcal_tableName;
	// ログインユーザ
	$userId = get_current_user_id();
	// テーブルの接頭辞と名前を指定
	$table_name = $wpdb->prefix . $rcal_tableName;
	if($date){
		$datetime = $date." 00:00:00";
		$query = "DELETE FROM {$table_name} WHERE date < '{$datetime}' AND user_id = {$userId};";
		$wpdb->query($query);
	}
}

/**
 * 【管理画面】予約カレンダーページコンテンツ (HTML)
 */
function _rcal_admin_calendar_optionpage_contents(){
	// ライセンスゲート
	if(!rcal_is_licensed()){
		echo '<div class="wrap">';
		echo '<h1>予約カレンダー</h1>';
		echo '<div class="notice notice-error"><p>このプラグインを使用するにはライセンスキーが必要です。</p></div>';
		echo '<p><a href="' . esc_url(admin_url('options-general.php?page=rcal-master-settings')) . '" class="button button-primary">ライセンス設定へ</a></p>';
		echo '</div>';
		return;
	}
	
	global $rcal_status;
	$userId = get_current_user_id();

	if(isset($_POST['rcal_calendar_submit'])){
		_rcal_set_database_calendar($_POST);

		// カレンダーの表示可否をセット
		$disabled = isset($_POST['rcal_calendar_disabled']);
		update_user_meta($userId, 'rcal_calendar_disabled', $disabled);
	}

	$tpl = new Tmpl();
	$tpl->rcalStatud = $rcal_status;
  $tpl->calendarData = rcal_get_calendar(false, false, $userId, true) ?? array();
	$tpl->calendarDisabled = get_user_meta($userId, 'rcal_calendar_disabled', true);
  $tpl->render('admin/calendarSettings.tpl.php');
}

/* ------------------------------------------------------------------------------ */
/**
 * 【管理画面】ユーザープロフィールに店舗メタ入力欄を追加
 */
add_action('show_user_profile', 'rcal_add_user_store_fields');
add_action('edit_user_profile', 'rcal_add_user_store_fields');
function rcal_add_user_store_fields($user){
    if (!current_user_can('edit_user', $user->ID)) { return; }
    
    // ライセンスゲート
    if(!rcal_is_licensed()){
        echo '<h2>予約カレンダー設定</h2>';
        echo '<div class="notice notice-error inline"><p>このプラグインを使用するにはライセンスキーが必要です。<a href="' . esc_url(admin_url('options-general.php?page=rcal-master-settings')) . '">ライセンス設定へ</a></p></div>';
        return;
    }
    
    global $rcal_areaNameCategoryMap;
    global $rcal_areaGroupedPrefs;

    $shop_area = get_user_meta($user->ID, 'shop_area', true);
    $shop_tel = get_user_meta($user->ID, 'shop_tel', true);
    $shop_time_s = get_user_meta($user->ID, 'shop_time_s', true);
    $shop_holiday_s = get_user_meta($user->ID, 'shop_holiday_s', true);
    // 週次定休日（0:日〜6:土）の配列
    $rcal_weekly_holidays = get_user_meta($user->ID, 'rcal_weekly_holidays', true);
    if(!is_array($rcal_weekly_holidays)) { $rcal_weekly_holidays = array(); }
    $rcal_notify_email = get_user_meta($user->ID, 'rcal_notify_email', true);
	// 予約枠設定メタ（分単位）
	$rcal_open_min = intval(get_user_meta($user->ID, 'rcal_open_min', true));
	$rcal_close_min = intval(get_user_meta($user->ID, 'rcal_close_min', true));
	$rcal_slot_duration_min = intval(get_user_meta($user->ID, 'rcal_slot_duration_min', true));
	$rcal_slot_gap_min = intval(get_user_meta($user->ID, 'rcal_slot_gap_min', true));
	// デフォルト（後方互換: 10:00-17:00 / 60分 / 間隔0）
	if($rcal_open_min <= 0 && $rcal_open_min !== 0){ $rcal_open_min = 600; }
	if($rcal_close_min <= 0){ $rcal_close_min = 1020; }
	if(!in_array($rcal_slot_duration_min, array(15,30,60,90,120), true)){ $rcal_slot_duration_min = 60; }
	if(!in_array($rcal_slot_gap_min, array(0,15,30), true)){ $rcal_slot_gap_min = 0; }

    $area_key = '';
    if (!empty($shop_area) && isset($rcal_areaNameCategoryMap[$shop_area])) {
        $area_key = $rcal_areaNameCategoryMap[$shop_area];
    }
    $area_pref = !empty($area_key) ? get_user_meta($user->ID, $area_key, true) : '';
    $prefs_map = is_array($rcal_areaGroupedPrefs) ? $rcal_areaGroupedPrefs : array();
    ?>
    <hr class="rcal_hr">
    <h2>店舗情報（予約カレンダー）</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="shop_area">エリア</label></th>
            <td>
                <select name="shop_area" id="shop_area">
                    <option value="">（未選択）</option>
                    <?php foreach($rcal_areaNameCategoryMap as $name => $key): ?>
                        <option value="<?php echo esc_attr($name); ?>" <?php selected($shop_area, $name); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">例: 東京 / 関東 など</p>
            </td>
        </tr>
        <tr>
            <th><label for="area_pref">都道府県</label></th>
            <td>
                <?php
                    $pref_list = array();
                    if (!empty($area_key) && isset($prefs_map[$area_key]) && is_array($prefs_map[$area_key])) {
                        $pref_list = $prefs_map[$area_key];
                    }
                ?>
                <select name="area_pref" id="area_pref">
                    <option value="">（未選択）</option>
                    <?php foreach($pref_list as $p): ?>
                        <option value="<?php echo esc_attr($p); ?>" <?php selected($area_pref, $p); ?>><?php echo esc_html($p); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">※エリア選択に応じて選択肢が切り替わります</p>
                <script>
                (function(){
                    var areaSelect = document.getElementById('shop_area');
                    var prefSelect = document.getElementById('area_pref');
                    var areaNameToKey = <?php echo wp_json_encode($rcal_areaNameCategoryMap ?? array()); ?>;
                    var keyToPrefs = <?php echo wp_json_encode($prefs_map); ?>;
                    function setOptions(list, selected){
                        while (prefSelect.firstChild) prefSelect.removeChild(prefSelect.firstChild);
                        var opt0 = document.createElement('option');
                        opt0.value = '';
                        opt0.textContent = '（未選択）';
                        prefSelect.appendChild(opt0);
                        if (Array.isArray(list)){
                            list.forEach(function(v){
                                var opt = document.createElement('option');
                                opt.value = v; opt.textContent = v;
                                if (selected && selected === v) opt.selected = true;
                                prefSelect.appendChild(opt);
                            });
                        }
                    }
                    areaSelect && areaSelect.addEventListener('change', function(){
                        var name = this.value || '';
                        var key = areaNameToKey && areaNameToKey[name] ? areaNameToKey[name] : '';
                        var list = (key && keyToPrefs && keyToPrefs[key]) ? keyToPrefs[key] : [];
                        setOptions(list, '');
                    });
                })();
                </script>
            </td>
        </tr>
        <tr>
            <th><label for="shop_tel">電話番号</label></th>
            <td>
                <input type="text" name="shop_tel" id="shop_tel" class="regular-text" value="<?php echo esc_attr($shop_tel); ?>" />
            </td>
        </tr>
        <tr>
            <th><label for="shop_time_s">営業時間（表示用）</label></th>
            <td>
                <input type="text" name="shop_time_s" id="shop_time_s" class="regular-text" value="<?php echo esc_attr($shop_time_s); ?>" />
            </td>
        </tr>
        <tr>
            <th><label>予約枠設定</label></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">予約枠設定</legend>
                    <p class="rcal_time_slots_p">
                        開始時刻：
                        <select name="rcal_open_min" id="rcal_open_min">
                            <?php
                            for($m=0; $m<=1440; $m+=15){
                                $h = floor($m/60);
                                $mm = $m%60;
                                $label = ($m===1440) ? '24:00' : sprintf('%02d:%02d',$h,$mm);
                                echo '<option value="'.esc_attr($m).'"'.selected($rcal_open_min,$m,false).'>'.esc_html($label).'</option>';
                            }
                            ?>
                        </select>
                        〜
                        終了時刻：
                        <select name="rcal_close_min" id="rcal_close_min">
                            <?php
                            for($m=0; $m<=1440; $m+=15){
                                $h = floor($m/60);
                                $mm = $m%60;
                                $label = ($m===1440) ? '24:00' : sprintf('%02d:%02d',$h,$mm);
                                echo '<option value="'.esc_attr($m).'"'.selected($rcal_close_min,$m,false).'>'.esc_html($label).'</option>';
                            }
                            ?>
                        </select>
                    </p>
                    <p class="rcal_time_slots_p">
                        予約枠の長さ：
                        <select name="rcal_slot_duration_min" id="rcal_slot_duration_min">
                            <?php foreach(array(15,30,60,90,120) as $dur): ?>
                                <option value="<?php echo esc_attr($dur); ?>" <?php selected($rcal_slot_duration_min, $dur); ?>><?php echo esc_html($dur); ?>分</option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p class="rcal_time_slots_p">
                        インターバルの間隔（次の予約枠との間隔）：
                        <select name="rcal_slot_gap_min" id="rcal_slot_gap_min">
                            <?php foreach(array(0,15,30) as $gap): ?>
                                <option value="<?php echo esc_attr($gap); ?>" <?php selected($rcal_slot_gap_min, $gap); ?>><?php echo esc_html($gap); ?>分</option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <div class="rcal_time_slots_preview">
                        <strong>プレビュー</strong>
                        <div id="rcal_time_slots_items" style="margin-top:8px;"></div>
                        <div class="rcal_time_slots_preview_description">
                            <p>※開始/終了/枠長/間隔の設定に基づいて、予約枠が自動生成されます。</p>
                        </div>
                    </div>
                </fieldset>
                <script>
                (function(){
                    function minutesToHHmm(min){
                        if(min === 1440){ return '24:00'; }
                        var h = Math.floor(min/60);
                        var m = min%60;
                        return ('0'+h).slice(-2)+':'+('0'+m).slice(-2);
                    }
                    function renderPreview(){
                        var open = parseInt(document.getElementById('rcal_open_min').value,10);
                        var close = parseInt(document.getElementById('rcal_close_min').value,10);
                        var dur = parseInt(document.getElementById('rcal_slot_duration_min').value,10);
                        var gap = parseInt(document.getElementById('rcal_slot_gap_min').value,10);
                        var wrap = document.getElementById('rcal_time_slots_items');
                        if(!wrap){ return; }
                        wrap.innerHTML = '';
                        if(isNaN(open)||isNaN(close)||isNaN(dur)||isNaN(gap)|| close <= open || (close - open) < dur){
                            wrap.textContent = '有効な設定にしてください（終了>開始、範囲≥枠長）。';
                            return;
                        }
                        var slots = [];
                        var t = open;
                        while(t + dur <= close){
                            slots.push(minutesToHHmm(t));
                            t += dur + gap;
                        }
                        if(slots.length === 0){
                            wrap.textContent = '予約枠が生成されません。設定を見直してください。';
                            return;
                        }
                        var ul = document.createElement('ul');
                        ul.className = 'rcal_time_slots_items';
                        slots.forEach(function(s){
                            var li = document.createElement('li');
                            li.textContent = s;
                            ul.appendChild(li);
                        });
                        wrap.appendChild(ul);
                    }
                    ['rcal_open_min','rcal_close_min','rcal_slot_duration_min','rcal_slot_gap_min'].forEach(function(id){
                        var el = document.getElementById(id);
                        if(el){ el.addEventListener('change', renderPreview); }
                    });
                    renderPreview();
                })();
                </script>
            </td>
        </tr>
        <tr>
            <th><label for="shop_holiday_s">定休日（表示用）</label></th>
            <td>
                <input type="text" name="shop_holiday_s" id="shop_holiday_s" class="regular-text" value="<?php echo esc_attr($shop_holiday_s); ?>" />
            </td>
        </tr>
        <tr>
            <th><label>定休日（設定用）</label></th>
            <td>
                <?php $weeks = array('日','月','火','水','木','金','土'); ?>
                <fieldset class="rcal_weekly_holidays">
                    <?php foreach($weeks as $wi => $wlabel): ?>
                        <label style="display:inline-block;margin-right:12px;">
                            <input type="checkbox" name="rcal_weekly_holidays[]" value="<?php echo esc_attr($wi); ?>" <?php checked(in_array($wi, $rcal_weekly_holidays, true)); ?> />
                            <?php echo esc_html($wlabel); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <p class="description">※選択した曜日は予約カレンダーの全枠が「×」になります。<br>
                ※選択解除した場合、予約カレンダーで該当曜日が「×」のままになっているので、手動で修正してください。</p>
            </td>
        </tr>
        <tr>
            <th><label for="rcal_notify_email">予約通知先メールアドレス</label></th>
            <td>
                <input type="email" name="rcal_notify_email" id="rcal_notify_email" class="regular-text" value="<?php echo esc_attr($rcal_notify_email); ?>" />
                <p class="description">この店舗の予約通知を受け取るメールアドレス（未設定時は管理者メール）。</p>
            </td>
        </tr>
    </table>
    <?php wp_nonce_field('rcal_user_store_fields', 'rcal_user_store_fields_nonce'); ?>
    <?php
}

add_action('personal_options_update', 'rcal_save_user_store_fields');
add_action('edit_user_profile_update', 'rcal_save_user_store_fields');
function rcal_save_user_store_fields($user_id){
    if (!current_user_can('edit_user', $user_id)) { return; }
    if (empty($_POST['rcal_user_store_fields_nonce']) || !wp_verify_nonce($_POST['rcal_user_store_fields_nonce'], 'rcal_user_store_fields')) { return; }
    global $rcal_areaNameCategoryMap;

    $shop_area = isset($_POST['shop_area']) ? sanitize_text_field($_POST['shop_area']) : '';
    $area_pref = isset($_POST['area_pref']) ? sanitize_text_field($_POST['area_pref']) : '';
    $shop_tel = isset($_POST['shop_tel']) ? sanitize_text_field($_POST['shop_tel']) : '';
    $shop_time_s = isset($_POST['shop_time_s']) ? sanitize_text_field($_POST['shop_time_s']) : '';
    $shop_holiday_s = isset($_POST['shop_holiday_s']) ? sanitize_text_field($_POST['shop_holiday_s']) : '';
    $rcal_notify_email = isset($_POST['rcal_notify_email']) ? sanitize_email($_POST['rcal_notify_email']) : '';
    // 週次定休日
    $rcal_weekly_holidays = isset($_POST['rcal_weekly_holidays']) && is_array($_POST['rcal_weekly_holidays'])
        ? array_values(array_unique(array_map('intval', $_POST['rcal_weekly_holidays'])))
        : array();
    // 予約枠設定（分）
    $rcal_open_min = isset($_POST['rcal_open_min']) ? intval($_POST['rcal_open_min']) : 600;
    $rcal_close_min = isset($_POST['rcal_close_min']) ? intval($_POST['rcal_close_min']) : 1020;
    $rcal_slot_duration_min = isset($_POST['rcal_slot_duration_min']) ? intval($_POST['rcal_slot_duration_min']) : 60;
    $rcal_slot_gap_min = isset($_POST['rcal_slot_gap_min']) ? intval($_POST['rcal_slot_gap_min']) : 0;
    // サニタイズ・バリデーション（00:00〜24:00=0..1440, 15分刻み）
    $rcal_open_min = max(0, min(1440, $rcal_open_min - ($rcal_open_min % 15)));
    $rcal_close_min = max(0, min(1440, $rcal_close_min - ($rcal_close_min % 15)));
    if(!in_array($rcal_slot_duration_min, array(15,30,60,90,120), true)) { $rcal_slot_duration_min = 60; }
    if(!in_array($rcal_slot_gap_min, array(0,15,30), true)) { $rcal_slot_gap_min = 0; }
    // 終了は開始より後、かつ範囲が枠長以上
    if($rcal_close_min <= $rcal_open_min || ($rcal_close_min - $rcal_open_min) < $rcal_slot_duration_min){
        // 後方互換の既定に戻す
        $rcal_open_min = 600; // 10:00
        $rcal_close_min = 1020; // 17:00
        $rcal_slot_duration_min = 60;
        $rcal_slot_gap_min = 0;
    }

    update_user_meta($user_id, 'shop_area', $shop_area);
    if (!empty($shop_area) && isset($rcal_areaNameCategoryMap[$shop_area])) {
        $area_key = $rcal_areaNameCategoryMap[$shop_area];
        update_user_meta($user_id, $area_key, $area_pref);
    }
    // 一覧表示を最新化するため、slug/display_name等は get_userdata が参照するため保存不要
    // ここでトリガー的にメタの更新時刻を残す（任意）
    update_user_meta($user_id, 'rcal_last_profile_update', current_time('mysql'));
    update_user_meta($user_id, 'shop_tel', $shop_tel);
    update_user_meta($user_id, 'shop_time_s', $shop_time_s);
    update_user_meta($user_id, 'shop_holiday_s', $shop_holiday_s);
    if(!empty($rcal_notify_email)){
        update_user_meta($user_id, 'rcal_notify_email', $rcal_notify_email);
    } else {
        delete_user_meta($user_id, 'rcal_notify_email');
    }
    update_user_meta($user_id, 'rcal_weekly_holidays', $rcal_weekly_holidays);
    // 予約枠設定メタ保存
    update_user_meta($user_id, 'rcal_open_min', $rcal_open_min);
    update_user_meta($user_id, 'rcal_close_min', $rcal_close_min);
    update_user_meta($user_id, 'rcal_slot_duration_min', $rcal_slot_duration_min);
    update_user_meta($user_id, 'rcal_slot_gap_min', $rcal_slot_gap_min);
}
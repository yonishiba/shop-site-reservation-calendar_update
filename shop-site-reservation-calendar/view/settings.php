<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* ------------------------------------------------------------------------------ */
global $wp_version;
global $rcal_tableName;
global $rcal_db_version_optionKey;
global $rcal_db_version;
global $rcal_status;
global $rcal_selectShopContactBtn;

/* ------------------------------------------------------------------------------ */
/**
 * 【表示側】CSS/JS読み込み発動用
 */
add_action('wp_enqueue_scripts', 'rcal_style_script_enqueue_view');
if (!function_exists('rcal_style_script_enqueue_view')) {
function rcal_style_script_enqueue_view() {
    global $rcal_selectShopContactBtn;
    global $rcal_shortcode_used;

    // 必要なページでのみ読み込み（ショートコード使用 or 共通ボタン有効）
    if (empty($rcal_selectShopContactBtn) && empty($rcal_shortcode_used)) {
        return;
    }

    // バージョン（キャッシュバスティング）
    $js_file = RCAL_DIR . 'js/view/reservationCalendar.js';
    $css_file = RCAL_DIR . 'css/view/reservationCalendar.css';
    $js_ver = file_exists($js_file) ? filemtime($js_file) : NULL;
    $css_ver = file_exists($css_file) ? filemtime($css_file) : NULL;

    wp_register_script('rcal_view_js', RCAL_URL . '/js/view/reservationCalendar.js', array('jquery'), $js_ver);
    wp_register_style('rcal_view_css', RCAL_URL . '/css/view/reservationCalendar.css', array(), $css_ver);
	// JSファイルでAjaxURLを使用できる用設定
	/*
	引数1：スクリプトの登録ハンドル名（wp_register_scriptで登録したハンドル名）
	引数2：JavaScriptオブジェクトとして機能する、文字列（オブジェクト名）
	引数3：JavaScriptへ渡したい値を含んだ配列（引数2で設定した値でアクセス可 => rcal_ajax.ajax_url）
	*/
	wp_localize_script( 'rcal_view_js', 'rcal_ajax', array(
		'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rcal_ajax')
	));
	// アクセントカラーをCSS変数で注入（デフォルト: #90edc7）
    $accent = get_option('rcal_accent_color');
    $accent = sanitize_hex_color($accent) ?: '#90edc7';
    $on_accent = get_option('rcal_on_accent_color');
    $on_accent = sanitize_hex_color($on_accent) ?: '#ffffff';
    if (!empty($accent)) {
        $inline = ":root{--rcal-accent: {$accent}; --rcal-on-accent: {$on_accent};}";
        wp_add_inline_style('rcal_view_css', $inline);
    }
	wp_enqueue_script('rcal_view_js');
	wp_enqueue_style('rcal_view_css');
}
}

/* ------------------------------------------------------------------------------ */

/**
 * 【Ajax専用】カレンダー情報取得
 */
add_action('wp_ajax_rcal_calendar_ajax_get_calendar', 'rcal_calendar_ajax_get_calendar');// ログインユーザーのみ実行
add_action('wp_ajax_nopriv_rcal_calendar_ajax_get_calendar', 'rcal_calendar_ajax_get_calendar');// 未ログインユーザーのみ実行
if (!function_exists('rcal_calendar_ajax_get_calendar')) {
function rcal_calendar_ajax_get_calendar(){
    check_ajax_referer('rcal_ajax', '_nonce');
    
    // ライセンスゲート
    if(!rcal_is_licensed()){
        wp_send_json_error(array('message' => 'ライセンスキーが無効です。'));
    }
    
    $post = wp_unslash($_POST);
    $post_userId = false;
    if(!empty($post['slug'])) {
        $user = get_user_by('slug', sanitize_text_field($post['slug']));
        if ($user && isset($user->ID)) { $post_userId = (int)$user->ID; }
    }
    if(!$post_userId && !empty($post['userid'])) {
        $post_userId = absint($post['userid']);
    }
    if (empty($post_userId)) { wp_die(); }

    $post_y = (!empty($post['y']))? intval($post['y']) : false;
    $post_m = (!empty($post['m']))? intval($post['m']) : false;
    $otheritem_caution_hidden = (!empty($post['otheritem_caution_hidden']))? true : false;
    rcal_ajax_calendar(
        $post_y,
        $post_m,
        $post_userId,
        $otheritem_caution_hidden
    );
    wp_die();
}
}

/**
 * 【Ajax専用】日付指定でタイムテーブル情報取得
 */
add_action('wp_ajax_rcal_calendar_ajax_date_timetable', 'rcal_calendar_ajax_date_timetable');// ログインユーザーのみ実行
add_action('wp_ajax_nopriv_rcal_calendar_ajax_date_timetable', 'rcal_calendar_ajax_date_timetable');// 未ログインユーザーのみ実行
if (!function_exists('rcal_calendar_ajax_date_timetable')) {
function rcal_calendar_ajax_date_timetable(){
    check_ajax_referer('rcal_ajax', '_nonce');
    
    // ライセンスゲート
    if(!rcal_is_licensed()){
        wp_send_json_error(array('message' => 'ライセンスキーが無効です。'));
    }
    
    $post = wp_unslash($_POST);
    $post_userId = false;
    if(!empty($post['slug'])) {
        $user = get_user_by('slug', sanitize_text_field($post['slug']));
        if ($user && isset($user->ID)) { $post_userId = (int)$user->ID; }
    } elseif(!empty($post['userid'])) {
        $post_userId = absint($post['userid']);
    }
    if (empty($post_userId)) { wp_die(); }
    $post_y = (empty($post['y']))? false : intval($post['y']);
    $post_m = (empty($post['m']))? false : intval($post['m']);
    $post_d = (empty($post['d']))? false : intval($post['d']);
    $data = rcal_timeTable(
        $post_y,
        $post_m,
        $post_d,
        $post_userId
    );
    echo $data;
    wp_die();
}
}

/* ------------------------------------------------------------------------------ */

// マンスリーLP用の外販告知機能は削除しました

/* ------------------------------------------------------------------------------ */
/**
 * 【表示側】店舗選択来店予約ボタンの表示
 */
if($rcal_selectShopContactBtn){
	add_action('wp_footer', 'rcal_calendar_selectShopContact_template', 10);
	function rcal_calendar_selectShopContact_template(){
		global $rcal_shortcode_used;
		
		// ショートコード使用時は、ショートコード内でモーダルを出力するため、ここではスキップ
		if($rcal_shortcode_used){
			return;
		}
		
		// ライセンスゲート
		if(!rcal_is_licensed()){
			return;
		}
		
		$tpl = new Tmpl();
		$groupedShopList = rcal_get_all_shop_users();
		
		// 有効なユーザー数をカウント
		$totalCount = 0;
		$singleUserId = null;
		foreach($groupedShopList as $shopList){
			if(is_array($shopList)) {
				$totalCount += count($shopList);
				// 1名の場合のユーザーIDを保持
				if($totalCount === 1 && count($shopList) === 1) {
					$singleUserId = $shopList[0]['id'];
				}
			}
		}
		
		$tpl->groupedShopList = $groupedShopList;
		$tpl->isSelectShopContactBtn = true;
		$tpl->classname = '';
		$tpl->title = 'ご希望の店舗を選択してください';
		$tpl->totalShopCount = $totalCount;
		$tpl->singleUserId = $singleUserId;
		$tpl->render('view/selectShopContact.tpl.php');
	}
}

?>
<?php
/*
 * Shop Site Reservation Calendar
 *
 * @package SSRC
 * @author  VOTRA Co., Ltd.
 * 
 * @wordpress-plugin
 * Plugin Name:       Shop Site Reservation Calendar
 * Plugin URI:        https://votra.jp/lp/wpcal/
 * Description:       店舗サイト予約カレンダー
 * Version:           2.0.6
 * Author:            VOTRA Co., Ltd.
 * Author URI:        https://votra.jp
 * Update URI:        https://license-server.yoshinori-nishibayashi.workers.dev/update.json
 * Requires PHP:      8.0
 * Requires at least: 6.0
 * Copyright 2008-2025 VOTRA (email : info@votra.jp)
 */

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'RCAL_URL', plugins_url( '', __FILE__ ) );
define( 'RCAL_DIR', plugin_dir_path( __FILE__ ) );

/* ------------------------------------------------------------------------------ */
global $wp_version;
global $rcal_tableName;
global $rcal_db_version_optionKey;
global $rcal_db_version;
global $rcal_calendarLimit;
global $rcal_status;
global $rcal_holidayWeek;
global $rcal_reserveRestriction;
global $rcal_selectShopContactBtn;
global $rcal_areaNameCategoryMap;
global $rcal_areaCategories;
global $rcal_areaSortNames;
global $rcal_areaGroupedPrefs;
global $rcal_holidayCSV;
global $rcal_shortcode_used;

/**
 * テーブル名
 *
 * 先頭に $wpdb->prefix を追加して使用
 */
$rcal_tableName = "reserve_calendar";

/**
 * 祝日情報
 */
$rcal_holidayCSV = 'https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv';
$rcal_shortcode_used = false;

/**
 * テーブル構造バージョン
 *
 * 構造変更時、バージョン数値を変更するとアップデート関数発動
 * @var $rcal_db_version_optionKey オプションのキー
 * @var $rcal_db_version バージョン数値
 */
$rcal_db_version_optionKey = 'rcal_db_version';
$rcal_db_version = '2.0';

/**
 * カレンダー 月表示数
 *
 * (int) 今月を含めて何か月分表示するか
 */
$rcal_calendarLimit = 3;

// 固定の時間テーブルは廃止（ユーザープロフィール設定から動的生成に移行）

/**
 * ステータス設定（セレクトボックスの中身や表示に使う）
 *
 * (array) key => (int) value => (char)
 */
$rcal_status = array(
	1 => '○',
	0 => '×',
	3 => 'TEL'
);

/**
 * 【表示側用】日ごとステータス設定
 *
 * (int)
 * 〇の数がn個以上 => 〇
 * 全部TEL => TEL
 * 全部× => ×
 */
$rcal_date_status = 1;

/**
 * 定休日（曜日）
 *
 * (array) value => (int)
 * 日 => 0, 月 => 1, 火 => 2, 水 => 3, 木 => 4, 金 => 5, 土 => 6
 */
$rcal_holidayWeek = array();

/**
 * 予約制限
 *
 * (int)
 * 今日を含めずn日後までは、予約できないようにする
 */
$rcal_reserveRestriction = 0;

/**
 * 店舗選択予約ボタンの表示制限
 *
 * (int)
 * 0 => false（非表示）
 * 1 => true （表示）
 */
$rcal_selectShopContactBtn = 1;

/**
 * エリア名と都道府県のつなぎ込み設定
 */
$rcal_areaNameCategoryMap = array(
	'北海道・東北' => 'area_1',
	'東京' => 'area_tokyo',
	'関東' => 'area_2',
	'甲信越' => 'area_3',
	'東海・北陸' => 'area_4',
	'関西' => 'area_5',
	'中国・四国' => 'area_6',
	'九州' => 'area_7',
);

/**
 * 地域情報の選択肢リスト取得
 */
// エリア情報の取得（安全化）
try {
    if (isset($wpdb) && $wpdb instanceof wpdb) {
        $rcal_areaCategories = $wpdb->get_var("SELECT post_content FROM {$wpdb->posts} WHERE post_excerpt = 'shop_area'");
    } else {
        $rcal_areaCategories = '';
    }
} catch (Throwable $e) {
    $rcal_areaCategories = '';
}

if (!empty($rcal_areaCategories)) {
    // WP の maybe_unserialize で安全に復元
    $maybe = maybe_unserialize($rcal_areaCategories);
    if (is_array($maybe) && isset($maybe['choices']) && is_array($maybe['choices'])) {
        $rcal_areaCategories = array_values($maybe['choices']);
    } else {
        $rcal_areaCategories = array();
    }
} else {
    $rcal_areaCategories = array();
}

/**
 * 地域のソート順（取得失敗時は $rcal_areaNameCategoryMap をセット）
 */
$rcal_areaSortNames = !empty($rcal_areaCategories)
    ? array_values(array_filter(array_map(function($area) use ($rcal_areaNameCategoryMap){
        return $rcal_areaNameCategoryMap[$area] ?? null;
    }, $rcal_areaCategories)))
    : array_values($rcal_areaNameCategoryMap);
if (!in_array('area_others', $rcal_areaSortNames, true)) {
    $rcal_areaSortNames[] = 'area_others';
}

/**
 * 各エリアごとの都道府県一覧
 */
try {
    if (isset($wpdb) && $wpdb instanceof wpdb) {
        $rcal_areaGroupedPrefs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_excerpt, post_content FROM {$wpdb->posts} WHERE post_excerpt LIKE %s",
                $wpdb->esc_like('area_') . '%'
            ),
            ARRAY_A
        );
    } else {
        $rcal_areaGroupedPrefs = array();
    }
} catch (Throwable $e) {
    $rcal_areaGroupedPrefs = array();
}

if (!empty($rcal_areaGroupedPrefs)) {
    // エリア別にソート
    usort($rcal_areaGroupedPrefs, function($a, $b) use ($rcal_areaSortNames) {
        $pos_a = array_search($a['post_excerpt'], $rcal_areaSortNames, true);
        $pos_b = array_search($b['post_excerpt'], $rcal_areaSortNames, true);
        if ($pos_a === false || $pos_b === false) {
            return 0;
        }
        return $pos_a <=> $pos_b;
    });

    // 都道府県リスト抽出（安全化）
    $rcal_areaGroupedPrefs = array_reduce($rcal_areaGroupedPrefs, function($prefs, $area) {
        $content = maybe_unserialize($area['post_content']);
        if (is_array($content) && isset($content['choices']) && is_array($content['choices'])) {
            $prefs[$area['post_excerpt']] = array_values($content['choices']);
        } else {
            $prefs[$area['post_excerpt']] = array();
        }
        return $prefs;
    }, array());
}

// DBから取得できない場合のデフォルト都道府県リスト
if (empty($rcal_areaGroupedPrefs) || !is_array($rcal_areaGroupedPrefs)) {
    $rcal_areaGroupedPrefs = array(
        'area_tokyo' => array('東京都'),
        'area_1' => array('北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県'),
        'area_2' => array('茨城県','栃木県','群馬県','埼玉県','千葉県','神奈川県'),
        'area_3' => array('新潟県','山梨県','長野県'),
        'area_4' => array('静岡県','愛知県','岐阜県','三重県','富山県','石川県','福井県'),
        'area_5' => array('滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県'),
        'area_6' => array('鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県'),
        'area_7' => array('福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'),
    );
}

/* ------------------------------------------------------------------------------ */

/* ------------------------------------------------------------------------------ */
/**
 * プラグイン有効化時発動用
 */
register_activation_hook( __FILE__, 'rcal_activate' );
function rcal_activate(){
	_rcal_install();
	
	// インストールUUID生成（ライセンス認証用）
	if (!get_option('rcal_install_uuid')) {
		update_option('rcal_install_uuid', wp_generate_uuid4(), false);
	}
	
	// ライセンス定期再検証のWP-Cronイベント登録
	if (!wp_next_scheduled('rcal_license_reverify_event')) {
		wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', 'rcal_license_reverify_event');
	}
}

/**
 * プラグイン無効化時発動用
 * ※今現在は不要のためコメントアウト
 */
register_deactivation_hook( __FILE__, 'rcal_deactivate' );
function rcal_deactivate(){
	_rcal_deactivate();
}

/**
 * テーブル構造アップデートチェック
 *
 * プラグインロード時、テーブル構造のアップデートをチェックし、バージョンが違ったら構造更新
 */
add_action( 'plugins_loaded', 'rcal_update_db_check' );
function rcal_update_db_check() {
	global $rcal_db_version_optionKey, $rcal_db_version;
	if(get_option($rcal_db_version_optionKey) != $rcal_db_version){
		_rcal_install();
	}
}

/**
 * プラグイン削除時発動用
 */
register_uninstall_hook( __FILE__, 'rcal_uninstall' );
function rcal_uninstall(){
	_rcal_uninstall();
}

/* ------------------------------------------------------------------------------ */
/**
 * 初期設定（DB作成）
 */
function _rcal_install(){
	global $wpdb, $rcal_tableName, $rcal_db_version_optionKey, $rcal_db_version;

	$installed_ver = get_option( "rcal_db_version" );

	if($installed_ver != $rcal_db_version){
		$table_name = "".$wpdb->prefix . $rcal_tableName."";
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id mediumint(9) NOT NULL,
		date date DEFAULT '0000-00-00' NOT NULL,
		time tinyint DEFAULT '0' NOT NULL,
		status tinyint DEFAULT '0' NOT NULL,
		UNIQUE KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( $sql );
		update_option($rcal_db_version_optionKey, $rcal_db_version);
	}
}

/**
 * プラグイン無効化時のクリーンアップ
 */
function _rcal_deactivate() {
	// ライセンス定期再検証のWP-Cronイベント削除
	$timestamp = wp_next_scheduled('rcal_license_reverify_event');
	if ($timestamp) {
		wp_unschedule_event($timestamp, 'rcal_license_reverify_event');
	}
}

/**
 * プラグイン削除時のクリーンアップ
 * テーブル削除
 * オプション削除
 * 
 * 注意: この関数はプラグイン削除時のみ実行されます。
 * プラグイン更新時には実行されません。
 */
function _rcal_uninstall() {
	global $wpdb, $rcal_tableName, $rcal_db_version_optionKey;

	delete_option($rcal_db_version_optionKey);
	
	// ライセンス関連のデータ削除
	// 注意: プラグイン削除時のみ削除されます（更新時は保持）
	delete_option('rcal_license_key');
	delete_option('rcal_install_uuid');
	delete_transient('rcal_license_cache');
	
	// その他のオプション削除
	delete_option('rcal_accent_color');
	delete_option('rcal_on_accent_color');
	delete_option('rcal_privacypolicy_url');
	delete_option('rcal_reply_email_greeting');
	delete_option('rcal_reply_email_signature');
	delete_option('rcal_complete_message');
	delete_option('rcal_form_notes');
	delete_option('rcal_selectShopContact_btn_text');
	delete_option('rcal_selectShopContact_btn_icon');

	$table_name = "".$wpdb->prefix . $rcal_tableName."";
	$sql = "DROP TABLE IF EXISTS ".$table_name."";
	$wpdb->query($sql);
}

/* ------------------------------------------------------------------------------ */
require RCAL_DIR.'includes/utils.php';
require RCAL_DIR.'includes/license.php';
require RCAL_DIR.'includes/updater.php';
require RCAL_DIR.'includes/function.php';
require RCAL_DIR.'admin/settings.php';
require RCAL_DIR.'view/settings.php';

/* ------------------------------------------------------------------------------ */
/**
 * ショートコード: [reservation_calendar user="slug" userid="123" months="3" show_tel="1"]
 * - user / userid はどちらか指定可（user優先）。未指定時は一覧ボタン+モーダルのみを出力
 */
add_shortcode('reservation_calendar', function($atts){
    global $rcal_shortcode_used;
    $rcal_shortcode_used = true;

    // ライセンスゲート
    if(!rcal_is_licensed()){
        return '<div style="border: 1px solid #d63638; padding: 16px; color: #d63638; background: #fff; margin: 20px 0;">このプラグインを使用するにはライセンスキーが必要です。</div>';
    }

    $atts = shortcode_atts(array(
        'user' => '',
        'userid' => '',
        'months' => '',
        'show_tel' => '',
    ), $atts, 'reservation_calendar');

    ob_start();

    // 一覧（予約ボタン+モーダル）を常に出力
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

    // 特定ユーザーが指定されている場合は当月のカレンダーを即時描画
    $userId = false;
    if (!empty($atts['user'])) {
        $user = get_user_by('slug', sanitize_text_field($atts['user']));
        if ($user) { $userId = $user->ID; }
    } elseif (!empty($atts['userid'])) {
        $userId = absint($atts['userid']);
    }

    if (!empty($userId)) {
        // ショートコード埋め込み用のラッパーdivを追加（モーダルと完全に同じDOM構造を生成）
        echo '<div class="rcal-shortcode-embed-wrapper" data-rcal-mode="shortcode">';
        echo '<div id="selectShopContactArea" class="rcal-shortcode-area">'; // モーダルと同じID
        echo '<div id="selectShopContact" class="rcal-shortcode-select-shop-contact">';
        echo '<div class="selectShopContactWrap rcal-shortcode-wrap">';
        echo '<div class="selectShopContactInner rcal-shortcode-inner">';
        echo '<div id="selectShopContact_calendar" class="selectShopContactContents rcal-shortcode-calendar active">';
        echo '<div id="selectShopContact_calendarPrevBtn">'; // 戻るボタン（非表示）
        echo '<a href="javascript:void(0);">戻る</a>';
        echo '</div>';
        echo '<div id="selectShopContact_calendarArea" class="rcal-shortcode-calendar-area">';
        rcal_ajax_calendar(false, false, $userId, false, false);
        echo '</div>'; // selectShopContact_calendarArea
        echo '</div>'; // selectShopContact_calendar
        echo '</div>'; // selectShopContactInner
        echo '</div>'; // selectShopContactWrap
        echo '</div>'; // selectShopContact
        echo '</div>'; // selectShopContactArea
        echo '</div>'; // rcal-shortcode-embed-wrapper
        
        // ショートコード埋め込み用のスライダー初期化スクリプト
        echo '<script type="text/javascript">';
        echo 'jQuery(document).ready(function($) {';
        echo '  if (typeof rcalSliderEvSetting === "function") {';
        echo '    rcalSliderEvSetting();';
        echo '  }';
        echo '});';
        echo '</script>';
    }

    return ob_get_clean();
});

?>
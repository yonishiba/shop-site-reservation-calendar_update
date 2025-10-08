<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* ------------------------------------------------------------------------------ */
global $wp_version;
global $rcal_tableName;
global $rcal_db_version_optionKey;
global $rcal_db_version;
global $rcal_status;
global $rcal_areaNameCategoryMap;
global $rcal_areaCategories;
global $rcal_areaSortNames;
global $rcal_areaGroupedPrefs;
global $rcal_holidayCSV;
/* ------------------------------------------------------------------------------ */

/**
 * 祝日CSVを取得して配列に変換（キー: 'Y-m-d' => 値: 祝日名）
 * - 内閣府CSVは Shift_JIS 系のため UTF-8 に変換
 * - 日付表記は 'YYYY/M/D' を 'Y-m-d' に正規化
 * - 取得失敗時は空配列を返す（フェイルセーフ）
 * - 負荷軽減のため一時キャッシュ
 *
 * @return array<string,string>
 */
function rcal_fetch_holidays(){
    $cache = get_transient('rcal_holidays_cache');
    if (is_array($cache)) {
        return $cache;
    }
    global $rcal_holidayCSV;
    $holidays = array();
    // WordPress の HTTP API を利用
    $response = function_exists('wp_remote_get') ? wp_remote_get($rcal_holidayCSV, array('timeout' => 8)) : false;
    if (is_wp_error($response)) {
        set_transient('rcal_holidays_cache', $holidays, HOUR_IN_SECONDS);
        return $holidays;
    }
    $body = $response ? wp_remote_retrieve_body($response) : '';
    if (empty($body)) {
        set_transient('rcal_holidays_cache', $holidays, HOUR_IN_SECONDS);
        return $holidays;
    }
    // 文字コード判定とUTF-8化（CSVは SJIS-win が多い）
    $enc = function_exists('mb_detect_encoding') ? mb_detect_encoding($body, 'SJIS-win,SJIS,UTF-8,CP932,EUC-JP,ISO-2022-JP', true) : 'SJIS-win';
    if (!$enc) { $enc = 'SJIS-win'; }
    $text = ($enc === 'UTF-8') ? $body : (function_exists('mb_convert_encoding') ? mb_convert_encoding($body, 'UTF-8', $enc) : $body);
    // 行分割（改行コード混在に対応）
    $lines = preg_split("/\r\n|\n|\r/", trim($text));
    if (!is_array($lines) || count($lines) === 0) {
        set_transient('rcal_holidays_cache', $holidays, HOUR_IN_SECONDS);
        return $holidays;
    }
    // 1行目はヘッダ
    foreach ($lines as $idx => $line) {
        if ($idx === 0) { continue; }
        if ($line === '') { continue; }
        // CSVとして安全にパース
        $cols = function_exists('str_getcsv') ? str_getcsv($line) : explode(',', $line);
        if (!is_array($cols) || count($cols) < 2) { continue; }
        $dateRaw = trim($cols[0]);
        $name = trim($cols[1]);
        // 'YYYY/M/D' or 'YYYY/MM/DD' を DateTime で解釈
        $dt = DateTime::createFromFormat('Y/n/j', $dateRaw);
        if (!$dt) { $dt = DateTime::createFromFormat('Y/m/d', $dateRaw); }
        if ($dt) {
            $key = $dt->format('Y-m-d');
            $holidays[$key] = $name;
        }
    }
    // 12時間キャッシュ
    set_transient('rcal_holidays_cache', $holidays, 12 * HOUR_IN_SECONDS);
    return $holidays;
}

// メール送信の安定化用（グローバルに一度だけ定義）
if(!function_exists('rcal_configure_phpmailer_plain')){
    function rcal_configure_phpmailer_plain($phpmailer){
        $phpmailer->CharSet = 'UTF-8';
        $phpmailer->Encoding = 'quoted-printable';
        $phpmailer->isHTML(false);
        $phpmailer->ContentType = 'text/plain';
        // WP_PHPMailer では $LE は protected のため設定不可
        // 改行は PHPMailer 既定に委ねる（CRLF）
    }
}
if(!function_exists('rcal_mail_content_type_plain')){
    function rcal_mail_content_type_plain(){ return 'text/plain; charset=UTF-8'; }
}
if(!function_exists('rcal_mail_charset_utf8')){
    function rcal_mail_charset_utf8(){ return 'UTF-8'; }
}

/**
 * ユーザーの予約枠（開始分の配列と表示ラベル）を生成
 * @return array [ [start_minute => int, label => 'HH:MM'] , ... ]
 */
function rcal_get_user_time_slots($userId, $dateYmd = null){
    $open = intval(get_user_meta($userId, 'rcal_open_min', true));
    $close = intval(get_user_meta($userId, 'rcal_close_min', true));
    $dur = intval(get_user_meta($userId, 'rcal_slot_duration_min', true));
    $gap = intval(get_user_meta($userId, 'rcal_slot_gap_min', true));
    if($open <= 0 && $open !== 0){ $open = 600; }
    if($close <= 0){ $close = 1020; }
    if(!in_array($dur, array(15,30,60,90,120), true)){ $dur = 60; }
    if(!in_array($gap, array(0,15,30), true)){ $gap = 0; }
    $slots = array();
    if($close <= $open || ($close - $open) < $dur){
        return $slots; // 不正設定時は空
    }
    $t = $open;
    while(($t + $dur) <= $close){
        $h = floor($t/60);
        $m = $t % 60;
        $label = ($t===1440) ? '24:00' : sprintf('%02d:%02d', $h, $m);
        $slots[] = array('start_minute' => $t, 'label' => $label);
        $t += $dur + $gap;
    }
    return $slots;
}

/**
 * ユーザIDから店舗情報を取得
 * 予約ボタン押下時の店舗一覧表示に利用しています
 *
 * @param int/boolean userId => ユーザID
 */
function rcal_get_shop_user($userId = false) {
	global $rcal_areaNameCategoryMap;

	if(!$userId){
		return null;
	}

	$userData = get_userdata($userId)->data;
	$userMeta = get_user_meta($userId, '', true);

	// 店舗名称
	$displayName = $userData->display_name ?? '';
	// ユーザスラッグ
	$slug = $userData->user_nicename ?? '';
	// 地域カテゴリ（名称、フィールド名）
    $areaCategoryName = '';
    $areaCategoryKey = '';
    if(isset($userMeta['shop_area'])) {
        $areaCategoryName = array_pop($userMeta['shop_area']) ?? '';
        $areaCategoryKey = $rcal_areaNameCategoryMap[$areaCategoryName] ?? '';
    }
	// 都道府県名
	$pref = '';
	if(!empty($areaCategoryKey)) {
		$pref = array_shift($userMeta[$areaCategoryKey]) ?? '';
	}
	// 店舗名称（短縮版）
    $shortName = '';
    if(isset($userMeta['first_name'])) {
        $shortName = array_pop($userMeta['first_name']) ?? '';
    }
    // フォールバック: 表示名を短縮名に
    if(empty($shortName)) {
        $shortName = $displayName;
    }
	// 店舗営業時間
	$time = '';
	if(isset($userMeta['shop_time_s'])) {
		$time = array_pop($userMeta['shop_time_s']) ?? '';
	}
	// 店舗定休日
	$holiday = '';
	if(isset($userMeta['shop_holiday_s'])) {
		$holiday = array_pop($userMeta['shop_holiday_s']) ?? '';
	}
	// 店舗電話番号
	$tel = '';
	if(isset($userMeta['shop_tel'])) {
		$tel = array_pop($userMeta['shop_tel']) ?? '';
	}
	// 表示順序
	$order = 0;
	if(isset($userMeta['display_order'])) {
		$order = array_pop($userMeta['display_order']) ?? 0;
	}
	// 主要メタ情報が存在しない author アカウントは店舗ユーザとみなさない
    // フォールバック: エリア未設定時は「その他」扱い
    if (empty($areaCategoryKey)) {
        $areaCategoryKey = 'area_others';
        $areaCategoryName = 'その他';
    }
    // pref は未設定でも許容
    if(empty($slug) || empty($shortName)) {
        return array();
    }
	// カレンダー非表示設定
	$disabled = 0;
	if(isset($userMeta['rcal_calendar_disabled'])) {
		$disabled = array_pop($userMeta['rcal_calendar_disabled']) ?? 0;
	}
	return array(
		'id' => $userId,
		'shortName' => $shortName,
		'displayName' => $displayName,
		'areaCategoryKey' => $areaCategoryKey,
		'areaCategoryName' => $areaCategoryName,
		'holiday' => $holiday,
		'time' => $time,
		'pref' => $pref,
		'slug' => $slug,
		'tel' => $tel,
		'order' => $order,
		'disabled' => $disabled,
	);
}

/**
 * ユーザスラッグ名から店舗情報を取得する
 *
 * @param string/boolean slug => ユーザスラッグ
 */
function rcal_get_shop_user_by_slug($slug = false) {
	$user = get_user_by('slug', $slug);
	if($user) {
		return rcal_get_shop_user($user->ID);
	}
	return NULL;
}

/**
 * 投稿者権限のユーザを店舗ユーザとみなし、全ての店舗情報を取得
 *
 * @param string[] excludes => 除外対象のスラッグ名リスト
 */
function rcal_get_all_shop_users($excludes = array()) {
	global $rcal_areaSortNames;
	global $rcal_areaGroupedPrefs;

$__rcal_users = get_users(array('role' => 'author'));
if (empty($__rcal_users)) {
    $__rcal_users = get_users(array('capability' => array('author')));
}
$shopList = array_map(function($user) {
    return rcal_get_shop_user($user->ID);
}, $__rcal_users);

	$shopList = array_filter($shopList, function($shop) use ($excludes) {
		if(empty($shop)) {
			return false;
		}
		// カレンダーに表示しないフラグが立っている場合
		if($shop['disabled']) {
			return false;
		}
		// ユーザスラッグが除外リストに入っていれば結果に含めない
		return !in_array($shop['slug'], $excludes);
	});

	$groupedShopList = array();
	// 店舗エリアでグルーピング
	foreach($rcal_areaSortNames as $areaName) {
		$groupedShopList[$areaName] = array();
	}
	foreach($shopList as $shop) {
		$category = $shop['areaCategoryKey'];
		$groupedShopList[$category][] = $shop;
	}
	// 都道府県、支店のソート
	foreach($rcal_areaGroupedPrefs as $group => $prefs) {
		$targets = $groupedShopList[$group];
		usort($targets, function($a, $b) use ($prefs) {
			$pa = $a['pref'];
			$pb = $b['pref'];
			if($pa == $pb) {
				// 同じ都道府県であれば、表示順序でソート（大きい数ほど優先度高）
				return $a['order'] < $b['order'];
			} else {
				return  array_search($pa, $prefs) > array_search($pb, $prefs);
			}
		});
		$groupedShopList[$group] = $targets;
	}
	return $groupedShopList;
}

/**
 * カレンダー表示フラグを取得する
 * @param int/boolean userId => ユーザID
 * @return boolean 表示する: true / 表示しない: false
 */
function rcal_get_calendar_availability($userId = false) {
	$value = get_user_meta($userId, 'rcal_calendar_disabled', true) ?? false;
	// NOTE: 内部的にはネガティブな値で扱い、外部からは反転
	return !$value;
}

// 外販告知設定（催事情報）機能は削除しました

/**
 * カレンダーデータ取得用関数（改善版）
 *
 * @param int/str $year => 年
 * @param int/str $month => 月
 * @param int/boolean userId => ユーザID
 * @param boolean holiday => 祝日を表示するかどうか
 *
 */
function rcal_get_calendar(
    $year = false,
    $month = false,
    $userId = false,
    $holidayDisplay = false
){
    // ライセンスゲート
    if(!rcal_is_licensed()){
        return array();
    }
    
    global $wpdb;
    global $rcal_tableName;
    global $rcal_calendarLimit;
    // 動的スロットに切替（$rcal_timeTable, $rcal_synergy_time は使用しない）
    global $rcal_status;
    global $rcal_date_status;
    global $rcal_holidayWeek;
    global $rcal_holidayCSV;
    $table_name = $wpdb->prefix . $rcal_tableName;
    $data = array();
    // カレンダーデータ
    $calendarData = array();
    // 祝日データ
    $holidayData = array();
    $nowYear = date('Y');
    $nowMonth = date('m');

    if($year && $month){
        $nowYear = $year;
        $nowMonth = $month;
    }
    // nヶ月分のカレンダーカウント用
    $calendarDataCnt = 0;
    // 祝日設定
    $holidayFlg = $holidayDisplay;
    if($holidayFlg){
        // 内閣府CSVから祝日を取得し、'Y-m-d' をキーとする配列に整形
        $holidayData = rcal_fetch_holidays();
    }

    // 取得期間の設定
    $startDate = date('Y-m-d', mktime(0, 0, 0, $nowMonth, 1, $nowYear));
    $endDate = date('Y-m-d', strtotime("$startDate +{$rcal_calendarLimit} month -1 day"));
    
    if($year && $month){
        $endDate = date('Y-m-t', strtotime($startDate));
    }

    // 対象期間の全データを一括取得
    $query = $wpdb->prepare("
        SELECT date, time, status
        FROM {$table_name}
        WHERE date BETWEEN %s AND %s
        AND user_id = %d ORDER BY date ASC, time ASC
    ", $startDate, $endDate, $userId);
    $results = $wpdb->get_results($query, ARRAY_A);

    // データを日付別に整理
    $dataByDate = [];
    foreach ($results as $result) {
        $date = $result['date'];
        $time = $result['time'];
        $status = $result['status'];
        if (!isset($dataByDate[$date])) {
            $dataByDate[$date] = [];
        }
        $dataByDate[$date][$time] = $status;
    }

    // カレンダー生成
    $targetDate = $startDate;
    $calendarDataCnt = 0;
    while ($calendarDataCnt < $rcal_calendarLimit) {
        $cal = [];
        $monthDayNum = date('t', strtotime($targetDate));

        for ($i = 1; $i <= $monthDayNum; $i++) {
            $y = date('Y', strtotime($targetDate));
            $m = date('m', strtotime($targetDate));
            $n = date('n', strtotime($targetDate));
            $j = date('j', strtotime($targetDate));
            $d = $i;
            $day = date('Y-m-d', strtotime("$y-$m-$d"));
            $w = date('w', strtotime($day));
            $holi = '';
            if ($holidayFlg && isset($holidayData[$day])) {
                $holi = $holidayData[$day];
            }
            $time = [];
            // 【表示側用】その日のステータス値換算用
            $dateStatusCircleNum = 0;
            $dateStatusCrossNum = 0;
            $dateStatusTelNum = 0;
            $dateStatusTriangleNum = 0;
            // 当日のスロットを生成
            $slots = rcal_get_user_time_slots($userId, $day);
            $formattedDate = date('Y-m-d', strtotime($day));
            // ユーザー毎の週次定休日（0:日〜6:土）
            $weeklyHolidays = get_user_meta($userId, 'rcal_weekly_holidays', true);
            if(!is_array($weeklyHolidays)) { $weeklyHolidays = array(); }
            foreach ($slots as $idx => $slot) {
                $statusNum = 1;
                if (in_array(intval($w), $weeklyHolidays, true)) {
                    $statusNum = 0; // 週次定休日は一律 ×
                } elseif (isset($dataByDate[$formattedDate][$idx])) {
                    $statusNum = $dataByDate[$formattedDate][$idx];
                } else {
                    if (in_array($w, $rcal_holidayWeek)) {
                        $statusNum = 0; // 休日は×
                    } else {
                        $statusNum = 1; // 既定は●
                    }
                }
                switch ($statusNum) {
                    case 0: $dateStatusCrossNum++; break; // ×
                    case 1: $dateStatusCircleNum++; break; // ●
                    case 3: $dateStatusTelNum++; break; // TEL
                }
                $time[] = array(
                    'key' => $idx,
                    'label' => $slot['label'],
                    'status' => $statusNum,
                    'statusText' => $rcal_status[$statusNum],
                    // シナジー撤去予定のため暫定: インデックス+1
                    'synergyStatus' => ($idx + 1)
                );
            }

            // 【表示側用】その日のステータス判別
            if ($dateStatusCircleNum >= $rcal_date_status) {
                $dateStatusNum = 1; // ●
                $dateStatusCl = 'triangle';
            } elseif ($dateStatusCrossNum >= count($slots)) {
                $dateStatusNum = 0; // ×
                $dateStatusCl = 'cross';
            } elseif ($dateStatusTelNum > 0 && $dateStatusCircleNum == 0 && $dateStatusTriangleNum == 0) {
                $dateStatusNum = 3; // TEL
                $dateStatusCl = 'tel';
            } elseif ($dateStatusTelNum >= count($slots)) {
                $dateStatusNum = 3; // TEL
                $dateStatusCl = 'tel';
            } else {
                $dateStatusNum = 1; // ●
                $dateStatusCl = 'triangle';
            }

            $cal[] = array(
                'date' => $day,
                'y' => $y,
                'm' => $m,
                'n' => $n,
                'd' => $d,
                'j' => $j,
                'week' => $w,
                'holiday' => $holi,
                'dateStatus' => $dateStatusNum,
                'dateStatusText' => $rcal_status[$dateStatusNum],
                'dateStatusCl' => $dateStatusCl,
                'time' => $time
            );
        }

        // 次の月情報に更新
        $targetDate = date("Y-m-1", strtotime($targetDate . " +1 month"));
        $calendarData[] = $cal;
        $calendarDataCnt++;
    }
    $data = $calendarData;
    return $data;
}
  
/**
 * 日付指定でタイムテーブル情報取得
 *
 * @param int/str $year => 年
 * @param int/str $month => 月
 * @param int/str $day => 日
 * @param int/boolean userID => ユーザID
 *
 */
function rcal_get_timetable($year = false, $month = false, $day = false, $userId = false){
	// ライセンスゲート
	if(!rcal_is_licensed()){
		return array();
	}
	
	global $wpdb;
	global $rcal_tableName;
	global $rcal_calendarLimit;
    // 動的スロットに切替（$rcal_timeTable, $rcal_synergy_time は使用しない）
	global $rcal_status;
	global $rcal_date_status;
	global $rcal_holidayWeek;

	$data = array();
	$table_name = $wpdb->prefix . $rcal_tableName;

	// カレンダーデータ
	$calendarData = array();
	$nowYear = date('Y');
	$nowMonth = date('m');
	$nowDay = date('d');

	if($year && $month && $day){
		$nowYear = $year;
		$nowMonth = $month;
		$nowDay = $day;
	}

	// 日付データ格納
	// スタート日（今月）
	$targetDate = date('Y-m-d',mktime(0,0,0,$nowMonth,$nowDay,$nowYear));
	$cal = array();
	$y = date('Y',strtotime($targetDate));
	$m = date('m',strtotime($targetDate));
	$d = date('d',strtotime($targetDate));
	$day = date('Y-m-d',strtotime($y.'-'.$m.'-'.$d));
	$w = date('w',strtotime($day));
	$time = array();
    // 当日のスロットを生成
    $slots = rcal_get_user_time_slots($userId, $day);
    // ユーザー毎の週次定休日（0:日〜6:土）
    $weeklyHolidays = get_user_meta($userId, 'rcal_weekly_holidays', true);
    if(!is_array($weeklyHolidays)) { $weeklyHolidays = array(); }
    foreach($slots as $idx => $slot){
        $statusNum = 0;
        // データチェック（互換: time列はインデックス）
        $query = "SELECT time, status FROM {$table_name} WHERE date = '{$day}' AND time = {$idx} AND user_id = {$userId};";
        $results = $wpdb->get_row($query,ARRAY_A);
        if(in_array(intval($w), $weeklyHolidays, true)){
            $statusNum = 0;// 週次定休日は ×
        }elseif($results){
            $statusNum = $results['status'];
        }else{
            if(in_array($w,$rcal_holidayWeek)){
                $statusNum = 0;// ×
            }else{
                $statusNum = 1;// ●
            }
        }
        $time[] = array(
            'key' => $idx,
            'label' => $slot['label'],
            'status' => $statusNum,
            'statusText' => $rcal_status[$statusNum]
        );
    }
	$data = $time;
	return $data;
}

/* ------------------------------------------------------------------------------ */
/**
 * 【表示側】日付指定でタイムテーブルHTML
 */
function rcal_timeTable(
	$year = false,
	$month = false,
	$day = false,
	$userId = false
){
	$tpl = new Tmpl();
	$tpl->timeMap = rcal_get_timetable($year, $month, $day, $userId);
	$tpl->shopUser = rcal_get_shop_user($userId);
	$tpl->userId = $userId;
	$tpl->year = $year;
	$tpl->month = $month;
	$tpl->day = $day;
	$tpl->privacyUrl = get_option('rcal_privacy_url');
	$tpl->render('view/timeTable.tpl.php');
}

/**
 * 【表示側】Ajax: 予約作成
 */
add_action('wp_ajax_rcal_booking_create', 'rcal_booking_create');
add_action('wp_ajax_nopriv_rcal_booking_create', 'rcal_booking_create');
function rcal_booking_create(){
    check_ajax_referer('rcal_ajax', '_nonce');
    
    // ライセンスゲート
    if(!rcal_is_licensed()){
        wp_send_json_error(array('message' => 'ライセンスキーが無効です。'));
    }
    
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $userId = isset($post['userid']) ? absint($post['userid']) : 0;
    $date   = isset($post['date']) ? sanitize_text_field($post['date']) : '';
    $timeIdx= isset($post['timeidx']) ? absint($post['timeidx']) : -1;
    $name   = isset($post['name']) ? sanitize_text_field($post['name']) : '';
    $email  = isset($post['email']) ? sanitize_email($post['email']) : '';
    $tel    = isset($post['tel']) ? sanitize_text_field($post['tel']) : '';
    $note   = isset($post['note']) ? sanitize_textarea_field($post['note']) : '';
    $agree  = isset($post['agree']) ? (int)$post['agree'] : 0;

    try{
        // プライバシーURLが設定されているときだけ同意必須
        $requireAgree = !!get_option('rcal_privacy_url');
        // 日付はサーバ側で正規化（DBのdateは Y-m-d）
        $date_ts = strtotime($date);
        $date_norm = $date_ts ? date('Y-m-d', $date_ts) : '';
        // メールの妥当性
        if(function_exists('is_email') && !empty($email)){
            if(!is_email($email)) { $email = ''; }
        }
        if(empty($userId) || empty($date_norm) || $timeIdx < 0 || empty($name) || empty($email) || empty($tel) || ($requireAgree && !$agree)){
            wp_send_json_error(array('message' => '入力内容に不備があります。'));
        }

    global $wpdb, $rcal_tableName;
    $table_name = $wpdb->prefix . $rcal_tableName;

        // 枠の空き状況を確認しつつ、条件付きUPDATEで×へ（二重予約防止）
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} SET status = 0 WHERE user_id = %d AND date = %s AND time = %d AND status = 1",
            $userId, $date_norm, $timeIdx
        ));

        if($updated === false){
            wp_send_json_error(array('message' => '更新時にエラーが発生しました。'));
        }
        if($updated === 0){
            // 既に埋まっている
            wp_send_json_error(array('message' => '選択された枠は既に埋まりました。別の時間をお選びください。'));
        }

    // スロットラベル（HH:MM）取得
    $timeLabel = '';
    if(function_exists('rcal_get_user_time_slots')){
        $slots = rcal_get_user_time_slots($userId, $date_norm);
        if(is_array($slots) && isset($slots[$timeIdx]) && isset($slots[$timeIdx]['label'])){
            $timeLabel = $slots[$timeIdx]['label'];
        }
    }

    // 通知メール: ユーザー毎の通知先 / フォールバック admin_email
    $notify_to = get_user_meta($userId, 'rcal_notify_email', true);
    if(empty($notify_to)){
        $notify_to = get_option('admin_email');
    }
    $shop = rcal_get_shop_user($userId);
    $subject = '新しいご予約: ' . ($shop['displayName'] ?? '') . ' ' . $date_norm . ' ' . ($timeLabel ?: ('枠#'.($timeIdx+1)));
    
    // マスター設定から挨拶文と署名を取得
    $greeting = get_option('rcal_email_greeting');
    $signature = get_option('rcal_email_signature');
    
    $lines = array();
    $lines[] = $name .' 様';
    $lines[] = '';
    // 656行目：挨拶文を追加
    if(!empty($greeting)){
        $lines[] = $greeting;
        $lines[] = '';
    }
    $lines[] = '店舗 : ' . ($shop['displayName'] ?? '');
    $lines[] = '日付 : ' . $date_norm;
    $lines[] = '時間 : ' . (!empty($timeLabel) ? $timeLabel : (($timeIdx + 1) . '枠目'));
    $lines[] = 'お名前 : ' . $name .' 様';
    $lines[] = 'メール : ' . $email;
    $lines[] = '電話 : ' . $tel;
    $lines[] = '';
    if(!empty($note)){ $lines[] = 'メモ: ' . $note; }
    // 663行目：署名を追加
    if(!empty($signature)){
        $lines[] = '';
        $lines[] = $signature;
    }
    $body_text = implode("\n", $lines);
    
    // mb_send_mail を主軸とする（Mac Mail.app 対応）
    if(function_exists('mb_language')){ @mb_language('Japanese'); }
    if(function_exists('mb_internal_encoding')){ @mb_internal_encoding('UTF-8'); }
    
    $fromHeader = "From: WordPress <wordpress@u-up.jp>";
    
    // 店舗への通知
    $r1 = @mb_send_mail($notify_to, $subject, $body_text, $fromHeader);
    
    // お客様への自動返信
    $customer_body = "以下の内容で承りました。\n\n" . $body_text;
    $r2 = @mb_send_mail($email, 'ご予約を受け付けました', $customer_body, $fromHeader);
    
    if(empty($r1) || empty($r2)){
        wp_send_json_error(array('message' => 'メール送信に失敗しました。時間をおいて再度お試しください。'));
    }

        $success_message = get_option('rcal_success_message');
        if(empty($success_message)){
            $success_message = '予約を受け付けました。';
        }
        wp_send_json_success(array('message' => $success_message));
    }catch(Throwable $e){
        $msg = '[rcal] ajax booking fatal: '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine();
        if(function_exists('set_transient')){ set_transient('rcal_last_error', $msg, 5 * MINUTE_IN_SECONDS); }
        if(function_exists('error_log')){ error_log($msg); }
        $payload = array(
            'message' => 'サーバー内部エラーが発生しました。時間をおいて再度お試しください。',
            'debug' => $msg
        );
        wp_send_json_error($payload);
    }
}

// 外販告知設定（催事情報）機能は削除しました

/**
 * 【表示側】カレンダー表示用関数
 */
function rcal_ajax_calendar(
	$year = false,
	$month = false,
	$userId = false,
	$otheritem_caution_hidden = false
){
	// ライセンスゲート
	if(!rcal_is_licensed()){
		return;
	}
	
	global $rcal_reserveRestriction;

	// カレンダーを表示しないオプションが有効な場合
	if(!rcal_get_calendar_availability($userId)) {
		return;
	}

	$sliderEnableFlg = !$month ? true : false;
	// 店舗ユーザ情報
	$shopUser = rcal_get_shop_user($userId);
    // 表示側用カレンダーデータ（祝日取得を有効化）
    $calendarData = rcal_get_calendar($year, $month, $userId, true);

	$tpl = new Tmpl();
	$tpl->userId = $userId;
	$tpl->shopUser = $shopUser;
	$tpl->calendarData = $calendarData;
    $tpl->today = current_time( 'Y-m-d' );
	$tpl->sliderEnableFlg = $sliderEnableFlg;
	$tpl->rcal_reserveRestriction = $rcal_reserveRestriction;
	$tpl->otheritem_caution_hidden = $otheritem_caution_hidden;
	$tpl->asShopPage = true;
	// プライバシーURL（フォーム用）
	$tpl->privacyUrl = get_option('rcal_privacy_url');
	// フォーム注意事項
	$tpl->formNotice = get_option('rcal_form_notice');
	$tpl->render('view/ajaxCalendar.tpl.php');
}

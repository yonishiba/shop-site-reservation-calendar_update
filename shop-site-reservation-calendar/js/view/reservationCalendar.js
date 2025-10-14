//<![CDATA[
jQuery(document).ready(function ($) {

	/**
	 * 共通変数
	 *
	 */
	// -----------------------------------------------------------------------
	var $wrap = $('#wrap');

	var ev_rcalEv = 'rcalEv';
	var clName_popupOpen = 'rcalPopupOpen';
	var clName_timeTable = 'timeTable';
	var clName_timeTable_loading = 'loading';
	var spd_fade = 300;
	var pcWinSize = 1080;// PCブレークポイント
	var tabletWinSize = 768;// Tabletブレークポイント
	var spWinSize = 767;// SPブレークポイント

	/**
	 * 【親サイト限定】店舗選択来店予約
	 *
	 * ショップを選択して、各カレンダーデータ取得
	 *
	 */
	// -----------------------------------------------------------------------
	var clName_selectShopContact_tab = 'active';

	//var _selectShopContactBtn = '#selectShopContactBtn';
	var _selectShopContactBtn = '.selectShopContactBtn';
	var _selectShopContactBg = '#selectShopContactBg';
	var _selectShopContactArea = '#selectShopContactArea';
	var _selectShopContactCloseBtn = '#selectShopContactCloseBtn > a';
	var _selectShopContactShopListBtn = '.selectShopContact_shopListBtn';

	var _selectShopContactWrap = '.selectShopContactWrap';
	var _selectShopContactShopList = '#selectShopContact_shopList';
	var _selectShopContactCalendar = '#selectShopContact_calendar';
	var _selectShopContactCalendarArea = '#selectShopContact_calendarArea';
	var _selectShopContactCalendarPrevBtn = '#selectShopContact_calendarPrevBtn > a';

	var _selectShopContactShopListBtn = '#selectShopContact_shopList .selectShopListWrap .selectShopContact_shopListBtn';

	var $selectShopContactBg = $(_selectShopContactBg);
	var $selectShopContactArea = $(_selectShopContactArea);

	var $selectShopContactShopList = $(_selectShopContactShopList);
	var $selectShopContactCalendar = $(_selectShopContactCalendar);

	var $selectShopContactShopListBtn = $(_selectShopContactShopListBtn);

	var data_shopUserId = 'ssc_shopid';
	var ev_selectShopContact = 'selectShopContact';
	var flg_selectShopContactClick = false;
	var flg_shopSelectionSkipped = false; // 店舗選択をスキップしたかどうか

	function selectShopPopupOpen() {
		$selectShopContactShopList.addClass(clName_selectShopContact_tab);
		$selectShopContactCalendar.removeClass(clName_selectShopContact_tab);

		$selectShopContactBg.stop().fadeIn(spd_fade);
		$selectShopContactArea.stop().fadeIn(spd_fade);

		// スクロール制限
		var t = ($(window).scrollTop() * -1);
		$wrap.addClass(clName_popupOpen);
		$wrap.css({
			'width': '100%',
			'position': 'fixed',
			'top': t + 'px'
		});

		$('html,body').css('height', '100%');
	}

	function selectShopPopupClose() {
		$selectShopContactBg.stop().fadeOut(spd_fade);
		$selectShopContactArea.stop().fadeOut(spd_fade, function () {
			$selectShopContactShopList.addClass(clName_selectShopContact_tab);
			$selectShopContactCalendar.removeClass(clName_selectShopContact_tab);
		});

		// スクロール制限解除（topが未設定のケースに対応）
		var topVal = 0;
		if ($wrap && $wrap.length) {
			var cssTop = $wrap.css('top');
			if (typeof cssTop === 'string') {
				var parsed = parseInt(cssTop, 10);
				if (!isNaN(parsed)) topVal = parsed;
			}
		}
		var scrY = parseInt(topVal * -1, 10);
		if (isNaN(scrY)) {
			scrY = $(window).scrollTop() || 0;
		}
		$wrap.removeClass(clName_popupOpen);
		$wrap.attr('style', '');
		$('html,body').attr('style', '');
		$('html,body').scrollTop(scrY);

		// フラグをリセット
		flg_shopSelectionSkipped = false;
	}

	// ポップアップ立ち上げ
	$(document).on('click.' + ev_selectShopContact + '', _selectShopContactBtn, function (e) {
		$this = $(this);

		// 有効なユーザーが1名の場合は店舗選択をスキップしてカレンダーを直接表示
		var totalShops = parseInt($this.attr('data-total-shops'), 10) || 0;
		var singleUserId = parseInt($this.attr('data-single-user-id'), 10) || 0;

		if (totalShops === 1 && singleUserId > 0) {
			// 1名の場合は直接カレンダーを表示
			flg_shopSelectionSkipped = true; // フラグを立てる
			selectShopPopupOpen();
			// 店舗選択を自動実行
			setTimeout(function () {
				var $autoBtn = $('.selectShopContact_shopListBtn[data-ssc_shopid="' + singleUserId + '"]');
				if ($autoBtn.length > 0) {
					$autoBtn.trigger('click');
				}
			}, 100);
		} else {
			// 複数の場合は通常通り店舗選択画面を表示
			flg_shopSelectionSkipped = false; // フラグをリセット
			selectShopPopupOpen();
		}

		e.preventDefault();
		return false;
	});


	// ポップアップ閉じ
	$(document).on('click.' + ev_selectShopContact + '', _selectShopContactBg, function () {
		selectShopPopupClose();
	});

	$(document).on('click.' + ev_selectShopContact + '', _selectShopContactCloseBtn, function (e) {
		selectShopPopupClose();

		e.preventDefault();
		return false;
	});


	// 店舗名クリックでカレンダーデータ取得 / 表示
	$(document).on('click.' + ev_selectShopContact + '', _selectShopContactShopListBtn, function (e) {
		if (!flg_selectShopContactClick) {
			flg_selectShopContactClick = true;

			var $this = $(this);
			var userid = $this.data(data_shopUserId);
			var y = false;
			var m = false;

			$this.parents(_selectShopContactWrap).addClass(clName_timeTable_loading);

			// HTMLを追加するカレンダーエリア
			var $parents = $this.parents(_selectShopContactShopList).next(_selectShopContactCalendar);
			var $trg = $parents.find(_selectShopContactCalendarArea);

			$.ajax({
				url: rcal_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'rcal_calendar_ajax_get_calendar',
					_nonce: rcal_ajax.nonce,
					userid,
					y,
					m,
				},
				success: function (data) {
					var result = data;

					// 既に存在する場合は削除してから追加
					if ($trg.children().length) {
						$trg.children().remove();
					}

					$trg.append(result);

					rcalSliderEvSetting();

					$this.parents(_selectShopContactWrap).removeClass(clName_timeTable_loading);

					$selectShopContactShopList.removeClass(clName_selectShopContact_tab);
					$selectShopContactCalendar.addClass(clName_selectShopContact_tab);

					// 店舗選択をスキップした場合は「戻る」ボタンを非表示
					if (flg_shopSelectionSkipped) {
						$(_selectShopContactCalendarPrevBtn).hide();
					} else {
						$(_selectShopContactCalendarPrevBtn).show();
					}

					flg_selectShopContactClick = false;

				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.log(jqXHR, textStatus, errorThrown, arguments);

					var $html = '';
					$html += '<div class="' + clName_timeTable + ' error">' + "\n";
					$html += '<p>エラーが発生しました。時間をおいてから再度お試しください。</p>' + "\n";
					$html += '</div>' + "\n";

					$trg.append($html);

					$this.parents(_selectShopContactWrap).removeClass(clName_timeTable_loading);

					$selectShopContactShopList.removeClass(clName_selectShopContact_tab);
					$selectShopContactCalendar.addClass(clName_selectShopContact_tab);
					flg_selectShopContactClick = false;
				}
			});
		}

		e.preventDefault();
		return false;
	});



	// ショップ選択へ戻るボタン
	$(document).on('click.' + ev_selectShopContact + '', _selectShopContactCalendarPrevBtn, function (e) {
		if (!flg_selectShopContactClick) {
			flg_selectShopContactClick = true;

			var $this = $(this);

			// ショートコード埋め込みモードかチェック（現在のボタンの親要素をチェック）
			var isShortcodeMode = $this.closest('.rcal-shortcode-embed-wrapper').length > 0;

			// フォーム表示中の場合は、カレンダー＆時間選択に戻る
			if ($('#rcal_bookingFormArea').is(':visible') || $('#rcal_successArea').is(':visible')) {
				$('#rcal_bookingFormArea').hide();
				$('#rcal_successArea').hide();
				$('.rcal_calendarMainWrap').show();
				$('#rcal_openBookingBtn').prop('disabled', true);
				$('#rcal_openBookingBtn').show();
				$('.js-rcal-select-time').removeClass('selected');

				// ショートコード埋め込み時は「戻る」ボタンを非表示
				if (isShortcodeMode) {
					// クラスを削除して非表示にする
					// 現在の埋め込みコンテキスト内の戻るボタンを探す
					var $backBtn = $this.closest('.rcal-shortcode-embed-wrapper').find('#selectShopContact_calendarPrevBtn');
					$backBtn.removeClass('rcal-show-back-btn');
					// 中のaタグのスタイルも削除
					$backBtn.find('a').css('display', '');
				} else {
					// 店舗選択をスキップした場合は「戻る」ボタンを非表示、それ以外は表示
					if (flg_shopSelectionSkipped) {
						$(_selectShopContactCalendarPrevBtn).hide();
					} else {
						$(_selectShopContactCalendarPrevBtn).show();
					}
				}
			} else {
				// ショートコード埋め込み時は店舗一覧への戻りを無効化
				if (!isShortcodeMode) {
					// それ以外は通常通り店舗一覧へ戻る
					$selectShopContactShopList.addClass(clName_selectShopContact_tab);
					$selectShopContactCalendar.removeClass(clName_selectShopContact_tab);
				}
			}

			flg_selectShopContactClick = false;
		}
		e.preventDefault();
		return false;
	});

	/**
	 * 【子サイト限定】この店舗へ来店予約ボタン
	 *
	 * 現在見ている子サイトに対して、カレンダーデータ取得
	 *
	 */
	// -----------------------------------------------------------------------
	var _thisShopContactBtn = '#thisShopContactBtn';
	var _thisShopContactBg = '#thisShopContactBg';
	var _thisShopContactArea = '#thisShopContactArea';
	var _thisShopContactCloseBtn = '#thisShopContactCloseBtn > a';

	var _thisShopContactWrap = '.thisShopContactWrap';
	var _thisShopContactCalendar = '#thisShopContact_calendar';
	var _thisShopContactCalendarArea = '#thisShopContact_calendarArea';

	var $thisShopContactBg = $(_thisShopContactBg);
	var $thisShopContactArea = $(_thisShopContactArea);

	var data_shopUserId = 'ssc_shopid';

	var ev_thisShopContact = 'thisShopContact';

	var flg_thisShopContactClick = false;


	function thisShopPopupOpen() {
		$thisShopContactBg.stop().fadeIn(spd_fade);
		$thisShopContactArea.stop().fadeIn(spd_fade);

		// スクロール制限
		var t = ($(window).scrollTop() * -1);
		$wrap.addClass(clName_popupOpen);
		$wrap.css({
			'width': '100%',
			'position': 'fixed',
			'top': t + 'px'
		});

		$('html,body').css('height', '100%');
	}

	function thisShopPopupClose() {
		$thisShopContactBg.stop().fadeOut(spd_fade);
		$thisShopContactArea.stop().fadeOut(spd_fade);

		// スクロール制限解除（topが未設定のケースに対応）
		var topVal = 0;
		if ($wrap && $wrap.length) {
			var cssTop = $wrap.css('top');
			if (typeof cssTop === 'string') {
				var parsed = parseInt(cssTop, 10);
				if (!isNaN(parsed)) topVal = parsed;
			}
		}
		var scrY = parseInt(topVal * -1, 10);
		if (isNaN(scrY)) {
			scrY = $(window).scrollTop() || 0;
		}
		$wrap.removeClass(clName_popupOpen);
		$wrap.attr('style', '');
		$('html,body').attr('style', '');
		$('html,body').scrollTop(scrY);
	}

	// ポップアップ立ち上げ & カレンダーデータ取得 / 表示
	$(document).on('click.' + ev_thisShopContact + '', _thisShopContactBtn, function (e) {
		if (!flg_thisShopContactClick) {
			flg_thisShopContactClick = true;

			var $this = $(this);
			var userid = $this.data(data_shopUserId);
			var y = false;
			var m = false;

			$thisShopContactArea.find(_thisShopContactWrap).addClass(clName_timeTable_loading);

			// HTMLを追加するカレンダーエリア
			var $parents = $thisShopContactArea.find(_thisShopContactCalendar);
			var $trg = $parents.find(_thisShopContactCalendarArea);

			thisShopPopupOpen();

			$.ajax({
				url: rcal_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'rcal_calendar_ajax_get_calendar',
					userid,
					y,
					m,
				},
				success: function (data) {
					var result = data;

					// 既に存在する場合は削除してから追加
					if ($trg.children().length) {
						$trg.children().remove();
					}

					$trg.append(result);

					rcalSliderEvSetting();

					$thisShopContactArea.find(_thisShopContactWrap).removeClass(clName_timeTable_loading);

					flg_thisShopContactClick = false;
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.log(jqXHR, textStatus, errorThrown, arguments);

					var $html = '';
					$html += '<div class="' + clName_timeTable + ' error">' + "\n";
					$html += '<p>エラーが発生しました。時間をおいてから再度お試しください。</p>' + "\n";
					$html += '</div>' + "\n";

					$trg.append($html);

					$thisShopContactArea.find(_thisShopContactWrap).removeClass(clName_timeTable_loading);

					flg_thisShopContactClick = false;
				}
			});
		}

		e.preventDefault();
		return false;
	});


	// ポップアップ閉じ
	$(document).on('click.' + ev_thisShopContact + '', _thisShopContactBg, function () {
		thisShopPopupClose();
	});

	$(document).on('click.' + ev_thisShopContact + '', _thisShopContactCloseBtn, function (e) {
		thisShopPopupClose();

		e.preventDefault();
		return false;
	});

	/* 店舗選択　SP */
	/*----------------------------------------------------------*/
	var $wrap = $('#wrap');

	var childNav_shopListDt = $('#selectShopContact_shopList .selectShopListWrap dl dt');
	var dd = $('dd');
	var activeCl = 'active';
	var navAnimeTime = 400;
	var shopListClickEv = 'shopListClickEv';

	function bindAreaAccordion() {
		var currentW = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
		childNav_shopListDt.off('click.' + shopListClickEv);
		if (currentW < pcWinSize) { // SP/TABのみクリックで開閉
			childNav_shopListDt.on('click.' + shopListClickEv, function (e) {
				var $this = $(this);
				$this.toggleClass(activeCl);
				$this.next(dd).stop().slideToggle();
			});
		} else { // PCは常時展開＆クリック不可
			childNav_shopListDt.addClass(activeCl);
			childNav_shopListDt.next(dd).show();
		}
	}
	bindAreaAccordion();

	/*spNavMenuFunc();*/

	var winW = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;


	$(window).on('load resize', function () {
		var currentW = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
		bindAreaAccordion();
		winW = currentW;
	});

	/**
	 * 【共通】カレンダー 日付ステータスクリックでタイムテーブルAjax呼出し
	 *
	 */
	// -----------------------------------------------------------------------
	var _rcalCalendar = '.rcal_calendar';
	var _dateStatusBtn = '.ajaxDateStatusBtn';
	var _timeTableWrap = '.rcal_calendar_timeTableWrap';

	// 旧予約ボタン用のラッパは廃止
	var clName_dateStatusBtnActive = 'active';

	$(document).on('click.' + ev_rcalEv + '', _dateStatusBtn, function (e) {
		var $this = $(this);
		var shopId = $this.data('rcal_userid');
		var thisDate = $this.data('rcal_date');

		var thisDateSlice = thisDate.split('-');
		var y = parseInt(thisDateSlice[0]);
		var m = parseInt(thisDateSlice[1]);
		var d = parseInt(thisDateSlice[2]);

		// 旧予約ボタンの表示切替は不要

		if (shopId == '' || shopId == null || shopId == undefined) {
			shopId = false;
		}

		//		alert(shopId);
		//		alert(y+"\n"+m+"\n"+d);

		$this.parents(_rcalCalendar).find(_timeTableWrap).addClass(clName_timeTable_loading);

		$.ajax({
			url: rcal_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'rcal_calendar_ajax_date_timetable',
				_nonce: rcal_ajax.nonce,
				userid: shopId,
				y: y,
				m: m,
				d: d
			},
			success: function (data) {
				//				var result = JSON.parse(data);
				var result = data;
				//console.log(result);

				// 既に存在する場合は削除してから追加
				if ($this.parents(_rcalCalendar).find(_timeTableWrap).find('.' + clName_timeTable).length) {
					$this.parents(_rcalCalendar).find(_timeTableWrap).find('.' + clName_timeTable).remove();
				}

				// HTML
				var $html = '';
				$html = data;

				$this.parents(_rcalCalendar).find(_timeTableWrap).removeClass(clName_timeTable_loading);
				$this.parents(_rcalCalendar).find(_timeTableWrap).append($html);

				$this.parents(_rcalCalendar).find(_dateStatusBtn).removeClass(clName_dateStatusBtnActive);
				$this.addClass(clName_dateStatusBtnActive);
			},
			error: function (jqXHR, textStatus, errorThrown) {
				//				console.log( jqXHR, textStatus, errorThrown, arguments);

				var $html = '';
				$html += '<div class="' + clName_timeTable + ' error">' + "\n";
				$html += '<p>エラーが発生しました。時間をおいてから再度お試しください。</p>' + "\n";
				$html += '</div>' + "\n";

				$this.parents(_rcalCalendar).find(_timeTableWrap).removeClass(clName_timeTable_loading);
				$this.parents(_rcalCalendar).find(_timeTableWrap).append($html);
			}
		});
		e.preventDefault();
		return false;
	});

	/**
	 * 時間選択（時間ボタンクリック）
	 */
	var rcal_selectedTimeData = {};
	$(document).on('click.' + ev_rcalEv + '', '.js-rcal-select-time', function (e) {
		var $this = $(this);
		var userid = $this.data('rcal_userid');
		var date = $this.data('rcal_date');
		var timeidx = $this.data('rcal_timeidx');
		var timelabel = $this.data('rcal_timelabel');

		// 選択状態をトグル
		$('.js-rcal-select-time').removeClass('selected');
		$this.addClass('selected');

		// データを保持
		rcal_selectedTimeData = {
			userid: userid,
			date: date,
			timeidx: timeidx,
			timelabel: timelabel
		};

		// 「来店予約」ボタンを有効化
		$('#rcal_openBookingBtn').prop('disabled', false);

		e.preventDefault();
		return false;
	});

	// 「来店予約」ボタンクリック → フォーム表示
	$(document).on('click.' + ev_rcalEv + '', '#rcal_openBookingBtn', function (e) {
		if (!rcal_selectedTimeData.userid) {
			alert('時間を選択してください。');
			return false;
		}

		var $form = $('#rcal_bookingForm');
		if (!$form.length) { return; }

		// フォームにデータをセット
		$form.find('input[name="_nonce"]').val(rcal_ajax.nonce);
		$form.find('input[name="userid"]').val(rcal_selectedTimeData.userid);
		$form.find('input[name="date"]').val(rcal_selectedTimeData.date);
		$form.find('input[name="timeidx"]').val(rcal_selectedTimeData.timeidx);
		$form.find('input[name="timelabel"]').val(rcal_selectedTimeData.timelabel);
		$form.find('.rcal_formMessage').hide().text('');

		// ショートコード埋め込みモードかチェック（現在のボタンの親要素をチェック）
		var $this = $(this);
		var isShortcodeMode = $this.closest('.rcal-shortcode-embed-wrapper').length > 0;

		// カレンダー＆時間選択を非表示、フォームを表示
		$('.rcal_calendarMainWrap').hide();
		$('#rcal_bookingFormArea').show();
		$('#rcal_successArea').hide();
		$('#rcal_openBookingBtn').hide();

		// フォーム表示時は「戻る」ボタンを常に表示
		if (isShortcodeMode) {
			// ショートコード埋め込み時は、クラスを追加して表示
			// 現在の埋め込みコンテキスト内の戻るボタンを探す
			var $backBtn = $this.closest('.rcal-shortcode-embed-wrapper').find('#selectShopContact_calendarPrevBtn');
			// クラスを追加してCSSで表示
			$backBtn.addClass('rcal-show-back-btn');
			// 中のaタグも表示
			$backBtn.find('a').css('display', 'inline-block');
		} else {
			// モーダル時は通常通りshow()で表示
			$(_selectShopContactCalendarPrevBtn).show();
		}

		e.preventDefault();
		return false;
	});
	$(document).on('submit.' + ev_rcalEv + '', '#rcal_bookingForm', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $msg = $form.find('.rcal_formMessage');
		$msg.removeClass('error success').hide();
		var formData = $form.serializeArray();
		formData.push({ name: 'action', value: 'rcal_booking_create' });
		$.ajax({
			url: rcal_ajax.ajax_url,
			type: 'POST',
			data: $.param(formData),
			success: function (res) {
				if (res && res.success) {
					// 成功時：フォームを非表示、完了画面を表示、「戻る」ボタンを非表示
					var successMsg = (res.data && res.data.message) ? res.data.message : '予約を受け付けました。';
					$('#rcal_successMessage').text(successMsg);

					// 予約情報を表示（リセット前に取得）
					if (rcal_selectedTimeData.date && rcal_selectedTimeData.timelabel) {
						// 店舗名を取得
						var shopName = $('.calShop').first().text().trim() || '店舗名';
						$('#rcal_successShopName').text(shopName);

						// 日付をフォーマット（例：2025年10月9日）
						var dateStr = rcal_selectedTimeData.date; // YYYY-MM-DD
						if (dateStr) {
							var dateParts = dateStr.split('-');
							if (dateParts.length === 3) {
								var year = dateParts[0];
								var month = parseInt(dateParts[1], 10);
								var day = parseInt(dateParts[2], 10);
								var formattedDate = year + '年' + month + '月' + day + '日';
								$('#rcal_successDate').text(formattedDate);
							}
						}

						// 時間を表示（例：10:00〜）
						var timeLabel = rcal_selectedTimeData.timelabel || '';
						$('#rcal_successTime').text(timeLabel + '〜');
					}

					$('#rcal_bookingFormArea').hide();
					$('#rcal_successArea').show();
					// 「戻る」ボタンを非表示（モーダル・ショートコード共通）
					$(_selectShopContactCalendarPrevBtn).hide();
					$(_selectShopContactCalendarPrevBtn).removeClass('rcal-show-back-btn');
					$(_selectShopContactCalendarPrevBtn).find('a').css('display', ''); // aタグのスタイルも削除
					$('#rcal_openBookingBtn').hide();
					// フォームをリセット
					$form[0].reset();
					rcal_selectedTimeData = {};
				} else {
					var baseMsg = (res && res.data && res.data.message) ? res.data.message : 'エラーが発生しました。';
					var debugMsg = (res && res.data && res.data.debug) ? ('\n' + res.data.debug) : '';
					$msg.addClass('error').text(baseMsg + debugMsg).show();
					if (debugMsg) { try { console.error('[rcal-debug]', res.data.debug); } catch (e) { } }
				}
			},
			error: function () {
				$msg.addClass('error').text('通信エラーが発生しました。').show();
			}
		});
		return false;
	});

	/**
	 * 【共通】カレンダーの月 スライダー切り替え
	 *
	 */
	// -----------------------------------------------------------------------
	var _rcal_calendarSliderWrap = '.rcal_calendarSliderWrap';
	var _rcal_calendarSliderInner = '.rcal_calendarSliderInner';
	var _rcal_calendarSlide = '.rcal_calendarSlide';
	var _rcal_calendarSliderControllers = '.rcal_calendarSliderControllers';

	var $rcal_calendarSliderWrap = $(_rcal_calendarSliderWrap);

	var clName_sliderEnabled = 'rcal_calendarSliderEnabled';
	var clName_calendarSliderNext = 'rcal_calendarSliderNext';
	var clName_calendarSliderPrev = 'rcal_calendarSliderPrev';
	var clName_calendarSliderActive = 'active';
	var clName_calendarSliderControllersDisabled = 'disabled';

	var ev_rcalSlider = 'rcalSlider';

	var flg_rcalSliderControllerClick = false;

	function rcalSliderDesignSetting() {
		$rcal_calendarSliderWrap.each(function (idx) {
			var $this = $(this);
			if ($this.hasClass(clName_sliderEnabled)) {
				var $slideInner = $this.find(_rcal_calendarSliderInner);
				var $slide = $slideInner.find(_rcal_calendarSlide);

				var slideLength = $slide.length;
				var slideW = $this.outerWidth();
				//$slideInner.css('width',(slideW * slideLength));
				//$slide.css('width',slideW);
			}
		});
	}

	function rcalSliderEvSetting() {
		$(document).off('.' + ev_rcalSlider);

		// CSS設定
		rcalSliderDesignSetting();

		$(document).on('resize.' + ev_rcalSlider + ' load.' + ev_rcalSlider + '', 'window', function () {
			rcalSliderDesignSetting();
		});

		$(window).resize(function () {
			rcalSliderDesignSetting();
		});

		// 次の月・前の月クリック
		$(document).on('click.' + ev_rcalSlider + '', _rcal_calendarSliderControllers, function (e) {
			var $this = $(this);
			var $parents = $this.parents(_rcal_calendarSliderWrap);
			var parentIdx = $(_rcal_calendarSliderWrap).index($parents);

			if (!flg_rcalSliderControllerClick) {
				flg_rcalSliderControllerClick = true;

				if ($parents.hasClass(clName_sliderEnabled)) {
					var $slideInner = $parents.find(_rcal_calendarSliderInner);
					var $slide = $slideInner.find(_rcal_calendarSlide);

					var slideLength = $slide.length;
					var slideW = $parents.outerWidth();

					var moveLeft = 0;

					var nowSlide = $(_rcal_calendarSliderWrap + ':eq(' + parentIdx + ') ' + _rcal_calendarSlide + '.' + clName_calendarSliderActive).index();
					var nextSlide = 0;

					var nextPrevFlg = false;
					if ($this.hasClass(clName_calendarSliderNext)) {
						nextPrevFlg = 'Next';
					} else if ($this.hasClass(clName_calendarSliderPrev)) {
						nextPrevFlg = 'Prev';
					} else {
						nextPrevFlg = 'Next';
					}

					if (nextPrevFlg == 'Next') {
						nextSlide = (nowSlide + 1);
						if (nextSlide >= slideLength) { nextSlide = (slideLength - 1); }
					} else if (nextPrevFlg == 'Prev') {
						nextSlide = (nowSlide - 1);
						if (nextSlide < 0) { nextSlide = 0; }
					} else {
						nextSlide = nowSlide;
					}

					moveLeft = (slideW * nextSlide * -1);
					//					console.log('Flg：'+nextPrevFlg+"\n"+'ParentIdx：'+parentIdx+"\n"+'NowIdx：'+nowSlide+"\n"+'NextIdx：'+nextSlide+"\n"+'MoveLeft：'+moveLeft+"\n");

					if (nextSlide >= (slideLength - 1)) {
						$(_rcal_calendarSliderWrap + ':eq(' + parentIdx + ') ' + _rcal_calendarSliderControllers + '.' + clName_calendarSliderNext).addClass(clName_calendarSliderControllersDisabled);
					} else {
						$(_rcal_calendarSliderWrap + ':eq(' + parentIdx + ') ' + _rcal_calendarSliderControllers + '.' + clName_calendarSliderNext).removeClass(clName_calendarSliderControllersDisabled);
					}

					if (nextSlide <= 0) {
						$(_rcal_calendarSliderWrap + ':eq(' + parentIdx + ') ' + _rcal_calendarSliderControllers + '.' + clName_calendarSliderPrev).addClass(clName_calendarSliderControllersDisabled);
					} else {
						$(_rcal_calendarSliderWrap + ':eq(' + parentIdx + ') ' + _rcal_calendarSliderControllers + '.' + clName_calendarSliderPrev).removeClass(clName_calendarSliderControllersDisabled);
					}

					$slide.removeClass(clName_calendarSliderActive);
					$slide.eq(nextSlide).addClass(clName_calendarSliderActive);
					$slideInner.css('margin-left', moveLeft);

					setTimeout(function () {
						flg_rcalSliderControllerClick = false;
					}, 300);
				} else {
					flg_rcalSliderControllerClick = false;
				}
			}
			e.preventDefault();
			return false;
		});
	}
	rcalSliderEvSetting();

	/**
	 * ショートコード埋め込みモードの初期化
	 */
	// ショートコード埋め込み時は「戻る」ボタンを最初から非表示
	$('.rcal-shortcode-embed-wrapper').each(function () {
		$(this).find(_selectShopContactCalendarPrevBtn).hide();
	});
});
//]]>
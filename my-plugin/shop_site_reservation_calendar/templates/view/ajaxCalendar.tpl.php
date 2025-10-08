<?php
/**
 * カレンダー表示
 *
 * @var array $_->calendarData             カレンダー情報
 * @var str   $_->userId                   店舗ユーザID
 * @var array $_->shopUser                 店舗情報
 * @var date  $_->today                    今日の日付
 * @var bool  $_->sliderEnableFlg          カレンダースライダー有効化フラグ
 * @var int   $_->rcal_reserveRestriction  予約制限の期間日数
 * @var bool  $_->otheritem_caution_hidden 固定表示フラグ
 * @var bool  $_->asShopPage               店舗ページ向けかどうか
 * @var str   $_->privacyUrl               プライバシーポリシーURL
 * @var str   $_->formNotice               フォーム注意事項
 */
?>
<h1 class="calShop"><?php echo esc_html(isset($_->shopUser['display_name']) ? $_->shopUser['display_name'] : ($_->shopUser['displayName'] ?? '')); ?></h1>
<?php // 外販告知表示は撤廃 ?>
<?php if(!empty($_->calendarData)): ?>
    <div class="rcal_display_ajax_calendarMainWrap rcal_calendarMainWrap">
        <div class="rcal_statusDetailWrap">
            <div class="rcal_statusDetail">
                <div class="detailBox">
                    <p><span class="icon iconImg triangle" aria-label="ご予約可能">◯</span><span class="text">ご予約可能</span></p>
                </div>
                <div class="detailBox">
                    <p><span class="icon iconImg cross" aria-label="ご予約不可">×</span><span class="text">ご予約不可</span></p>
                    <p><span class="icon tel"></span><span class="text">お電話ください</span></p>
                </div>
            </div>
        </div>
        <div class="rcal_display_ajax_calendarSliderWrap rcal_calendarSliderWrap <?php echo ($_->sliderEnableFlg) ? 'rcal_calendarSliderEnabled' : 'rcal_calendarSliderDisabled'; ?>">
            <?php if($_->sliderEnableFlg): ?>
                <a href="javascript:void(0);" class="rcal_display_ajax_calendarSliderControllers rcal_calendarSliderControllers rcal_calendarSliderNext"></a>
                <a href="javascript:void(0);" class="rcal_display_ajax_calendarSliderControllers rcal_calendarSliderControllers rcal_calendarSliderPrev disabled"></a>
            <?php endif; ?>
            <div class="rcal_display_ajax_calendarSliderInner rcal_calendarSliderInner">
                <?php
                    $slideCnt = 0;
                    $weeks = array(
                        array('sun', '日'),
                        array('mon', '月'),
                        array('tue', '火'),
                        array('wed', '水'),
                        array('thu', '木'),
                        array('fri', '金'),
                        array('sat', '土'),
                    );
                ?>
                <?php foreach($_->calendarData as $calendar): ?>
                    <div class="rcal_display_ajax_calendarSlide rcal_calendarSlide <?php echo ($slideCnt == 0 && $_->sliderEnableFlg) ? 'active' : ''; ?>">
                        <div class="rcal_display_ajax_calendarWrap rcal_calendarWrap">
                            <div class="rcal_display_ajax_calendarHeader rcal_calendarHeader">
                                <h2 class="rcal_display_ajax_calendarTitle rcal_calendarTitle">
                                    <?php echo $calendar[0]['y']; ?>年<span class="monthNum"><?php echo $calendar[0]['n']; ?></span>月
                                </h2>
                            </div>
                            <div class="rcal_calendar">
                                <table class="rcal_calendar_mainCalendar">
                                    <tr class="week">
                                        <?php foreach($weeks as $week): ?>
                                            <th class="<?php echo $week[0]; ?>"><?php echo $week[1]; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                    <?php
                                        $cellCnt = 0;
                                        $dateStartFlg = true;
                                    ?>
                                    <?php $reserveLimit = date("Y-m-d", strtotime($_->today . "+" . $_->rcal_reserveRestriction . " day")); ?>
                                    <?php foreach($calendar as $v): ?>
                                        <?php if($cellCnt >= 7): // 土曜日で折り返す ?>
                                            </tr>
                                            <tr>
                                            <?php $cellCnt = 0; ?>
                                        <?php endif; ?>
                                        <?php
                                            $day = new DateTime($v['date']); // 日付
                                            $w = $v['week'];
                                        ?>
                                        <?php if($dateStartFlg): // 月の初めの開始位置を設定する ?>
                                            <?php for($cellCnt = 0; $cellCnt < $w; $cellCnt++): ?>
                                                <td class="empty"></td>
                                            <?php endfor; ?>
                                            <?php $dateStartFlg = false; ?>
                                        <?php endif; ?>
                                        <?php
                                            $dd = $day->format('Y-m-d');
                                            $ddtime = strtotime($dd);
                                            $clName = 'day ' . $weeks[$w][0] . (!empty($v['holiday']) ? ' holiday' : '');
                                        ?>
                                        <?php if(strtotime($_->today) === $ddtime): // 本日 ?>
                                            <?php if($v['dateStatus'] == 0): ?>
                                                <td class="<?php echo $clName; ?>">
                                                    <span class="tdInner">
                                                        <span class="num"><?php echo $day->format('j'); ?></span>
                                                        <div class="dateStatus">
                                                            <p>
                                                                <span class="icon <?php echo $v['dateStatusCl']; ?>" aria-label="<?php echo $v['dateStatusText']; ?>"><?php echo ($v['dateStatusCl'] === 'cross' ? '×' : '◯'); ?></span>
                                                            </p>
                                                        </div>
                                                    </span>
                                                </td>
                                            <?php elseif($v['dateStatus'] == 3): ?>
                                                <td class="<?php echo $clName; ?> today">
                                                    <a href="tel:<?php echo str_replace('-', '', $_->shopUser['tel']); ?>" class="tdInner">
                                                        <span class="num"><?php echo $day->format('j'); ?></span>
                                                        <span class="reserveLimitText">
                                                            <span class="tel"></span>
                                                        </span>
                                                    </a>
                                                </td>
                                            <?php else: ?>
                                                <td class="<?php echo $clName; ?> today">
                                                    <a href="tel:<?php echo str_replace('-', '', $_->shopUser['tel']); ?>" class="tdInner">
                                                        <span class="num"><?php echo $day->format('j'); ?></span>
                                                        <span class="reserveLimitText">
                                                            <span class="tel"></span>
                                                        </span>
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                        <?php elseif(strtotime($_->today) > $ddtime): // 今日より過去 ?>
                                            <td class="<?php echo $clName; ?> past">
                                                <span class="tdInner">
                                                    <span class="num"><?php echo $day->format('j'); ?></span>
                                                    <span class="icon passed"></span>
                                                </span>
                                            </td>
                                        <?php else: // 今日より未来 ?>
                                            <?php if($v['dateStatus'] == 0): //×だったら ?>
                                                <td class="<?php echo $clName; ?>">
                                                    <span class="tdInner">
                                                    <span class="num"><?php echo $day->format('j'); ?></span>
                                                    <div class="dateStatus">
                                                        <p>
                                                            <span class="icon <?php echo $v['dateStatusCl']; ?>" aria-label="<?php echo $v['dateStatusText']; ?>"><?php echo ($v['dateStatusCl'] === 'cross' ? '×' : '◯'); ?></span>
                                                        </p>
                                                    </div>
                                                    </span>
                                                </td>
                                            <?php elseif(strtotime($reserveLimit) >= $ddtime): // 予約制限チェック $reserveLimit日前まで予約可 ?>
                                                <td class="<?php echo $clName; ?> reserveLimit">
                                                    <a href="tel:<?php echo str_replace('-', '', $_->shopUser['tel']); ?>" class="tdInner">
                                                        <span class="num"><?php echo $day->format('j'); ?></span>
                                                        <span class="reserveLimitText">
                                                            <span class="tel"></span>
                                                        </span>
                                                    </a>
                                                </td>
                                            <?php elseif($v['dateStatus'] == 3): //TELだったら ?>
                                                <td class="<?php echo $clName; ?> today">
                                                    <a href="tel:<?php echo str_replace('-', '', $_->shopUser['tel']); ?>" class="tdInner">
                                                        <span class="num"><?php echo $day->format('j'); ?></span>
                                                        <span class="reserveLimitText">
                                                            <span class="tel"></span>
                                                        </span>
                                                    </a>
                                                </td>
                                            <?php else: ?>
                                                <td class="<?php echo $clName; ?>">
                                                    <a href="javascript:void(0);"
                                                        class="ajaxDateStatusBtn tdInner"
                                                        data-rcal_date="<?php echo $day->format('Y-m-d'); ?>"
                                                        data-rcal_userid="<?php echo $_->userId; ?>">
                                                        <span class="num"><?php echo $day->format('j'); ?></span>
                                                        <div class="dateStatus">
                                                            <p>
                                                                <span class="icon <?php echo $v['dateStatusCl']; ?>" aria-label="<?php echo $v['dateStatusText']; ?>"><?php echo ($v['dateStatusCl'] === 'cross' ? '×' : '◯'); ?></span>
                                                            </p>
                                                        </div>
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php $cellCnt += 1; ?>
                                    <?php endforeach; ?>
                                    </tr>
                                </table>
                                <div class="rcal_calendar_timeTableWrap">
                                    <div class="timeTableTextArea"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php $slideCnt += 1; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- 予約ボタンエリア（電話案内はフォーム下へ移動） -->
        <div class="rcal_display_ajax_calendarFooter rcal_calendarFooter"></div>
    </div>
    <?php // スライダー外：予約フォームボタン、予約フォーム、送信完了エリアを単一配置 ?>
    <div class="rcal_reserveBtn_wrap" id="rcal_globalReserveBtnWrap" style="margin-top:20px; text-align:center;">
        <button type="button" class="rcal_btn rcal_btn--primary js-rcal-open-booking" id="rcal_openBookingBtn" disabled>予約フォームへ</button>
    </div>
    <div class="rcal_bookingFormArea" id="rcal_bookingFormArea" style="display:none;">
        <div class="rcal_bookingForm_inner">
            <h3 class="rcal_bookingForm_title">予約フォーム</h3>
            <form class="rcal_bookingForm" id="rcal_bookingForm">
                <input type="hidden" name="_nonce" value="" />
                <input type="hidden" name="userid" value="<?php echo (int)$_->userId; ?>" />
                <input type="hidden" name="date" value="" />
                <input type="hidden" name="timeidx" value="" />
                <input type="hidden" name="timelabel" value="" />
                <?php if(!empty($_->formNotice)): ?>
                <div class="rcal_formNotice">
                    <?php echo nl2br(esc_html($_->formNotice)); ?>
                </div>
                <?php endif; ?>
                <p class="rcal_field"><label>お名前<span class="req">必須</span><br><input type="text" name="name" required></label></p>
                <p class="rcal_field"><label>メールアドレス<span class="req">必須</span><br><input type="email" name="email" required></label></p>
                <p class="rcal_field"><label>お電話番号<span class="req">必須</span><br><input type="tel" name="tel" required></label></p>
                <p class="rcal_field"><label>ご要望・メモ（任意）<br><textarea name="note" rows="3"></textarea></label></p>
                <?php if(!empty($_->privacyUrl)): ?>
                <p class="rcal_field"><label><input type="checkbox" name="agree" value="1" required> <a href="<?php echo esc_url($_->privacyUrl); ?>" target="_blank" rel="noopener noreferrer">プライバシーポリシー</a>に同意します</label></p>
                <?php endif; ?>
                <div class="rcal_actions">
                    <button type="submit" class="rcal_btn rcal_btn--primary">この内容で予約する</button>
                </div>
                <div class="rcal_formMessage" aria-live="polite" style="display:none;"></div>
            </form>
        </div>
    </div>
    <div class="rcal_successArea" id="rcal_successArea" style="display:none;">
        <div class="rcal_success_inner">
            <h3 class="rcal_success_title">ありがとうございます</h3>
            <div class="rcal_success_booking_info" id="rcal_successBookingInfo">
                <h4>ご予約内容</h4>
                <dl>
                    <dt>店舗名：</dt>
                    <dd id="rcal_successShopName"></dd>
                    <br>
                    <dt>日付：</dt>
                    <dd id="rcal_successDate"></dd>
                    <br>
                    <dt>時間：</dt>
                    <dd id="rcal_successTime"></dd>
                </dl>
            </div>
            <p class="rcal_success_message" id="rcal_successMessage"></p>
        </div>
    </div>
    <?php // 電話予約案内：フォームの下に移動 ?>
    <?php if($_->shopUser['tel']): ?>
        <?php $_->shopUser['tel'] = mb_convert_kana($_->shopUser['tel'], 'a','UTF-8'); ?>
        <div class="rcal_display_ajax_calendarReservationTelArea rcal_calendarReservationTelArea" style="margin-top:20px;">
            <p class="telText">お電話でもご予約可能です</p>
            <?php if($_->asShopPage): ?>
                <div class="telInfoWrap">
            <?php endif; ?>
                <p class="telNumber">
                    <span class="icon tel"></span>
                    <a href="tel:<?php echo str_replace('-', '', $_->shopUser['tel']); ?>"><?php echo $_->shopUser['tel']; ?></a>
                </p>
                <?php if(!empty($_->shopUser['time']) || !empty($_->shopUser['holiday'])): ?>
                    <p class="regularHolidayText">
                    <?php if(!empty($_->shopUser['time'])): ?>
                        <?php echo $_->shopUser['time']; ?>
                    <?php endif; ?>
                    <?php if(!empty($_->shopUser['holiday'])): ?>
                        （<?php echo $_->shopUser['holiday']; ?>を除く）
                    <?php endif; ?>
                    </p>
                <?php endif; ?>
            <?php if($_->asShopPage): ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
/**
 * 予約カレンダー設定画面
 *
 * @var Array $_->calendarData カレンダー情報
 * @var Boolean $_->calendarDisabled カレンダーを表示しないフラグ
 * @var Array $_->rcalStatud １時間ごとのステータス選択肢（◯、✕、TEL）
 */
?>
<div>
	<h2>予約カレンダー</h2>
	<form action="" method="post">
        <div class="rcal_calendarArea">
            <div class="rcal_calendarHeader">
                <p class="submit">
                    <input type="submit" class="button-primary" name="rcal_calendar_submit" value="<?php echo '更新' ?>">
                </p>
                <label class="rcal_calendarDisabled">
                    <input type="checkbox" name="rcal_calendar_disabled" value="1" <?php echo $_->calendarDisabled ? 'checked' : ''; ?> />
                    <span>カレンダーを表示しない</span>
                </label>
            </div>
            <?php $tabIndex = 0; ?>
            <div class="rcal_adminTabs">
                <ul class="rcal_adminTabs_nav">
                    <?php foreach($_->calendarData as $calendar): ?>
                        <li class="rcal_adminTabs_navItem <?php echo ($tabIndex === 0) ? 'active' : ''; ?>" data-tab-index="<?php echo $tabIndex; ?>">
                            <?php echo $calendar[0]['y']; ?>年<?php echo $calendar[0]['m']; ?>月
                        </li>
                        <?php $tabIndex += 1; ?>
                    <?php endforeach; ?>
                </ul>
                <?php $tabIndex = 0; ?>
                <?php foreach($_->calendarData as $calendar): ?>
                <div class="rcal_calendarWrap rcal_adminTabs_panel <?php echo ($tabIndex === 0) ? 'active' : ''; ?>" data-tab-index="<?php echo $tabIndex; ?>">
                    <h2 class="rcal_calendarTitle"><?php echo $calendar[0]['y']; ?>年<?php echo $calendar[0]['m']; ?>月</h2>
                <div class="rcal_statusDetail">
                    <!-- <p><span class="bold">○</span> => まもなく満席</p> -->
                    <span class="bold">〇</span> => ご予約おすすめ
                    <span class="bold">✕</span> => ご予約不可（定休日）
                    <span class="bold">TEL</span> => お電話ください
                </div>
                <div class="rcal_calendar">
                    <table class="rcal_calendarTable">
                        <tr class="header">
                            <th class="sun">日</th>
                            <th class="mon">月</th>
                            <th class="tue">火</th>
                            <th class="wed">水</th>
                            <th class="thu">木</th>
                            <th class="fri">金</th>
                            <th class="sat">土</th>
                        </tr>
                        <tr>
                        <?php
                            $index = 0;
                            $weekClass = array('sun','mon','tue','wed','thu','fri','sat');
                            $isStartDate = true;
                        ?>
                        <?php foreach($calendar as $weekData): ?>
                            <?php if($index >= 7): // 土曜日で折り返す ?>
                                </tr><tr>
                                <?php $index = 0; ?>
                            <?php endif; ?>
                            <?php $day = new DateTime($weekData['date']); // 日付 ?>
                            <?php $week = $weekData['week']; ?>
                            <?php if($isStartDate): ?>
                                <?php for($index = 0; $index < $week; $index += 1): // 月の初めの開始位置を設定する ?>
                                    <td class="empty"></td>
                                <?php endfor; ?>
                                <?php $isStartDate = false; ?>
                            <?php endif; ?>
                            <td class="<?php echo 'day ' . $weekClass[$week] . (!empty($weekData['holiday']) ? ' holiday' : ''); ?>">
                                <span class="num"><?php echo $day->format('d') ?></span>
                                <div class="timeTable">
                                    <ul>
                                    <?php foreach($weekData['time'] as $time): ?>
                                        <li>
                                            <dl>
                                                <dt><?php echo $time['label'] ?></dt>
                                                <dd>
                                                    <select name="status_<?php echo $day->format('Y-m-d'); ?>_<?php echo $time['key'] ?>" class="statusSelect">
                                                    <?php foreach($_->rcalStatud as $key => $state): ?>
                                                        <?php if(isset($time['status']) && $key == $time['status']): ?>
                                                            <option value="<?php echo $key ?>" selected="selected"><?php echo $state; ?></option>
                                                        <?php else: ?>
                                                            <option value="<?php echo $key ?>"><?php echo $state; ?></option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                    </select>
                                                </dd>
                                            </dl>
                                        </li>
                                    <?php endforeach; ?>
                                    </ul>
                                </div>
                            </td>
                            <?php $index += 1; ?>
                        <?php endforeach; ?>
                        </tr>
                    </table>
                </div>
                </div>
                <?php $tabIndex += 1; ?>
                <?php endforeach; ?>
            </div>
            <p class="submit">
                <input type="submit" class="button-primary" name="rcal_calendar_submit" value="<?php echo '更新' ?>">
            </p>
        </div>
    </form>
</div>

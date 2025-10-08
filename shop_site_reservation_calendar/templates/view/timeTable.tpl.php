<?php
/**
 * 予約可能時間をボタンで一覧表示
 *
 * @var Array $_->timeMap 予約可能時間一覧
 * @var String $_->shopUser
 * @var String $_->year
 * @var String $_->month
 * @var String $_->day
 */
?>
<div class="timeTable">
	<div class="timeTableTitleArea">
		<p class="title">予約時間を選択してください</p>
	</div>
    <div class="timeTableDetailArea" id="rcal_timeTableDetailArea">
		<ul>
		<?php foreach($_->timeMap as $time): ?>
			<?php
				$statusCl = '';
				switch($time['status']) {
					case 0: $statusCl = 'cross'; break;        // ×
					case 1: $statusCl = 'triangle'; break;    // ●
					case 3: $statusCl = 'tel'; break;        // TEL
					default: $statusCl = 'triangle';        // ●
				}
			?>
			<li>
			<?php if($time['status'] != 0): ?>
				<?php if($time['status'] == 3): ?>
					<a
						href="tel:<?php echo str_replace('-', '', $_->shopUser['tel']); ?>"
						class="timeBtnWrap linkTel">
						<span class="icon <?php echo $statusCl; ?>" aria-label="お電話ください">TEL</span>
						<span class="time"><?php echo $time['label']; ?></span>
					</a>
				<?php else: ?>
                    <a
                        href="javascript:void(0);"
                        data-rcal_userid="<?php echo (int)$_->userId; ?>"
                        data-rcal_date="<?php echo $_->year.'-'.$_->month.'-'.$_->day; ?>"
                        data-rcal_timeidx="<?php echo (int)$time['key']; ?>"
                        data-rcal_timelabel="<?php echo esc_attr($time['label']); ?>"
                        class="timeStatusBtn timeBtnWrap link js-rcal-select-time">
						<span class="icon <?php echo $statusCl; ?>" aria-label="<?php echo ($statusCl==='cross'?'ご予約不可':'ご予約可'); ?>"><?php echo ($statusCl==='cross'?'×':'◯'); ?></span>
						<span class="time"><?php echo $time['label']; ?></span>
					</a>
				<?php endif; ?>
			<?php else: ?>
				<div class="timeBtnWrap noLink">
					<span class="icon <?php echo $statusCl; ?>" aria-label="ご予約不可">×</span>
					<span class="time"><?php echo $time['label']; ?></span>
				</div>
			<?php endif; ?>
			</li>
		<?php endforeach; ?>
		</ul>
		<?php /* 予約フォームボタンはスライド外（ajaxCalendar.tpl.php）に集約 */ ?>
    </div>

</div>
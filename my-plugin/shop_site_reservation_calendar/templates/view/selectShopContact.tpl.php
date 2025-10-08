<?php if($_->isSelectShopContactBtn): ?>
<?php
    $openBtnText = get_option('rcal_open_button_text');
    if(empty($openBtnText)) { $openBtnText = 'ご予約はこちら'; }
    $openBtnIcon = get_option('rcal_open_button_icon');
    if(empty($openBtnIcon)) { $openBtnIcon = RCAL_URL . '/images/icon/calendar_bk.png'; }
?>
<a href="javascript:void(0);" 
   id="selectShopContactBtn" 
   class="selectShopContactBtn"
   data-total-shops="<?php echo (int)($_->totalShopCount ?? 0); ?>"
   data-single-user-id="<?php echo (int)($_->singleUserId ?? 0); ?>">
    <img src="<?php echo esc_url($openBtnIcon); ?>" alt="<?php echo esc_attr($openBtnText); ?>">
    <?php echo esc_html($openBtnText); ?>
</a>
<?php endif; ?>
<div id="selectShopContactBg" class="<?php echo ($_->classname ?? ''); ?>"></div>
<div id="selectShopContactArea" class="<?php echo ($_->classname ?? ''); ?>">
    <div id="selectShopContact" class="<?php echo ($_->classname ?? ''); ?>">
        <div class="selectShopContactWrap <?php echo ($_->classname ?? ''); ?>">
            <div class="selectShopContactInner <?php echo ($_->classname ?? ''); ?>">
                <div id="selectShopContactCloseBtn" class="<?php echo ($_->classname ?? ''); ?>">
                    <a href="javascript:void(0);"></a>
                </div>
                <div id="selectShopContact_shopList" class="selectShopContactContents <?php echo ($_->classname ?? ''); ?> active">
                    <div class="shopSelectTitleArea <?php echo ($_->classname ?? ''); ?>">
                        <p class="title">ご希望の店舗を選択してください</p>
                    </div>
                    <div class="selectShopListWrap <?php echo ($_->classname ?? ''); ?>">
                    <?php 
                        $index = 0; 
                        $totalCount = 0;
                        
                        // 全ユーザーの都道府県を収集して一致判定
                        $allPrefs = array();
                        $allShops = array();
                        foreach($_->groupedShopList as $shopList) {
                            if (is_array($shopList)) {
                                $totalCount += count($shopList);
                                foreach($shopList as $shop) {
                                    $allShops[] = $shop;
                                    $pref = trim($shop['pref'] ?? '');
                                    if ($pref !== '') {
                                        $allPrefs[] = $pref;
                                    }
                                }
                            }
                        }
                        
                        // ユニークな都道府県が1つだけかチェック
                        $uniquePrefs = array_unique($allPrefs);
                        $isSinglePref = (!empty($uniquePrefs) && count($uniquePrefs) === 1);
                    ?>
                    <?php if (empty($totalCount)): ?>
                        <p>現在、表示できる店舗がありません。</p>
                    <?php endif; ?>
                    <?php if ($isSinglePref): ?>
                        <?php // 全員が同じ都道府県の場合：シンプルなULリスト表示 ?>
                        <ul>
                        <?php foreach($allShops as $shop): ?>
                            <li>
                                <a href="javascript:void(0);"
                                    class="selectShopContact_shopListBtn <?php echo ($_->classname ?? ''); ?>"
                                    data-ssc_shopid="<?php echo $shop['id']; ?>">
                                    <?php echo esc_html($shop['displayName'] ?? $shop['shortName'] ?? ''); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <?php // 複数の都道府県がある場合：既存のDL構造で表示 ?>
                        <?php foreach($_->groupedShopList as $shopList): ?>
                        <?php if (empty($shopList)) { continue; } ?>
                        <?php $areaName = $shopList[0]['areaCategoryName'] ?? ''; ?>
                        <?php if (empty($areaName) || $areaName === 'その他') { // エリア未設定や「その他」は見出し無しでULだけ表示 ?>
                            <ul>
                            <?php foreach($shopList as $key => $shop): ?>
                                <li>
                                    <a href="javascript:void(0);"
                                        class="selectShopContact_shopListBtn <?php echo ($_->classname ?? ''); ?>"
                                        data-ssc_shopid="<?php echo $shop['id']; ?>">
                                        <?php echo esc_html($shop['displayName'] ?? $shop['shortName'] ?? ''); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                            <?php continue; }
                        ?>
                        <?php
                            // 都道府県でグルーピング（既存のエリア内ソート順を維持するため、出現順ベース）
                            $hidePrefHeading = ($areaName === '東京都');
                            $prefGroups = array();
                            if ($hidePrefHeading) {
                                // 見出し不要: すべて空キーに集約
                                $prefGroups[''] = $shopList;
                            } else {
                                foreach ($shopList as $s) {
                                    $p = trim($s['pref'] ?? '');
                                    if ($p === '') { $p = ''; }
                                    if (!isset($prefGroups[$p])) { $prefGroups[$p] = array(); }
                                    $prefGroups[$p][] = $s;
                                }
                            }
                        ?>
                        <dl>
                            <dt><span class="areaName"><?php echo esc_html($areaName); ?></span></dt>
                            <dd>
                                <?php foreach($prefGroups as $prefName => $shops): ?>
                                    <?php if ($prefName !== ''): ?>
                                        <div class="prefGroup">
                                            <p class="prefName" role="heading" aria-level="3"><?php echo esc_html($prefName); ?></p>
                                            <ul>
                                                <?php foreach($shops as $shop): ?>
                                                    <li>
                                                        <a href="javascript:void(0);"
                                                            class="selectShopContact_shopListBtn <?php echo ($_->classname ?? ''); ?>"
                                                            data-ssc_shopid="<?php echo $shop['id']; ?>">
                                                            <?php echo esc_html($shop['displayName'] ?? $shop['shortName'] ?? ''); ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <ul>
                                            <?php foreach($shops as $shop): ?>
                                                <li>
                                                    <a href="javascript:void(0);"
                                                        class="selectShopContact_shopListBtn <?php echo ($_->classname ?? ''); ?>"
                                                        data-ssc_shopid="<?php echo $shop['id']; ?>">
                                                        <?php echo esc_html($shop['displayName'] ?? $shop['shortName'] ?? ''); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </dd>
                        </dl>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
                <div id="selectShopContact_calendar" class="selectShopContactContents <?php echo ($_->classname ?? ''); ?>">
                    <div id="selectShopContact_calendarPrevBtn" class="<?php echo ($_->classname ?? ''); ?>">
                        <a href="javascript:void(0);">戻る</a>
                    </div>
                    <div id="selectShopContact_calendarArea" class="<?php echo ($_->classname ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
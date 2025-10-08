/* 【管理画面】予約カレンダー用JS */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var navItems = document.querySelectorAll('.rcal_adminTabs_navItem');
    var panels = document.querySelectorAll('.rcal_adminTabs_panel');
    if (!navItems.length || !panels.length) return;
    function activate(idx) {
      navItems.forEach(function (el) { el.classList.toggle('active', el.getAttribute('data-tab-index') == idx); });
      panels.forEach(function (el) { el.classList.toggle('active', el.getAttribute('data-tab-index') == idx); });
    }
    navItems.forEach(function (item) {
      item.addEventListener('click', function (e) { e.preventDefault(); activate(this.getAttribute('data-tab-index')); });
    });
  });
})();
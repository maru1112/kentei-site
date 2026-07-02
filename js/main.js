// モバイル用ハンバーガーメニューの開閉
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var header = document.querySelector('.header');
    var toggle = document.querySelector('.nav-toggle');
    if (!header || !toggle) return;

    toggle.addEventListener('click', function () {
      var open = header.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    // メニュー内リンクを押したら閉じる
    header.querySelectorAll('.nav a').forEach(function (a) {
      a.addEventListener('click', function () {
        header.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  });
})();

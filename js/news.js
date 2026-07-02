// お知らせデータ(data/news.json)を読み込んで一覧を描画する
// - index.html: #news-latest に最新3件(タグ付き)
// - news.html:  #news-list に全件
(function () {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var latest = document.getElementById('news-latest');
    var list = document.getElementById('news-list');
    if (!latest && !list) return;

    fetch('data/news.json')
      .then(function (res) { return res.json(); })
      .then(function (items) {
        // 日付の新しい順に並べる(JSON側の並び順ミスに備える)
        items.sort(function (a, b) { return a.date < b.date ? 1 : -1; });

        if (latest) {
          latest.innerHTML = items.slice(0, 3).map(function (item) {
            return '<div class="news-row">' +
              '<span class="news-row__date">' + esc(item.date) + '</span>' +
              '<span class="news-row__tag">' + esc(item.tag) + '</span>' +
              '<span class="news-row__ttl">' + esc(item.title) + '</span>' +
              '</div>';
          }).join('');
        }

        if (list) {
          list.innerHTML = items.map(function (item) {
            var inner = '<div class="news-item">' +
              '<span class="news-item__date">' + esc(item.date) + '</span>' +
              '<span class="news-item__ttl">' + esc(item.title) + '</span>' +
              '<span class="news-item__arrow">→</span>' +
              '</div>';
            return item.url ? '<a href="' + esc(item.url) + '">' + inner + '</a>' : inner;
          }).join('');
        }
      })
      .catch(function (err) {
        console.error('お知らせの読み込みに失敗しました:', err);
      });
  });
})();

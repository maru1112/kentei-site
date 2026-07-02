// お知らせデータ(data/news.json)を読み込んで描画する
// - index.html:       #news-latest に最新3件(タグ付き)
// - news.html:        #news-list に全件
// - news-detail.html: #news-detail に ?d=日付 で指定された1件の本文
// リンク先は item.url、なければ item.body がある場合に詳細ページへ。
// どちらもないお知らせはリンクなし(矢印も表示しない)。
(function () {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function linkFor(item) {
    if (item.url) return item.url;
    if (item.body) return 'news-detail.html?d=' + encodeURIComponent(item.date);
    return '';
  }

  function renderLatest(el, items) {
    el.innerHTML = items.slice(0, 3).map(function (item) {
      var row = '<div class="news-row">' +
        '<span class="news-row__date">' + esc(item.date) + '</span>' +
        '<span class="news-row__tag">' + esc(item.tag) + '</span>' +
        '<span class="news-row__ttl">' + esc(item.title) + '</span>' +
        '</div>';
      var href = linkFor(item);
      return href ? '<a href="' + esc(href) + '">' + row + '</a>' : row;
    }).join('');
  }

  function renderList(el, items) {
    el.innerHTML = items.map(function (item) {
      var href = linkFor(item);
      var inner = '<div class="news-item">' +
        '<span class="news-item__date">' + esc(item.date) + '</span>' +
        '<span class="news-item__ttl">' + esc(item.title) + '</span>' +
        (href ? '<span class="news-item__arrow">→</span>' : '') +
        '</div>';
      return href ? '<a href="' + esc(href) + '">' + inner + '</a>' : inner;
    }).join('');
  }

  function renderDetail(el, items) {
    var date = new URLSearchParams(location.search).get('d');
    var item = null;
    for (var i = 0; i < items.length; i++) {
      if (items[i].date === date) { item = items[i]; break; }
    }

    if (!item) {
      el.innerHTML = '<p class="news-detail__notfound">お探しのお知らせは見つかりませんでした。</p>' +
        '<a class="news-detail__back" href="news.html">← お知らせ一覧へ戻る</a>';
      return;
    }

    document.title = item.title + ' | 一般社団法人 日本総合検定資格センター';

    var paragraphs = String(item.body || '').split(/\n+/).filter(function (p) {
      return p.trim() !== '';
    }).map(function (p) {
      return '<p>' + esc(p) + '</p>';
    }).join('');

    el.innerHTML =
      '<div class="news-detail__meta">' +
        '<span class="news-detail__date">' + esc(item.date) + '</span>' +
        (item.tag ? '<span class="news-detail__tag">' + esc(item.tag) + '</span>' : '') +
      '</div>' +
      '<h2 class="news-detail__ttl">' + esc(item.title) + '</h2>' +
      '<div class="news-detail__body">' + paragraphs + '</div>' +
      '<a class="news-detail__back" href="news.html">← お知らせ一覧へ戻る</a>';
  }

  document.addEventListener('DOMContentLoaded', function () {
    var latest = document.getElementById('news-latest');
    var list = document.getElementById('news-list');
    var detail = document.getElementById('news-detail');
    if (!latest && !list && !detail) return;

    fetch('data/news.json')
      .then(function (res) { return res.json(); })
      .then(function (items) {
        // 日付の新しい順に並べる(JSON側の並び順ミスに備える)
        items.sort(function (a, b) { return a.date < b.date ? 1 : -1; });

        if (latest) renderLatest(latest, items);
        if (list) renderList(list, items);
        if (detail) renderDetail(detail, items);
      })
      .catch(function (err) {
        console.error('お知らせの読み込みに失敗しました:', err);
      });
  });
})();

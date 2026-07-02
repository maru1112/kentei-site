# kentei-site

一般社団法人 日本総合検定資格センターの静的サイト。GitHub Pagesで公開(mainブランチにマージすると自動デプロイ)。

## お知らせの更新方法

お知らせのデータは `data/news.json` に一元管理されている。`index.html`(最新3件・タグ付き)と `news.html`(全件)は `js/news.js` がこのJSONを読み込んで描画するため、**HTMLの編集は不要**。

新しいお知らせを追加するには、`data/news.json` の配列の先頭にオブジェクトを1件追加する:

```json
{ "date": "2026.07.15", "tag": "受付", "title": "お知らせのタイトル", "url": "" }
```

- `date`: `YYYY.MM.DD` 形式。表示は日付の新しい順に自動ソートされる
- `tag`: トップページの最新3件にのみ表示される短いラベル(例: 受付 / 会場 / 重要 / 検定 / お知らせ / イベント)
- `url`: 詳細ページがある場合のみ相対パスを指定(例: `news/2026-07-15.html`)。空文字ならリンクなしで表示される

## ページ構成

- 全ページルート直下のフラットなHTML(index, kentei, business, about, faq, news, contact, recruit)
- スタイルは `css/styles.css` に集約、JSは `js/main.js`(ナビ開閉)と `js/news.js`(お知らせ描画)
- ファビコンは `favicon.ico`(ルート)と `assets/favicon-*.png` / `assets/apple-touch-icon.png`

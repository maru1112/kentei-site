# kentei-site

一般社団法人 日本総合検定資格センターの静的サイト。GitHub Pagesで公開(mainブランチにマージすると自動デプロイ)。

## お知らせの更新方法

お知らせのデータは `data/news.json` に一元管理されている。`index.html`(最新3件・タグ付き)と `news.html`(全件)は `js/news.js` がこのJSONを読み込んで描画するため、**HTMLの編集は不要**。

新しいお知らせを追加するには、`data/news.json` の配列の先頭にオブジェクトを1件追加する:

```json
{ "date": "2026.07.15", "title": "お知らせのタイトル", "url": "", "body": "本文の1段落目。\n2段落目。" }
```

- `date`: `YYYY.MM.DD` 形式。表示は日付の新しい順に自動ソートされる。詳細ページのURLキーにもなるため同日2件は不可
- `body`: 本文。指定すると詳細ページ(`news-detail.html?d=日付`)へのリンクが自動で付く。`\n` で段落を分ける。HTMLは書けない(エスケープされる)
- `url`: 外部ページや個別HTMLに飛ばしたい場合のみ相対パスを指定。`body` より優先される
- `body` も `url` もないお知らせはリンクなし(矢印も非表示)で一覧に表示される
- タグ(ラベル)は廃止済み。`tag` フィールドを追加しても表示されない

## ページ構成

- 全ページルート直下のフラットなHTML(index, kentei, business, about, faq, news, contact, recruit)
- スタイルは `css/styles.css` に集約、JSは `js/main.js`(ナビ開閉)と `js/news.js`(お知らせ描画)
- ファビコンは `favicon.ico`(ルート)と `assets/favicon-*.png` / `assets/apple-touch-icon.png`

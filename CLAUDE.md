# kentei-site

一般社団法人 日本総合検定資格センターの静的サイト(https://kentei-center.or.jp/)。

## デプロイ

**Xserver** にFTPSで自動デプロイされる(`.github/workflows/deploy.yml`)。**mainブランチにpush/マージすると1〜2分で本番反映**。GitHub Pagesではない。

## ページ構成

ルート直下のフラットなHTML。共通のヘッダー・フッターを各ファイルが持つ(テンプレート機構なし)ため、**フッターやheadの変更は全HTMLに同じ編集を適用する必要がある**。

| ファイル | 内容 |
|---|---|
| `index.html` | トップ(お知らせ最新3件を表示) |
| `kentei.html` / `business.html` / `about.html` / `faq.html` / `recruit.html` | 各ページ |
| `news.html` | お知らせ一覧 |
| `news-detail.html` | お知らせ詳細(`?d=日付` で1件表示。`noindex`) |
| `contact.html` + `contact.php` | お問い合わせフォームと送信処理 |
| `privacy.html` | 個人情報保護方針 |

- スタイルは `css/styles.css` に集約(デザイントークンは `:root` に定義)
- JSは `js/main.js`(ナビ開閉)、`js/news.js`(お知らせ描画)、`js/contact.js`(フォーム送信)
- ファビコンは `favicon.ico`(ルート)と `assets/favicon-*.png` / `assets/apple-touch-icon.png`
- SNS共有画像は `assets/ogp.png`

## お知らせの更新方法

お知らせのデータは `data/news.json` に一元管理されている。`index.html`(最新3件)と `news.html`(全件)は `js/news.js` がこのJSONを読み込んで描画するため、**HTMLの編集は不要**。

新しいお知らせを追加するには、`data/news.json` の配列の先頭にオブジェクトを1件追加する:

```json
{ "date": "2026.07.15", "title": "お知らせのタイトル", "url": "", "body": "本文の1段落目。\n2段落目。" }
```

- `date`: `YYYY.MM.DD` 形式。表示は日付の新しい順に自動ソートされる。詳細ページのURLキーにもなるため同日2件は不可
- `body`: 本文。指定すると詳細ページ(`news-detail.html?d=日付`)へのリンクが自動で付く。`\n` で段落を分ける。HTMLは書けない(エスケープされる)
- `url`: 外部ページや個別HTMLに飛ばしたい場合のみ相対パスを指定。`body` より優先される
- `body` も `url` もないお知らせはリンクなし(矢印も非表示)で一覧に表示される
- タグ(ラベル)は廃止済み。`tag` フィールドを追加しても表示されない

## お問い合わせフォーム

`contact.html` → `contact.php`(PHPの `mb_send_mail`)で送信。`js/contact.js` が非同期送信し、ページ遷移なしで完了/エラーを表示する(JS無効時は `contact.php` が結果ページを返す)。

- **送信先・送信元は `contact.php` 冒頭の定数**(`TO_EMAIL` / `FROM_EMAIL`)で設定。現在はどちらも `info@kentei-center.or.jp`
- **エンベロープ送信元 `-f` の指定は必須**。Xserverでは未指定だと配信されない/迷惑メール扱いになる
- スパム対策にハニーポット項目(`name="website"`、CSSで画面外へ)を設置。埋まっていたら成功を装って破棄する
- ヘッダインジェクション対策として改行を含む入力を拒否

## メール認証(迷惑メール対策)

**サイトはXserver、メールはGoogle Workspace** という構成。この前提を崩さないこと。

- **SPF**: `v=spf1 include:_spf.google.com include:spf.sender.xserver.jp ~all`(Google発とXserver発の両方を許可)
- **DKIM**: Google Workspace側で有効化済み(`google._domainkey`)。info@ から送るメールに署名が付く
- **DMARC**: `_dmarc` に `p=none`(監視のみ)。レポートが info@ に届く
- フォームメールはXserver発のためDKIM署名は付かないが、**SPFアラインメントでDMARCが成立**する

⚠️ **Xserverにメールアカウントを作らないこと。** XserverのDKIM設定はメールアカウントが1件以上ないと有効化できないが、作成するとXserverが自ドメイン宛メールをローカル配送し、Google Workspaceに届かなくなるリスクがある。そのためXserverのDKIMは意図的に見送っている。

## SEO

新しいページを追加するときは、以下も揃えること(既存ページの `<head>` をコピーするのが早い):

- `<link rel="canonical">` と `<meta name="robots">`
- OGP / Twitter Card 一式(`og:title` / `og:description` / `og:url` / `og:image`)
- Google Analytics(GA4)のgtag.jsスニペット(`<head>` 直後、測定ID `G-DT82KPRKSF`)
- `<meta name="format-detection" content="telephone=no,address=no,email=no">`(iOSが住所・電話番号を自動リンク化するのを防ぐ)
- パンくずがあるページは `BreadcrumbList` のJSON-LD
- `sitemap.xml` にURLを追加

その他:
- 構造化データ:`index.html` に `Organization`、`faq.html` に `FAQPage`(**表示テキストと一致させること**)
- `.htaccess` で http→https / www有り→無し を301リダイレクト(正規URLは `https://kentei-center.or.jp/`)
- `robots.txt` から `sitemap.xml` を参照
- Search Console の所有権確認タグは `index.html` の `<head>` にある

## 注意事項

- 省庁ロゴなど、後援・所管の関係があると誤認させる表示は載せない(フッターは中立的なテキストリンクのみ)

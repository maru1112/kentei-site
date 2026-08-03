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
- ヘッダインジェクション対策として改行を含む入力を拒否

### 営業・スパム対策

`contact.php` 冒頭の「スパム・営業対策の設定」ブロックで調整できる。**設計方針は「確実な自動投稿だけを破棄し、判断が微妙なものは配信して印を付ける」**(正当な問い合わせを取りこぼさないことを優先)。

| 対策 | 挙動 |
|---|---|
| ハニーポット(`name="website"`、CSSで画面外へ) | 埋まっていたら成功を装って破棄 |
| 滞在時間(`name="elapsed"`、`js/contact.js` が設定) | `MIN_ELAPSED_SEC` 秒未満の送信を破棄 |
| 同一IPの連投制限 | `RATE_LIMIT_WINDOW` 秒あたり `RATE_LIMIT_COUNT` 件まで。超過時はエラー表示。記録は一時ディレクトリにIPをハッシュ化して保存 |
| 迷惑度スコア | `SCORE_REJECT` 以上は破棄、`SCORE_FLAG` 以上は件名に `[要確認]` を付けて配信(本文末尾に判定理由を追記) |

スコアの加点要素:本文のURL数、氏名/会社名/住所へのURL混入、`NG_WORDS` との部分一致(1語 +3)、本文に日本語が無い、キリル文字、滞在時間情報なし。

- **NGワードは1語だけなら破棄されない**(例:「SEOに関する検定を作りたい」は `[要確認]` 付きで配信される)。2語以上一致すると破棄される
- しきい値やNGワードを変えたときは、正当な問い合わせが破棄されないか必ず確認すること

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

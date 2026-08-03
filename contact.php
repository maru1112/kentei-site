<?php
/**
 * お問い合わせフォーム送信処理(Xserver / PHP mail)
 *
 * contact.html のフォームから POST を受け取り、内容を指定アドレスへメール送信する。
 * JavaScript(js/contact.js)経由の場合は JSON を返し、
 * JavaScript 無効時は通常の POST として完了/エラーページを表示する。
 */

// ===== 設定(ここを書き換えれば送信先などを変更できます) =====
// 受信先メールアドレス(お問い合わせの届く先)
const TO_EMAIL   = 'info@kentei-center.or.jp';
// 送信元アドレス。迷惑メール対策のため、独自ドメインのアドレスを使用。
const FROM_EMAIL = 'info@kentei-center.or.jp';
// 件名の接頭辞
const SUBJECT_PREFIX = '[お問い合わせ] ';

// ===== スパム・営業対策の設定 =====
// フォーム表示から送信までの最短秒数。これより速い送信はボットとみなす
const MIN_ELAPSED_SEC = 3;
// 同一IPからの送信制限(RATE_LIMIT_WINDOW 秒あたり RATE_LIMIT_COUNT 件まで)
const RATE_LIMIT_COUNT  = 10;
const RATE_LIMIT_WINDOW = 3600;
// 迷惑度スコアのしきい値
//   SCORE_REJECT 以上 … 破棄する(送信者には成功を装う)
//   SCORE_FLAG   以上 … 件名に印を付けて配信する(誤判定でも取りこぼさないため)
const SCORE_REJECT = 5;
const SCORE_FLAG   = 2;
// 迷惑判定時に件名へ付ける印。Gmail等でフィルタする際の目印になる
const FLAG_PREFIX = '[要確認] ';
// 営業・スパムに多い語(部分一致・大文字小文字は区別しない)。1語あたり +3 点
const NG_WORDS = [
    'SEO', '被リンク', '検索順位', '上位表示', 'アクセスアップ', '相互リンク',
    'ホームページ制作', 'サイト制作', 'ホームページ作成', 'Web制作',
    '格安', '激安', '料金を大幅', 'コスト削減のご提案',
    '副業', '不労所得', '仮想通貨', 'ビットコイン', 'FX投資',
    'ファクタリング', '資金調達', '債務', '出会い', 'アダルト',
    'テレアポ', '営業代行', 'リスト販売', '一斉送信', 'メール配信代行',
    'viagra', 'casino', 'crypto', 'payday loan', 'backlink',
];
// ============================================================

mb_language('Japanese');
mb_internal_encoding('UTF-8');

$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

/** 完了・エラーを返して終了する */
function respond($ok, $message, $errors = []) {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $ok, 'message' => $message, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    } else {
        render_page($ok, $message, $errors);
    }
    exit;
}

/** JavaScript 無効時向けの結果ページ */
function render_page($ok, $message, $errors) {
    $title = $ok ? '送信完了' : '送信エラー';
    $err_html = '';
    if (!$ok && $errors) {
        $err_html = '<ul class="form-alert__list">';
        foreach ($errors as $e) {
            $err_html .= '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $err_html .= '</ul>';
    }
    $msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title} | 一般社団法人 日本総合検定資格センター</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&family=Shippori+Mincho:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" href="favicon.ico" sizes="48x48">
<link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="site">
  <section class="section section--paper" style="min-height:60vh">
    <div class="form-wrap">
      <div class="section-head" style="margin-bottom:30px">
        <div class="eyebrow">CONTACT</div>
        <h2>{$title}</h2>
      </div>
      <div class="form-result">
        <p>{$msg}</p>
        {$err_html}
        <a class="form-result__back" href="contact.html">← お問い合わせページへ戻る</a>
      </div>
    </div>
  </section>
</div>
</body>
</html>
HTML;
}

// POST 以外はフォームへ戻す
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

/**
 * 同一IPからの短時間の連投を制限する。
 * 記録は一時ディレクトリに置き、IPはハッシュ化して保存する。
 * ファイル操作に失敗した場合は制限をかけない(フォームを止めないことを優先)。
 */
function rate_limit_exceeded($ip) {
    if ($ip === '') {
        return false;
    }
    $file = sys_get_temp_dir() . '/kentei_contact_rate.json';
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return false;
    }
    @flock($fp, LOCK_EX);
    $log = json_decode((string)stream_get_contents($fp), true);
    if (!is_array($log)) {
        $log = [];
    }
    // 集計期間を過ぎた記録を掃除する
    $now = time();
    foreach ($log as $k => $times) {
        $kept = array_values(array_filter((array)$times, function ($t) use ($now) {
            return is_int($t) && $t > $now - RATE_LIMIT_WINDOW;
        }));
        if ($kept) {
            $log[$k] = $kept;
        } else {
            unset($log[$k]);
        }
    }
    $key  = hash('sha256', $ip);
    $over = isset($log[$key]) && count($log[$key]) >= RATE_LIMIT_COUNT;
    if (!$over) {
        $log[$key][] = $now;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($log));
    @flock($fp, LOCK_UN);
    fclose($fp);
    return $over;
}

/**
 * 入力内容から迷惑度スコアを算出する。
 * 戻り値は [スコア, 判定理由の配列]。
 */
function spam_score(array $data, $has_elapsed) {
    $score  = 0;
    $reason = [];

    $message = $data['message'];
    // 本文中のURL数。営業メールはリンクを複数含むことが多い
    $urls = preg_match_all('#https?://#i', $message);
    if ($urls >= 3) {
        $score += 4;
        $reason[] = "本文にURLが{$urls}件";
    } elseif ($urls >= 1) {
        $score += 1;
        $reason[] = "本文にURLが{$urls}件";
    }

    // 氏名・会社名・住所にURLが入るのは、ほぼ自動投稿
    foreach (['name', 'company', 'address'] as $key) {
        if (preg_match('#https?://#i', $data[$key])) {
            $score += 4;
            $reason[] = '氏名・会社名・住所にURL';
            break;
        }
    }

    // 営業・スパムに多い語
    $haystack = $message . ' ' . $data['company'] . ' ' . $data['name'];
    foreach (NG_WORDS as $word) {
        if (mb_stripos($haystack, $word) !== false) {
            $score += 3;
            $reason[] = "NGワード「{$word}」";
        }
    }

    // 本文に日本語(ひらがな・カタカナ・漢字)が一切ない
    if ($message !== '' && !preg_match('/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $message)) {
        $score += 3;
        $reason[] = '本文に日本語が含まれない';
    }

    // キリル文字(ロシア語圏からの自動投稿に多い)
    if (preg_match('/[\x{0400}-\x{04FF}]/u', $haystack)) {
        $score += 4;
        $reason[] = 'キリル文字を含む';
    }

    // JavaScriptを通さない直接POST(ボットの可能性)
    if (!$has_elapsed) {
        $score += 1;
        $reason[] = '滞在時間の情報なし';
    }

    return [$score, $reason];
}

// スパム対策1:ハニーポット(人間には見えない項目)が埋まっていたら成功を装って破棄
if (!empty($_POST['website'])) {
    respond(true, 'お問い合わせを受け付けました。');
}

// スパム対策2:フォーム表示から送信までが速すぎる場合はボットとみなして破棄
// (js/contact.js がページ表示からの経過ミリ秒を送る)
$has_elapsed = isset($_POST['elapsed']) && $_POST['elapsed'] !== '';
if ($has_elapsed && (int)$_POST['elapsed'] < MIN_ELAPSED_SEC * 1000) {
    respond(true, 'お問い合わせを受け付けました。');
}

// スパム対策3:同一IPからの連投を制限する
if (rate_limit_exceeded(isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '')) {
    respond(false, '短時間に複数回送信されています。お手数ですが、しばらく時間をおいてからお試しください。');
}

// 入力値の取得
$fields = [
    'type'     => 'お問い合わせ種別',
    'name'     => 'お名前',
    'furigana' => 'フリガナ',
    'company'  => '会社・団体名',
    'email'    => 'メールアドレス',
    'tel'      => '電話番号',
    'address'  => 'ご住所',
    'message'  => 'お問い合わせ内容',
];
$data = [];
foreach ($fields as $key => $label) {
    $data[$key] = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}

// バリデーション
$errors = [];
$required = ['type', 'name', 'company', 'email', 'tel', 'address', 'message'];
foreach ($required as $key) {
    if ($data[$key] === '') {
        $errors[] = $fields[$key] . 'を入力してください。';
    }
}
if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'メールアドレスの形式が正しくありません。';
}
if (empty($_POST['consent'])) {
    $errors[] = '個人情報保護方針への同意が必要です。';
}
// ヘッダインジェクション対策(改行を含む値を拒否)
foreach (['name', 'email', 'tel'] as $key) {
    if (preg_match('/[\r\n]/', $data[$key])) {
        $errors[] = '不正な入力が含まれています。';
        break;
    }
}

if ($errors) {
    respond(false, '入力内容をご確認ください。', $errors);
}

// スパム対策4:内容から迷惑度を判定する
// しきい値以上は破棄し、疑わしい程度なら件名に印を付けて配信する
list($score, $spam_reason) = spam_score($data, $has_elapsed);
if ($score >= SCORE_REJECT) {
    respond(true, 'お問い合わせを受け付けました。');
}
$is_flagged = ($score >= SCORE_FLAG);

// メール本文の組み立て
$body  = "お問い合わせフォームより送信がありました。\n\n";
$body .= "──────────────────────────\n";
foreach ($fields as $key => $label) {
    $value = $data[$key] !== '' ? $data[$key] : '(未入力)';
    $body .= $label . "：\n" . $value . "\n\n";
}
$body .= "──────────────────────────\n";
$body .= "送信日時：" . date('Y-m-d H:i:s') . "\n";
if (!empty($_SERVER['REMOTE_ADDR'])) {
    $body .= "IPアドレス：" . $_SERVER['REMOTE_ADDR'] . "\n";
}
if ($is_flagged) {
    $body .= "\n※ 営業・スパムの可能性があると判定されました(スコア {$score})\n";
    $body .= "   判定理由：" . implode(' / ', $spam_reason) . "\n";
    $body .= "   誤判定の場合もあるため、内容をご確認ください。\n";
}

$subject = ($is_flagged ? FLAG_PREFIX : '') . SUBJECT_PREFIX . $data['type'];

// 差出人・返信先ヘッダ
$from_header = mb_encode_mimeheader('日本総合検定資格センター お問い合わせ') . ' <' . FROM_EMAIL . '>';
$headers  = 'From: ' . $from_header . "\r\n";
$headers .= 'Reply-To: ' . $data['email'] . "\r\n";

// Xserverではエンベロープ送信元(-f)の指定が必須。指定しないと配信されない/
// 迷惑メール扱いになることが多い。SPFに spf.sender.xserver.jp を含めることで
// この送信元でSPF認証が通り、DMARCも成立する(詳細はCLAUDE.md参照)。
$envelope = '-f' . FROM_EMAIL;
$sent = mb_send_mail(TO_EMAIL, $subject, $body, $headers, $envelope);

if ($sent) {
    respond(true, 'お問い合わせを受け付けました。担当者より折り返しご連絡いたします。');
} else {
    respond(false, '送信処理に失敗しました。お手数ですが、時間をおいて再度お試しください。');
}

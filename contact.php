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

// スパム対策:ハニーポット(人間には見えない項目)が埋まっていたら成功を装って破棄
if (!empty($_POST['website'])) {
    respond(true, 'お問い合わせを受け付けました。');
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

$subject = SUBJECT_PREFIX . $data['type'];

// 差出人・返信先ヘッダ
$from_header = mb_encode_mimeheader('日本総合検定資格センター お問い合わせ') . ' <' . FROM_EMAIL . '>';
$headers  = 'From: ' . $from_header . "\r\n";
$headers .= 'Reply-To: ' . $data['email'] . "\r\n";

// Xserverではエンベロープ送信元(-f)の指定が必須。指定しないと配信されない/
// 迷惑メール扱いになることが多い。FROM_EMAIL はサーバー上に実在するアドレスにすること。
$envelope = '-f' . FROM_EMAIL;
$sent = mb_send_mail(TO_EMAIL, $subject, $body, $headers, $envelope);

if ($sent) {
    respond(true, 'お問い合わせを受け付けました。担当者より折り返しご連絡いたします。');
} else {
    respond(false, '送信処理に失敗しました。お手数ですが、時間をおいて再度お試しください。');
}

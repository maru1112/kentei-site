// お問い合わせフォームの送信(contact.php へ非同期POST)
// JavaScript が無効な場合は通常の POST 送信にフォールバックする。
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('contact-form');
    if (!form) return;

    var button = form.querySelector('.form-submit button');
    var alertBox = document.getElementById('form-alert');

    function showAlert(message) {
      if (!alertBox) return;
      alertBox.textContent = message;
      alertBox.style.display = 'block';
      alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideAlert() {
      if (alertBox) alertBox.style.display = 'none';
    }

    function showSuccess(message) {
      var card = form;
      var panel = document.createElement('div');
      panel.className = 'form-success';
      panel.innerHTML =
        '<div class="form-success__mark">✓</div>' +
        '<h3 class="form-success__ttl">送信が完了しました</h3>' +
        '<p class="form-success__txt">' + message + '</p>' +
        '<a class="form-success__link" href="index.html">トップページへ戻る</a>';
      card.parentNode.replaceChild(panel, card);
      panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    form.addEventListener('submit', function (e) {
      // ブラウザ標準の必須チェックを先に走らせる
      if (!form.checkValidity()) {
        return; // ネイティブのバリデーションUIに任せる
      }
      e.preventDefault();
      hideAlert();

      if (button) {
        button.disabled = true;
        button.dataset.label = button.textContent;
        button.textContent = '送信中…';
      }

      fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: new FormData(form)
      })
        .then(function (res) { return res.json(); })
        .then(function (result) {
          if (result && result.ok) {
            showSuccess(result.message || 'お問い合わせを受け付けました。');
          } else {
            var msg = (result && result.errors && result.errors.length)
              ? result.errors.join(' ')
              : (result && result.message) || '送信に失敗しました。時間をおいて再度お試しください。';
            showAlert(msg);
            restoreButton();
          }
        })
        .catch(function () {
          showAlert('送信中にエラーが発生しました。通信環境をご確認のうえ、再度お試しください。');
          restoreButton();
        });

      function restoreButton() {
        if (button) {
          button.disabled = false;
          button.textContent = button.dataset.label || '送信する';
        }
      }
    });
  });
})();

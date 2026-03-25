<?php
/** @var bool $hasPasskeys */
$hasPasskeys = $hasPasskeys ?? false;
?>

<section aria-labelledby="login-title">
    <h1 id="login-title">Anmelden</h1>

    <?php if (!$hasPasskeys): ?>
        <p class="text-muted">Kein Passkey registriert. <a href="/register">Passkey erstellen</a></p>
    <?php else: ?>
        <p class="text-muted">Melde dich mit deinem Passkey an, um Galerien zu verwalten und Bilder hochzuladen.</p>

        <div id="login-container" style="max-width: 400px;">
            <button id="login-btn" class="btn btn--primary">Mit Passkey anmelden</button>
        </div>

        <div id="login-status" class="flash flash--error" role="alert" hidden>
            <span id="login-status-msg"></span>
        </div>

        <noscript>
            <div class="flash flash--error" role="alert">
                <span>JavaScript ist erforderlich, um die WebAuthn-Anmeldung durchzuführen.</span>
            </div>
        </noscript>
    <?php endif; ?>
</section>

<?php if ($hasPasskeys): ?>
<script>
(function() {
    var btn = document.getElementById('login-btn');
    var statusEl = document.getElementById('login-status');
    var statusMsg = document.getElementById('login-status-msg');

    if (!window.PublicKeyCredential) {
        showError('Dein Browser unterstützt keine Passkeys.');
        btn.disabled = true;
        return;
    }

    btn.addEventListener('click', function() {
        statusEl.hidden = true;
        btn.disabled = true;
        btn.textContent = 'Wird angemeldet\u2026';

        fetch('/login/challenge', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) throw new Error(data.error);

            var options = data.options;
            options.challenge = b64ToBuffer(options.challenge);
            if (options.allowCredentials) {
                options.allowCredentials.forEach(function(c) {
                    c.id = b64ToBuffer(c.id);
                });
            }

            return navigator.credentials.get({publicKey: options});
        })
        .then(function(assertion) {
            return fetch('/login/complete', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id: assertion.id,
                    clientDataJSON: bufferToB64(assertion.response.clientDataJSON),
                    authenticatorData: bufferToB64(assertion.response.authenticatorData),
                    signature: bufferToB64(assertion.response.signature)
                })
            });
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) throw new Error(data.error);
            window.location.href = data.redirect || '/';
        })
        .catch(function(err) {
            showError(err.message || 'Anmeldung fehlgeschlagen.');
            btn.disabled = false;
            btn.textContent = 'Mit Passkey anmelden';
        });
    });

    function showError(msg) {
        statusMsg.textContent = msg;
        statusEl.hidden = false;
    }

    function b64ToBuffer(b64) {
        var s = b64.replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(s);
        var buf = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) buf[i] = raw.charCodeAt(i);
        return buf.buffer;
    }

    function bufferToB64(buf) {
        var bytes = new Uint8Array(buf);
        var s = '';
        for (var i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
})();
</script>
<?php endif; ?>

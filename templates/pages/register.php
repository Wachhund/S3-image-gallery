<?php
/** @var string $status — 'ready' | 'disabled' | 'consumed' | 'has_passkey' */
$status = $status ?? 'disabled';
?>

<?php if ($status === 'disabled'): ?>
    <section class="empty-state" aria-labelledby="reg-title">
        <h1 id="reg-title" class="empty-state__title">Registrierung deaktiviert</h1>
        <p class="empty-state__description">
            Kein Einmal-Passwort konfiguriert. Setze <code>S3G_REGISTRATION_OTP</code>
            in der <code>.env</code>-Datei, um die Passkey-Registrierung zu aktivieren.
        </p>
    </section>

<?php elseif ($status === 'consumed'): ?>
    <section class="empty-state" aria-labelledby="reg-title">
        <h1 id="reg-title" class="empty-state__title">Registrierung abgeschlossen</h1>
        <p class="empty-state__description">
            Das Einmal-Passwort wurde bereits verwendet. Ein Passkey ist registriert.
        </p>
        <a href="/login" class="btn btn--primary">Anmelden</a>
    </section>

<?php elseif ($status === 'has_passkey'): ?>
    <section class="empty-state" aria-labelledby="reg-title">
        <h1 id="reg-title" class="empty-state__title">Passkey vorhanden</h1>
        <p class="empty-state__description">
            Es ist bereits ein Passkey registriert.
        </p>
        <a href="/login" class="btn btn--primary">Anmelden</a>
    </section>

<?php else: ?>
    <section aria-labelledby="reg-title">
        <h1 id="reg-title">Passkey registrieren</h1>
        <p class="text-muted">
            Gib das Einmal-Passwort ein, um einen Passkey auf diesem Gerät zu erstellen.
        </p>

        <div id="reg-form-container">
            <form id="reg-form" class="form-group" style="max-width: 400px;">
                <label for="otp" class="form-label">Einmal-Passwort</label>
                <input type="password" id="otp" name="otp" class="form-input"
                       required autocomplete="off" spellcheck="false"
                       placeholder="Einmal-Passwort eingeben">
                <br>
                <button type="submit" class="btn btn--primary" id="reg-submit">
                    Passkey erstellen
                </button>
            </form>
        </div>

        <div id="reg-status" class="flash flash--error" role="alert" hidden>
            <span id="reg-status-msg"></span>
        </div>

        <div id="reg-success" class="flash flash--success" role="alert" hidden>
            <span>Passkey erfolgreich registriert! <a href="/login">Jetzt anmelden</a></span>
        </div>

        <noscript>
            <div class="flash flash--error" role="alert">
                <span>JavaScript ist erforderlich, um einen Passkey zu erstellen.
                Die WebAuthn-API ist nur im Browser mit JavaScript verfügbar.</span>
            </div>
        </noscript>
    </section>

    <script>
    (function() {
        var form = document.getElementById('reg-form');
        var container = document.getElementById('reg-form-container');
        var statusEl = document.getElementById('reg-status');
        var statusMsg = document.getElementById('reg-status-msg');
        var successEl = document.getElementById('reg-success');
        var submitBtn = document.getElementById('reg-submit');

        if (!window.PublicKeyCredential) {
            showError('Dein Browser unterstützt keine Passkeys (WebAuthn). Bitte verwende einen aktuellen Browser.');
            submitBtn.disabled = true;
            return;
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            statusEl.hidden = true;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Wird erstellt\u2026';

            var otp = document.getElementById('otp').value;

            fetch('/register/challenge', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({otp: otp})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) throw new Error(data.error);

                var options = data.options;
                options.challenge = b64ToBuffer(options.challenge);
                options.user.id = b64ToBuffer(options.user.id);
                if (options.excludeCredentials) {
                    options.excludeCredentials.forEach(function(c) {
                        c.id = b64ToBuffer(c.id);
                    });
                }

                return navigator.credentials.create({publicKey: options});
            })
            .then(function(credential) {
                return fetch('/register/complete', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        clientDataJSON: bufferToB64(credential.response.clientDataJSON),
                        attestationObject: bufferToB64(credential.response.attestationObject)
                    })
                });
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) throw new Error(data.error);
                container.hidden = true;
                successEl.hidden = false;
            })
            .catch(function(err) {
                showError(err.message || 'Registrierung fehlgeschlagen.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Passkey erstellen';
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

<?php
function render_signin_card(string $tab = 'patient', array $devErrors = [], array $staffErrors = [], string $staffEmail = ''): void
{
    $googleReady = strpos(GOOGLE_CLIENT_ID, 'PASTE-YOUR') === false;
    $isPatient = $tab !== 'staff';
    ?>
    <div class="signin-card" id="signin" data-tabs>
        <div class="tabs" role="tablist" aria-label="Sign in as">
            <button type="button" role="tab" id="tab-patient" aria-controls="panel-patient"
                    aria-selected="<?= $isPatient ? 'true' : 'false' ?>" data-tab="panel-patient">Patient</button>
            <button type="button" role="tab" id="tab-staff" aria-controls="panel-staff"
                    aria-selected="<?= $isPatient ? 'false' : 'true' ?>" data-tab="panel-staff">Staff</button>
        </div>

        <div role="tabpanel" id="panel-patient" aria-labelledby="tab-patient" <?= $isPatient ? '' : 'hidden' ?>>
            <h2>Sign in to book</h2>
            <p class="muted">Use your Google account. If it's your first time, your patient account is created automatically.</p>

            <?php if ($googleReady): ?>
                <script src="https://accounts.google.com/gsi/client" async></script>
                <div id="g_id_onload" data-client_id="<?= h(GOOGLE_CLIENT_ID) ?>"
                     data-callback="onGoogleCredential" data-auto_prompt="false"></div>
                <div class="google-btn">
                    <div class="g_id_signin" data-type="standard" data-size="large" data-theme="filled_blue"
                         data-text="continue_with" data-shape="rectangular" data-logo_alignment="left" data-width="360"></div>
                </div>
                <form id="googleForm" method="post" action="<?= h(url('auth_google.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="credential" id="credential">
                </form>
                <script>
                    function onGoogleCredential(response) {
                        document.getElementById('credential').value = response.credential;
                        document.getElementById('googleForm').submit();
                    }
                </script>
            <?php else: ?>
                <div class="alert alert-warning">Google sign-in isn't set up yet. Add your Client ID to <code>config.php</code> (see README).</div>
            <?php endif; ?>

            <ul class="perks">
                <li>No new password to remember</li>
                <li>Book for yourself and your family</li>
            </ul>

            <?php if (dev_login_allowed()): ?>
                <div class="dev-box">
                    <h3>Test login <span class="badge badge-env">Localhost only</span></h3>
                    <form method="post" action="<?= h(url('login.php')) ?>" data-validate-form novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="dev">
                        <div class="field">
                            <label for="dev_email">Any email</label>
                            <input type="email" id="dev_email" name="email" value="<?= h(input('email')) ?>" maxlength="190"
                                   data-validate="required|email" class="<?= invalid_class($devErrors, 'email') ?>" placeholder="juan@gmail.com">
                            <?= field_error($devErrors, 'email') ?>
                        </div>
                        <button class="btn btn-ghost btn-block" type="submit">Continue as test patient</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div role="tabpanel" id="panel-staff" aria-labelledby="tab-staff" <?= $isPatient ? 'hidden' : '' ?>>
            <h2>Staff sign-in</h2>
            <p class="muted">For Barangay 509 Health Center staff and doctors.</p>
            <?php if (!empty($staffErrors['form'])): ?><div class="alert alert-error"><?= h($staffErrors['form']) ?></div><?php endif; ?>
            <form method="post" action="<?= h(url('staff_login.php')) ?>" data-validate-form novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="staff_email">Email</label>
                    <input type="email" id="staff_email" name="email" value="<?= h($staffEmail) ?>" maxlength="190" autocomplete="username"
                           data-validate="required|email" class="<?= invalid_class($staffErrors, 'email') ?>">
                    <?= field_error($staffErrors, 'email') ?>
                </div>
                <div class="field">
                    <label for="staff_password">Password</label>
                    <div class="input-row">
                        <input type="password" id="staff_password" name="password" autocomplete="current-password"
                               data-validate="required" class="<?= invalid_class($staffErrors, 'password') ?>">
                        <button type="button" class="btn btn-ghost" data-show-password="staff_password" aria-label="Show password">Show</button>
                    </div>
                    <?= field_error($staffErrors, 'password') ?>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in</button>
            </form>
            <p class="small muted center" style="margin: 16px 0 0">Forgot your password? Ask a health center administrator to reset it.</p>
        </div>
    </div>
    <?php
}

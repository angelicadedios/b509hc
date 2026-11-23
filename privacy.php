<?php
require __DIR__ . '/includes/bootstrap.php';

$updated = 'November 23, 2026';

$address = defined('CLINIC_ADDRESS') && CLINIC_ADDRESS !== '' ? CLINIC_ADDRESS : 'Barangay 509, Sampaloc, Manila';
$email   = defined('CLINIC_EMAIL') ? CLINIC_EMAIL : '';
$phone   = defined('CLINIC_PHONE') ? CLINIC_PHONE : '';

$pageTitle = 'Privacy Policy';
require __DIR__ . '/includes/header.php';
?>
<article class="card legal">
    <h1>Privacy Policy</h1>
    <p class="muted">Last updated: <?= h($updated) ?></p>

    <p>This policy explains what information the <?= h(APP_NAME) ?> appointment system collects, why we collect it,
        who can see it, and your rights under the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

    <h2>Who we are</h2>
    <p><?= h(APP_NAME) ?>, <?= h($address) ?>. This website lets residents book consultations and result readings
        at the health center.</p>

    <h2>Information we collect</h2>
    <h3>From your Google account</h3>
    <p>When you sign in with Google, we receive only your <strong>name</strong>, <strong>email address</strong> and
        a <strong>Google account ID</strong>. We do not receive your Google password, contacts, files, or any other
        Google data.</p>
    <h3>Information you give us</h3>
    <ul>
        <li><strong>Patient details</strong> for you and the family members you book for: full name, sex, birth date,
            mobile number and address, and their relationship to you.</li>
        <li><strong>PhilHealth / YAKAP details:</strong> your PhilHealth / YAKAP number and a picture of your PhilHealth ID,
            and the PhilHealth number of a dependent if you give it.</li>
        <li><strong>Booking details:</strong> visit type, service, date and time slot, priority (Senior Citizen, PWD or
            Pregnant) and any notes you write.</li>
        <li><strong>Files you upload</strong>: your PhilHealth ID (required to register), and optionally lab results,
            prescriptions, and Senior Citizen or PWD ID pictures.</li>
    </ul>
    <h3>Information created by the system</h3>
    <p>Queue numbers, appointment status (upcoming, completed, missed, cancelled), cancellation reasons, your account
        status (waiting for approval, verified or deactivated), notifications and emails sent to you, and a record of the
        <strong>medicines given</strong> to each patient (medicine, quantity, prescribing doctor, the staff member who gave it,
        and the date and time).</p>

    <h2>Why we use it</h2>
    <ul>
        <li>To check your PhilHealth / YAKAP details and that you live in the barangay before your account can book.</li>
        <li>To email you when your account is approved.</li>
        <li>To schedule your appointment and give you a queue number.</li>
        <li>To let health center staff verify the patient when you arrive.</li>
        <li>To tell you when the health center needs to cancel or move your appointment.</li>
        <li>To keep a record of visits and of medicines given, and to manage the health center's medicine stock.</li>
    </ul>
    <p>We do not use your information for advertising, and we do not sell or rent it to anyone.</p>

    <h2>Who can see your information</h2>
    <ul>
        <li><strong>You</strong> can see your own bookings and the bookings you made for your family.</li>
        <li><strong>Authorized health center staff</strong> can see patient and booking details to run the clinic.</li>
        <li>Our service providers process the data only to run this website and do not use it for anything else:
            <strong>Vercel</strong> (runs the website), <strong>TiDB Cloud by PingCAP</strong> (stores the database and uploaded
            files), and <strong>Google</strong> (sign-in, and Gmail to send approval emails). Their servers may be outside the
            Philippines, in which case we still protect your data as this policy and the Data Privacy Act require.</li>
    </ul>
    <p>We only share information with others when the law requires it.</p>

    <h2>How we protect it</h2>
    <p>Staff need a password to sign in, and only signed-in users can see their own records. Uploaded files can only
        be opened by the patient who uploaded them and by staff. The site uses an encrypted (https) connection, and the
        connection between the website and the database is encrypted too. Staff can deactivate an account that gives
        false details; its records are kept but it can no longer sign in.</p>

    <h2>How long we keep it</h2>
    <p>We keep appointment and medicine records for as long as the health center needs them for its records and as
        allowed by law. You can ask us to delete your account and the records you created, unless the health center is required
        to keep them.</p>

    <h2>Your rights</h2>
    <p>Under the Data Privacy Act, you have the right to:</p>
    <ul>
        <li>be informed about how your personal data is used;</li>
        <li>access your personal data;</li>
        <li>object to its processing;</li>
        <li>have it corrected if it is wrong;</li>
        <li>have it erased or blocked;</li>
        <li>get a copy of it (data portability);</li>
        <li>claim damages; and</li>
        <li>file a complaint with the National Privacy Commission (privacy.gov.ph).</li>
    </ul>
    <p>You can update your own details anytime in <strong>Profile &amp; dependents</strong>. For other requests,
        contact us below.</p>

    <h2>Google user data</h2>
    <p>Our use of information received from Google APIs adheres to the
        <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>,
        including the Limited Use requirements. We use your Google name and email only to create and identify your
        patient account.</p>

    <h2>Contact us</h2>
    <p><?= h(APP_NAME) ?><br>
        <?= h($address) ?>
        <?php if ($email !== ''): ?><br>Email: <?= h($email) ?><?php endif; ?>
        <?php if ($phone !== ''): ?><br>Phone: <?= h($phone) ?><?php endif; ?>
        <?php if ($email === '' && $phone === ''): ?><br>You can also ask at the health center during clinic hours.<?php endif; ?>
    </p>

    <h2>Changes to this policy</h2>
    <p>If we change this policy, we will update the date at the top of this page.</p>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>	
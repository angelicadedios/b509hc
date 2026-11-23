<?php
require __DIR__ . '/includes/bootstrap.php';

$u = current_user();
if ($u) redirect($u['role'] === 'staff' ? 'staff/index.php' : 'patient/bookings.php');

$open  = date('g A', strtotime(CLINIC_OPEN));
$close = date('g A', strtotime(CLINIC_CLOSE));
$sampleDate = date('D, M j', strtotime('next tuesday'));

$pageTitle = 'Book an appointment';
$mainClass = '';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/signin_card.php';
?>

<section class="hero-band">
    <div class="container hero">
        <?php render_signin_card('patient'); ?>

        <div class="hero-message">
            <span class="place-chip"><i></i>Sampaloc, Manila</span>
            <h1>Get your queue number before you leave home.</h1>
            <p class="lead">Book a consultation or result reading online, then come in at your time slot. No more lining up early in the morning.</p>

            <figure class="hero-figure" aria-label="Example of a queue ticket">
                <div class="ticket">
                    <div class="ticket-stub">
                        <span>Queue no.</span>
                        <strong>05</strong>
                        <small>Ref. <?= h(date('ymd', strtotime('next tuesday'))) ?>-05</small>
                    </div>
                    <div class="ticket-body">
                        <div class="ticket-when"><?= h($sampleDate) ?></div>
                        <div class="ticket-time">8:40 AM – 8:50 AM</div>
                        <dl class="summary-list compact">
                            <dt>Patient</dt><dd>Maria Santos</dd>
                            <dt>Service</dt><dd>Consultation</dd>
                            <dt>Priority</dt><dd><span class="chip chip-senior">Senior Citizen</span></dd>
                        </dl>
                    </div>
                </div>
                <figcaption>You get a ticket like this right after booking.</figcaption>
            </figure>
        </div>
    </div>
</section>

<section class="container facts" id="hours">
    <div class="fact">
        <span class="fact-icon fi-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
        <div><h3>Mon to Sat, <?= h($open) ?> to <?= h($close) ?></h3><p>Closed on Sundays and holidays.</p></div>
    </div>
    <div class="fact">
        <span class="fact-icon fi-yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="7" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M17 11l2 2 4-4"/></svg></span>
        <div><h3>Walk-ins are still served</h3><p>Every other 10-minute slot is kept for walk-in patients.</p></div>
    </div>
    <div class="fact">
        <span class="fact-icon fi-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.6-1.3 1.7-2 3-2s2.4.7 3 2M15 10h3M15 14h3"/></svg></span>
        <div><h3>Bring a valid ID</h3><p>Plus your Senior or PWD ID, lab results and prescriptions.</p></div>
    </div>
</section>

<section class="container how" id="how">
    <h2>How booking works</h2>
    <p class="muted">Register once, then book in about three minutes.</p>
    <ol class="how-steps">
        <li><span>1</span><strong>Sign in and register</strong><p>With your Google account. Add your PhilHealth / YAKAP number and ID. We email you once staff approve it.</p></li>
        <li><span>2</span><strong>Choose the patient</strong><p>Yourself or a family member. Saved details are filled in for you.</p></li>
        <li><span>3</span><strong>Pick a date and time</strong><p>Only open slots are shown, Monday to Saturday.</p></li>
        <li class="last"><span>4</span><strong>Get your queue number</strong><p>Show it at the health center on your visit.</p></li>
    </ol>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

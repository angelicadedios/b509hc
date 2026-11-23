<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/patient_fields.php';
$user = require_role('patient');
$uid = (int)$user['id'];
if (!isset($_GET['done'])) require_verified_patient($user);

if (isset($_GET['done'])) {
    $a = q('SELECT a.*, p.first_name, p.middle_name, p.last_name, p.suffix
            FROM appointments a JOIN patients p ON p.id = a.patient_id
            WHERE a.id = ? AND a.user_id = ?', [(int)$_GET['done'], $uid])->fetch();
    if (!$a) redirect('patient/bookings.php');
    $pageTitle = 'Appointment booked';
    $activeNav = 'book';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="card summary-card">
        <span class="confirm-mark">&#10003;</span>
        <h1>Your appointment is booked</h1>
        <p class="muted">Show this queue number at the health center. Please arrive 10 minutes before your time slot.</p>

        <div class="ticket">
            <div class="ticket-stub">
                <span>Queue no.</span>
                <strong><?= h(short_queue($a['queue_no'])) ?></strong>
                <small>Ref. <?= h($a['queue_no']) ?></small>
            </div>
            <div class="ticket-body">
                <div class="ticket-when"><?= h(date('l, F j, Y', strtotime($a['appt_date']))) ?></div>
                <div class="ticket-time"><?= h(slot_label($a['slot_time'])) ?></div>
                <dl class="summary-list compact">
                    <dt>Patient</dt><dd><?= h(full_name($a)) ?></dd>
                    <dt>Service</dt><dd><?= h($a['service']) ?>, <?= h(strtolower($a['visit_type'])) ?></dd>
                    <dt>Priority</dt><dd><?= priority_chip($a['priority']) ?></dd>
                </dl>
            </div>
        </div>

        <p class="note">Bring a valid ID<?= in_array($a['priority'], ['Senior Citizen', 'PWD'], true) ? ', your ' . h($a['priority']) . ' ID,' : '' ?> and any lab results or prescriptions.</p>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" onclick="window.print()">Print ticket</button>
            <a class="btn btn-primary" href="<?= h(url('patient/bookings.php')) ?>">Go to my bookings</a>
        </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$self = self_record($uid);
$dependents = dependents_of($uid);

$errors = [];
$bookedFor = 'self';
$dependentId = '';
$patientValues = $self ?? [];
$booking = ['visit_type' => '', 'service' => '', 'appt_date' => '', 'slot_time' => '', 'priority' => 'Regular', 'notes' => ''];
$startStep = 1;

if (is_post()) {
    verify_csrf();

    $bookedFor = input('booked_for');
    $dependentId = input('dependent_id');
    $existingDep = null;

    if (!in_array($bookedFor, ['self', 'dependent'], true)) {
        $errors['booked_for'] = 'Please choose who this booking is for.';
        $bookedFor = 'self';
    }
    if ($bookedFor === 'dependent') {
        if ($dependentId === '') {
            $errors['dependent_id'] = 'Please choose a patient or add a new one.';
        } elseif ($dependentId !== 'new') {
            $existingDep = q('SELECT * FROM patients WHERE id = ? AND user_id = ? AND is_self = 0 AND is_deleted = 0',
                [(int)$dependentId, $uid])->fetch();
            if (!$existingDep) $errors['dependent_id'] = 'Patient not found.';
        }
    }

    [$pData, $pErrors] = validate_patient($_POST, $bookedFor === 'dependent');
    $pData['philhealth_no'] = '';
    if ($bookedFor === 'dependent') {
        [$pData['philhealth_no'], $phErr] = validate_philhealth(input('philhealth_no'), false, (int)($existingDep['id'] ?? 0));
        if ($phErr) $pErrors['philhealth_no'] = $phErr;
    }
    [$bData, $bErrors] = validate_booking($_POST, $pData);
    $errors += $pErrors + $bErrors;
    $patientValues = $pData;
    $booking = $bData;

    $resultFile = $idFile = null;
    if (!$errors) {
        $resultFile = save_upload('result_file', ['jpg', 'png', 'pdf'], $errors);
        if (in_array($bData['priority'], ['Senior Citizen', 'PWD'], true)) {
            $idFile = save_upload('id_file', ['jpg', 'png'], $errors);
        }
        if ($errors) { delete_upload($resultFile); delete_upload($idFile); }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $cols = [$pData['first_name'], $pData['middle_name'] ?: null, $pData['last_name'], $pData['suffix'] ?: null,
                     $pData['sex'], $pData['birth_date'], $pData['mobile'], $pData['region'], $pData['province'],
                     $pData['municipality'], $pData['barangay'], $pData['district'] ?: null, $pData['zone'] ?: null, $pData['street']];
            $set = 'first_name=?, middle_name=?, last_name=?, suffix=?, sex=?, birth_date=?, mobile=?,
                    region=?, province=?, municipality=?, barangay=?, district=?, zone=?, street=?';

            if ($bookedFor === 'self') {
                if ($self) {
                    q("UPDATE patients SET $set WHERE id = ?", array_merge($cols, [$self['id']]));
                    $patientId = (int)$self['id'];
                } else {
                    q("INSERT INTO patients SET $set, user_id = ?, is_self = 1", array_merge($cols, [$uid]));
                    $patientId = (int)$pdo->lastInsertId();
                }
            } else {
                if ($existingDep) {
                    q("UPDATE patients SET $set, relationship = ?, philhealth_no = ? WHERE id = ?",
                        array_merge($cols, [$pData['relationship'], $pData['philhealth_no'] ?: null, $existingDep['id']]));
                    $patientId = (int)$existingDep['id'];
                } else {
                    q("INSERT INTO patients SET $set, relationship = ?, philhealth_no = ?, user_id = ?, dep_seq = ?, is_self = 0",
                        array_merge($cols, [$pData['relationship'], $pData['philhealth_no'] ?: null, $uid, next_dep_seq($uid)]));
                    $patientId = (int)$pdo->lastInsertId();
                }
            }

            $apptId = create_appointment([
                'user_id' => $uid, 'patient_id' => $patientId, 'booked_for' => $bookedFor,
                'visit_type' => $bData['visit_type'], 'service' => $bData['service'],
                'appt_date' => $bData['appt_date'], 'slot_time' => $bData['slot_time'],
                'priority' => $bData['priority'], 'notes' => $bData['notes'],
                'result_file' => $resultFile, 'id_file' => $idFile,
            ]);
            $pdo->commit();
            redirect('patient/book.php?done=' . $apptId);
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            delete_upload($resultFile);
            delete_upload($idFile);
            $errors['slot_time'] = $e->getMessage();
        }
    }

    $step1 = ['booked_for', 'dependent_id'];
    $step2 = ['relationship', 'philhealth_no', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'birth_date', 'mobile',
              'region', 'province', 'municipality', 'barangay', 'district', 'zone', 'street'];
    if (array_intersect(array_keys($errors), $step1)) $startStep = 1;
    elseif (array_intersect(array_keys($errors), $step2)) $startStep = 2;
    else $startStep = 3;
    flash('error', 'Please check the highlighted fields. If you attached files, please attach them again.');
}

$prefill = ['self' => $self ? array_intersect_key($self, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'birth_date', 'mobile', 'region', 'province', 'municipality', 'barangay', 'district', 'zone', 'street'])) : null, 'deps' => []];
foreach ($dependents as $d) {
    $prefill['deps'][$d['id']] = array_intersect_key($d, array_flip(['relationship', 'philhealth_no', 'first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'birth_date', 'mobile', 'region', 'province', 'municipality', 'barangay', 'district', 'zone', 'street']));
}

$pageTitle = 'Book Appointment';
$activeNav = 'book';
$pageScripts = ['address.js', 'calendar.js', 'book.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Book an appointment</h1>
    <p>Open Monday to Saturday. Fields marked <span class="req">*</span> are required.</p>
</div>

<ol class="stepper" id="stepper">
    <li data-step="1">For whom</li>
    <li data-step="2">Patient info</li>
    <li data-step="3">Booking details</li>
    <li data-step="4">Confirm</li>
</ol>

<form method="post" enctype="multipart/form-data" id="bookForm" class="card" novalidate data-start-step="<?= $startStep ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_MB * 1024 * 1024 ?>">
    <script type="application/json" id="prefillData"><?= json_encode($prefill, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

    <section class="step" data-step="1">
        <h2>Who is this appointment for?</h2>
        <div class="choice-grid">
            <label class="choice">
                <input type="radio" name="booked_for" value="self" <?= $bookedFor === 'self' ? 'checked' : '' ?>>
                <span><strong>Myself</strong><small>Uses the details in your profile.</small></span>
            </label>
            <label class="choice">
                <input type="radio" name="booked_for" value="dependent" <?= $bookedFor === 'dependent' ? 'checked' : '' ?>>
                <span><strong>Someone else</strong><small>A family member or another patient. They're saved to your dependents.</small></span>
            </label>
        </div>
        <?= field_error($errors, 'booked_for') ?>

        <div class="field" id="dependentPicker" <?= $bookedFor === 'dependent' ? '' : 'hidden' ?>>
            <label for="dependent_id">Choose patient <span class="req">*</span></label>
            <select id="dependent_id" name="dependent_id" data-validate="required" class="<?= invalid_class($errors, 'dependent_id') ?>">
                <option value="">-- Select --</option>
                <?php foreach ($dependents as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $dependentId === (string)$d['id'] ? 'selected' : '' ?>>
                        <?= h(full_name($d)) ?> (<?= h($d['relationship']) ?>)
                    </option>
                <?php endforeach; ?>
                <option value="new" <?= $dependentId === 'new' ? 'selected' : '' ?>>+ Add new dependent / other patient</option>
            </select>
            <?= field_error($errors, 'dependent_id') ?>
            <?php if ($dependents): ?><small class="hint">Saved patients are prefilled. You can still update their info.</small><?php endif; ?>
        </div>

        <div class="form-actions">
            <span></span>
            <button type="button" class="btn btn-primary" data-next>Continue</button>
        </div>
    </section>

    <section class="step" data-step="2" hidden>
        <h2>Patient information</h2>
        <?php render_patient_fields($patientValues, $errors, true, $user['email'], 'optional'); ?>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-prev>Back</button>
            <button type="button" class="btn btn-primary" data-next>Continue</button>
        </div>
    </section>

    <section class="step" data-step="3" hidden>
        <h2>Booking details</h2>
        <div class="grid grid-2">
            <div class="field">
                <label for="visit_type">Visit type <span class="req">*</span></label>
                <select id="visit_type" name="visit_type" data-validate="required" class="<?= invalid_class($errors, 'visit_type') ?>">
                    <?= select_options(VISIT_TYPES, $booking['visit_type']) ?>
                </select>
                <?= field_error($errors, 'visit_type') ?>
            </div>
            <div class="field">
                <label for="service">Service <span class="req">*</span></label>
                <select id="service" name="service" data-validate="required" class="<?= invalid_class($errors, 'service') ?>">
                    <?= select_options(SERVICES, $booking['service']) ?>
                </select>
                <?= field_error($errors, 'service') ?>
            </div>
        </div>

        <div class="date-slot">
            <div class="field">
                <label>Date <span class="req">*</span> <span class="opt">(Sundays closed)</span></label>
                <div class="datepicker" data-input="appt_date" data-min="<?= first_booking_date() ?>" data-max="<?= last_booking_date() ?>"></div>
                <input type="hidden" id="appt_date" name="appt_date" value="<?= h($booking['appt_date']) ?>" data-validate="required|bookdate">
                <?= field_error($errors, 'appt_date') ?>
            </div>
            <div class="field">
                <label for="slot_time">Time slot <span class="req">*</span></label>
                <select id="slot_time" name="slot_time" data-slots-for="appt_date" data-selected="<?= h($booking['slot_time']) ?>"
                        data-validate="required" class="<?= invalid_class($errors, 'slot_time') ?>">
                    <option value="">-- Pick a date first --</option>
                </select>
                <?= field_error($errors, 'slot_time') ?>
                <small class="hint">10-minute slots. The times in between are kept for walk-in patients.</small>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="field">
                <label for="priority">Priority</label>
                <select id="priority" name="priority" data-chips class="<?= invalid_class($errors, 'priority') ?>">
                    <?= select_options(PRIORITIES, $booking['priority'], false) ?>
                </select>
                <?= field_error($errors, 'priority') ?>
            </div>
            <div class="field">
                <label for="mobileMirror">Mobile number <span class="opt">(from patient info)</span></label>
                <input type="text" id="mobileMirror" readonly class="readonly" value="<?= h($patientValues['mobile'] ?? '') ?>">
            </div>
        </div>

        <div class="grid grid-2">
            <div class="field">
                <label for="result_file">Results / prescription / other documents <span class="opt">(optional)</span></label>
                <input type="file" id="result_file" name="result_file" accept=".jpg,.jpeg,.png,.pdf" data-compress
                       data-validate="file:jpg,jpeg,png,pdf|maxmb:<?= MAX_UPLOAD_MB ?>" class="<?= invalid_class($errors, 'result_file') ?>">
                <?= field_error($errors, 'result_file') ?>
                <small class="hint">JPG, PNG or PDF, max <?= MAX_UPLOAD_MB ?> MB. Please still bring the original on your visit.</small>
            </div>
            <div class="field" id="idUploadWrap" hidden>
                <label for="id_file">Senior Citizen / PWD ID picture <span class="opt">(optional)</span></label>
                <input type="file" id="id_file" name="id_file" accept=".jpg,.jpeg,.png" data-compress
                       data-validate="file:jpg,jpeg,png|maxmb:<?= MAX_UPLOAD_MB ?>" class="<?= invalid_class($errors, 'id_file') ?>">
                <?= field_error($errors, 'id_file') ?>
                <small class="hint">JPG or PNG, max <?= MAX_UPLOAD_MB ?> MB. Please still bring your ID on your visit.</small>
            </div>
        </div>

        <div class="field">
            <label for="notes">Notes <span class="opt">(optional)</span></label>
            <textarea id="notes" name="notes" rows="3" maxlength="500" data-validate="max:500" data-counter
                      placeholder="Symptoms, concerns, or anything the doctor should know" class="<?= invalid_class($errors, 'notes') ?>"><?= h($booking['notes']) ?></textarea>
            <?= field_error($errors, 'notes') ?>
        </div>

        <div class="selection-bar">
            <div class="sel-text"><small>Your selection</small><strong id="selSummary">Pick a date and time slot</strong></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="button" class="btn btn-ghost" data-prev>Back</button>
                <button type="button" class="btn btn-sun" data-next>Review booking</button>
            </div>
        </div>
    </section>

    <section class="step" data-step="4" hidden>
        <h2>Review and confirm</h2>
        <p class="muted">Check the details below. You can't edit a booking after it's confirmed, only cancel it.</p>
        <div class="review" id="reviewBox"></div>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-prev>Back</button>
            <button type="button" class="btn btn-primary" data-open="confirmModal">Confirm booking</button>
        </div>
    </section>
</form>

<div class="modal" id="confirmModal" hidden>
    <div class="modal-box">
        <h3>Confirm this booking?</h3>
        <p class="muted">The patient and booking details can't be changed after this. If something is wrong later, you'll need to cancel and book again.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close>Go back</button>
            <button type="button" class="btn btn-primary" id="finalSubmit">Confirm booking</button>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

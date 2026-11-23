<?php
/**
 * $philhealth: 'register' = account holder registration (number + ID picture required),
 *              'locked'   = account holder already verified (shown read-only),
 *              'optional' = dependent (number optional),
 *              ''         = not shown.
 */
function render_patient_fields(array $v, array $e, bool $isDependent, string $email = '', string $philhealth = ''): void
{
    $val = fn($k) => h($v[$k] ?? '');
    ?>
    <?php if ($isDependent): ?>
    <div class="grid grid-3" id="relationshipRow">
        <div class="field">
            <label for="relationship">Relationship to account holder <span class="req">*</span></label>
            <select id="relationship" name="relationship" data-validate="required" class="<?= invalid_class($e, 'relationship') ?>">
                <?= select_options(RELATIONSHIPS, (string)($v['relationship'] ?? '')) ?>
            </select>
            <?= field_error($e, 'relationship') ?>
        </div>
        <?php if ($philhealth === 'optional'): ?>
        <div class="field span-2">
            <label for="philhealth_no">PhilHealth / YAKAP number <span class="opt">(optional)</span></label>
            <input type="text" id="philhealth_no" name="philhealth_no" maxlength="14" placeholder="00-000000000-0" inputmode="numeric"
                   value="<?= $val('philhealth_no') ?>" data-validate="philhealth" data-philhealth class="<?= invalid_class($e, 'philhealth_no') ?>">
            <?= field_error($e, 'philhealth_no') ?>
            <small class="hint">Leave blank if they don't have one (e.g. minors). They'll use your number + .D<?= isset($v['dep_seq']) && $v['dep_seq'] ? (int)$v['dep_seq'] : '#' ?>.</small>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($philhealth === 'register'): ?>
    <h3 class="subhead">PhilHealth / YAKAP</h3>
    <div class="grid grid-2">
        <div class="field">
            <label for="philhealth_no">PhilHealth / YAKAP number <span class="req">*</span></label>
            <input type="text" id="philhealth_no" name="philhealth_no" maxlength="14" placeholder="00-000000000-0" inputmode="numeric"
                   value="<?= $val('philhealth_no') ?>" data-validate="required|philhealth" data-philhealth class="<?= invalid_class($e, 'philhealth_no') ?>">
            <?= field_error($e, 'philhealth_no') ?>
            <small class="hint">The 12-digit number on your PhilHealth ID.</small>
        </div>
        <div class="field">
            <label for="philhealth_file">Picture of your PhilHealth ID <?= empty($v['philhealth_file']) ? '<span class="req">*</span>' : '<span class="opt">(upload again only to replace)</span>' ?></label>
            <input type="file" id="philhealth_file" name="philhealth_file" accept=".jpg,.jpeg,.png,.pdf" data-compress
                   data-validate="<?= empty($v['philhealth_file']) ? 'required|' : '' ?>file:jpg,jpeg,png,pdf|maxmb:<?= MAX_UPLOAD_MB ?>" class="<?= invalid_class($e, 'philhealth_file') ?>">
            <?= field_error($e, 'philhealth_file') ?>
            <?php if (!empty($v['philhealth_file'])): ?>
                <small class="hint">Uploaded: <a target="_blank" href="<?= h(url('file.php?f=' . $v['philhealth_file'])) ?>">view your PhilHealth ID</a></small>
            <?php else: ?>
                <small class="hint">JPG, PNG or PDF. Staff use this to verify your account.</small>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($philhealth === 'locked'): ?>
    <h3 class="subhead">PhilHealth / YAKAP</h3>
    <div class="grid grid-2">
        <div class="field">
            <label>PhilHealth / YAKAP number</label>
            <input type="text" value="<?= $val('philhealth_no') ?>" readonly class="readonly">
            <small class="hint">Verified by the health center. To change it, please ask the staff.</small>
        </div>
        <?php if (!empty($v['philhealth_file'])): ?>
        <div class="field">
            <label>PhilHealth ID</label>
            <p style="margin:10px 0 0"><a target="_blank" href="<?= h(url('file.php?f=' . $v['philhealth_file'])) ?>">View uploaded ID</a></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <h3 class="subhead">Name</h3>
    <div class="grid grid-4">
        <div class="field">
            <label for="first_name">First name <span class="req">*</span></label>
            <input type="text" id="first_name" name="first_name" maxlength="50" value="<?= $val('first_name') ?>"
                   data-validate="required|name" class="<?= invalid_class($e, 'first_name') ?>" autocomplete="given-name">
            <?= field_error($e, 'first_name') ?>
        </div>
        <div class="field">
            <label for="middle_name">Middle name <span class="opt">(optional)</span></label>
            <input type="text" id="middle_name" name="middle_name" maxlength="50" value="<?= $val('middle_name') ?>"
                   data-validate="name" class="<?= invalid_class($e, 'middle_name') ?>" autocomplete="additional-name">
            <?= field_error($e, 'middle_name') ?>
        </div>
        <div class="field">
            <label for="last_name">Last name <span class="req">*</span></label>
            <input type="text" id="last_name" name="last_name" maxlength="50" value="<?= $val('last_name') ?>"
                   data-validate="required|name" class="<?= invalid_class($e, 'last_name') ?>" autocomplete="family-name">
            <?= field_error($e, 'last_name') ?>
        </div>
        <div class="field">
            <label for="suffix">Suffix <span class="opt">(optional)</span></label>
            <select id="suffix" name="suffix" class="<?= invalid_class($e, 'suffix') ?>">
                <option value="">None</option>
                <?= select_options(SUFFIXES, (string)($v['suffix'] ?? ''), false) ?>
            </select>
            <?= field_error($e, 'suffix') ?>
        </div>
    </div>

    <div class="grid grid-4">
        <div class="field">
            <label for="sex">Sex <span class="req">*</span></label>
            <select id="sex" name="sex" data-validate="required" class="<?= invalid_class($e, 'sex') ?>">
                <?= select_options(SEXES, (string)($v['sex'] ?? '')) ?>
            </select>
            <?= field_error($e, 'sex') ?>
        </div>
        <div class="field">
            <label for="birth_date">Birth date <span class="req">*</span></label>
            <input type="date" id="birth_date" name="birth_date" value="<?= $val('birth_date') ?>"
                   max="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d', strtotime('-120 years')) ?>"
                   data-validate="required|birthdate" class="<?= invalid_class($e, 'birth_date') ?>">
            <?= field_error($e, 'birth_date') ?>
        </div>
        <div class="field">
            <label for="mobile">Mobile number <span class="req">*</span></label>
            <input type="tel" id="mobile" name="mobile" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX"
                   value="<?= $val('mobile') ?>" data-validate="required|mobile" class="<?= invalid_class($e, 'mobile') ?>">
            <?= field_error($e, 'mobile') ?>
        </div>
        <?php if ($email !== ''): ?>
        <div class="field">
            <label>Email <span class="opt">(from Google login)</span></label>
            <input type="email" value="<?= h($email) ?>" readonly class="readonly">
        </div>
        <?php endif; ?>
    </div>

    <h3 class="subhead">Address</h3>
    <div class="address-group"
         data-region="<?= $val('region') ?>" data-province="<?= $val('province') ?>"
         data-municipality="<?= $val('municipality') ?>" data-barangay="<?= $val('barangay') ?>">
        <div class="grid grid-4">
            <div class="field">
                <label>Region <span class="req">*</span></label>
                <select name="region" data-addr="region" data-validate="required" class="<?= invalid_class($e, 'region') ?>">
                    <option value="<?= $val('region') ?>"><?= ($v['region'] ?? '') !== '' ? $val('region') : 'Loading...' ?></option>
                </select>
                <?= field_error($e, 'region') ?>
            </div>
            <div class="field">
                <label>Province <span class="req">*</span></label>
                <select name="province" data-addr="province" data-validate="required" class="<?= invalid_class($e, 'province') ?>">
                    <option value="<?= $val('province') ?>"><?= ($v['province'] ?? '') !== '' ? $val('province') : '-- Select region first --' ?></option>
                </select>
                <?= field_error($e, 'province') ?>
            </div>
            <div class="field">
                <label>City / Municipality <span class="req">*</span></label>
                <select name="municipality" data-addr="municipality" data-validate="required" class="<?= invalid_class($e, 'municipality') ?>">
                    <option value="<?= $val('municipality') ?>"><?= ($v['municipality'] ?? '') !== '' ? $val('municipality') : '-- Select province first --' ?></option>
                </select>
                <?= field_error($e, 'municipality') ?>
            </div>
            <div class="field">
                <label>Barangay <span class="req">*</span></label>
                <select name="barangay" data-addr="barangay" data-validate="required" class="<?= invalid_class($e, 'barangay') ?>">
                    <option value="<?= $val('barangay') ?>"><?= ($v['barangay'] ?? '') !== '' ? $val('barangay') : '-- Select city first --' ?></option>
                </select>
                <?= field_error($e, 'barangay') ?>
            </div>
        </div>
    </div>
    <div class="grid grid-4">
        <div class="field">
            <label for="district">District <span class="opt">(optional)</span></label>
            <input type="text" id="district" name="district" maxlength="60" placeholder="e.g. Sampaloc"
                   value="<?= $val('district') ?>" data-validate="max:60" class="<?= invalid_class($e, 'district') ?>">
            <?= field_error($e, 'district') ?>
        </div>
        <div class="field">
            <label for="zone">Zone <span class="opt">(optional)</span></label>
            <input type="text" id="zone" name="zone" maxlength="20" placeholder="e.g. 50"
                   value="<?= $val('zone') ?>" data-validate="zone" class="<?= invalid_class($e, 'zone') ?>">
            <?= field_error($e, 'zone') ?>
        </div>
        <div class="field span-2">
            <label for="street">House number &amp; street name <span class="req">*</span></label>
            <input type="text" id="street" name="street" maxlength="150" placeholder="e.g. 1234 Dapitan St."
                   value="<?= $val('street') ?>" data-validate="required|min:3|max:150" class="<?= invalid_class($e, 'street') ?>">
            <?= field_error($e, 'street') ?>
        </div>
    </div>
    <?php
}

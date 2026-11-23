<?php
const SUFFIXES       = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
const SEXES          = ['Male', 'Female'];
const RELATIONSHIPS  = ['Child', 'Parent', 'Spouse', 'Sibling', 'Grandparent', 'Grandchild', 'Relative', 'Other'];
const VISIT_TYPES    = ['First Time', 'Follow-up'];
const SERVICES       = ['Consultation', 'Result Reading'];
const PRIORITIES     = ['Regular', 'Senior Citizen', 'PWD', 'Pregnant'];
const CANCEL_REASONS = [
    'Schedule conflict',
    'Feeling better / no longer needed',
    'Emergency',
    'Transportation problem',
    'Booked the wrong date or service',
    'Other personal reason',
];

const NAME_PATTERN   = "/^[\\p{L} .'\\-]{2,50}$/u";
const MOBILE_PATTERN = '/^09\d{9}$/';

function password_error(string $pw): ?string
{
    if (strlen($pw) < 8 || strlen($pw) > 72 || !preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'At least 8 characters with an uppercase letter, a lowercase letter and a number.';
    }
    return null;
}

function valid_date_string(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

function booking_date_error(string $d): ?string
{
    if ($d === '') return 'Please choose a date.';
    if (!valid_date_string($d)) return 'Invalid date.';
    if ($d < date('Y-m-d')) return 'Date cannot be in the past.';
    if ($d > last_booking_date()) return 'You can only book up to ' . BOOKING_DAYS_AHEAD . ' days ahead.';
    if (date('w', strtotime($d)) === '0') return 'The health center is closed on Sundays.';
    return null;
}

function validate_patient(array $in, bool $isDependent): array
{
    $e = [];
    $c = [];
    foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'birth_date', 'mobile',
              'region', 'province', 'municipality', 'barangay', 'district', 'zone', 'street', 'relationship'] as $k) {
        $v = $in[$k] ?? '';
        $c[$k] = is_string($v) ? trim(preg_replace('/\s+/', ' ', $v)) : '';
    }

    if ($c['first_name'] === '') $e['first_name'] = 'First name is required.';
    elseif (!preg_match(NAME_PATTERN, $c['first_name'])) $e['first_name'] = "Use letters, spaces, . ' - only (2-50 characters).";

    if ($c['middle_name'] !== '' && !preg_match(NAME_PATTERN, $c['middle_name'])) $e['middle_name'] = "Use letters, spaces, . ' - only (2-50 characters).";

    if ($c['last_name'] === '') $e['last_name'] = 'Last name is required.';
    elseif (!preg_match(NAME_PATTERN, $c['last_name'])) $e['last_name'] = "Use letters, spaces, . ' - only (2-50 characters).";

    if ($c['suffix'] !== '' && !in_array($c['suffix'], SUFFIXES, true)) $e['suffix'] = 'Invalid suffix.';

    if (!in_array($c['sex'], SEXES, true)) $e['sex'] = 'Please select sex.';

    if ($c['birth_date'] === '') $e['birth_date'] = 'Birth date is required.';
    elseif (!valid_date_string($c['birth_date'])) $e['birth_date'] = 'Invalid date.';
    elseif ($c['birth_date'] > date('Y-m-d')) $e['birth_date'] = 'Birth date cannot be in the future.';
    elseif (age_of($c['birth_date']) > 120) $e['birth_date'] = 'Please check the birth year.';

    if ($c['mobile'] === '') $e['mobile'] = 'Mobile number is required.';
    elseif (!preg_match(MOBILE_PATTERN, $c['mobile'])) $e['mobile'] = 'Enter an 11-digit number starting with 09 (e.g. 09171234567).';

    $required = ['region' => 'Region', 'province' => 'Province', 'municipality' => 'City / Municipality', 'barangay' => 'Barangay'];
    foreach ($required as $k => $label) {
        if ($c[$k] === '') $e[$k] = $label . ' is required.';
        elseif (mb_strlen($c[$k]) > 100) $e[$k] = $label . ' is too long.';
    }
    if (mb_strlen($c['district']) > 60) $e['district'] = 'District is too long (max 60).';
    if ($c['zone'] !== '' && !preg_match('/^[A-Za-z0-9 \-]{1,20}$/', $c['zone'])) $e['zone'] = 'Zone may only contain letters, numbers and dashes (max 20).';
    if ($c['street'] === '') $e['street'] = 'House number & street is required.';
    elseif (mb_strlen($c['street']) < 3 || mb_strlen($c['street']) > 150) $e['street'] = 'Must be 3 to 150 characters.';

    if ($isDependent) {
        if (!in_array($c['relationship'], RELATIONSHIPS, true)) $e['relationship'] = 'Please select the relationship.';
    } else {
        $c['relationship'] = '';
    }

    return [$c, $e];
}

/** Returns [normalized number or '', error or null]. */
function validate_philhealth(string $raw, bool $required, int $exceptPatientId = 0): array
{
    $raw = trim($raw);
    if ($raw === '') return ['', $required ? 'PhilHealth / YAKAP number is required.' : null];
    $no = normalize_philhealth($raw);
    if ($no === null) return [$raw, 'Enter the 12-digit number, e.g. 12-345678901-2.'];
    if (philhealth_taken($no, $exceptPatientId)) return [$no, 'This PhilHealth number is already registered. If this is a mistake, please ask the health center.'];
    return [$no, null];
}

function validate_booking(array $in, array $patient): array
{
    $e = [];
    $c = [];
    foreach (['visit_type', 'service', 'appt_date', 'slot_time', 'priority', 'notes'] as $k) {
        $v = $in[$k] ?? '';
        $c[$k] = is_string($v) ? trim($v) : '';
    }

    if (!in_array($c['visit_type'], VISIT_TYPES, true)) $e['visit_type'] = 'Please select the visit type.';
    if (!in_array($c['service'], SERVICES, true)) $e['service'] = 'Please select a service.';

    $dateErr = booking_date_error($c['appt_date']);
    if ($dateErr) $e['appt_date'] = $dateErr;

    if ($c['slot_time'] === '') $e['slot_time'] = 'Please choose a time slot.';
    elseif (!find_booking_slot($c['slot_time'])) $e['slot_time'] = 'Invalid time slot.';
    elseif (!$dateErr && !slot_is_available($c['appt_date'], $c['slot_time'])) $e['slot_time'] = 'That slot is no longer available. Please pick another one.';

    if ($c['priority'] === '') $c['priority'] = 'Regular';
    if (!in_array($c['priority'], PRIORITIES, true)) {
        $e['priority'] = 'Invalid priority.';
    } elseif (!empty($patient['birth_date']) && valid_date_string($patient['birth_date'])) {
        if ($c['priority'] === 'Senior Citizen' && age_of($patient['birth_date']) < 60) {
            $e['priority'] = 'Senior Citizen priority is only for patients 60 years old and above.';
        }
        if ($c['priority'] === 'Pregnant' && ($patient['sex'] ?? '') !== 'Female') {
            $e['priority'] = 'Pregnant priority is only for female patients.';
        }
    }

    if (mb_strlen($c['notes']) > 500) $e['notes'] = 'Notes must be 500 characters or less.';
    $c['notes'] = strip_tags($c['notes']);

    return [$c, $e];
}

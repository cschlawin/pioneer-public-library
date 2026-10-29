<?php
/**
 * security/sanitize.php
 *
 * Output escaping, server-side validation, and the small display and form
 * helpers every view shares. Every dynamic value that reaches HTML is passed
 * through e().
 */
declare(strict_types=1);

/** Escape a value for HTML output. Used on every echoed dynamic value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Read a trimmed string from POST. Non-string payloads (arrays) fall back. */
function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Read a trimmed string from GET. */
function get_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/* -------------------------------------------------------------------------
 * Validation helpers.
 * Each returns null when the value is acceptable, or the message to show
 * under the field when it is not (FR 32: server-side, field-level messages).
 * ---------------------------------------------------------------------- */

/** The one email message the wireframes show, reused for empty and malformed. */
const EMAIL_ERROR = 'Enter an email address with an @ and a domain.';

function validate_required(string $value, string $label): ?string
{
    return trim($value) === '' ? 'Enter ' . $label . '.' : null;
}

function validate_email(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return EMAIL_ERROR;
    }
    return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? EMAIL_ERROR : null;
}

function validate_max_length(string $value, int $max, string $label): ?string
{
    return mb_strlen(trim($value)) > $max
        ? ucfirst($label) . ' must be ' . $max . ' characters or fewer.'
        : null;
}

function validate_zip(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'Enter a ZIP code.';
    }
    return preg_match('/^\d{5}(-\d{4})?$/', $value) === 1
        ? null
        : 'Enter a 5-digit ZIP code, or ZIP+4.';
}

function validate_state(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'Enter a two-letter state.';
    }
    return preg_match('/^[A-Za-z]{2}$/', $value) === 1
        ? null
        : 'Enter a two-letter state, for example UT.';
}

function validate_phone(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'Enter a phone number.';
    }
    $digits = (string) preg_replace('/\D/', '', $value);
    return (strlen($digits) === 10 || strlen($digits) === 11)
        ? null
        : 'Enter a phone number with 10 digits, for example 801-555-0142.';
}

function validate_iso_date(string $value, string $label = 'a date'): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'Enter ' . $label . '.';
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
        return 'Enter ' . $label . ' as YYYY-MM-DD, for example 2018-04-03.';
    }
    return null;
}

function validate_year(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'Enter a year.';
    }
    if (preg_match('/^\d{4}$/', $value) !== 1) {
        return 'Enter a four-digit year.';
    }
    $year = (int) $value;
    return ($year >= 1000 && $year <= 2100) ? null : 'Enter a year between 1000 and 2100.';
}

function validate_int_range(string $value, int $min, int $max, string $label): ?string
{
    $value = trim($value);
    if ($value === '') {
        return 'Enter ' . $label . '.';
    }
    if (preg_match('/^\d+$/', $value) !== 1) {
        return 'Enter ' . $label . ' as a whole number.';
    }
    $number = (int) $value;
    if ($number < $min || $number > $max) {
        return 'Enter ' . $label . ' between ' . $min . ' and ' . $max . '.';
    }
    return null;
}

/* -------------------------------------------------------------------------
 * Form rendering helpers. They keep the aria wiring in one place: a field
 * with an error gets aria-invalid and aria-describedby pointing at the
 * message element (accessibility requirement).
 * ---------------------------------------------------------------------- */

/** Render the error message for a field, or an empty string. */
function field_error_html(array $errors, string $key): string
{
    if (!isset($errors[$key])) {
        return '';
    }
    return '<p class="field-error" id="err-' . e($key) . '">' . e($errors[$key]) . '</p>';
}

/** aria-invalid / aria-describedby attributes for a field with an error. */
function field_aria(array $errors, string $key): string
{
    return isset($errors[$key])
        ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"'
        : '';
}

/** "has-error" class for a field wrapper. */
function field_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' field has-error' : ' field';
}

/* -------------------------------------------------------------------------
 * Display formatters.
 * ---------------------------------------------------------------------- */

/** Format a stored decimal as US dollars. */
function money(mixed $amount): string
{
    return '$' . number_format((float) $amount, 2);
}

/** 2021-03-04 becomes "March 4, 2021". */
function date_long(string $iso): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    return $parsed === false ? $iso : $parsed->format('F j, Y');
}

/** 2026-10-02 becomes "10/02", the short form the wireframes use. */
function date_mmdd(string $iso): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    return $parsed === false ? $iso : $parsed->format('m/d');
}

/** "B. Smith" becomes "BS" for the header avatar. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach ($parts as $part) {
        if ($part !== '' && preg_match('/[A-Za-z]/', $part) === 1) {
            $out .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($out) === 2) {
            break;
        }
    }
    return $out === '' ? '?' : $out;
}

<?php

declare(strict_types=1);

namespace EcoLife\Lead\Model;

/**
 * Server-side validation for a submitted enquiry.
 *
 * Everything the browser was told is re-checked here, because everything the
 * browser was told is editable in devtools. In particular every select value is
 * checked against the whitelists on the Lead model rather than being trusted
 * because it came from a <select> the server rendered.
 *
 * Returns clean data on success and a field => message map on failure. Nothing
 * is written to the database before this passes.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $clean = [];

    /** @param array<string, mixed> $post */
    public function validate(array $post): bool
    {
        $this->errors = [];
        $this->clean  = [];

        $type = $this->pickFrom('lead_type', $post, array_combine(Lead::TYPES, Lead::TYPES), 'residential');
        $this->clean['lead_type'] = $type;

        $this->requireText($post, 'name', 'Please tell us your name.', 150);
        $this->validatePhone($post);
        $this->validateEmail($post);
        $this->requireText($post, 'city', 'Please tell us which city or village.', 100);
        $this->validatePincode($post);

        $this->clean['state'] = $this->text($post, 'state', 100) ?: 'Maharashtra';

        $range = $this->pickFrom('monthly_bill_range', $post, Lead::BILL_RANGES, null);
        $this->clean['monthly_bill_range'] = $range;

        $roof = $this->pickFrom('roof_type', $post, Lead::ROOF_TYPES, null);
        $this->clean['roof_type'] = $roof;

        $this->clean['monthly_bill_amount'] = $this->positiveNumber($post, 'monthly_bill_amount', 9999999.99);
        $this->clean['roof_area_sqft']      = $this->positiveInt($post, 'roof_area_sqft', 1000000);
        $this->clean['message']             = $this->text($post, 'message', 2000) ?: null;

        match ($type) {
            'society'    => $this->validateSociety($post),
            'commercial' => $this->validateCommercial($post),
            default      => null,
        };

        return $this->errors === [];
    }

    // ------------------------------------------------------------- variants

    /** @param array<string, mixed> $post */
    private function validateSociety(array $post): void
    {
        $name = $this->text($post, 'society_name', 190);
        if ($name === '') {
            $this->errors['society_name'] = 'Please give the name of the society.';
        }
        $this->clean['society_name'] = $name ?: null;

        $this->clean['society_designation'] =
            $this->pickFrom('society_designation', $post, Lead::SOCIETY_DESIGNATIONS, null);
        $this->clean['agm_status'] =
            $this->pickFrom('agm_status', $post, Lead::AGM_STATUSES, null);
        $this->clean['total_flats'] = $this->positiveInt($post, 'total_flats', 65535);
    }

    /** @param array<string, mixed> $post */
    private function validateCommercial(array $post): void
    {
        $company = $this->text($post, 'company_name', 190);
        if ($company === '') {
            $this->errors['company_name'] = 'Please give the business name.';
        }
        $this->clean['company_name'] = $company ?: null;

        $gstin = strtoupper($this->text($post, 'gstin', 15));
        if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]{3}$/', $gstin)) {
            $this->errors['gstin'] = 'That does not look like a valid GSTIN.';
        }
        $this->clean['gstin'] = $gstin ?: null;
    }

    // --------------------------------------------------------------- fields

    /** @param array<string, mixed> $post */
    private function validatePhone(array $post): void
    {
        $digits = preg_replace('/\D+/', '', $this->text($post, 'phone', 20));

        // Indian mobile numbers, with or without the 91 country code.
        if (strlen((string) $digits) === 12 && str_starts_with((string) $digits, '91')) {
            $digits = substr((string) $digits, 2);
        }

        if (!preg_match('/^[6-9]\d{9}$/', (string) $digits)) {
            $this->errors['phone'] = 'Please give a 10-digit mobile number starting 6, 7, 8 or 9.';
            return;
        }

        $this->clean['phone'] = $digits;
    }

    /** @param array<string, mixed> $post */
    private function validateEmail(array $post): void
    {
        $email = $this->text($post, 'email', 190);

        if ($email === '') {
            $this->clean['email'] = null;   // optional -- a phone number is enough
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'That email address does not look right.';
            return;
        }

        $this->clean['email'] = strtolower($email);
    }

    /** @param array<string, mixed> $post */
    private function validatePincode(array $post): void
    {
        $pincode = preg_replace('/\D+/', '', $this->text($post, 'pincode', 10));

        if (!preg_match('/^[1-9]\d{5}$/', (string) $pincode)) {
            $this->errors['pincode'] = 'Please give a 6-digit PIN code.';
            return;
        }

        $this->clean['pincode'] = $pincode;
    }

    /** @param array<string, mixed> $post */
    private function requireText(array $post, string $field, string $message, int $max): void
    {
        $value = $this->text($post, $field, $max);

        if ($value === '') {
            $this->errors[$field] = $message;
            return;
        }

        $this->clean[$field] = $value;
    }

    // -------------------------------------------------------------- helpers

    /** @param array<string, mixed> $post */
    private function text(array $post, string $field, int $max): string
    {
        $value = $post[$field] ?? '';
        if (!is_scalar($value)) {
            return '';
        }
        // Strip control characters: a newline inside a name is either a paste
        // accident or an attempt at header injection downstream in the email.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '';
        return mb_substr(trim($value), 0, $max);
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, string> $allowed
     */
    private function pickFrom(array|string $field, array $post, array $allowed, ?string $default): ?string
    {
        $value = $this->text($post, is_string($field) ? $field : '', 60);

        if ($value === '') {
            return $default;
        }

        if (!array_key_exists($value, $allowed)) {
            // Not a user error worth explaining -- the form never offered this
            // value, so anything else arrived by tampering. Fall back silently.
            $this->errors[is_string($field) ? $field : 'field'] = 'Please choose one of the listed options.';
            return $default;
        }

        return $value;
    }

    /** @param array<string, mixed> $post */
    private function positiveInt(array $post, string $field, int $max): ?int
    {
        $raw = $this->text($post, $field, 12);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }
        $value = (int) $raw;
        return $value > 0 && $value <= $max ? $value : null;
    }

    /** @param array<string, mixed> $post */
    private function positiveNumber(array $post, string $field, float $max): ?float
    {
        $raw = $this->text($post, $field, 14);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $value = (float) $raw;
        return $value > 0 && $value <= $max ? round($value, 2) : null;
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function getCleanData(): array
    {
        return $this->clean;
    }
}

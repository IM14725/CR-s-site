<?php
declare(strict_types=1);

// Shared rules for CR and Ministry accounts, used by admin.php and edit-user.php.

const LEVELS  = ['level 4', 'level 5', 'level 6', 'level 7-I', 'level 7-II', 'level 8'];
const STREAMS = ['A', 'B', 'C', 'D'];

/**
 * Clean and validate the member form fields (CR or Ministry).
 * Returns [$data, $errors]. $data is safe to save only when $errors is empty.
 */
function validate_member(array $input): array
{
    $data = [
        'fullName'  => preg_replace('/\s+/', ' ', trim((string)($input['fullName'] ?? ''))),
        'regNumber' => strtoupper(trim((string)($input['regNumber'] ?? ''))),
        'level'     => (string)($input['level'] ?? ''),
        'stream'    => (string)($input['stream'] ?? ''),
    ];
    $errors = [];

    if (!preg_match("/^[\p{L} .'-]{3,100}$/u", $data['fullName'])) {
        $errors[] = 'Full name must be 3–100 letters (spaces, dots, hyphens and apostrophes allowed).';
    }
    if (!preg_match(REG_PATTERN, $data['regNumber'])) {
        $errors[] = 'Registration number must look like NIT/BIT/2024/2441.';
    }
    if (!in_array($data['level'], LEVELS, true)) {
        $errors[] = 'Choose a valid level.';
    }
    if (!in_array($data['stream'], STREAMS, true)) {
        $errors[] = 'Choose a valid stream.';
    }

    return [$data, $errors];
}

/** First name + "@" + 6 secure random digits, e.g. Issa@759621 */
function generate_password(string $fullName): string
{
    $digits = '';
    for ($i = 0; $i < 6; $i++) {
        $digits .= random_int(0, 9);
    }
    return explode(' ', $fullName)[0] . '@' . $digits;
}

/** True when an INSERT/UPDATE failed because the reg number already exists. */
function is_duplicate(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}

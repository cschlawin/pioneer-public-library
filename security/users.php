<?php
/**
 * security/users.php
 *
 * The demo accounts. Only password_hash() output is stored here; no plaintext
 * password appears in this file or any other (BR 14, FR 33).
 *
 * The two accounts are the course-mandated test users for the final phase:
 *   bsmith - Admin  - B. Smith
 *   pjones - Member - P. Jones
 *
 * Their passwords are recorded in the project README, not in this file and not
 * on any page of the site (BR 14).
 *
 * The hashes below were produced with password_hash($password, PASSWORD_DEFAULT)
 * on PHP 8.4 and are verified with password_verify(). To change a password,
 * regenerate its hash and replace the value; never add a plaintext copy.
 */
declare(strict_types=1);

function demo_users(): array
{
    return [
        'bsmith' => [
            'username'     => 'bsmith',
            'display_name' => 'B. Smith',
            'role'         => 'admin',
            'email'        => 'b.smith@example.org',
            'password_hash' => '$2y$12$YrBySsIvi/MZyJ.41uhDQe.lac6JP/0F.MLU9mhNM3Nt0M5jT/ZLG',
        ],
        'pjones' => [
            'username'     => 'pjones',
            'display_name' => 'P. Jones',
            'role'         => 'member',
            'email'        => 'p.jones@example.org',
            'password_hash' => '$2y$12$0ALkWiXFlylxdq.3KKYwQO7P/zqBp9ozlhAu9gaaT51vDmpLcghEO',
        ],
    ];
}

/** Look up one demo account by username, or null. */
function find_demo_user(string $username): ?array
{
    $users = demo_users();
    return $users[$username] ?? null;
}

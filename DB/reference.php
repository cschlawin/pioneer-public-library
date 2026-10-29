<?php
/**
 * DB/reference.php
 *
 * The controlled vocabularies the forms and filters use. Nothing here is
 * stored per record beyond the short key: the label is derived on display so
 * the wording lives in exactly one place (DR 04, DR 05).
 */
declare(strict_types=1);

/** Media types, in the order the home page and filters show them (DR 04). */
function media_types(): array
{
    return [
        'book'       => 'Book',
        'film'       => 'Film',
        'music'      => 'Music',
        'periodical' => 'Periodical',
        'audiobook'  => 'Audiobook',
    ];
}

function media_type_label(string $key): string
{
    $types = media_types();
    return $types[$key] ?? 'Unknown';
}

/** True when the key is one of the five media types. */
function is_valid_media_type(string $key): bool
{
    return array_key_exists($key, media_types());
}

/** The descriptive fields each media type carries (DR 04, WF 07 note). */
function media_type_fields(string $key): array
{
    return match ($key) {
        'book'       => ['creator' => 'Author', 'publisher' => 'Publisher', 'publish_date' => 'Publish date', 'genre' => 'Genre', 'isbn' => 'ISBN'],
        'film'       => ['creator' => 'Director', 'year' => 'Year', 'genre' => 'Genre'],
        'music'      => ['creator' => 'Artist'],
        'periodical' => ['issue' => 'Issue', 'publish_date' => 'Publish date'],
        'audiobook'  => ['creator' => 'Author', 'publisher' => 'Publisher', 'genre' => 'Genre'],
        default      => [],
    };
}

/** Copy conditions (DR 05). */
function copy_conditions(): array
{
    return ['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'];
}

/** Copy statuses (BR 08). Checked out is set by the checkout flow only. */
function copy_statuses(): array
{
    return ['available' => 'Available', 'checked out' => 'Checked out', 'missing' => 'Missing'];
}

function copy_status_label(string $key): string
{
    $statuses = copy_statuses();
    return $statuses[$key] ?? ucfirst($key);
}

function copy_condition_label(string $key): string
{
    $conditions = copy_conditions();
    return $conditions[$key] ?? ucfirst($key);
}

/** Member account statuses (BR 06). A member is deactivated, never deleted. */
function member_statuses(): array
{
    return ['active' => 'Active', 'inactive' => 'Inactive'];
}

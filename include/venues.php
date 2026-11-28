<?php
declare(strict_types=1);

function official_venues(): array
{
    static $venues = null;
    if ($venues === null) {
        $json = file_get_contents(__DIR__ . '/../assets/venues.json');
        $venues = json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
    }
    return $venues;
}

function is_official_venue(string $name): bool
{
    foreach (official_venues() as $v) {
        if ($v['name'] === $name) {
            return true;
        }
    }
    return false;
}
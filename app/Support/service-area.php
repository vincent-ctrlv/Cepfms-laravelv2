<?php

/**
 * CEPFMS — Service area: submissions are only accepted for locations inside
 * the City of San Jose del Monte, Bulacan.
 *
 * Barangay list: the city's 62 barangays (after Muzon was split into four),
 * per the city's Wikipedia barangay table. If the city creates, renames, or
 * merges a barangay, update SJDM_BARANGAYS — the submit form's dropdown and
 * the server-side check both read from it.
 */

const SJDM_CITY = 'San Jose del Monte';

const SJDM_BARANGAYS = [
    'Assumption', 'Bagong Buhay I', 'Bagong Buhay II', 'Bagong Buhay III', 'Citrus',
    'Ciudad Real', 'Dulong Bayan', 'Fatima I', 'Fatima II', 'Fatima III', 'Fatima IV',
    'Fatima V', 'Francisco Homes-Guijo', 'Francisco Homes-Mulawin', 'Francisco Homes-Narra',
    'Francisco Homes-Yakal', 'Gaya-Gaya', 'Graceville', 'Gumaoc Central', 'Gumaoc East',
    'Gumaoc West', 'Kaybanban', 'Kaypian', 'Lawang Pari', 'Maharlika', 'Minuyan Proper',
    'Minuyan I', 'Minuyan II', 'Minuyan III', 'Minuyan IV', 'Minuyan V', 'Muzon East',
    'Muzon Proper', 'Muzon South', 'Muzon West', 'Paradise III', 'Poblacion', 'Poblacion I',
    'San Isidro', 'San Manuel', 'San Martin I', 'San Martin II', 'San Martin III',
    'San Martin IV', 'San Martin de Porres', 'San Pedro', 'San Rafael I', 'San Rafael II',
    'San Rafael III', 'San Rafael IV', 'San Rafael V', 'San Roque', 'Santa Cruz I',
    'Santa Cruz II', 'Santa Cruz III', 'Santa Cruz IV', 'Santa Cruz V', 'Santo Cristo',
    'Santo Niño I', 'Santo Niño II', 'Sapang Palay Proper', 'Tungkong Mangga',
];

/**
 * City boundary outline (OpenStreetMap, simplified ~50 m) as [lng, lat]
 * points. The submit form's map and the server-side check both use this
 * file, so a pin is accepted or rejected the same way in both places.
 */
function sjdm_boundary(): array
{
    static $ring = null;
    if ($ring === null) {
        $data = json_decode((string) file_get_contents(app_path('Data/sjdm-boundary.json')), true);
        $ring = $data['ring_lng_lat'] ?? [];
    }
    return $ring;
}

/** Returns the canonical barangay name for $input (case-insensitive), or null if it isn't in SJDM. */
function sjdm_barangay(string $input): ?string
{
    $needle = mb_strtolower(trim($input));
    foreach (SJDM_BARANGAYS as $name) {
        if (mb_strtolower($name) === $needle) return $name;
    }
    return null;
}

/** True when the point lies inside the city boundary (ray-casting point-in-polygon). */
function sjdm_contains(float $lat, float $lng): bool
{
    $ring = sjdm_boundary();
    $inside = false;
    for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
        [$xi, $yi] = $ring[$i];
        [$xj, $yj] = $ring[$j];
        if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
            $inside = !$inside;
        }
    }
    return $inside;
}

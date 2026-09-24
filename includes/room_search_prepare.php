<?php
/*
 * Shared sanitization for the tenant room search/filter controls.
 *
 * Only allowlist values are trusted. This keeps the GET parameter handling
 * (location/category/type/max_price) consistent between the Tenant Home hero
 * and the Browse Rooms results page, while the search form is only rendered
 * on the Tenant Home page.
 */

/*
 * Lightweight location guard for a search query. More lenient than the full
 * address validation used when a room is listed, but still rejects obvious
 * garbage such as "!!!!", "12345", or "aaaa".
 */
if (!function_exists('is_searchable_location')) {
    function is_searchable_location($value)
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > 255) {
            return false;
        }

        if (preg_match_all('/[\p{L}]/u', $value) < 2) {
            return false;
        }

        if (!preg_match('/^[\p{L}\p{N}]/u', $value)) {
            return false;
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $value);
        if (preg_match('/^(.)\1+$/u', $letters)) {
            return false;
        }

        return true;
    }
}

$searchCategories = ['Room', 'Flat/Apartment'];

$searchTypesByCategory = [
    'Room'           => ['Single Room', 'Double Room', 'Shared Room'],
    'Flat/Apartment' => ['1RK', '1BHK', '2BHK', '3BHK'],
];

$searchAllTypes = array_merge(...array_values($searchTypesByCategory));

$searchLocation = '';
$searchCategory = '';
$searchType     = '';
$searchMaxPrice = '';

$searchMaxPriceLimit = 10000000;

$rawLocation = trim((string) ($_GET['location'] ?? ''));
if (is_searchable_location($rawLocation)) {
    $searchLocation = $rawLocation;
}

$rawCategory = trim((string) ($_GET['category'] ?? ''));
if (in_array($rawCategory, $searchCategories, true)) {
    $searchCategory = $rawCategory;
}

$typeAllowlist = $searchCategory !== ''
    ? ($searchTypesByCategory[$searchCategory] ?? [])
    : $searchAllTypes;

$rawType = trim((string) ($_GET['type'] ?? ''));
if (in_array($rawType, $typeAllowlist, true)) {
    $searchType = $rawType;
}

$rawMaxPrice = trim((string) ($_GET['max_price'] ?? ''));
if ($rawMaxPrice !== '' && is_numeric($rawMaxPrice)
    && (float) $rawMaxPrice > 0 && (float) $rawMaxPrice <= $searchMaxPriceLimit) {
    $searchMaxPrice = $rawMaxPrice;
}

/*
 * Preserve any non-search query parameters so nothing unrelated is dropped
 * when the search form or the "Clear Filters" link navigates to results.
 */
$unrelatedParams = [];
foreach ($_GET as $paramName => $paramValue) {
    if (in_array($paramName, ['location', 'category', 'type', 'max_price'], true)) {
        continue;
    }
    $unrelatedParams[$paramName] = $paramValue;
}

/*
 * The search form always submits to the Browse Rooms page where results are
 * shown. Both pages live in /tenant/, so a relative target resolves there.
 */
$searchFormAction = 'rooms.php';
$clearFiltersUrl  = 'rooms.php';

if (!empty($unrelatedParams)) {
    $searchFormAction .= '?' . http_build_query($unrelatedParams);
    $clearFiltersUrl  .= '?' . http_build_query($unrelatedParams);
}
<?php
/*
 * Tenant hero + search/filter panel.
 *
 * Rendered on the Tenant Home page. Relies on the variables prepared by
 * includes/room_search_prepare.php ($searchCategories, $searchTypesByCategory,
 * $searchAllTypes, $searchLocation, $searchCategory, $searchType,
 * $searchMaxPrice, $searchFormAction, $clearFiltersUrl).
 */
?>

<section class="tenant-hero">

    <div class="tenant-hero-container">

        <span class="tenant-hero-badge">For Tenants</span>

        <h1 class="tenant-hero-title">
            Find Your Perfect Room, <br>
            Without the <span>Hassle</span>
        </h1>

        <p class="tenant-hero-description">
            Browse verified rooms, compare prices, save your favorites,and connect directly with trusted landlords
            across Nepal.
        </p>

        <div class="tenant-search-panel">

            <form class="tenant-search-form" action="<?= htmlspecialchars($searchFormAction) ?>" method="GET"
                aria-label="Search rooms" novalidate>

                <div class="tenant-search-field tenant-search-location">

                    <label for="search-location">Location</label>

                    <input type="text" id="search-location" name="location" placeholder="Search by location"
                        maxlength="255" value="<?= htmlspecialchars($searchLocation) ?>">

                </div>

                <div class="tenant-search-field tenant-search-category">

                    <label for="search-category">Category</label>

                    <select id="search-category" name="category">
                        <option value="" <?= $searchCategory === '' ? 'selected' : '' ?>>All Categories</option>
                        <?php foreach ($searchCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $searchCategory === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>

                <div class="tenant-search-field tenant-search-type">

                    <label for="search-type">Type</label>

                    <select id="search-type" name="type">
                        <option value="" <?= $searchType === '' ? 'selected' : '' ?>>All Types</option>
                        <?php foreach ($searchAllTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $searchType === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>

                <div class="tenant-search-field tenant-search-price">

                    <label for="search-max-price">Maximum Price</label>

                    <input type="number" id="search-max-price" name="max_price" placeholder="e.g. 15000" min="0.01"
                        step="0.01" inputmode="decimal" value="<?= htmlspecialchars($searchMaxPrice) ?>">

                </div>

                <div class="tenant-search-action">

                    <button type="submit" class="tenant-search-btn">
                        <svg class="tenant-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                            <line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" />
                        </svg>
                        Search Rooms
                    </button>

                </div>

            </form>

            <div class="tenant-search-clear">
                <a href="<?= htmlspecialchars($clearFiltersUrl) ?>" class="tenant-search-clear-link">
                    <svg class="tenant-search-clear-icon" viewBox="0 0 24 24" fill="none"
                        xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
                        <path d="M9 9L15 15M15 9L9 15" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" />
                    </svg>
                    Clear Filters
                </a>
            </div>

        </div>

    </div>

</section>

<script>
    (function () {

        var categorySelect = document.getElementById('search-category');
        var typeSelect = document.getElementById('search-type');

        var typeMaps = <?= json_encode($searchTypesByCategory) ?>;
        var allTypes = <?= json_encode($searchAllTypes) ?>;

        function typesFor(category) {
            return Object.prototype.hasOwnProperty.call(typeMaps, category)
                ? typeMaps[category]
                : allTypes;
        }

        function buildTypeOptions(category) {
            var types = typesFor(category);

            typeSelect.innerHTML = '';

            var allTypesOption = document.createElement('option');
            allTypesOption.value = '';
            allTypesOption.textContent = 'All Types';
            typeSelect.appendChild(allTypesOption);

            types.forEach(function (type) {
                var option = document.createElement('option');
                option.value = type;
                option.textContent = type;
                typeSelect.appendChild(option);
            });
        }

        function onCategoryChange() {
            var previousType = typeSelect.value;
            var category = categorySelect.value;

            buildTypeOptions(category);

            if (previousType !== '' && typesFor(category).indexOf(previousType) !== -1) {
                typeSelect.value = previousType;
            }
        }

        categorySelect.addEventListener('change', onCategoryChange);

        var initialCategory = <?= json_encode($searchCategory) ?>;
        var initialType = <?= json_encode($searchType) ?>;

        buildTypeOptions(initialCategory);

        if (initialType !== '' && typesFor(initialCategory).indexOf(initialType) !== -1) {
            typeSelect.value = initialType;
        }
    })();
</script>
const grid = document.querySelector('#propertyGrid');
const filter = document.querySelector('#propertyFilter');
const district = document.querySelector('#district');
const propertyType = document.querySelector('#propertyType');
const priceRange = document.querySelector('#priceRange');
const statusText = document.querySelector('#apiStatus');
const clearButton = document.querySelector('#clearFilter');
const menuButton = document.querySelector('.menu-button');
const nav = document.querySelector('#mainNav');

const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
}[character]));

const truncate = (value, maximum = 116) => {
    const text = String(value || '').trim();
    return text.length > maximum ? `${text.slice(0, maximum).trim()}…` : text;
};

function propertyCard(property, index) {
    const sold = property.status === 'sold';
    const mediaLabel = `${property.image_count || 1} photo${property.image_count === 1 ? '' : 's'}${property.has_video ? ' + video' : ''}`;
    return `
        <article class="property-card ${sold ? 'is-sold' : ''}" style="--delay:${index * 60}ms" data-tilt>
            <a class="property-image" href="${escapeHtml(property.detail_url)}">
                <img src="${escapeHtml(property.image_url)}" alt="${escapeHtml(property.title)}" loading="lazy">
                <span class="status-badge ${sold ? 'sold' : 'available'}"><i></i>${escapeHtml(property.status_label)}</span>
                <span class="media-count">▣ ${escapeHtml(mediaLabel)}</span>
            </a>
            <div class="property-body">
                <div class="property-meta"><span>⌖ ${escapeHtml(property.district)}</span><span>${escapeHtml(property.property_type_label)}</span></div>
                <h3><a href="${escapeHtml(property.detail_url)}">${escapeHtml(property.title)}</a></h3>
                <p>${escapeHtml(truncate(property.details))}</p>
                <div class="property-bottom">
                    <strong>${escapeHtml(property.price)}</strong>
                    <a class="round-arrow" href="${escapeHtml(property.detail_url)}" aria-label="View ${escapeHtml(property.title)}">↗</a>
                </div>
                ${sold ? '<span class="sold-message">This property has found its new owner</span>' : `<a class="enquire-link" href="${escapeHtml(property.enquiry_url)}" target="_blank" rel="noopener">Enquire on WhatsApp →</a>`}
            </div>
        </article>`;
}

async function loadProperties() {
    // Read all filter values once so the request and Clear button stay in sync.
    const selectedDistrict = district.value.trim();
    const selectedType = propertyType.value.trim().toLowerCase();
    const selectedPriceRange = priceRange.value.trim();
    const hasFilters = Boolean(selectedDistrict || selectedType || selectedPriceRange);
    grid.setAttribute('aria-busy', 'true');
    statusText.textContent = 'Loading the latest listings…';
    clearButton.hidden = !hasFilters;

    try {
        const params = new URLSearchParams();
        if (selectedDistrict) params.set('district', selectedDistrict);
        if (selectedType) params.set('type', selectedType);
        // Send the range as one value. The API validates and expands it, which
        // avoids losing an open-ended value such as "50000000-".
        if (selectedPriceRange) params.set('price_range', selectedPriceRange);
        const queryString = params.toString();
        const apiUrl = `api/properties.php${queryString ? `?${queryString}` : ''}`;
        const response = await fetch(apiUrl, {
            headers: { Accept: 'application/json' }
        });
        const responseText = await response.text();
        let payload;
        try {
            payload = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error('The property API returned an invalid response. Open api/properties.php directly to see the PHP error.');
        }
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Request failed');

        if (!payload.properties.length) {
            grid.innerHTML = `<div class="empty-state"><span>⌂</span><h3>No places here—yet.</h3><p>Try another district or view all properties.</p><button type="button" data-show-all>Show all properties</button></div>`;
        } else {
            grid.innerHTML = payload.properties.map(propertyCard).join('');
            activateCardTilt();
        }
        statusText.textContent = `${payload.count} ${payload.count === 1 ? 'property' : 'properties'} found`;
        const url = new URL(window.location.href);
        ['district', 'type', 'price_range', 'min_price', 'max_price'].forEach(key => url.searchParams.delete(key));
        params.forEach((value, key) => url.searchParams.set(key, value));
        history.replaceState({}, '', `${url.pathname}${url.search}#properties`);
    } catch (error) {
        const message = escapeHtml(error.message || 'Please check Apache and MySQL, then try again.');
        grid.innerHTML = `<div class="empty-state error-state"><span>!</span><h3>We couldn’t load the listings.</h3><p>${message}</p><button type="button" data-retry>Try again</button></div>`;
        statusText.textContent = error.message || 'Property API unavailable';
    } finally {
        grid.setAttribute('aria-busy', 'false');
    }
}

filter?.addEventListener('submit', event => {
    event.preventDefault();
    loadProperties();
});

clearButton?.addEventListener('click', () => {
    district.value = '';
    propertyType.value = '';
    priceRange.value = '';
    loadProperties();
});

grid?.addEventListener('click', event => {
    if (event.target.closest('[data-show-all]')) {
        // "Show all" must reset every filter, not only the district.
        district.value = '';
        propertyType.value = '';
        priceRange.value = '';
        loadProperties();
    }
    if (event.target.closest('[data-retry]')) loadProperties();
});

menuButton?.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    menuButton.setAttribute('aria-expanded', String(open));
});

nav?.addEventListener('click', event => {
    if (event.target.closest('a')) {
        nav.classList.remove('open');
        menuButton.setAttribute('aria-expanded', 'false');
    }
});

const initialParams = new URLSearchParams(window.location.search);
const initialDistrict = initialParams.get('district') || '';
if ([...district.options].some(option => option.value === initialDistrict)) district.value = initialDistrict;
const initialType = initialParams.get('type') || '';
if ([...propertyType.options].some(option => option.value === initialType)) propertyType.value = initialType;
// Restore a matching price option when the page URL contains filter values.
const legacyMin = initialParams.get('min_price') || '';
const legacyMax = initialParams.get('max_price') || '';
const initialPriceRange = initialParams.get('price_range') || (legacyMin || legacyMax ? `${legacyMin}-${legacyMax}` : '');
if ([...priceRange.options].some(option => option.value === initialPriceRange)) {
    priceRange.value = initialPriceRange;
}
loadProperties();

function activateCardTilt() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !window.matchMedia('(pointer: fine)').matches) return;
    document.querySelectorAll('[data-tilt]').forEach(card => {
        card.addEventListener('pointermove', event => {
            const bounds = card.getBoundingClientRect();
            const x = (event.clientX - bounds.left) / bounds.width - .5;
            const y = (event.clientY - bounds.top) / bounds.height - .5;
            card.style.setProperty('--rx', `${-y * 8}deg`);
            card.style.setProperty('--ry', `${x * 10}deg`);
            card.style.setProperty('--mx', `${(x + .5) * 100}%`);
            card.style.setProperty('--my', `${(y + .5) * 100}%`);
        });
        card.addEventListener('pointerleave', () => {
            card.style.setProperty('--rx', '0deg');
            card.style.setProperty('--ry', '0deg');
        });
    });
}

const scene = document.querySelector('[data-scene]');
const stage = document.querySelector('[data-scene-stage]');
if (scene && stage && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    scene.addEventListener('pointermove', event => {
        const bounds = scene.getBoundingClientRect();
        const x = (event.clientX - bounds.left) / bounds.width - .5;
        const y = (event.clientY - bounds.top) / bounds.height - .5;
        stage.style.setProperty('--scene-x', `${x * 13}deg`);
        stage.style.setProperty('--scene-y', `${-y * 10}deg`);
        stage.style.setProperty('--light-x', `${(x + .5) * 100}%`);
        stage.style.setProperty('--light-y', `${(y + .5) * 100}%`);
    });
    scene.addEventListener('pointerleave', () => {
        stage.style.setProperty('--scene-x', '0deg');
        stage.style.setProperty('--scene-y', '0deg');
    });
}

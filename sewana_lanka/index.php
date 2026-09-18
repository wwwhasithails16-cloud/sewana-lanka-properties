<?php
require_once __DIR__ . '/functions.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Discover verified homes, land, apartments and commercial property across Sri Lanka with Sewana Lanka.">
    <meta name="theme-color" content="#fffaf4">
    <title>Sewana Lanka | Find a place to belong</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <!-- filemtime prevents the browser from keeping an older filter script. -->
    <script src="assets/app.js?v=<?= (int) filemtime(__DIR__ . '/assets/app.js') ?>" defer></script>
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php" aria-label="Sewana Lanka home">
        <a href="admin.php"><span class="brand-mark"><img src="assets/sewana-logo.jpg" alt=""></span></a>
        <span><strong>SEWANA LANKA PROPERTIES</strong></span>
    </a>

    <button class="menu-button" type="button" aria-expanded="false" aria-controls="mainNav">
        <span></span><span></span><span></span><span class="sr-only">Menu</span>
    </button>

    <nav class="main-nav" id="mainNav" aria-label="Main navigation">
        <a href="#home">Home</a>
        <a href="#properties">Properties</a>
        <a href="#about">Our story</a>
        <a href="#contact">Contact</a>
        <a class="nav-cta" href="https://wa.me/94751227606" target="_blank" rel="noopener">Let’s talk <span>↗</span></a>
    </nav>
</header>

<main>
<section class="hero" id="home">
    <div class="hero-copy">
        <div class="eyebrow"><i></i> Properties across all 25 districts</div>
        <h1>Your next chapter<br>starts <em>here.</em></h1>
        <p>Handpicked homes, land and investment opportunities across Sri Lanka—made beautifully simple to discover.</p>
        <div class="hero-actions">
            <a class="button primary" href="#properties">Explore properties <span>→</span></a>
            <a class="text-link" href="https://wa.me/94751227606" target="_blank" rel="noopener"><span class="play-dot">●</span> Speak to an advisor</a>
        </div>
        <div class="trust-row">
            <div class="avatar-stack" aria-hidden="true"><span>S</span><span>L</span><span>♥</span></div>
            <p><strong>Trusted local guidance</strong><br><span>Clear, direct and personal support</span></p>
        </div>
    </div>

    <div class="hero-visual" aria-label="Interactive 3D property showcase" data-scene>
        <div class="scene-glow glow-purple"></div>
        <div class="scene-glow glow-coral"></div>
        <div class="scene-floor"></div>
        <div class="scene-stage" data-scene-stage>
            <div class="house-card">
                <div class="house-card-face">
                    <img src="assets/hero.jpg" alt="Beautiful modern property surrounded by greenery">
                    <div class="image-shade"></div>
                    <div class="featured-copy"><small>FEATURED COLLECTION</small><strong>Live beautifully<br>in Sri Lanka.</strong></div>
                </div>
                <div class="house-card-edge edge-right"></div>
                <div class="house-card-edge edge-bottom"></div>
            </div>

            <div class="float-panel panel-location"><span class="panel-icon purple">⌖</span><div><small>EXPLORE</small><strong>25 districts</strong></div></div>
            <div class="float-panel panel-verified"><span class="panel-icon mint">✓</span><div><strong>Verified listings</strong><small>Updated by our team</small></div></div>
            <div class="float-panel panel-rating"><strong>4.9</strong><span>★★★★★</span><small>LOCAL GUIDANCE</small></div>

            <div class="mini-building building-one"><i class="cube-front"></i><i class="cube-side"></i><i class="cube-top"></i></div>
            <div class="mini-building building-two"><i class="cube-front"></i><i class="cube-side"></i><i class="cube-top"></i></div>
            <div class="mini-tree tree-one"><i></i><b></b></div>
            <div class="mini-tree tree-two"><i></i><b></b></div>
            <div class="orbit orbit-one"><span></span></div>
            <div class="orbit orbit-two"><span></span></div>
        </div>
        <p class="drag-hint"><span>↔</span> Move to explore</p>
    </div>
</section>

<section class="marquee" aria-label="Property types">
    <div><span>Dream homes</span><i>✦</i><span>Land</span><i>✦</i><span>Apartments</span><i>✦</i><span>Commercial</span><i>✦</i><span>Investments</span><i>✦</i></div>
</section>

<section class="section property-section" id="properties">
    <div class="section-heading left">
        <div><span class="kicker">FRESH ON THE MARKET</span><h2>Places worth<br><em>falling for.</em></h2></div>
        <p>Explore our latest listings and find a space that feels just right.</p>
    </div>

    <form class="filter-bar" id="propertyFilter">
        <div class="select-wrap">
            <span>⌖</span>
            <label for="district">Location</label>
            <select name="district" id="district">
                <option value="">All Sri Lanka</option>
                <?php foreach (districts() as $district): ?>
                    <option value="<?= e($district) ?>"><?= e($district) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- Price is a single dropdown, matching the Location control. -->
        <div class="select-wrap compact">
            <span>Rs.</span>
            <label for="priceRange">Price range</label>
            <select name="price_range" id="priceRange">
                <option value="">Any price</option>
                <option value="0-5000000">Under Rs. 5 million</option>
                <option value="5000000-10000000">Rs. 5–10 million</option>
                <option value="10000000-25000000">Rs. 10–25 million</option>
                <option value="25000000-50000000">Rs. 25–50 million</option>
                <option value="50000000-">Rs. 50 million and above</option>
            </select>
        </div>
        <div class="select-wrap compact">
            <span>⌂</span>
            <label for="propertyType">Property type</label>
            <select name="type" id="propertyType">
                <option value="">All types</option>
                <option value="house">House</option>
                <option value="land">Land</option>
                <option value="commercial">Commercial</option>
                <option value="apartment">Apartment</option>
            </select>
        </div>
        <button class="button primary" type="submit">Find properties <span>→</span></button>
        <button class="clear-filter" id="clearFilter" type="button" hidden>Clear</button>
        <span class="api-status" id="apiStatus" role="status" aria-live="polite">Loading the latest listings…</span>
    </form>

    <div class="property-grid" id="propertyGrid" aria-live="polite" aria-busy="true">
        <div class="property-skeleton"></div><div class="property-skeleton"></div><div class="property-skeleton"></div>
    </div>
    <noscript><div class="empty-state"><h3>JavaScript is required</h3><p>Please enable JavaScript to load the latest properties from our live API.</p></div></noscript>
</section>

<section class="about-section" id="about">
    <div class="about-art">
        <div class="shape shape-yellow"></div><div class="shape shape-pink"></div><div class="shape shape-blue"></div>
        <img src="assets/sewana-logo.jpg" alt="Sewana Lanka">
        <span class="round-note">LOCAL KNOWLEDGE<br>REAL CONNECTIONS</span>
    </div>
    <div class="about-copy">
        <span class="kicker">WHY SEWANA LANKA?</span>
        <h2>Real estate,<br>with a little more <em>heart.</em></h2>
        <p>Property decisions are personal. That’s why we combine local knowledge with straightforward support, helping you move with confidence.</p>
        <div class="value-list">
            <div><span>01</span><p><strong>Island-wide discovery</strong><small>Browse every Sri Lankan district in seconds.</small></p></div>
            <div><span>02</span><p><strong>See the full picture</strong><small>Rich photo galleries and property videos.</small></p></div>
            <div><span>03</span><p><strong>Status you can trust</strong><small>Availability managed directly by our team.</small></p></div>
        </div>
    </div>
</section>

<section class="cta-band">
    <span class="cta-spark">✦</span>
    <div><span>READY WHEN YOU ARE</span><h2>Let’s find your place<br>in Sri Lanka.</h2></div>
    <a class="button dark" href="https://wa.me/94751227606" target="_blank" rel="noopener">Start a conversation <span>↗</span></a>
</section>
</main>

<footer id="contact">
    <div class="footer-grid">
        <div class="footer-intro">
            <a class="brand footer-brand-link" href="index.php"><span class="brand-mark"><img src="assets/sewana-logo.jpg" alt=""></span><span><strong>Sewana</strong><small>LANKA PROPERTIES</small></span></a>
            <p>Good places. Honest guidance.<br>Real connections.</p>
        </div>
        <!-- The administrator login is intentionally not linked from the public website. -->
        <div><h3>Explore</h3><a href="#home">Home</a><a href="#properties">Properties</a><a href="#about">Our story</a></div>

        <div class="contact-list"><h3>Say hello</h3><a href="tel:+94751227606"><?= contactIcon('call') ?><span>+94 75 122 7606</span></a><a href="mailto:Sewanalanka.info@gmail.com"><?= contactIcon('email') ?><span>Sewanalanka.info@gmail.com</span></a><a href="https://www.facebook.com/share/18CM1Nfgk7/" target="_blank" rel="noopener"><?= contactIcon('facebook') ?><span>Facebook</span></a><a href="https://wa.me/94751227606" target="_blank" rel="noopener"><?= contactIcon('whatsapp') ?><span>WhatsApp</span></a></div>
        <div class="powered"><img src="assets/dark-forest-logo.png" alt="PrimeWeb Studio"><p>Powered by<br><strong>PrimeWeb Studio</strong></p><div class="contact-list"><a href="tel:+94726558309"><?= contactIcon('call') ?><span>+94 72 655 8309</span></a><a href="mailto:hasithails16@gmail.com"><?= contactIcon('email') ?><span>hasithails16@gmail.com</span></a><a href="https://www.facebook.com/share/19BgWZwTkB/" target="_blank" rel="noopener"><?= contactIcon('facebook') ?><span>Facebook</span></a><a href="https://wa.me/94726558309" target="_blank" rel="noopener"><?= contactIcon('whatsapp') ?><span>WhatsApp</span></a></div></div>
    </div>
    <div class="copyright"><span>© <?= date('Y') ?> Sewana Lanka</span><span>@Powerd by PrimeWeb Studio</span></div>
</footer>
</body>
</html>

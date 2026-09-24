<!DOCTYPE html>
<html lang="fil">
<head>
<meta charset="UTF-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>Resident Portal | Barangay Anabu I-G</title>
<link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
<link rel="stylesheet" href="{{ asset('css/government.css') }}">
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>
<div class="portal-government-bar"><span>Republic of the Philippines</span><span>City of Imus, Cavite</span></div>

<div id="toast-wrap" role="status" aria-live="polite"></div>
<div id="loader" style="display:none;"><div class="spinner"></div><div style="font-size:13px;color:var(--text-muted);">Sandali lang...</div></div>

<!-- HEADER -->
<header class="header">
  <div class="header-seal"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G"></div>
  <div class="header-info">
    <h1>Barangay Anabu I-G</h1>
    <p>Official Resident Services Portal</p>
  </div>
  <div class="header-badge"><span></span> Resident services</div>
  <button class="btn btn-outline" type="button" data-resident-theme>Change theme</button>
</header>

@include('partials.resident-nav')

<div class="container" id="main-content" tabindex="-1">
  <section class="portal-hero" aria-labelledby="portal-title">
    <div class="portal-hero-copy">
      <div class="portal-hero-kicker"><span class="portal-hero-kicker-mark"></span> BARANGAY ANABU I-G DIGITAL SERVICES</div>
      <h2 id="portal-title">Barangay services,<br><em>within your reach.</em></h2>
      <p>Explore barangay information and request official documents through our secure resident portal. Your barangay office reviews every submission.</p>
      <div class="portal-hero-actions">
        <a class="btn portal-hero-primary" href="{{ route('portal.request.create') }}">Request a document <span aria-hidden="true">&rarr;</span></a>
      </div>
    </div>
    <div class="portal-hero-aside">
      <img src="{{ asset('images/anabu-logo.jpg') }}" alt="" aria-hidden="true">
      <div class="portal-hero-aside-label">ANABU I-G<br>IMUS, CAVITE</div>
      <div class="portal-hero-aside-line"></div>
      <p>Serving our community with accessible barangay services.</p>
    </div>
  </section>

  <section class="portal-service-section" id="services" aria-labelledby="portal-services-title">
    <div class="portal-section-heading"><div><div class="portal-eyebrow">What you can do here</div><h2 id="portal-services-title">Resident services</h2></div><a href="{{ route('portal.information') }}#requirements">View requirements <span aria-hidden="true">&rarr;</span></a></div>
    <div class="portal-service-grid">
      <a class="portal-service-card" href="{{ route('portal.request.create') }}"><span class="portal-service-number">01 / DOCUMENTS</span><span class="portal-service-symbol" aria-hidden="true">&#9638;</span><strong>Request a document</strong><span>Submit a barangay clearance, residency certificate, ID, and other document requests.</span><span class="portal-service-link">Start a request <span aria-hidden="true">&rarr;</span></span></a>
      <a class="portal-service-card" href="{{ auth('resident')->check() ? route('portal.account') : route('portal.login') }}"><span class="portal-service-number">02 / YOUR ACCOUNT</span><span class="portal-service-symbol" aria-hidden="true">&#9776;</span><strong>Track your requests</strong><span>Check submitted documents and follow their progress in your resident account.</span><span class="portal-service-link">{{ auth('resident')->check() ? 'View my requests' : 'Resident login' }} <span aria-hidden="true">&rarr;</span></span></a>
    </div>
  </section>

  <section class="portal-community-section" id="population" aria-labelledby="population-title">
    <div class="portal-section-heading"><div><div class="portal-eyebrow">Our community</div><h2 id="population-title">Population</h2></div></div>
    <div class="portal-population-card">
      <div><span class="portal-card-label">Barangay Anabu I-G</span><strong>2,345</strong><p>residents in the 2024 Census of Population</p></div>
      <div class="portal-population-source"><span>OFFICIAL SOURCE</span><p>Philippine Statistics Authority, 2024 POPCEN. Census population is different from the number of residents registered in this portal.</p><a href="https://psa.gov.ph/classification/psgc/barangays/0402109000" target="_blank" rel="noopener noreferrer">View PSA data <span aria-hidden="true">&nearr;</span></a></div>
    </div>
  </section>

  <section class="portal-community-section" id="announcements" aria-labelledby="announcements-title">
    <div class="portal-section-heading"><div><div class="portal-eyebrow">Stay informed</div><h2 id="announcements-title">News &amp; announcements</h2></div></div>
    <div class="portal-information-grid">
      <article class="portal-information-card"><span class="portal-card-label">BARANGAY UPDATES</span><h3>Official announcements</h3><p>Wala pang inilalathalang anunsiyo ang Barangay Anabu I-G sa portal na ito. Bumalik dito para sa mga susunod na update.</p></article>
      <article class="portal-information-card"><span class="portal-card-label">CITY UPDATES</span><h3>Balita mula sa Lungsod ng Imus</h3><p>Basahin ang mga pinakabagong balita at abiso sa opisyal na website ng pamahalaang lungsod.</p><a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer">Visit City of Imus news <span aria-hidden="true">&nearr;</span></a></article>
    </div>
  </section>

  <section class="portal-community-section" id="contacts" aria-labelledby="contacts-title">
    <div class="portal-section-heading"><div><div class="portal-eyebrow">Help when you need it</div><h2 id="contacts-title">Contacts &amp; emergency hotlines</h2></div><a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer">Source: City of Imus <span aria-hidden="true">&nearr;</span></a></div>
    <div class="portal-contact-grid">
      <article class="portal-information-card"><span class="portal-card-label">BARANGAY OFFICE</span><h3>Barangay Anabu I-G</h3><p>Anabu I-G, City of Imus, Cavite</p><p>The barangay phone number is awaiting official confirmation. Please visit the office for resident account or document assistance.</p></article>
      <article class="portal-information-card"><span class="portal-card-label">EMERGENCY</span><h3>National emergency hotline</h3><a class="portal-contact-number" href="tel:911">911</a><p>For urgent police, fire, or medical assistance.</p></article>
      <article class="portal-information-card"><span class="portal-card-label">CITY RESPONSE</span><h3>City of Imus emergency</h3><a class="portal-contact-number" href="tel:+63468889911">(046) 888 9911</a><p>City government emergency hotline.</p></article>
      <article class="portal-information-card"><span class="portal-card-label">POLICE</span><h3>Imus PNP</h3><a class="portal-contact-number" href="tel:+639985985601">0998 598 5601</a><p>Imus Philippine National Police.</p></article>
      <article class="portal-information-card"><span class="portal-card-label">FIRE</span><h3>Bureau of Fire Protection</h3><a class="portal-contact-number" href="tel:+639155283256">0915 528 3256</a><p>Fire response number listed by the City of Imus.</p></article>
    </div>
  </section>

  <section class="portal-community-section" id="location" aria-labelledby="location-title">
    <div class="portal-section-heading"><div><div class="portal-eyebrow">Find us</div><h2 id="location-title">Barangay location</h2></div><a href="https://www.google.com/maps/search/?api=1&amp;query=Barangay+Anabu+I-G%2C+Imus%2C+Cavite" target="_blank" rel="noopener noreferrer">Open full map <span aria-hidden="true">&nearr;</span></a></div>
    <div class="portal-map-panel"><iframe title="Map search for Barangay Anabu I-G, Imus, Cavite" src="https://maps.google.com/maps?q=Barangay%20Anabu%20I-G%2C%20Imus%2C%20Cavite&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe><p>Barangay Anabu I-G, City of Imus, Cavite. Confirm the exact office entrance before visiting.</p></div>
  </section>

  <section class="portal-community-section portal-links-section" id="quick-links" aria-labelledby="quick-links-title">
    <div><div class="portal-section-heading"><div><div class="portal-eyebrow">Get things done</div><h2 id="quick-links-title">Quick links</h2></div></div><div class="portal-link-list"><a href="{{ route('portal.request.create') }}">Request a document <span aria-hidden="true">&rarr;</span></a><a href="{{ route('portal.information') }}#requirements">Document requirements <span aria-hidden="true">&rarr;</span></a><a href="{{ auth('resident')->check() ? route('portal.account') : route('portal.login') }}">My resident account <span aria-hidden="true">&rarr;</span></a></div></div>
    <div><div class="portal-section-heading"><div><div class="portal-eyebrow">Official resources</div><h2>Government links</h2></div></div><div class="portal-link-list"><a href="https://cityofimus.gov.ph/" target="_blank" rel="noopener noreferrer">City Government of Imus <span aria-hidden="true">&nearr;</span></a><a href="https://psa.gov.ph/" target="_blank" rel="noopener noreferrer">Philippine Statistics Authority <span aria-hidden="true">&nearr;</span></a><a href="https://www.officialgazette.gov.ph/" target="_blank" rel="noopener noreferrer">Official Gazette <span aria-hidden="true">&nearr;</span></a></div></div>
  </section>

  <section class="portal-guidance" aria-label="How the portal works"><div><span class="portal-guidance-label">HOW IT WORKS</span><p><strong>Sign in</strong> with your resident account, <strong>submit</strong> your document request, then <strong>follow up</strong> with your reference number.</p></div><div class="portal-guidance-hours"><span>BARANGAY OFFICE HOURS</span><strong>Confirm before visiting</strong></div></section>

</div><!-- /container -->

<footer class="footer portal-footer"><div><strong>Barangay Anabu I-G</strong><span>City of Imus, Cavite &middot; Resident Services Portal</span></div><a href="{{ route('portal.information') }}#help">Resident assistance &rarr;</a></footer>
<script src="{{ asset('js/resident-account.js') }}?v={{ filemtime(public_path('js/resident-account.js')) }}"></script>
</body>
</html>

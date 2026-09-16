<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>SmartBrgy Portal - Document Request | Barangay Anabu I-G</title>
<link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body>

<div id="toast-wrap"></div>
<div id="loader" style="display:none;"><div class="spinner"></div><div style="font-size:13px;color:var(--text-muted);">Sandali lang...</div></div>

<!-- HEADER -->
<div class="header">
  <div class="header-seal"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G"></div>
  <div class="header-info">
    <h1>SmartBrgy Online Portal</h1>
    <p>Barangay Anabu I-G, Imus City, Cavite</p>
  </div>
  <div class="header-badge">🌐 Online</div>
  <div id="portal-theme-toggle" onclick="togglePortalTheme()">
    <span id="portal-theme-icon">🌙</span>
    <span id="portal-theme-label">Dark Mode</span>
  </div>
</div>

<div class="container">

  <!-- ═══════ SCREEN 1: WELCOME + TERMS AND CONDITIONS ═══════ -->
  <div class="screen active" id="screen-terms">
    <div class="steps">
      <div class="step active" id="s1"><div class="step-circle">1</div><div class="step-label">Kasunduan</div></div>
      <div class="step" id="s2"><div class="step-circle">2</div><div class="step-label">Dokumento</div></div>
      <div class="step" id="s3"><div class="step-circle">3</div><div class="step-label">Impormasyon</div></div>
      <div class="step" id="s4"><div class="step-circle">4</div><div class="step-label">Attachment</div></div>
      <div class="step" id="s5"><div class="step-circle">5</div><div class="step-label">Kumpirmasyon</div></div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>📋 Mga Tuntunin at Kundisyon</h2>
        <p>Basahin nang mabuti bago mag-request ng dokumento</p>
      </div>
      <div class="card-body">
        <div class="alert alert-yellow">
          <span style="font-size:18px;flex-shrink:0;">⚠️</span>
          <div><strong>Mahalaga:</strong> Ang inyong personal na impormasyon ay pinangangalagaan ng Barangay Anabu I-G alinsunod sa Republic Act 10173 (Data Privacy Act ng 2012). Basahin ang mga sumusunod bago magpatuloy.</div>
        </div>

        <div class="tnc-box" id="tnc-scroll">
          <h3>📜 KASUNDUAN SA PAGGAMIT NG SMARTBRGY ONLINE PORTAL</h3>
          <p><em>Barangay Anabu I-G, Imus City, Cavite — Bersyon 1.0, Mayo 2025</em></p>

          <h4>ARTIKULO I — LAYUNIN</h4>
          <p>Ang SmartBrgy Online Portal ay isang serbisyong elektroniko ng Barangay Anabu I-G na nagbibigay-daan sa mga residente na makapag-request ng iba't ibang dokumento nang hindi na kailangang pumunta pa sa barangay hall bilang unang hakbang. Ang layunin nito ay pabilisin at gawing mas madali ang proseso ng pagkuha ng mga barangay dokumento para sa lahat ng residente.</p>

          <h4>ARTIKULO II — SAKLAW NG SERBISYO</h4>
          <p>Maaaring i-request sa pamamagitan ng portal na ito ang mga sumusunod na dokumento:</p>
          <ol>
            <li>Barangay Clearance</li>
            <li>Certificate of Residency (Patunay ng Paninirahan)</li>
            <li>Certificate of Indigency (Patunay ng Kahirapan)</li>
            <li>Barangay ID</li>
            <li>First Time Jobseeker Certificate</li>
            <li>Business Clearance</li>
          </ol>
          <p><strong>Tandaan:</strong> Ang online na pag-request ay para lamang sa pagpapahayag ng interes. Ang aktwal na pagpapalabas ng dokumento ay isasagawa lamang sa Barangay Hall pagkatapos ng personal na pagbisita at pagberipika ng mga kinakailangang dokumento.</p>

          <h4>ARTIKULO III — MGA KINAKAILANGAN</h4>
          <p>Upang maging karapat-dapat sa pagkuha ng dokumento, ang residente ay dapat:</p>
          <ol>
            <li>Isang tunay na residente ng Barangay Anabu I-G, Imus City;</li>
            <li>Walang anumang nakasamang blotter o kaso na hindi pa nalulutas (para sa ilang uri ng dokumento);</li>
            <li>Magbigay ng totoong impormasyon sa lahat ng kinakailangang patlang;</li>
            <li>Personal na pumunta sa Barangay Hall upang kunin ang dokumento.</li>
          </ol>

          <h4>ARTIKULO IV — KOLEKSYON NG PERSONAL NA DATOS</h4>
          <p>Ang Barangay Anabu I-G, bilang Controller ng inyong personal na datos ayon sa RA 10173 (Data Privacy Act of 2012), ay nangangalap ng sumusunod na impormasyon:</p>
          <ul>
            <li>Buong pangalan</li>
            <li>Address sa barangay (Purok)</li>
            <li>Email address para sa mga notification</li>
            <li>Layunin ng paghingi ng dokumento</li>
          </ul>

          <h4>ARTIKULO V — PAANO GINAGAMIT ANG INYONG DATOS</h4>
          <p>Ang inyong personal na impormasyon ay gagamitin lamang para sa:</p>
          <ol>
            <li>Pagpoproseso ng inyong request na dokumento;</li>
            <li>Pag-verify ng inyong pagkatao sa oras ng pagkuha sa barangay hall;</li>
            <li>Pagpapadala ng update tungkol sa status ng inyong request sa inyong email address;</li>
            <li>Mga opisyal na rekord ng barangay na kinakailangan ng batas.</li>
          </ol>
          <p><strong>HINDI</strong> ibabahagi ang inyong impormasyon sa mga third party na walang kaugnayan sa serbisyo ng barangay.</p>

          <h4>ARTIKULO VI — SEGURIDAD NG DATOS</h4>
          <p>Ang Barangay Anabu I-G ay gumagamit ng mga teknikal at administratibong hakbang upang pangalagaan ang inyong personal na impormasyon laban sa anumang hindi awtorisadong pag-access, pagbabago, o pagsisiwalat. Ang lahat ng datos ay nakaimbak sa isang ligtas na sistema na may restricted na access.</p>

          <h4>ARTIKULO VII — MGA KARAPATAN NG DATOS-SUBJEK</h4>
          <p>Ayon sa RA 10173, mayroon kayong karapatang:</p>
          <ul>
            <li>Malaman kung anong personal na datos ang hawak ng barangay tungkol sa inyo;</li>
            <li>Makuha ang kopya ng inyong personal na datos;</li>
            <li>Ituwid ang anumang maling impormasyon;</li>
            <li>Humiling ng pagtanggal ng inyong datos (kung naaangkop at pinahihintulutan ng batas);</li>
            <li>Mag-file ng reklamo sa National Privacy Commission (NPC) kung naniniwala kayong nilabag ang inyong mga karapatan.</li>
          </ul>

          <h4>ARTIKULO VIII — RESPONSIBILIDAD NG GUMAGAMIT</h4>
          <p>Sa paggamit ng portal na ito, kayo ay sumasang-ayon na:</p>
          <ol>
            <li>Ang lahat ng impormasyon na inyong ibibigay ay totoong impormasyon at hindi mapanlinlang;</li>
            <li>Hindi ninyo gagamitin ang portal para sa anumang ilegal o maling layunin;</li>
            <li>Mauunawaan na ang online request ay hindi ginagarantiyahan ang agarang pagpapalabas ng dokumento — napapailalim ito sa verification ng barangay staff;</li>
            <li>Responsibilidad ninyo ang personal na pumunta sa Barangay Hall upang makuha ang dokumento at magdala ng kinakailangang valid ID.</li>
          </ol>

          <h4>ARTIKULO IX — BAYAD SA DOKUMENTASYON</h4>
          <p>Ang ilang mga dokumento ay may kaukulang bayad na babayaran sa Barangay Hall sa oras ng pagkuha. Hindi tinatanggap ang bayad sa pamamagitan ng online na pamamaraan. Ang kasalukuyang bayad ay nakalagay sa bawat uri ng dokumento sa portal na ito.</p>

          <h4>ARTIKULO X — VALIDITY NG REQUEST</h4>
          <p>Ang online request ay magiging valid sa loob ng <strong>30 araw</strong> mula sa petsa ng pagsusumite. Kung hindi kayo makapunta sa loob ng panahong ito, kailangan ninyong mag-request muli.</p>

          <h4>ARTIKULO XI — PAKIKIPAG-UGNAYAN</h4>
          <p>Para sa anumang katanungan o alalahanin, maaari kayong makipag-ugnayan sa:</p>
          <ul>
            <li>📍 Barangay Hall, Barangay Anabu I-G, Imus City, Cavite</li>
            <li>🕗 Lunes hanggang Biyernes: 8:00 AM – 5:00 PM</li>
          </ul>

          <p style="margin-top:16px;font-size:11px;color:var(--text-muted);">Huling na-update: Mayo 2025 | Bersyon 1.0 | Barangay Anabu I-G</p>
        </div>

        <div class="tnc-scroll-notice" id="tnc-notice">⬇️ Mag-scroll pababa upang mabasa ang lahat ng tuntunin bago makumpirma</div>

        <div class="checkbox-row">
          <input type="checkbox" id="tnc-agree" disabled onchange="onTncCheck()"/>
          <label for="tnc-agree">
            Nabasa ko at naunawaan ko ang mga <strong>Tuntunin at Kundisyon</strong> ng SmartBrgy Portal. Sumasang-ayon ako sa koleksyon at paggamit ng aking personal na impormasyon alinsunod sa <strong>Republic Act 10173 (Data Privacy Act of 2012)</strong> at sa mga patakaran ng Barangay Anabu I-G.
          </label>
        </div>

        <button class="btn btn-green btn-full" id="btn-proceed-terms" onclick="proceedFromTerms()" disabled>
          ✅ Sumasang-ayon Ako — Magpatuloy
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════ SCREEN 2: PUMILI NG DOKUMENTO ═══════ -->
  <div class="screen" id="screen-doctype">
    <div class="steps">
      <div class="step done" id="s1b"><div class="step-circle">✓</div><div class="step-label">Kasunduan</div></div>
      <div class="step active" id="s2b"><div class="step-circle">2</div><div class="step-label">Dokumento</div></div>
      <div class="step" id="s3b"><div class="step-circle">3</div><div class="step-label">Impormasyon</div></div>
      <div class="step" id="s4b"><div class="step-circle">4</div><div class="step-label">Attachment</div></div>
      <div class="step" id="s5b"><div class="step-circle">5</div><div class="step-label">Kumpirmasyon</div></div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>📄 Piliin ang Uri ng Dokumento</h2>
        <p>I-click ang dokumentong gusto ninyong i-request</p>
      </div>
      <div class="card-body">
        <div class="cert-grid" id="cert-type-grid">
          <div class="cert-btn" onclick="selectDoc('BC',this)"><div class="icon">📄</div><div class="label">Barangay Clearance</div><div class="fee">PHP 50.00</div><div class="days">✓ 1 araw</div></div>
          <div class="cert-btn" onclick="selectDoc('CR',this)"><div class="icon">🏠</div><div class="label">Certificate of Residency</div><div class="fee">PHP 50.00</div><div class="days">✓ 1 araw</div></div>
          <div class="cert-btn" onclick="selectDoc('CI',this)"><div class="icon">📋</div><div class="label">Certificate of Indigency</div><div class="fee">Libre</div><div class="days">✓ 1 araw</div></div>
          <div class="cert-btn" onclick="selectDoc('BID',this)"><div class="icon">🪪</div><div class="label">Barangay ID</div><div class="fee">PHP 100.00</div><div class="days">✓ 3-5 araw</div></div>
          <div class="cert-btn" onclick="selectDoc('CTFJ',this)"><div class="icon">💼</div><div class="label">First Time Jobseeker</div><div class="fee">Libre</div><div class="days">✓ 1 araw</div></div>
          <div class="cert-btn" onclick="selectDoc('BBC',this)"><div class="icon">🏪</div><div class="label">Business Clearance</div><div class="fee">PHP 200.00+</div><div class="days">✓ 3-5 araw</div></div>
        </div>

        <div id="doc-selected-info" style="display:none;" class="alert alert-green">
          <span id="doc-sel-icon" style="font-size:18px;flex-shrink:0;"></span>
          <div>Napili: <strong id="doc-sel-label"></strong> — <span id="doc-sel-fee" style="color:var(--green-dark);"></span></div>
        </div>

        <button class="btn btn-green btn-full" id="btn-proceed-doc" onclick="proceedFromDoc()" disabled>
          Susunod →
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════ SCREEN 3: FORM ═══════ -->
  <div class="screen" id="screen-form">
    <div class="steps">
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Kasunduan</div></div>
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Dokumento</div></div>
      <div class="step active"><div class="step-circle">3</div><div class="step-label">Impormasyon</div></div>
      <div class="step"><div class="step-circle">4</div><div class="step-label">Attachment</div></div>
      <div class="step"><div class="step-circle">5</div><div class="step-label">Kumpirmasyon</div></div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>✍️ Punan ang Impormasyon</h2>
        <p>Tiyaking tama ang lahat ng inyong impormasyon</p>
      </div>
      <div class="card-body">
        <div class="alert alert-blue" style="margin-bottom:16px;">
          <span style="font-size:16px;flex-shrink:0;">📄</span>
          <div>Nire-request: <strong id="form-doc-label"></strong> — <span id="form-doc-fee" style="font-weight:700;color:var(--blue);"></span></div>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-name">Buong Pangalan <span class="req">*</span></label>
          <input class="form-input" id="f-name" placeholder="Halimbawa: Juan dela Cruz" autocomplete="name"/>
          <div class="form-note">Isulat ang inyong buong pangalan tulad ng nakasaad sa inyong valid ID</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-address">Address sa Barangay <span class="req">*</span></label>
          <input class="form-input" id="f-address" placeholder="Purok, Sityo o Street — Barangay Anabu I-G"/>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-email">Email Address <span class="req">*</span> <span style="font-weight:400;color:#6b7280;">(para sa notification)</span></label>
          <input class="form-input" id="f-email" placeholder="example@gmail.com" type="email" autocomplete="email" required/>
          <div class="form-note">Dito ipapadala ang mga update tungkol sa inyong request</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-dob">Petsa ng Kapanganakan <span class="req">*</span></label>
          <input class="form-input" id="f-dob" type="date" required/>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-purpose">Layunin / Dahilan <span class="req">*</span></label>
          <input class="form-input" id="f-purpose" placeholder="Halimbawa: Para sa trabaho, scholarship, loan, business..."/>
        </div>

        <div id="business-field" style="display:none;" class="form-group">
          <label class="form-label" for="f-business">Pangalan ng Negosyo <span class="req">*</span></label>
          <input class="form-input" id="f-business" placeholder="Pangalan ng inyong negosyo"/>
        </div>

        <div class="alert alert-yellow" style="margin:16px 0 10px;">
          <span style="font-size:16px;flex-shrink:0;">🔒</span>
          <div><strong>Paalala sa Privacy:</strong> Ang inyong impormasyon ay mahigpit na kumpidensyal at gagamitin lamang para sa pagpoproseso ng inyong request ayon sa RA 10173.</div>
        </div>

        <div class="btn-row">
          <button class="btn btn-outline" onclick="goBack('screen-doctype')">← Bumalik</button>
          <button class="btn btn-green" onclick="goToAttachment()">Susunod → Attachment</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════ SCREEN 4: ATTACHMENT ═══════ -->
  <div class="screen" id="screen-attachment">
    <div class="steps">
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Kasunduan</div></div>
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Dokumento</div></div>
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Impormasyon</div></div>
      <div class="step active"><div class="step-circle">4</div><div class="step-label">Attachment</div></div>
      <div class="step"><div class="step-circle">5</div><div class="step-label">Kumpirmasyon</div></div>
    </div>
    <div class="card">
      <div class="card-header">
        <h2>🪪 I-upload ang Inyong Valid ID</h2>
        <p>Mag-upload ng larawan ng inyong valid ID bilang patunay ng pagkakakilanlan</p>
      </div>
      <div class="card-body">
        <div class="alert alert-blue" style="margin-bottom:16px;">
          <span style="font-size:16px;flex-shrink:0;">ℹ️</span>
          <div>Ang attachment ay <strong>optional</strong> ngunit nirerekomenda para mapabilis ang pagpoproseso ng inyong request.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Larawan ng Valid ID <span style="font-weight:400;color:#6b7280;">(JPG, PNG, WebP — max 5MB)</span></label>
          <div id="att-dropzone" style="border:2px dashed var(--border);border-radius:12px;padding:32px 16px;text-align:center;cursor:pointer;transition:border-color .2s;" onclick="document.getElementById('f-attachment').click()" ondragover="event.preventDefault();this.style.borderColor='var(--green)'" ondragleave="this.style.borderColor='var(--border)'" ondrop="handleAttachmentDrop(event)">
            <div style="font-size:36px;margin-bottom:8px;">🪪</div>
            <div style="font-size:13px;font-weight:600;color:var(--text-main);margin-bottom:4px;">I-click o i-drag ang larawan dito</div>
            <div style="font-size:11px;color:var(--text-sub);">JPG, PNG, WebP — hanggang 5MB</div>
          </div>
          <input type="file" id="f-attachment" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previewAttachment(this)"/>
        </div>

        <div id="att-preview" style="display:none;margin-top:12px;text-align:center;">
          <img id="att-preview-img" src="" alt="ID Preview" style="max-width:100%;max-height:220px;border-radius:10px;border:1px solid var(--border);object-fit:contain;"/>
          <div style="margin-top:8px;font-size:12px;color:var(--green);font-weight:600;" id="att-preview-name"></div>
          <button class="btn btn-outline" style="margin-top:8px;font-size:11px;" onclick="clearAttachment()">✕ Alisin</button>
        </div>

        <div class="btn-row" style="margin-top:20px;">
          <button class="btn btn-outline" onclick="goBack('screen-form')">← Bumalik</button>
          <button class="btn btn-green" onclick="submitRequest()">📨 I-submit ang Request</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════ SCREEN 5: KUMPIRMASYON ═══════ -->
  <div class="screen" id="screen-confirm">
    <div class="steps">
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Kasunduan</div></div>
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Dokumento</div></div>
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Impormasyon</div></div>
      <div class="step done"><div class="step-circle">✓</div><div class="step-label">Attachment</div></div>
      <div class="step active"><div class="step-circle">5</div><div class="step-label">Kumpirmasyon</div></div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="confirm-box">
          <div class="confirm-icon">✅</div>
          <h2 style="font-size:20px;font-weight:800;color:var(--green);margin-bottom:6px;">Request na Na-submit!</h2>
          <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">Ang inyong reference code ay:</p>
          <div class="confirm-code" id="conf-code">REQ-0000</div>
          <div class="confirm-note">
            <strong>I-screenshot o isulat ang code na ito.</strong><br/>
            Ibibigay ito sa Barangay Hall pagdating ninyo para makuha ang inyong dokumento.
          </div>

          <div class="confirm-steps">
            <p style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:8px;">📋 Susunod na Hakbang:</p>
            <ol>
              <li>Hintayin ang email update mula sa barangay tungkol sa status ng iyong request</li>
              <li>Pumunta sa <strong>Barangay Hall, Anabu I-G</strong> sa loob ng 30 araw</li>
              <li>Magdala ng <strong>1 valid government ID</strong></li>
              <li>Ipakita ang reference code: <strong id="conf-code-mini"></strong></li>
              <li>Bayaran ang kaukulang bayad (kung applicable)</li>
            </ol>
          </div>

          <div id="conf-summary" class="alert alert-green" style="text-align:left;margin-bottom:16px;"></div>

          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <button class="btn btn-outline" onclick="goToStatus()">🔍 I-check ang Status</button>
            <button class="btn btn-green" onclick="newRequest()">➕ Bagong Request</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════ SCREEN 5: STATUS CHECKER ═══════ -->
  <div class="screen" id="screen-status">
    <div class="card">
      <div class="card-header">
        <h2>🔍 I-check ang Status ng Request</h2>
        <p>Ilagay ang inyong reference code upang makita ang status</p>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label" for="status-code">Reference Code <span class="req">*</span></label>
          <div style="display:flex;gap:8px;">
            <input class="form-input" id="status-code" placeholder="Halimbawa: REQ-2025-0001" style="flex:1;" onkeydown="if(event.key==='Enter')checkStatus()"/>
            <button class="btn btn-green" onclick="checkStatus()">🔍 Hanapin</button>
          </div>
        </div>
        <div id="status-result" style="display:none;"></div>
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);text-align:center;">
          <button class="btn btn-outline" onclick="showScreen('screen-terms')">← Bumalik sa Simula</button>
        </div>
      </div>
    </div>
  </div>

</div><!-- /container -->

<!-- QUICK NAV FOOTER -->
<div class="footer">
  <div style="margin-bottom:8px;">
    <button class="btn btn-outline" style="font-size:11px;padding:6px 14px;" onclick="showScreen('screen-status')">🔍 I-check ang Status</button>
  </div>
  🏛️ Barangay Anabu I-G, Imus City, Cavite &nbsp;|&nbsp; SmartBrgy Portal v2.0
  <br>Ang lahat ng personal na impormasyon ay pinoprotektahan ng RA 10173 (Data Privacy Act of 2012)
</div>

<script>
const API = '';
const DOC_TYPES = {
  'BC':   { label:'Barangay Clearance',       icon:'📄', fee:'PHP 50.00',   days:'1 araw' },
  'CR':   { label:'Certificate of Residency', icon:'🏠', fee:'PHP 50.00',   days:'1 araw' },
  'CI':   { label:'Certificate of Indigency', icon:'📋', fee:'Libre',       days:'1 araw' },
  'BID':  { label:'Barangay ID',              icon:'🪪', fee:'PHP 100.00',  days:'3-5 araw' },
  'CTFJ': { label:'First Time Jobseeker',     icon:'💼', fee:'Libre',       days:'1 araw' },
  'BBC':  { label:'Business Clearance',       icon:'🏪', fee:'PHP 200.00+', days:'3-5 araw' },
};

let selectedDocId = null;
let tncScrolled = false;
let lastCode = '';
let _portalDark = false;

// ── Session persistence ──
const _SK = 'smartbrgy_session';

const tncBox = document.getElementById('tnc-scroll');

tncBox.addEventListener('scroll', function () {
    if (this.scrollTop + this.clientHeight >= this.scrollHeight - 30) {
        tncScrolled = true;

        const checkbox = document.getElementById('tnc-agree');
        const notice = document.getElementById('tnc-notice');

        checkbox.disabled = false;

        notice.innerHTML =
            '✅ Nabasa mo na ang lahat ng tuntunin — maaari nang magpatuloy';

        notice.style.background = '#d1fae5';
        notice.style.borderColor = '#6ee7b7';
        notice.style.color = '#065f46';

        _saveSession();
    }
});

function onTncCheck() {
    const checkbox = document.getElementById('tnc-agree');
    const button = document.getElementById('btn-proceed-terms');

    button.disabled = !checkbox.checked;

    _saveSession();
}

function proceedFromTerms() {
    const checkbox = document.getElementById('tnc-agree');

    if (!checkbox.checked) return;

    showScreen('screen-doctype');
}

document.addEventListener('DOMContentLoaded', () => {
  const terms = document.getElementById('tnc-scroll');
  if (terms) {
    terms.innerHTML = `
      <h3>SmartBrgy Online Portal Terms and Conditions</h3>
      <p><em>Barangay Anabu I-G, Imus City, Cavite - Version 1.0, May 2025</em></p>
      <h4>Purpose</h4>
      <p>The SmartBrgy Online Portal allows residents to submit initial requests for barangay documents online. Submission through this portal does not automatically release a document; barangay staff must still verify the request and supporting information.</p>
      <h4>Available Services</h4>
      <ol><li>Barangay Clearance</li><li>Certificate of Residency</li><li>Certificate of Indigency</li><li>Barangay ID</li><li>First Time Jobseeker Certificate</li><li>Business Clearance</li></ol>
      <h4>Eligibility and Verification</h4>
      <p>You must be a resident of Barangay Anabu I-G, provide accurate information, and personally visit the Barangay Hall when required. Some documents may require good standing or additional validation before release.</p>
      <h4>Data Privacy</h4>
      <p>Barangay Anabu I-G collects your name, barangay address, email address, requested document, and purpose only for processing, verification, status updates, and official barangay records. Your information will be handled under Republic Act 10173, the Data Privacy Act of 2012.</p>
      <h4>Your Responsibilities</h4>
      <ol><li>Provide true and complete information.</li><li>Do not use the portal for false, misleading, or unlawful requests.</li><li>Understand that online submission is subject to staff review and does not guarantee immediate approval.</li><li>Bring a valid ID and required documents when claiming the requested document.</li></ol>
      <h4>Fees and Validity</h4>
      <p>Applicable document fees are paid at the Barangay Hall. Online requests remain valid for 30 days from submission. After that period, a new request may be required.</p>
      <h4>Contact</h4>
      <p>For questions, visit Barangay Hall, Barangay Anabu I-G, Imus City, Cavite, Monday to Friday, 8:00 AM to 5:00 PM.</p>
      <p style="margin-top:16px;font-size:11px;color:var(--text-muted);">Last updated: May 2025 | Version 1.0 | Barangay Anabu I-G</p>`;
  }
  const title = document.querySelector('#screen-terms .card-header h2');
  const subtitle = document.querySelector('#screen-terms .card-header p');
  const notice = document.getElementById('tnc-notice');
  const label = document.querySelector('label[for="tnc-agree"]');
  if (title) title.textContent = 'Terms and Conditions';
  if (subtitle) subtitle.textContent = 'Please read carefully before requesting a document';
  if (notice) notice.textContent = 'Scroll down to read all terms before confirming';
  if (label) label.innerHTML = 'I have read and understood the <strong>Terms and Conditions</strong> of the SmartBrgy Portal. I agree to the collection and use of my personal information under <strong>Republic Act 10173 (Data Privacy Act of 2012)</strong> and Barangay Anabu I-G policies.';
  const proceed = document.getElementById('btn-proceed-terms');
  if (proceed) proceed.textContent = 'I Agree - Continue';

  // Auto-save form fields on every keystroke
  ['f-name','f-address','f-email','f-purpose','f-business','f-dob'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', _saveSession);
  });

  // Restore session state after all DOM setup is done
  _restoreSession();
});
</script>
<script src="{{ asset('js/portal-form.js')  }}" ></script>
<script src="{{ asset('js/portal-session.js')  }}" ></script>
<script src="{{ asset('js/attachments.js')  }}" ></script>
<script src="{{ asset('js/portal-ui.js')  }}" ></script>
<script src="{{ asset('js/document-request.js')  }}" ></script>
<script src="{{ asset('js/status-checker.js')  }}" ></script>
</body>
</html>

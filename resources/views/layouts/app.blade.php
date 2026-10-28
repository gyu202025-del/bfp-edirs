<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — BFP Web GIS Emergency Dispatch System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>

@include('partials.navbar')
@include('partials.sidebar')

<main class="app-main" id="appMain">
    @yield('content')
    @include('partials.footer')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Sidebar collapse / mobile toggle
    document.getElementById('menuToggle')?.addEventListener('click', function () {
        const sidebar = document.querySelector('.sidebar');
        const main = document.getElementById('appMain');
        if (window.innerWidth <= 992) {
            sidebar.classList.toggle('open');
        } else {
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
        }
    });
</script>

{{-- ============ GLOBAL ALERT BANNER ============ --}}
<div id="alertBanner" style="
    display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 2900;
    background: #B3251A; color: #fff; align-items: center; justify-content: center;
    gap: 1rem; padding: .7rem; font-size: .88rem; flex-wrap: wrap;
">
    <span id="alertBannerText">⚠️ New report(s) awaiting acknowledgment</span>
    <span id="soundBlockedHint" style="display:none; font-size:.78rem; opacity:.9;">
        🔇 Sound is blocked by the browser. Click anywhere to turn it on.
    </span>
    <button id="viewDetailsBtn" class="btn btn-sm btn-outline-light">View Details</button>
    <button id="acknowledgeAllBtn" class="btn btn-sm btn-light">Acknowledge All</button>
</div>

{{-- ============ NEW REPORT DETAIL BOARD ============ --}}
<div class="modal fade" id="newReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white" id="nrHeader" style="background:#B3251A;">
                <h5 class="modal-title mb-0">
                    🚨 New Incident Report
                    <span id="nrCounter" class="badge bg-light text-dark ms-2"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <span id="nrCategory" class="badge bg-danger fs-6"></span>
                            <span id="nrNumber" class="ms-2 fw-semibold"></span>
                        </div>
                        <dl class="row mb-0 small">
                            <dt class="col-4">Reported</dt>   <dd class="col-8" id="nrDate"></dd>
                            <dt class="col-4">Municipality</dt><dd class="col-8" id="nrMunicipality"></dd>
                            <dt class="col-4">Barangay</dt>   <dd class="col-8" id="nrBarangay"></dd>
                            <dt class="col-4">Landmark</dt>   <dd class="col-8" id="nrLandmark"></dd>
                            <dt class="col-4">Description</dt><dd class="col-8" id="nrDescription"></dd>
                            <dt class="col-4">Reporter</dt>   <dd class="col-8" id="nrReporter"></dd>
                            <dt class="col-4">Contact</dt>    <dd class="col-8" id="nrContact"></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <div id="nrMap" style="height:260px; border-radius:8px; border:1px solid #dee2e6;"></div>
                        <div id="nrNoMap" class="text-muted small d-none">No location pin on this report.</div>
                    </div>
                </div>
                <div class="alert alert-warning small mt-3 mb-0">
                    Acknowledging stops the alert and sends the nearest active station in this municipality.
                </div>
            </div>
            <div class="modal-footer">
                <div class="btn-group me-auto">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="nrPrev">&laquo; Prev</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="nrNext">Next &raquo;</button>
                </div>
                <a href="#" id="nrMapLink" class="btn btn-outline-primary btn-sm">View on GIS Map</a>
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger btn-sm" id="nrAckBtn">Acknowledge</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const POLL_MS = 1000;   // how often to check for new reports, in milliseconds
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const READ_URL = @json(route('notifications.markRead', ['notification' => '__ID__']));
    const MAP_URL  = @json(route('gis.map'));

    let items = [];              // unread notifications (newest first), each with .incident
    let currentId = null;        // notification shown on the board
    let boardOpen = false;
    let polling = false;
    const known = new Set();     // ids already handled on this page

    // Ids the user closed the board on. Remembered per browser tab so the board
    // does not pop up again on every page change.
    let dismissed = new Set();
    try { dismissed = new Set(JSON.parse(sessionStorage.getItem('dismissedReports') || '[]')); } catch (e) {}
    function saveDismissed() {
        try { sessionStorage.setItem('dismissedReports', JSON.stringify(Array.from(dismissed))); } catch (e) {}
    }

    // ---------------- Audio ----------------
    let audioCtx = null;
    let sirenNodes = null;
    let beepTimer = null;

    function audioReady() {
        return audioCtx && audioCtx.state === 'running';
    }

    function initAudio() {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return;
        audioCtx = new AC();
        audioCtx.onstatechange = function () { syncSound(); };
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().catch(function () {});
        }
    }

    // Any interaction lets the browser start (or restart) the sound.
    ['click', 'keydown', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, function () {
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume().catch(function () {});
            }
        }, { passive: true });
    });

    // Fire: one continuous siren (a tone swept up and down by a slow oscillator)
    function startSiren() {
        if (sirenNodes || !audioReady()) return;
        const osc = audioCtx.createOscillator();
        const lfo = audioCtx.createOscillator();
        const lfoGain = audioCtx.createGain();
        const gain = audioCtx.createGain();

        osc.type = 'sawtooth';
        osc.frequency.value = 750;
        lfo.type = 'triangle';
        lfo.frequency.value = 0.7;
        lfoGain.gain.value = 250;
        gain.gain.value = 0.35;

        lfo.connect(lfoGain);
        lfoGain.connect(osc.frequency);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        lfo.start();
        sirenNodes = { osc: osc, lfo: lfo };
    }

    function stopSiren() {
        if (!sirenNodes) return;
        try { sirenNodes.osc.stop(); sirenNodes.lfo.stop(); } catch (e) {}
        sirenNodes = null;
    }

    // Other categories: a double beep every 8 seconds
    function playBeepPair() {
        if (!audioReady()) return;
        [0, 0.25].forEach(function (delay) {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.25, audioCtx.currentTime + delay);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + delay + 0.2);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(audioCtx.currentTime + delay);
            osc.stop(audioCtx.currentTime + delay + 0.2);
        });
    }

    function startBeeps() {
        if (beepTimer || !audioReady()) return;
        playBeepPair();
        beepTimer = setInterval(playBeepPair, 8000);
    }

    function stopBeeps() {
        clearInterval(beepTimer);
        beepTimer = null;
    }

    function syncSound() {
        const categories = items.map(function (n) { return n.category; });
        const hasUnread = categories.length > 0;
        const hasFire = categories.indexOf('Fire') !== -1;

        if (!hasUnread) {
            stopSiren();
            stopBeeps();
        } else if (hasFire) {
            stopBeeps();
            startSiren();
        } else {
            stopSiren();
            startBeeps();
        }

        const hint = document.getElementById('soundBlockedHint');
        if (hint) hint.style.display = (hasUnread && !audioReady()) ? 'inline' : 'none';
    }

    // ---------------- Banner ----------------
    function renderBanner() {
        const banner = document.getElementById('alertBanner');
        const text = document.getElementById('alertBannerText');
        const count = items.length;
        if (count > 0) {
            text.textContent = '⚠️ ' + count + ' new report' + (count > 1 ? 's' : '') + ' awaiting acknowledgment';
            banner.style.display = 'flex';
        } else {
            banner.style.display = 'none';
        }
        syncSound();
    }

    // ---------------- Detail board ----------------
    const nrEl = document.getElementById('newReportModal');
    const nrModal = (typeof bootstrap !== 'undefined') ? bootstrap.Modal.getOrCreateInstance(nrEl) : null;
    let nrMap = null, nrMarker = null;

    function setText(id, value) {
        // textContent on purpose: report text comes from the public and must never be treated as HTML
        const v = (value === null || value === undefined || String(value).trim() === '') ? '—' : String(value);
        document.getElementById(id).textContent = v;
    }

    function headerColor(category) {
        const c = (category || '').toLowerCase();
        if (c.indexOf('fire') !== -1) return '#B3251A';
        if (c.indexOf('medical') !== -1) return '#2E7D32';
        if (c.indexOf('crime') !== -1 || c.indexOf('security') !== -1) return '#1565C0';
        if (c.indexOf('accident') !== -1) return '#EF6C00';
        return '#B3251A';
    }

    function updateMiniMap(inc) {
        const mapEl = document.getElementById('nrMap');
        const noMap = document.getElementById('nrNoMap');
        const lat = inc ? parseFloat(inc.latitude) : NaN;
        const lng = inc ? parseFloat(inc.longitude) : NaN;

        if (isNaN(lat) || isNaN(lng) || typeof L === 'undefined') {
            mapEl.classList.add('d-none');
            noMap.classList.remove('d-none');
            return;
        }
        mapEl.classList.remove('d-none');
        noMap.classList.add('d-none');

        const ll = [lat, lng];
        if (!nrMap) {
            nrMap = L.map(mapEl).setView(ll, 16);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap'
            }).addTo(nrMap);
            nrMarker = L.marker(ll).addTo(nrMap);
        } else {
            nrMap.setView(ll, 16);
            nrMarker.setLatLng(ll);
        }
        setTimeout(function () { nrMap.invalidateSize(); }, 150);
    }

    function fillBoard() {
        if (!items.length) return;
        let idx = items.findIndex(function (n) { return n.id === currentId; });
        if (idx < 0) { idx = 0; currentId = items[0].id; }

        const n = items[idx];
        const inc = n.incident || null;

        document.getElementById('nrHeader').style.background = headerColor(n.category);
        setText('nrCounter', (idx + 1) + ' of ' + items.length);
        setText('nrCategory', n.category || 'Report');
        setText('nrNumber', inc ? inc.incident_number : n.title);
        setText('nrDate', inc ? inc.reported_at : null);
        setText('nrMunicipality', inc ? inc.municipality : null);
        setText('nrBarangay', inc ? inc.barangay : null);
        setText('nrLandmark', inc ? inc.landmark : null);
        setText('nrDescription', inc ? inc.description : n.message);
        setText('nrReporter', inc ? inc.reporter_name : null);
        setText('nrContact', inc ? inc.reporter_contact : null);

        const link = document.getElementById('nrMapLink');
        if (inc && inc.latitude && inc.longitude) {
            link.href = MAP_URL + '?lat=' + encodeURIComponent(inc.latitude) + '&lng=' + encodeURIComponent(inc.longitude);
            link.classList.remove('disabled');
        } else {
            link.href = MAP_URL;
        }

        document.getElementById('nrPrev').disabled = items.length < 2;
        document.getElementById('nrNext').disabled = items.length < 2;

        updateMiniMap(inc);
    }

    function showBoard(id) {
        if (!nrModal || !items.length) return;
        currentId = id || items[0].id;
        fillBoard();
        if (!boardOpen) nrModal.show();
    }

    function step(delta) {
        if (items.length < 2) return;
        const idx = items.findIndex(function (n) { return n.id === currentId; });
        const next = (idx + delta + items.length) % items.length;
        currentId = items[next].id;
        fillBoard();
    }

    nrEl.addEventListener('shown.bs.modal', function () { boardOpen = true; fillBoard(); });
    nrEl.addEventListener('hidden.bs.modal', function () {
        boardOpen = false;
        // Closing is not acknowledging: the alert keeps sounding, but the board will not pop up again for these
        items.forEach(function (n) { dismissed.add(n.id); });
        saveDismissed();
    });

    document.getElementById('nrPrev').addEventListener('click', function () { step(-1); });
    document.getElementById('nrNext').addEventListener('click', function () { step(1); });
    document.getElementById('viewDetailsBtn').addEventListener('click', function () { showBoard(items[0] && items[0].id); });

    function refreshPageData() {
        // Lists that show incident status need a refresh after dispatching
        if (['/incidents', '/dispatches', '/dashboard'].indexOf(location.pathname) !== -1) {
            location.reload();
        }
    }

    // ---------------- Checking the server ----------------
    async function pollUnread() {
        if (polling) return;   // don't pile up requests if the server is slow
        polling = true;
        try {
            const res = await fetch("{{ route('notifications.liveUnread') }}", { headers: { 'Accept': 'application/json' } });
            if (!res.ok || res.redirected) return;   // redirected means the session expired
            const data = await res.json();
            items = data;

            const fresh = items.filter(function (n) { return !known.has(n.id) && !dismissed.has(n.id); });
            const otherModalOpen = !!document.querySelector('.modal.show:not(#newReportModal)');

            if (fresh.length && !otherModalOpen) {
                items.forEach(function (n) { known.add(n.id); });
                if (!boardOpen) showBoard(fresh[0].id);   // newest new report
            }
            if (boardOpen) {
                if (!items.length) { nrModal.hide(); } else { fillBoard(); }
            }
            renderBanner();
        } catch (e) {
            // Stay quiet; the next check will catch up.
        } finally {
            polling = false;
        }
    }

    // ---------------- Acknowledge ----------------
    document.getElementById('nrAckBtn').addEventListener('click', async function () {
        const btn = this;
        const id = currentId;
        if (id === null) return;
        btn.disabled = true;
        try {
            await fetch(READ_URL.replace('__ID__', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
        } catch (e) {}
        btn.disabled = false;

        items = items.filter(function (n) { return n.id !== id; });
        renderBanner();

        if (items.length) {
            currentId = items[0].id;
            fillBoard();
        } else {
            nrModal.hide();
            refreshPageData();
        }
    });

    document.getElementById('acknowledgeAllBtn')?.addEventListener('click', async function () {
        try {
            await fetch("{{ route('notifications.markAllRead') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
        } catch (e) {}
        items = [];
        renderBanner();
        if (boardOpen) nrModal.hide();
        refreshPageData();
    });

    // ---------------- Start ----------------
    initAudio();
    pollUnread();
    setInterval(pollUnread, POLL_MS);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) pollUnread();
    });
})();
</script>
@stack('scripts')
</body>
</html>
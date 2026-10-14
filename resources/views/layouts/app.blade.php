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

{{-- ============ GLOBAL SOUND ALERT SYSTEM ============ --}}
<div id="alertBanner" style="
    display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 2900;
    background: #B3251A; color: #fff; align-items: center; justify-content: center;
    gap: 1rem; padding: .7rem; font-size: .88rem; flex-wrap: wrap;
">
    <span id="alertBannerText">⚠️ New report(s) awaiting acknowledgment</span>
    <span id="soundBlockedHint" style="display:none; font-size:.78rem; opacity:.9;">
        🔇 Sound is blocked by the browser. Click anywhere to turn it on.
    </span>
    <button id="acknowledgeAllBtn" class="btn btn-sm btn-light">Acknowledge All</button>
</div>

<script>
(function () {
    const POLL_MS = 1000;   // how often to check for new reports, in milliseconds
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    let unread = new Map();   // notification id -> report category
    let polling = false;

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
        console.info('[alerts] audio starts as', audioCtx.state);
        audioCtx.onstatechange = function () {
            console.info('[alerts] audio is now', audioCtx.state);
            syncSound();
        };
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
        osc.frequency.value = 750;   // centre pitch
        lfo.type = 'triangle';
        lfo.frequency.value = 0.7;   // one full sweep about every 1.4 seconds
        lfoGain.gain.value = 250;    // sweeps between about 500 and 1000 Hz
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

    // Decide what should be sounding right now, based on what is unread
    function syncSound() {
        const categories = Array.from(unread.values());
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
    function render() {
        const banner = document.getElementById('alertBanner');
        const text = document.getElementById('alertBannerText');
        const count = unread.size;
        if (count > 0) {
            text.textContent = '⚠️ ' + count + ' new report' + (count > 1 ? 's' : '') + ' awaiting acknowledgment';
            banner.style.display = 'flex';
        } else {
            banner.style.display = 'none';
        }
        syncSound();
    }

    // ---------------- Checking the server ----------------
    async function pollUnread() {
        if (polling) return;   // don't pile up requests if the server is slow
        polling = true;
        try {
            const res = await fetch("{{ route('notifications.liveUnread') }}", { headers: { 'Accept': 'application/json' } });
            if (!res.ok || res.redirected) return;   // redirected means the session expired
            const data = await res.json();
            unread = new Map(data.map(function (n) { return [n.id, n.category]; }));
            render();
        } catch (e) {
            // Stay quiet; the next check will catch up.
        } finally {
            polling = false;
        }
    }

    // ---------------- Acknowledge ----------------
    document.getElementById('acknowledgeAllBtn')?.addEventListener('click', async function () {
        try {
            await fetch("{{ route('notifications.markAllRead') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
        } catch (e) {}
        unread = new Map();
        render();
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
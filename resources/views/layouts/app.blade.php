<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    gap: 1rem; padding: .7rem; font-size: .88rem;
">
    <span id="alertBannerText">⚠️ New report(s) awaiting acknowledgment</span>
    <button id="acknowledgeAllBtn" class="btn btn-sm btn-light">Acknowledge All</button>
</div>

<script>
(function () {
    let audioCtx = null;
    let audioUnlocked = false;
    let unreadIds = new Set();
    let sirenLoopTimer = null;
    let sirenActive = false;
    let standardBeepTimer = null;

    function unlockAudio() {
    if (audioUnlocked) return;
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    audioUnlocked = true;
}

    // Silently arm audio on the very first interaction of any kind — no visible prompt
['click', 'keydown', 'touchstart', 'scroll'].forEach(evt => {
    document.addEventListener(evt, unlockAudio, { once: true, passive: true });
});

    // Standard alert tone: two short beeps
    function playStandardBeep() {
        if (!audioCtx) return;
        [0, 0.25].forEach(delay => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.25, audioCtx.currentTime + delay);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + delay + 0.2);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start(audioCtx.currentTime + delay);
            osc.stop(audioCtx.currentTime + delay + 0.2);
        });
    }

    // One sweep cycle of the fire siren (low -> high -> low)
    const SIREN_CYCLE_SECONDS = 1.4;
    function playSirenCycle() {
        if (!audioCtx) return;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sawtooth';
        gain.gain.setValueAtTime(0.35, audioCtx.currentTime);
        osc.frequency.setValueAtTime(500, audioCtx.currentTime);
        osc.frequency.linearRampToValueAtTime(1000, audioCtx.currentTime + SIREN_CYCLE_SECONDS / 2);
        osc.frequency.linearRampToValueAtTime(500, audioCtx.currentTime + SIREN_CYCLE_SECONDS);
        osc.connect(gain).connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + SIREN_CYCLE_SECONDS);
    }

    // Starts a back-to-back looping siren with no gap between cycles
    function startContinuousSiren() {
        if (sirenActive || !audioCtx) return;
        sirenActive = true;
        playSirenCycle();
        sirenLoopTimer = setInterval(playSirenCycle, SIREN_CYCLE_SECONDS * 1000);
    }

    function stopContinuousSiren() {
        sirenActive = false;
        clearInterval(sirenLoopTimer);
        sirenLoopTimer = null;
    }

    function updateBanner(count) {
        const banner = document.getElementById('alertBanner');
        const text = document.getElementById('alertBannerText');
        if (count > 0) {
            text.textContent = `⚠️ ${count} new report${count > 1 ? 's' : ''} awaiting acknowledgment`;
            banner.style.display = 'flex';
        } else {
            banner.style.display = 'none';
        }
    }

    async function pollUnread() {
        try {
            const res = await fetch("{{ route('notifications.liveUnread') }}", { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            const currentIds = new Set(data.map(n => n.id));
            const hasFire = data.some(n => n.category === 'Fire');
            const hasNewOnes = data.some(n => !unreadIds.has(n.id));

            
            if (audioUnlocked) {
                if (hasFire) {
                    // Fire present among unread — keep the siren looping continuously
                    startContinuousSiren();
                    clearInterval(standardBeepTimer);
                    standardBeepTimer = null;
                } else if (data.length > 0) {
                    // Only non-fire unread — stop siren, use periodic beep instead
                    stopContinuousSiren();
                    if (!standardBeepTimer) {
                        playStandardBeep();
                        standardBeepTimer = setInterval(playStandardBeep, 8000);
                    }
                } else {
                    // Nothing unread — silence everything
                    stopContinuousSiren();
                    clearInterval(standardBeepTimer);
                    standardBeepTimer = null;
                }
            }

            unreadIds = currentIds;
            updateBanner(unreadIds.size);
        } catch (e) {
            // Silent fail — don't spam errors for a background poller
        }
    }

    document.getElementById('acknowledgeAllBtn')?.addEventListener('click', async function () {
        await fetch("{{ route('notifications.markAllRead') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });
        unreadIds = new Set();
        stopContinuousSiren();
        clearInterval(standardBeepTimer);
        standardBeepTimer = null;
        updateBanner(0);
    });

    pollUnread();
setInterval(pollUnread, 1500); // check for new reports every 1.5 seconds, on every page
})();
</script>
@stack('scripts')
</body>
</html>
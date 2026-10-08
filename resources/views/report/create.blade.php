<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report {{ $category }} — BFP Leyte EDIRS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body { background: var(--bg); }
        .report-shell { max-width: 520px; margin: 0 auto; padding: 2rem 1rem; }
        .map-wrap { position: relative; }
        #locMap { height: 200px; border-radius: 10px; }
        .map-wrap:fullscreen #locMap { height: 100vh; border-radius: 0; }
        .loc-status { font-size: .8rem; }
        .search-results {
            border: 1px solid var(--border, #E4E7EC);
            border-radius: 8px;
            margin-top: .35rem;
            max-height: 160px;
            overflow-y: auto;
            display: none;
        }
        .search-result-item {
            padding: .5rem .7rem;
            font-size: .82rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border, #E4E7EC);
        }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: #F5F5F5; }
        .autofill-badge {
            font-size: .7rem;
            color: var(--success, #16A34A);
            display: none;
        }
        .map-fullscreen-btn {
            position: absolute; top: 10px; right: 10px; z-index: 500;
            background: #fff;
            border: 1px solid var(--border, #E4E7EC);
            border-radius: 8px;
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,.15);
        }
        .map-fullscreen-btn:hover { background: #F5F5F5; }
    </style>
</head>
<body>
<div class="report-shell">
    <a href="{{ route('report.select') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Back</a>
    <h4 class="fw-semibold mt-2 mb-1">Report — {{ $category }}</h4>
    <p class="text-muted mb-4" style="font-size:.85rem;">Fill in what you can. Fields marked * are required.</p>

    <form method="POST" action="{{ route('report.store') }}" enctype="multipart/form-data" id="reportForm">
        @csrf
        <input type="hidden" name="category" value="{{ $category }}">
        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">

        <div class="card-flat p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0 fw-semibold" style="font-size:.85rem;">Your Location *</label>
                <span class="loc-status text-muted" id="locStatus"><i class="bi bi-hourglass-split"></i> Detecting...</span>
            </div>

            <div class="map-wrap mb-2" id="mapWrap">
                <button type="button" class="map-fullscreen-btn" id="mapFullscreenBtn" title="Expand map">
                    <i class="bi bi-arrows-fullscreen" id="mapFullscreenIcon"></i>
                </button>
                <div id="locMap"></div>
            </div>

            {{-- Manual address search — always available, critical fallback when GPS is denied --}}
            <div class="position-relative">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="addressSearch" placeholder="Or type an address / barangay / landmark to search">
                    <button type="button" class="btn btn-outline-secondary" id="addressSearchBtn">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <div class="search-results" id="searchResults"></div>
                <div class="text-muted mt-1" id="searchStatus" style="font-size:.72rem;"></div>
            </div>
        </div>

        <div class="row g-2 mb-1">
            <div class="col-6">
                <label class="form-label" style="font-size:.85rem;">Municipality / City *</label>
                <select name="municipality" id="municipalitySelect" class="form-select" required>
                    <option value="" selected disabled>Select...</option>
                    @foreach ($municipalities as $m)
                        <option value="{{ $m }}">{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6">
                <label class="form-label" style="font-size:.85rem;">Barangay *</label>
                <input type="text" name="barangay" id="barangayInput" class="form-control" placeholder="e.g. San Jose" required>
            </div>
        </div>
        <div class="mb-3">
            <span class="autofill-badge" id="autofillBadge"><i class="bi bi-check-circle-fill"></i> Auto-filled from your location — please confirm it's correct</span>
        </div>

        <div class="mb-3">
            <label class="form-label" style="font-size:.85rem;">Nearby Landmark</label>
            <input type="text" name="landmark" class="form-control" placeholder="e.g. near San Jose Elementary School">
        </div>
        <div class="mb-3">
            <label class="form-label" style="font-size:.85rem;">What's happening? *</label>
            <textarea name="description" class="form-control" rows="3" required placeholder="Briefly describe the situation"></textarea>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label" style="font-size:.85rem;">Your Name</label>
                <input type="text" name="reporter_name" class="form-control" placeholder="Optional">
            </div>
            <div class="col-6">
                <label class="form-label" style="font-size:.85rem;">Contact Number</label>
                <input type="text" name="reporter_contact" class="form-control" placeholder="Optional but helps responders">
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label" style="font-size:.85rem;">Photo</label>
            <input type="file" name="image" class="form-control" accept="image/*" capture="environment">
        </div>

        <button type="submit" class="btn btn-bfp w-100 py-2 fw-semibold" id="submitBtn" disabled>
            <i class="bi bi-send-fill"></i> Send Report
        </button>
    </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const municipalities = @json($municipalities);

    let map, marker;

    function initMap(lat, lng) {
        map = L.map('locMap').setView([lat, lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function (e) {
            const pos = marker.getLatLng();
            setLocation(pos.lat, pos.lng);
        });
    }

    function setLocation(lat, lng, zoom) {
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        document.getElementById('submitBtn').disabled = false;

        if (!map) {
            initMap(lat, lng);
        } else {
            map.setView([lat, lng], zoom || map.getZoom());
            marker.setLatLng([lat, lng]);
        }

        reverseGeocode(lat, lng);
    }

    // ---------- Fullscreen toggle for the location map ----------
    const mapWrap = document.getElementById('mapWrap');
    const mapFullscreenBtn = document.getElementById('mapFullscreenBtn');
    const mapFullscreenIcon = document.getElementById('mapFullscreenIcon');

    mapFullscreenBtn.addEventListener('click', function () {
        if (!document.fullscreenElement) {
            mapWrap.requestFullscreen().catch(() => {});
        } else {
            document.exitFullscreen();
        }
    });

    document.addEventListener('fullscreenchange', function () {
        const isFullscreen = !!document.fullscreenElement;
        mapFullscreenIcon.className = isFullscreen ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        setTimeout(() => { if (map) map.invalidateSize(); }, 150);
    });

    // ---------- Reverse geocoding: auto-fill Municipality + Barangay ----------
    function matchMunicipality(candidates) {
        for (const c of candidates) {
            if (!c) continue;
            const normalized = c.toLowerCase().replace(/city of|city/gi, '').trim();
            const found = municipalities.find(m => {
                const mNorm = m.toLowerCase().replace(/city/gi, '').trim();
                return mNorm === normalized || m.toLowerCase() === c.toLowerCase();
            });
            if (found) return found;
        }
        return null;
    }

    async function reverseGeocode(lat, lng) {
        const badge = document.getElementById('autofillBadge');
        badge.style.display = 'none';

        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`, {
                headers: { 'Accept-Language': 'en' }
            });
            const data = await res.json();
            const addr = data.address || {};

            const barangayGuess = addr.village || addr.suburb || addr.neighbourhood || addr.quarter || '';
            const matchedMuni = matchMunicipality([addr.city, addr.town, addr.municipality, addr.county]);

            let filledSomething = false;

            if (barangayGuess) {
                document.getElementById('barangayInput').value = barangayGuess;
                filledSomething = true;
            }
            if (matchedMuni) {
                document.getElementById('municipalitySelect').value = matchedMuni;
                filledSomething = true;
            }

            badge.style.display = filledSomething ? 'inline' : 'none';
        } catch (e) {
            // Silent fail — fields just stay empty for manual entry, no need to alarm the citizen
        }
    }

    // ---------- GPS auto-detect ----------
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            const lat = pos.coords.latitude, lng = pos.coords.longitude;
            document.getElementById('locStatus').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Location detected';
            setLocation(lat, lng, 16);
        }, function (error) {
            let message = 'Location unavailable — search or drag pin manually below';
            if (error.code === error.PERMISSION_DENIED) {
                message = 'Location permission denied — search or drag pin manually below';
            } else if (error.code === error.TIMEOUT) {
                message = 'Location taking too long — search or drag pin manually below';
            }
            document.getElementById('locStatus').innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> ' + message;
            initMap(11.2444, 125.0064); // fallback: Leyte province center
            setLocation(11.2444, 125.0064, 10);
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    } else {
        document.getElementById('locStatus').innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> GPS not supported — search or drag pin manually below';
        initMap(11.2444, 125.0064);
        setLocation(11.2444, 125.0064, 10);
    }

    // ---------- Manual address search (Nominatim / OpenStreetMap) ----------
    const searchInput = document.getElementById('addressSearch');
    const searchBtn = document.getElementById('addressSearchBtn');
    const resultsBox = document.getElementById('searchResults');
    const searchStatus = document.getElementById('searchStatus');

    async function searchAddress() {
        const query = searchInput.value.trim();
        if (!query) return;

        searchStatus.textContent = 'Searching...';
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';

        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=ph` +
                        `&viewbox=124.3,11.8,125.3,10.1&bounded=1&q=${encodeURIComponent(query)}`;

            const res = await fetch(url, { headers: { 'Accept-Language': 'en' } });
            const results = await res.json();

            if (results.length === 0) {
                searchStatus.textContent = 'No matches found — try a different address, or drag the pin manually.';
                return;
            }

            searchStatus.textContent = '';
            results.forEach(r => {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                item.textContent = r.display_name;
                item.addEventListener('click', () => {
                    setLocation(parseFloat(r.lat), parseFloat(r.lon), 16);
                    searchInput.value = r.display_name;
                    resultsBox.style.display = 'none';
                    document.getElementById('locStatus').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Location set from search';
                });
                resultsBox.appendChild(item);
            });
            resultsBox.style.display = 'block';
        } catch (e) {
            searchStatus.textContent = 'Search failed — check your connection, or drag the pin manually.';
        }
    }

    searchBtn.addEventListener('click', searchAddress);
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchAddress();
        }
    });
</script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report an Emergency — BFP Leyte EDIRS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body { background: var(--secondary); min-height: 100vh; }
        .select-shell { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; }
        .cat-card {
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
            border-radius: 16px; padding: 1.75rem 1.25rem; text-align: center; color: #fff;
            text-decoration: none; transition: transform .15s ease, background .15s ease; display: block;
        }
        .cat-card:hover { background: rgba(255,255,255,.16); transform: translateY(-3px); color: #fff; }
        .cat-card .icon { font-size: 2.1rem; margin-bottom: .6rem; }
        .cat-card.disabled { pointer-events: none; opacity: .35; }
    </style>
</head>
<body>
<div class="select-shell">
    <div style="width:48px;height:48px;border-radius:12px;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:1rem;">🚨</div>
    <h2 class="fw-semibold text-white mb-1">What do you want to report?</h2>
    <p class="mb-4" style="color:rgba(255,255,255,.7);">Select a category — we'll capture your location automatically.</p>

    <div class="row g-3" id="categoryGrid" style="max-width: 720px;">
        <div class="col-6 col-md-3">
            <a href="{{ route('report.create', 'fire') }}" class="cat-card disabled">
                <div class="icon">🔥</div>
                <div class="fw-semibold">Fire</div>
                <div style="font-size:.72rem; opacity:.7;">BFP</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('report.create', 'medical') }}" class="cat-card disabled">
                <div class="icon">🚑</div>
                <div class="fw-semibold">Medical</div>
                <div style="font-size:.72rem; opacity:.7;">Ambulance</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('report.create', 'crime') }}" class="cat-card disabled">
                <div class="icon">🚓</div>
                <div class="fw-semibold">Crime / Security</div>
                <div style="font-size:.72rem; opacity:.7;">PNP</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('report.create', 'accident') }}" class="cat-card disabled">
                <div class="icon">⚠️</div>
                <div class="fw-semibold">Accident</div>
                <div style="font-size:.72rem; opacity:.7;">Roadside / Vehicular</div>
            </a>
        </div>
    </div>
</div>

{{-- Mandatory warning / agreement modal --}}
<div class="modal fade" id="warningModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-semibold mb-0"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Before You Report</h5>
            </div>
            <div class="modal-body">
                <p style="font-size:.9rem;">
                    This system is intended <strong>only for genuine emergencies</strong>. Filing a false, malicious,
                    or fake report is a punishable offense under Philippine law:
                </p>
                <ul style="font-size:.85rem;" class="text-secondary">
                    <li class="mb-2"><strong>Republic Act No. 9514</strong> (Fire Code of the Philippines), Rule 11 — prohibits giving false or malicious fire alarms.</li>
                    <li class="mb-2"><strong>Article 155 of the Revised Penal Code</strong>, as amended by RA 11926 — penalizes causing false public alarm, punishable by <em>arresto menor</em> (1–30 days) or a fine of up to ₱40,000.</li>
                </ul>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="agreeCheck">
                    <label class="form-check-label" for="agreeCheck" style="font-size:.85rem;">
                        I understand and agree that this report is truthful, and that filing a false report is punishable by law.
                    </label>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-bfp w-100" id="agreeBtn" disabled>Continue</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const warningModal = new bootstrap.Modal(document.getElementById('warningModal'));
    warningModal.show();

    document.getElementById('agreeCheck').addEventListener('change', function () {
        document.getElementById('agreeBtn').disabled = !this.checked;
    });

    document.getElementById('agreeBtn').addEventListener('click', function () {
        warningModal.hide();
        document.querySelectorAll('.cat-card').forEach(card => card.classList.remove('disabled'));
    });
</script>
</body>
</html>
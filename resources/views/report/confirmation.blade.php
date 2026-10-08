<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Sent — BFP Leyte EDIRS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:var(--bg);">
    <div class="card-flat p-4 text-center" style="max-width:380px;">
        <div style="font-size:2.5rem;">✅</div>
        <h5 class="fw-semibold mt-2">Report Sent</h5>
        <p class="text-muted" style="font-size:.85rem;">Your {{ $incident->category }} report has been received and is being reviewed.</p>
        <div class="mono text-muted mb-3" style="font-size:.8rem;">{{ $incident->incident_number }}</div>
        <a href="{{ route('report.select') }}" class="btn btn-bfp w-100">Report Another</a>
    </div>
</body>
</html>
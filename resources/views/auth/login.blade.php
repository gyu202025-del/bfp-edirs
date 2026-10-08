<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In — BFP Web GIS Emergency Dispatch System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="login-shell">
    <div class="login-visual">
        <div class="d-flex align-items-center gap-2 mb-4" style="position:relative; z-index:1;">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.1rem;">🔥</div>
            <div>
                <div class="fw-semibold" style="font-size:1rem;">Web GIS Emergency</div>
                <div style="font-size:.8rem;opacity:.8;">Dispatch System</div>
            </div>
        </div>
        <h1 class="fw-semibold mb-2" style="max-width:480px; font-size:1.9rem; line-height:1.3; position:relative; z-index:1;">
            Real-time fire incident response for BFP Leyte
        </h1>
        <p style="max-width:440px; opacity:.85; font-size:.9rem; position:relative; z-index:1;">
            GIS-enabled dispatch, live GPS tracking, and SMS-based reporting across every municipality.
        </p>
    </div>

    <div class="login-panel">
        <div class="glass-card">
            <div class="text-center mb-4">
                <div style="width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin:0 auto .75rem;">🔥</div>
                <h2 class="fw-semibold mb-0" style="font-size:1.2rem;">BFP Leyte</h2>
                <div style="font-size:.8rem; opacity:.75;">Web-Based GIS Emergency Dispatch System</div>
            </div>

            @if ($errors->any() ?? false)
                <div class="alert alert-danger py-2" style="font-size:.82rem;">Invalid email or password.</div>
            @endif

            <form method="POST" action="{{ route('login.submit') ?? '#' }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" style="font-size:.82rem;">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="enter email" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-size:.82rem;">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="enter password" required>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4" style="font-size:.8rem;">
                    <label class="d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input m-0" name="remember"> Remember Me
                    </label>
                    <a href="#" class="text-white text-decoration-underline">Forgot Password?</a>
                </div>
                <button type="submit" class="btn w-100 py-2 fw-semibold" style="background:var(--accent); color:#1B263B;">LOGIN</button>
            </form>

            <p class="text-center mt-4 mb-0" style="font-size:.72rem; opacity:.7;">
                &copy; Bureau of Fire Protection
            </p>
        </div>
    </div>
</div>

</body>
</html>
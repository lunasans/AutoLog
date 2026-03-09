<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoLog Pro - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 2rem;
        }
        .login-card {
            width: 100%;
            max-width: 450px;
            padding: 3rem;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="bg-mesh">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <div class="glass-panel login-card">
        <div style="text-align: center; margin-bottom: 3rem;">
            <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; letter-spacing: -0.02em;">AutoLog<span style="color: var(--accent)">.</span></h1>
            <p style="color: var(--text-secondary)">Willkommen zurück in der Zentrale.</p>
        </div>

        <form action="{{ route('login') }}" method="POST">
            @csrf
            
            @if($errors->any())
                <div style="margin-bottom: 2rem; background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.2); border-radius: 0.75rem; padding: 1rem; color: var(--danger); font-size: 0.9rem;">
                    @foreach ($errors->all() as $error)
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i>
                            {{ $error }}
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="stat-label">Email Adresse</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@beispiel.de" style="height: 3.5rem; font-size: 1rem;">
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="stat-label">Passwort</label>
                <input type="password" name="password" required placeholder="••••••••" style="height: 3.5rem; font-size: 1rem;">
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2.5rem; cursor: pointer;">
                <input type="checkbox" name="remember" id="remember" style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent);">
                <label for="remember" style="font-size: 0.9rem; color: var(--text-secondary); cursor: pointer;">Angemeldet bleiben</label>
            </div>

            <button type="submit" class="btn-premium" style="width: 100%; justify-content: center; height: 3.75rem; font-size: 1.1rem; font-weight: 700;">
                Einloggen
            </button>
        </form>

        <div style="margin-top: 3rem; text-align: center; font-size: 0.85rem; color: var(--text-secondary);">
            <p>© 2026 AutoLog Pro System</p>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
</body>
</html>

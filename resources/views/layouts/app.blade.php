<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AutoLog')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    <!-- Animated background mesh -->
    <div class="bg-mesh">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <aside class="sidebar">
        <h1>AutoLog<span style="color: var(--accent)">.</span></h1>
        
        <nav>
            <h2>Vehicle Fleet</h2>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i data-lucide="layout-dashboard" style="width: 20px; height: 20px;"></i>
                Dashboard
            </a>
            <a href="{{ route('cars.create') }}" class="nav-link {{ request()->routeIs('cars.create') ? 'active' : '' }}">
                <i data-lucide="plus-circle" style="width: 20px; height: 20px;"></i>
                Add Vehicle
            </a>
            
            <h2 style="margin-top: 2rem;">Preferences</h2>
            <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                <i data-lucide="settings" style="width: 20px; height: 20px;"></i>
                Settings
            </a>
            <button onclick="toggleTheme()" class="nav-link" style="width: 100%; border: none; background: none; cursor: pointer; text-align: left;">
                <span id="theme-icon-container">
                    <i data-lucide="moon" style="width: 20px; height: 20px;"></i>
                </span>
                <span id="theme-text">Dark Mode</span>
            </button>
        </nav>

        <div style="margin-top: auto; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                <img src="{{ auth()->user()->avatar_url }}" alt="Avatar" style="width: 40px; height: 40px; border-radius: 0.75rem; object-fit: cover;">
                <div style="overflow: hidden;">
                    <p style="margin: 0; font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ auth()->user()->name }}</p>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" style="background: none; border: none; padding: 0; color: var(--text-secondary); font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                            <i data-lucide="log-out" style="width: 12px; height: 12px;"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
            <p style="font-size: 0.75rem; color: var(--text-secondary);">© 2026 AutoLog Pro</p>
        </div>
    </aside>

    <main class="main-content">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      lucide.createIcons();

      // Theme Management
      const theme = localStorage.getItem('theme') || 'dark';
      document.documentElement.setAttribute('data-theme', theme);
      updateThemeUI(theme);

      function toggleTheme() {
          const currentTheme = document.documentElement.getAttribute('data-theme');
          const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
          
          document.documentElement.setAttribute('data-theme', newTheme);
          localStorage.setItem('theme', newTheme);
          updateThemeUI(newTheme);
      }

      function updateThemeUI(theme) {
          const container = document.getElementById('theme-icon-container');
          const text = document.getElementById('theme-text');
          
          if (theme === 'light') {
              container.innerHTML = '<i data-lucide="sun" style="width: 20px; height: 20px;"></i>';
              text.innerText = 'Light Mode';
          } else {
              container.innerHTML = '<i data-lucide="moon" style="width: 20px; height: 20px;"></i>';
              text.innerText = 'Dark Mode';
          }
          lucide.createIcons();
      }
    </script>
    @stack('scripts')
</body>
</html>

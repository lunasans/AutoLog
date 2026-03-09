@extends('layouts.app')

@section('title', 'AutoLog Pro - Einstellungen')

@section('content')
    <div style="margin-bottom: 3rem;">
        <h2 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;">Einstellungen</h2>
        <p style="color: var(--text-secondary)">Verwalte dein Profil und deine Kontosicherheit.</p>
    </div>

    @if(session('success'))
        <div class="glass-panel" style="margin-bottom: 2rem; background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: var(--success); display: flex; align-items: center; gap: 0.75rem;">
            <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
            {{ session('success') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem;">
        <!-- Profile Settings -->
        <div class="glass-panel">
            <h3 style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="user" style="color: var(--accent); width: 22px; height: 22px;"></i>
                Profil Informationen
            </h3>

            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2.5rem;">
                    <div style="position: relative;">
                        <img src="{{ $user->avatar_url }}" alt="Avatar" style="width: 100px; height: 100px; border-radius: 2rem; object-fit: cover; border: 2px solid var(--border-color);">
                        <label for="avatar_input" style="position: absolute; bottom: -5px; right: -5px; background: var(--accent); color: white; width: 32px; height: 32px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);">
                            <i data-lucide="camera" style="width: 16px; height: 16px;"></i>
                        </label>
                        <input type="file" id="avatar_input" name="avatar" style="display: none;" onchange="this.form.submit()">
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 1.25rem;">{{ $user->name }}</h4>
                        <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">Deine Email: {{ $user->email }}</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="stat-label">Anzeigename</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                </div>

                <div class="form-group">
                    <label class="stat-label">Email Adresse</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                </div>

                <button type="submit" class="btn-premium" style="margin-top: 1rem;">Änderungen speichern</button>
            </form>
        </div>

        <!-- Security Settings -->
        <div class="glass-panel">
            <h3 style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="shield-check" style="color: var(--warning); width: 22px; height: 22px;"></i>
                Passwort ändern
            </h3>

            <form action="{{ route('password.update') }}" method="POST">
                @csrf
                @method('PUT')

                @if($errors->any())
                    <div style="margin-bottom: 1.5rem; color: var(--danger); font-size: 0.85rem;">
                        <ul style="list-style: none; padding: 0;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-group">
                    <label class="stat-label">Aktuelles Passwort</label>
                    <input type="password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label class="stat-label">Neues Passwort</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label class="stat-label">Passwort bestätigen</label>
                    <input type="password" name="password_confirmation" required>
                </div>

                <button type="submit" class="btn-premium" style="margin-top: 1rem; width: 100%; justify-content: center;">Passwort aktualisieren</button>
            </form>
        </div>
    </div>
@endsection

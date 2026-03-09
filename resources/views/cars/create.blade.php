@extends('layouts.app')

@section('title', 'AutoLog Pro - Fahrzeug registrieren')

@section('content')
    <div style="margin-bottom: 3rem;">
        <a href="{{ route('dashboard') }}" class="nav-link" style="display: inline-flex; margin-bottom: 1rem; padding-left: 0;">
            <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
            Zurück zur Übersicht
        </a>
        <h2 style="font-size: 2.5rem; font-weight: 800;">Neues Fahrzeug</h2>
        <p style="color: var(--text-secondary)">Erweitere deine Flotte um ein weiteres Prachtstück.</p>
    </div>

    <div class="glass-panel" style="max-width: 800px; position: relative; overflow: hidden;">
        <div style="position: absolute; top:0; right:0; padding: 2rem; opacity: 0.05;">
            <i data-lucide="plus-circle" style="width: 150px; height: 150px;"></i>
        </div>
        
        <form action="{{ route('cars.store') }}" method="POST">
            @csrf
            
            @if($errors->any())
                <div style="margin-bottom: 2.5rem; background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.2); border-radius: 1rem; padding: 1.5rem; color: var(--danger);">
                    <ul style="list-style: none; padding: 0;">
                        @foreach ($errors->all() as $error)
                            <li style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i>
                                {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="stat-label">Hersteller</label>
                    <input type="text" name="brand" placeholder="z.B. Porsche" value="{{ old('brand') }}" required autofocus>
                </div>
                <div class="form-group">
                    <label class="stat-label">Modell</label>
                    <input type="text" name="model" placeholder="z.B. 911 GT3" value="{{ old('model') }}" required>
                </div>
            </div>

            <div class="form-group">
                <label class="stat-label">Kennzeichen</label>
                <input type="text" name="license_plate" placeholder="S - GT 911" value="{{ old('license_plate') }}" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="stat-label">Baujahr</label>
                    <input type="number" name="year" placeholder="2024" value="{{ old('year') }}">
                </div>
                <div class="form-group">
                    <label class="stat-label">Nächste HU</label>
                    <input type="month" name="hu_due_at" value="{{ old('hu_due_at') }}">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="stat-label">Fahrgestellnummer (VIN)</label>
                    <input type="text" name="vin" placeholder="WPOZZZ..." value="{{ old('vin') }}">
                </div>
                <div class="form-group">
                    <label class="stat-label">Start-Kilometerstand</label>
                    <input type="number" name="initial_odometer" placeholder="0" value="{{ old('initial_odometer', 0) }}" required>
                </div>
            </div>

            <div style="margin-top: 3rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn-premium" style="flex: 2; justify-content: center; height: 3.5rem; font-size: 1.1rem;">
                    Fahrzeug speichern
                </button>
                <a href="{{ route('dashboard') }}" class="btn-premium" style="flex: 1; justify-content: center; background: rgba(255,255,255,0.05); box-shadow: none; color: var(--text-secondary);">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'AutoLog Pro - Tankvorgang bearbeiten')

@section('content')
    <div style="margin-bottom: 3rem;">
        <a href="{{ route('cars.show', $car) }}" class="nav-link" style="display: inline-flex; margin-bottom: 1rem; padding-left: 0;">
            <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
            Zurück zur Übersicht
        </a>
        <h2 style="font-size: 2.5rem; font-weight: 800;">Tankvorgang bearbeiten</h2>
        <p style="color: var(--text-secondary)">
            {{ $car->brand }} {{ $car->model }} &middot; erfasst am {{ \Carbon\Carbon::parse($fueling->date)->format('d.m.Y') }}
        </p>
    </div>

    <div class="glass-panel" style="max-width: 800px; position: relative; overflow: hidden;">
        <div style="position: absolute; top:0; right:0; padding: 2rem; opacity: 0.05;">
            <i data-lucide="fuel" style="width: 150px; height: 150px;"></i>
        </div>

        <form action="{{ route('fuelings.update', $fueling) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

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
                    <label class="stat-label">Datum</label>
                    <input type="date" name="date" value="{{ old('date', \Carbon\Carbon::parse($fueling->date)->format('Y-m-d')) }}" required autofocus>
                </div>
                <div class="form-group">
                    <label class="stat-label">Gefahrene KM</label>
                    <input type="number" step="0.1" name="trip_km" placeholder="Optional" value="{{ old('trip_km', $tripKm) }}">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1.5rem;">
                <div class="form-group">
                    <label class="stat-label">Liter</label>
                    <input type="number" step="0.01" name="liters" value="{{ old('liters', $fueling->liters) }}" required>
                </div>
                <div class="form-group">
                    <label class="stat-label">Gesamtpreis €</label>
                    <input type="number" step="0.01" name="price_total" value="{{ old('price_total', $fueling->price_total) }}" required>
                </div>
            </div>

            <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 1.5rem; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 0.75rem;">
                <i data-lucide="info" style="width: 14px; height: 14px; vertical-align: -2px;"></i>
                Änderst du die gefahrenen Kilometer, verschieben sich die Kilometerstände aller späteren
                Tankvorgänge um dieselbe Differenz — ihre eigenen Distanzen bleiben dabei erhalten.
                Ohne Kilometerangabe zählt der Eintrag für Kosten und Spritpreis, aber nicht für den Verbrauch.
            </p>

            <div class="form-group" style="margin-top: 1.5rem;">
                <label class="stat-label">Rechnung / Beleg</label>
                @if ($fueling->hasReceipt())
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem;">
                        <a href="{{ route('fuelings.receipt', $fueling) }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--accent);">
                            <i data-lucide="paperclip" style="width: 16px; height: 16px;"></i>
                            {{ $fueling->receipt_name }}
                        </a>
                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: var(--text-secondary);">
                            <input type="checkbox" name="remove_receipt" value="1" style="width: auto;">
                            entfernen
                        </label>
                    </div>
                @endif
                <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.5rem;">
                    {{ $fueling->hasReceipt() ? 'Eine neue Datei ersetzt den bisherigen Beleg.' : 'PDF oder Bild, optional.' }}
                </p>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2.5rem;">
                <button type="submit" class="btn-premium">Speichern</button>
                <a href="{{ route('cars.show', $car) }}" class="btn-premium" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary);">Abbrechen</a>
            </div>
        </form>
    </div>
@endsection

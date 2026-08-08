@extends('layouts.app')

@section('title', 'AutoLog Pro - ' . $car->brand . ' ' . $car->model)

@section('content')
    <div style="margin-bottom: 3rem; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <a href="{{ route('dashboard') }}" class="nav-link" style="display: inline-flex; margin-bottom: 1rem; padding-left: 0;">
                <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                Zurück zur Übersicht
            </a>
            <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 3rem;">
                <img src="{{ $car->logo_url }}"
                     onerror="this.onerror=null;this.src='{{ $car->logo_fallback }}'"
                     alt="Logo"
                     style="width: 80px; height: 80px; object-fit: contain; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));">
                <div>
                    <h2 style="font-size: 3rem; font-weight: 800; margin-bottom: 0.5rem; letter-spacing: -0.02em;">
                        {{ $car->brand }} {{ $car->model }}
                    </h2>
                    <div style="display: flex; align-items: center; gap: 1rem; color: var(--text-secondary);">
                        <span>{{ $car->year }}</span>
                        <span style="width: 4px; height: 4px; border-radius: 50%; background: var(--border-color);"></span>
                        <span class="license-tag">{{ $car->license_plate }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 1rem;">
            <a href="{{ route('cars.edit', $car) }}" class="btn-premium" style="background: rgba(255,255,255,0.05); box-shadow: none; color: var(--text-secondary);">
                <i data-lucide="edit" style="width: 18px; height: 18px;"></i> Bearbeiten
            </a>
            <button onclick="toggleForm('fuel-detail')" class="btn-premium">
                <i data-lucide="fuel" style="width: 18px; height: 18px;"></i> Tanken loggen
            </button>
            <button onclick="toggleForm('repair-detail')" class="btn-premium">
                <i data-lucide="wrench" style="width: 18px; height: 18px;"></i> Service loggen
            </button>
            <button onclick="toggleForm('parking-detail')" class="btn-premium">
                <i data-lucide="parking-circle" style="width: 18px; height: 18px;"></i> Parkticket
            </button>
            <form action="{{ route('cars.destroy', $car) }}" method="POST" onsubmit="return confirm('Bist du sicher? Alle Daten dieses Fahrzeugs werden unwiderruflich gelöscht!')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-premium" style="background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.2); color: var(--danger); box-shadow: none;">
                    <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i> Löschen
                </button>
            </form>
        </div>
    </div>

    <!-- Quick Forms -->
    <div id="fuel-detail" style="display: none; margin-bottom: 2rem;">
        <div class="glass-panel" style="max-width: 600px;">
            <h3 style="margin-bottom: 1.5rem;">Tanken erfassen</h3>
            <form id="fuel-form" action="{{ route('fuelings.store', $car) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <input type="number" step="0.01" name="liters" placeholder="Liter" required>
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <input type="number" step="0.01" name="price_total" placeholder="Gesamtpreis €" required>
                    <input type="number" step="0.1" name="trip_km" placeholder="Gefahrene KM (optional)">
                </div>
                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.5rem;">
                    Ohne Kilometer wird der Eintrag für Kosten und Spritpreis gezählt, aber nicht für den Verbrauch.
                </p>
                <div class="form-group" style="margin-top: 1rem;">
                    <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Rechnung / Beleg (PDF oder Bild, optional)</label>
                    <input type="file" name="receipt" id="fuel-receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    @if ($canScanReceipts)
                        <p id="fuel-scan-status" style="display: none; font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                    @endif
                </div>
                <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem;">Speichern</button>
            </form>

            @if ($canScanReceipts)
                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem;">Mehrere Belege auf einmal</h4>
                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        Aus jedem Beleg wird ein Eintrag mit Datum, Litern und Betrag. Die gefahrenen
                        Kilometer stehen nicht auf den Belegen — die trägst du je Eintrag nach.
                    </p>
                    <form action="{{ route('fuelings.import', $car) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="receipts[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem; background: rgba(255,255,255,0.05); box-shadow: none; color: var(--text-secondary);">
                            Belege einlesen
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <div id="repair-detail" style="display: none; margin-bottom: 2rem;">
        <div class="glass-panel" style="max-width: 600px;">
            <h3 style="margin-bottom: 1.5rem;">Service/Reparatur erfassen</h3>
            <form id="repair-form" action="{{ route('repairs.store', $car) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <input type="text" name="description" placeholder="Was wurde gemacht?" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" required>
                    <input type="number" step="0.01" name="cost" placeholder="Kosten €" required>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <input type="number" name="odometer_reading" placeholder="Kilometerstand (Optional)">
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Rechnung / Beleg (PDF oder Bild, optional)</label>
                    <input type="file" name="receipt" id="repair-receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    @if ($canScanRepairs)
                        <p id="repair-scan-status" style="display: none; font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                    @endif
                </div>
                <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem;">Loggen</button>
            </form>
        </div>
    </div>

    <div id="parking-detail" style="display: none; margin-bottom: 2rem;">
        <div class="glass-panel" style="max-width: 600px;">
            <h3 style="margin-bottom: 1.5rem;">Parkticket erfassen</h3>
            <form id="parking-form" action="{{ route('parking-tickets.store', $car) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <input type="text" name="location" placeholder="Wo geparkt? (z. B. Parkhaus Zentrum)" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" required>
                    <input type="number" step="0.01" name="cost" placeholder="Kosten €" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <input type="time" name="start_time" placeholder="Von">
                    <input type="time" name="end_time" placeholder="Bis">
                </div>
                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.5rem;">
                    Von/Bis sind optional – ohne Zeiten wird nur der Betrag gezählt.
                </p>
                <div class="form-group" style="margin-top: 1rem;">
                    <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Parkschein (PDF oder Foto, optional)</label>
                    <input type="file" name="receipt" id="parking-receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    @if ($canScanParking)
                        <p id="parking-scan-status" style="display: none; font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                    @endif
                </div>
                <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem;">Speichern</button>
            </form>

            @if ($canScanParking)
                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem;">Anbieter-Rechnung einlesen</h4>
                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        Rechnung von EasyPark & Co. hochladen – jeder abgerechnete Parkvorgang
                        wird ein eigener Eintrag. Einzelne Parkscheine gehen genauso.
                    </p>
                    <form action="{{ route('parking-tickets.import', $car) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="receipts[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem; background: rgba(255,255,255,0.05); box-shadow: none; color: var(--text-secondary);">
                            Rechnung einlesen
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <!-- Analytics Chart -->
    <div class="glass-panel" style="margin-bottom: 2rem; padding: 2rem;">
        <h3 style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
            <i data-lucide="line-chart" style="color: var(--accent); width: 22px; height: 22px;"></i>
            Verbrauch (L/100km)
        </h3>
        <div style="height: 300px; width: 100%;">
            <canvas id="fuelChart"></canvas>
        </div>
    </div>

    <!-- Fuel Price Trend -->
    <div class="glass-panel" style="margin-bottom: 2rem; padding: 2rem;">
        <h3 style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
            <i data-lucide="euro" style="color: var(--warning); width: 22px; height: 22px;"></i>
            Spritpreis (€/Liter)
            @if (count($pricePerLiter) > 1)
                <span style="margin-left: auto; font-size: 0.85rem; font-weight: 500; color: var(--text-secondary);">
                    {{ number_format(min($pricePerLiter), 3, ',', '.') }} – {{ number_format(max($pricePerLiter), 3, ',', '.') }} €
                </span>
            @endif
        </h3>
        @if (count($pricePerLiter))
            <div style="height: 300px; width: 100%;">
                <canvas id="priceChart"></canvas>
            </div>
        @else
            <p style="color: var(--text-secondary); font-size: 0.9rem;">Noch keine Tankvorgänge erfasst.</p>
        @endif
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 4rem;">
        <!-- Fueling Table -->
        <div class="glass-panel" style="padding: 0; overflow: hidden;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="display: flex; align-items: center; gap: 0.75rem;">
                    <i data-lucide="droplets" style="color: var(--accent); width: 20px; height: 20px;"></i>
                    Tanken-Historie
                </h3>
                <span style="font-size: 0.8rem; color: var(--text-secondary)">{{ $car->fuelings->count() }} Einträge</span>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: rgba(255,255,255,0.02); font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">
                            <th style="padding: 1rem 1.5rem;">Datum</th>
                            <th style="padding: 1rem 1.5rem;">Liter</th>
                            <th style="padding: 1rem 1.5rem;">Preis</th>
                            <th style="padding: 1rem 1.5rem;">Strecke</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($car->fuelings as $fuel)
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 1rem 1.5rem;">{{ \Carbon\Carbon::parse($fuel->date)->format('d.m.Y') }}</td>
                                <td style="padding: 1rem 1.5rem; font-weight: 600;">{{ number_format($fuel->liters, 2, ',', '.') }} L</td>
                                <td style="padding: 1rem 1.5rem;">{{ number_format($fuel->price_total, 2, ',', '.') }} €</td>
                                @php($trip = $tripDistances[$fuel->id] ?? null)
                                <td style="padding: 1rem 1.5rem; font-family: monospace; color: var(--text-secondary)"
                                    title="{{ $trip === null ? 'Ohne Kilometerangabe erfasst' : 'Kilometerstand danach: ' . number_format($fuel->odometer_reading, 0, ',', '.') }}">
                                    {{ $trip === null ? '–' : number_format($trip, 0, ',', '.') . ' km' }}
                                </td>
                                <td style="padding: 1rem 1.5rem; text-align: right;">
                                    @if($fuel->hasReceipt())
                                        <a href="{{ route('fuelings.receipt', $fuel) }}" title="Rechnung öffnen" style="color: var(--text-secondary); display: inline-block; padding: 4px;">
                                            <i data-lucide="paperclip" style="width: 16px; height: 16px;"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('fuelings.edit', $fuel) }}" title="Eintrag bearbeiten" style="color: var(--text-secondary); display: inline-block; padding: 4px;">
                                        <i data-lucide="pencil" style="width: 16px; height: 16px;"></i>
                                    </a>
                                    <form action="{{ route('fuelings.destroy', $fuel) }}" method="POST" onsubmit="return confirm('Eintrag wirklich löschen?')" style="display: inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.color='var(--danger)'; this.style.background='rgba(244, 63, 94, 0.1)'" onmouseout="this.style.color='var(--text-secondary)'; this.style.background='none'">
                                            <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="padding: 3rem; text-align: center; color: var(--text-secondary)">Noch keine Tankvorgänge.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Repair Table -->
        <div class="glass-panel" style="padding: 0; overflow: hidden;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="display: flex; align-items: center; gap: 0.75rem;">
                    <i data-lucide="wrench" style="color: var(--warning); width: 20px; height: 20px;"></i>
                    Service-Protokoll
                </h3>
                <span style="font-size: 0.8rem; color: var(--text-secondary)">{{ $car->repairs->count() }} Einträge</span>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: rgba(255,255,255,0.02); font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">
                            <th style="padding: 1rem 1.5rem;">Datum</th>
                            <th style="padding: 1rem 1.5rem;">Beschreibung</th>
                            <th style="padding: 1rem 1.5rem;">Kosten</th>
                            <th style="padding: 1rem 1.5rem;">KM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($car->repairs as $repair)
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 1rem 1.5rem;">{{ \Carbon\Carbon::parse($repair->date)->format('d.m.Y') }}</td>
                                <td style="padding: 1rem 1.5rem;">{{ $repair->description }}</td>
                                <td style="padding: 1rem 1.5rem; font-weight: 600; color: var(--danger)">{{ number_format($repair->cost, 2, ',', '.') }} €</td>
                                <td style="padding: 1rem 1.5rem; font-family: monospace; color: var(--text-secondary)">
                                    {{ $repair->odometer_reading ? number_format($repair->odometer_reading, 0, ',', '.') : '-' }}
                                </td>
                                <td style="padding: 1rem 1.5rem; text-align: right;">
                                    @if($repair->hasReceipt())
                                        <a href="{{ route('repairs.receipt', $repair) }}" title="Rechnung öffnen" style="color: var(--text-secondary); display: inline-block; padding: 4px;">
                                            <i data-lucide="paperclip" style="width: 16px; height: 16px;"></i>
                                        </a>
                                    @endif
                                    <form action="{{ route('repairs.destroy', $repair) }}" method="POST" onsubmit="return confirm('Eintrag wirklich löschen?')" style="display: inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.color='var(--danger)'; this.style.background='rgba(244, 63, 94, 0.1)'" onmouseout="this.style.color='var(--text-secondary)'; this.style.background='none'">
                                            <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="padding: 3rem; text-align: center; color: var(--text-secondary)">Noch keine Reparaturen.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Parking Table -->
    <div class="glass-panel" style="padding: 0; overflow: hidden; margin-bottom: 4rem;">
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="display: flex; align-items: center; gap: 0.75rem;">
                <i data-lucide="parking-circle" style="color: var(--success); width: 20px; height: 20px;"></i>
                Parktickets
            </h3>
            <span style="font-size: 0.8rem; color: var(--text-secondary)">{{ $car->parkingTickets->count() }} Einträge</span>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background: rgba(255,255,255,0.02); font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">
                        <th style="padding: 1rem 1.5rem;">Datum</th>
                        <th style="padding: 1rem 1.5rem;">Ort</th>
                        <th style="padding: 1rem 1.5rem;">Zeitraum</th>
                        <th style="padding: 1rem 1.5rem;">Kosten</th>
                        <th style="padding: 1rem 1.5rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($car->parkingTickets as $ticket)
                        <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 1rem 1.5rem;">{{ \Carbon\Carbon::parse($ticket->date)->format('d.m.Y') }}</td>
                            <td style="padding: 1rem 1.5rem;">{{ $ticket->location }}</td>
                            <td style="padding: 1rem 1.5rem; font-family: monospace; color: var(--text-secondary)">{{ $ticket->parked_period ?? '–' }}</td>
                            <td style="padding: 1rem 1.5rem; font-weight: 600;">{{ number_format($ticket->cost, 2, ',', '.') }} €</td>
                            <td style="padding: 1rem 1.5rem; text-align: right;">
                                @if($ticket->hasReceipt())
                                    <a href="{{ route('parking-tickets.receipt', $ticket) }}" title="Parkschein öffnen" style="color: var(--text-secondary); display: inline-block; padding: 4px;">
                                        <i data-lucide="paperclip" style="width: 16px; height: 16px;"></i>
                                    </a>
                                @endif
                                <form action="{{ route('parking-tickets.destroy', $ticket) }}" method="POST" onsubmit="return confirm('Eintrag wirklich löschen?')" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px; border-radius: 4px; transition: all 0.2s;" onmouseover="this.style.color='var(--danger)'; this.style.background='rgba(244, 63, 94, 0.1)'" onmouseout="this.style.color='var(--text-secondary)'; this.style.background='none'">
                                        <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-secondary)">Noch keine Parktickets.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stats summary for this car -->
    <div class="glass-panel">
        <h3 style="margin-bottom: 2rem;">Fahrzeug-Stammdaten</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
            <div>
                <span class="stat-label">FIN / VIN</span>
                <p style="font-family: monospace; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">{{ $car->vin ?: 'Nicht hinterlegt' }}</p>
            </div>
            <div>
                <span class="stat-label">Baujahr</span>
                <p style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">{{ $car->year ?: 'N/A' }}</p>
            </div>
            <div>
                <span class="stat-label">Zulassung</span>
                <p style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">{{ $car->license_plate }}</p>
            </div>
            <div>
                <span class="stat-label">Nächste HU (TÜV)</span>
                <p style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    {{ $car->hu_due_at ? \Carbon\Carbon::parse($car->hu_due_at)->format('m / Y') : 'N/A' }}
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function toggleForm(id) {
        const form = document.getElementById(id);
        const isOpen = form.style.display === 'block';
        form.style.display = isOpen ? 'none' : 'block';
    }

    // Chart.js implementation
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('fuelChart').getContext('2d');
        const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim();
        const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim();

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($fuelLabels) !!},
                datasets: [{
                    label: 'Verbrauch (L/100km)',
                    data: {!! json_encode($fuelConsumption) !!},
                    backgroundColor: accentColor + '22',
                    borderColor: accentColor,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: accentColor,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Verbrauch: ' + context.parsed.y + ' L/100km';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { 
                            color: textColor,
                            callback: function(value) { return Math.round(value * 100) / 100 + ' L'; }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });

        const priceCanvas = document.getElementById('priceChart');
        if (!priceCanvas) return;

        const warningColor = getComputedStyle(document.documentElement).getPropertyValue('--warning').trim();

        new Chart(priceCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: {!! json_encode($priceLabels) !!},
                datasets: [{
                    label: 'Spritpreis (€/L)',
                    data: {!! json_encode($pricePerLiter) !!},
                    backgroundColor: warningColor + '22',
                    borderColor: warningColor,
                    borderWidth: 3,
                    fill: true,
                    // Prices jump between fill-ups rather than easing into each
                    // other, so a straight line is the honest reading.
                    tension: 0,
                    pointBackgroundColor: warningColor,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toFixed(3).replace('.', ',') + ' €/L';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        // Fuel prices move in cents; zeroing the axis would
                        // flatten every change worth looking at.
                        beginAtZero: false,
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: {
                            color: textColor,
                            callback: function(value) { return value.toFixed(2).replace('.', ',') + ' €'; }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });
    });
</script>
@if ($canScanReceipts || $canScanRepairs || $canScanParking)
<script>
    // Reads a receipt as soon as it is picked and fills in whatever it could
    // find. Only empty fields are touched, so anything already typed wins.
    document.addEventListener('DOMContentLoaded', function () {
        function wireScanner(options) {
            const form = document.getElementById(options.form);
            const fileInput = document.getElementById(options.file);
            const status = document.getElementById(options.status);

            if (!form || !fileInput || !status) return;

            function show(message) {
                status.textContent = message;
                status.style.display = 'block';
            }

            fileInput.addEventListener('change', async function () {
                const file = fileInput.files[0];
                if (!file) {
                    status.style.display = 'none';
                    return;
                }

                show('Beleg wird gelesen …');

                const body = new FormData();
                body.append('receipt', file);
                body.append('_token', form.querySelector('input[name="_token"]').value);

                let data;
                try {
                    const response = await fetch(options.url, { method: 'POST', body: body });
                    if (!response.ok) throw new Error(response.status);
                    data = await response.json();
                } catch (e) {
                    show('Beleg konnte nicht gelesen werden – bitte die Werte eintragen.');
                    return;
                }

                // A document can hold more than one entry - filling the form
                // with the first would quietly drop the rest.
                if (options.holdsSeveral && options.holdsSeveral(data)) {
                    show(options.severalMessage);
                    return;
                }

                const filled = [];

                for (const [name, label] of Object.entries(options.fields)) {
                    const input = form.querySelector('[name="' + name + '"]');
                    if (!input) continue;

                    // A date input is prefilled with today, so it counts as
                    // empty for our purposes - the receipt knows better.
                    const isEmpty = !input.value || name === 'date';
                    if (data[name] !== null && data[name] !== undefined && isEmpty) {
                        input.value = data[name];
                        filled.push(label);
                    }
                }

                show(filled.length
                    ? 'Aus dem Beleg übernommen: ' + filled.join(', ') + '. Bitte prüfen.'
                    : 'Aus dem Beleg konnte nichts gelesen werden – bitte die Werte eintragen.');
            });
        }

        @if ($canScanReceipts)
            wireScanner({
                form: 'fuel-form',
                file: 'fuel-receipt',
                status: 'fuel-scan-status',
                url: '{{ route('receipts.scan.fueling') }}',
                fields: { date: 'Datum', liters: 'Liter', price_total: 'Gesamtpreis' },
            });
        @endif

        @if ($canScanParking)
            wireScanner({
                form: 'parking-form',
                file: 'parking-receipt',
                status: 'parking-scan-status',
                url: '{{ route('receipts.scan.parking') }}',
                fields: {
                    date: 'Datum',
                    location: 'Ort',
                    cost: 'Kosten',
                    start_time: 'Beginn',
                    end_time: 'Ende',
                },
                holdsSeveral: (data) => data.sessions > 1,
                severalMessage: 'Der Beleg enthält mehrere Parkvorgänge – nimm dafür weiter unten "Anbieter-Rechnung einlesen", dann wird jeder ein eigener Eintrag.',
            });
        @endif

        @if ($canScanRepairs)
            wireScanner({
                form: 'repair-form',
                file: 'repair-receipt',
                status: 'repair-scan-status',
                url: '{{ route('receipts.scan.repair') }}',
                fields: {
                    date: 'Datum',
                    description: 'Beschreibung',
                    cost: 'Kosten',
                    odometer_reading: 'Kilometerstand',
                },
            });
        @endif
    });
</script>
@endif
@endpush

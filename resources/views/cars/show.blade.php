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
                     onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($car->brand) }}&background=6366f1&color=fff&size=128'"
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
            <form action="{{ route('fuelings.store', $car) }}" method="POST">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <input type="number" step="0.01" name="liters" placeholder="Liter" required>
                    <input type="date" name="date" value="{{ date('Y-m-d') }}" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <input type="number" step="0.01" name="price_total" placeholder="Gesamtpreis €" required>
                    <input type="number" step="0.1" name="trip_km" placeholder="Gefahrene KM" required>
                </div>
                <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem;">Speichern</button>
            </form>
        </div>
    </div>

    <div id="repair-detail" style="display: none; margin-bottom: 2rem;">
        <div class="glass-panel" style="max-width: 600px;">
            <h3 style="margin-bottom: 1.5rem;">Service/Reparatur erfassen</h3>
            <form action="{{ route('repairs.store', $car) }}" method="POST">
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
                <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem;">Loggen</button>
            </form>
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
                            <th style="padding: 1rem 1.5rem;">KM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($car->fuelings as $fuel)
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                                <td style="padding: 1rem 1.5rem;">{{ \Carbon\Carbon::parse($fuel->date)->format('d.m.Y') }}</td>
                                <td style="padding: 1rem 1.5rem; font-weight: 600;">{{ number_format($fuel->liters, 2, ',', '.') }} L</td>
                                <td style="padding: 1rem 1.5rem;">{{ number_format($fuel->price_total, 2, ',', '.') }} €</td>
                                <td style="padding: 1rem 1.5rem; font-family: monospace; color: var(--text-secondary)">{{ number_format($fuel->odometer_reading, 0, ',', '.') }}</td>
                                <td style="padding: 1rem 1.5rem; text-align: right;">
                                    <form action="{{ route('fuelings.destroy', $fuel) }}" method="POST" onsubmit="return confirm('Eintrag wirklich löschen?')">
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
                                    <form action="{{ route('repairs.destroy', $repair) }}" method="POST" onsubmit="return confirm('Eintrag wirklich löschen?')">
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
    });
</script>
@endpush

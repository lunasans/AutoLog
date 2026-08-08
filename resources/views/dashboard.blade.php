@extends('layouts.app')

@section('title', 'AutoLog Pro - Fleet Intel')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3rem;">
        <div>
            <h2 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;">Übersicht</h2>
            <p style="color: var(--text-secondary)">Status deiner {{ count($stats) }} Fahrzeuge</p>
        </div>
        <a href="{{ route('cars.create') }}" class="btn-premium">
            <i data-lucide="plus-circle" style="width: 20px; height: 20px;"></i>
            Fahrzeug hinzufügen
        </a>
    </div>

    @if(session('success'))
        <div class="glass-panel" style="margin-bottom: 2rem; background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: var(--success); display: flex; align-items: center; gap: 0.75rem;">
            <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="car-grid">
        @forelse($stats as $stat)
            <div class="glass-panel">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <img src="{{ $stat['car']->logo_url }}" 
                             onerror="this.onerror=null;this.src='{{ $stat['car']->logo_fallback }}'"
                             alt="Logo" 
                             style="width: 40px; height: 40px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                        <div>
                            <a href="{{ route('cars.show', $stat['car']) }}" style="text-decoration: none; color: inherit;">
                                <h3 style="font-size: 1.25rem; font-weight: 700;">
                                    {{ $stat['car']->brand }} {{ $stat['car']->model }}
                                </h3>
                            </a>
                            <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">{{ $stat['car']->year ?? 'Baujahr unbekannt' }}</p>
                        </div>
                    </div>
                    <span class="license-tag">{{ $stat['car']->license_plate }}</span>
                </div>

                <div class="stat-group">
                    <div class="stat-box" title="Gesamt: {{ number_format($stat['total_spent'], 2, ',', '.') }} €">
                        <span class="stat-label">Sprit</span>
                        <div class="stat-value">{{ number_format($stat['total_fuel'], 2, ',', '.') }}<small style="font-size: 0.6em; margin-left: 2px;">€</small></div>
                    </div>
                    <div class="stat-box" title="Gesamt: {{ number_format($stat['total_spent'], 2, ',', '.') }} €">
                        <span class="stat-label">Werkstatt</span>
                        <div class="stat-value">{{ number_format($stat['total_repairs'], 2, ',', '.') }}<small style="font-size: 0.6em; margin-left: 2px;">€</small></div>
                    </div>
                    <div class="stat-box" title="Gesamt: {{ number_format($stat['total_spent'], 2, ',', '.') }} €">
                        <span class="stat-label">Parken</span>
                        <div class="stat-value">{{ number_format($stat['total_parking'], 2, ',', '.') }}<small style="font-size: 0.6em; margin-left: 2px;">€</small></div>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Verbrauch</span>
                        <div class="stat-value">{{ $stat['avg_consumption'] }}<small style="font-size: 0.6em; margin-left: 2px;">L/100</small></div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.2); padding: 0.75rem; border-radius: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="calendar" style="width: 16px; height: 16px; color: var(--text-secondary)"></i>
                        <span style="font-size: 0.85rem;">HU: {{ $stat['car']->hu_due_at ? \Carbon\Carbon::parse($stat['car']->hu_due_at)->format('m / Y') : 'N/A' }}</span>
                    </div>
                    @if($stat['hu_urgent'])
                        <span class="badge-pill badge-urgent">HU fällig</span>
                    @endif
                </div>

                <div style="margin-top: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <button onclick="toggleForm('fuel-{{ $stat['car']->id }}')" class="nav-link" style="justify-content: center; margin: 0; background: rgba(255,255,255,0.03);">
                        <i data-lucide="fuel" style="width: 18px; height: 18px;"></i> Tanken
                    </button>
                    <button onclick="toggleForm('repair-{{ $stat['car']->id }}')" class="nav-link" style="justify-content: center; margin: 0; background: rgba(255,255,255,0.03);">
                        <i data-lucide="wrench" style="width: 18px; height: 18px;"></i> Service
                    </button>
                    <button onclick="toggleForm('parking-{{ $stat['car']->id }}')" class="nav-link" style="justify-content: center; margin: 0; grid-column: span 2; background: rgba(255,255,255,0.03);">
                        <i data-lucide="parking-circle" style="width: 18px; height: 18px;"></i> Parkticket
                    </button>
                </div>

                <div id="fuel-{{ $stat['car']->id }}" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed var(--border-color);">
                    <form id="fuel-form-{{ $stat['car']->id }}" action="{{ route('fuelings.store', $stat['car']) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <input type="number" step="0.01" name="liters" placeholder="Liter" required>
                            <input type="date" name="date" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.75rem;">
                            <input type="number" step="0.01" name="price_total" placeholder="Preis €" required>
                            <input type="number" step="0.1" name="trip_km" placeholder="Trip KM" required>
                        </div>
                        <div class="form-group" style="margin-top: 0.75rem;">
                            <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Rechnung / Beleg (optional)</label>
                            <input type="file" name="receipt" id="fuel-receipt-{{ $stat['car']->id }}" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            @if ($canScanReceipts)
                                <p id="fuel-scan-status-{{ $stat['car']->id }}" style="display: none; font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                            @endif
                        </div>
                        <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem;">Speichern</button>
                    </form>
                </div>

                <div id="repair-{{ $stat['car']->id }}" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed var(--border-color);">
                    <form id="repair-form-{{ $stat['car']->id }}" action="{{ route('repairs.store', $stat['car']) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <input type="text" name="description" placeholder="Was wurde gemacht?" required style="grid-column: span 2;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.75rem;">
                            <input type="date" name="date" value="{{ date('Y-m-d') }}" required>
                            <input type="number" step="0.01" name="cost" placeholder="Kosten €" required>
                        </div>
                        <div class="form-group" style="margin-top: 0.75rem;">
                            <input type="number" name="odometer_reading" placeholder="KM-Stand (Optional)">
                        </div>
                        <div class="form-group" style="margin-top: 0.75rem;">
                            <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Rechnung / Beleg (optional)</label>
                            <input type="file" name="receipt" id="repair-receipt-{{ $stat['car']->id }}" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            @if ($canScanRepairs)
                                <p id="repair-scan-status-{{ $stat['car']->id }}" style="display: none; font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                            @endif
                        </div>
                        <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem;">Service loggen</button>
                    </form>
                </div>
                <div id="parking-{{ $stat['car']->id }}" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed var(--border-color);">
                    <form id="parking-form-{{ $stat['car']->id }}" action="{{ route('parking-tickets.store', $stat['car']) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <input type="text" name="location" placeholder="Wo geparkt?" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.75rem;">
                            <input type="date" name="date" value="{{ date('Y-m-d') }}" required>
                            <input type="number" step="0.01" name="cost" placeholder="Kosten €" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.75rem;">
                            <input type="time" name="start_time">
                            <input type="time" name="end_time">
                        </div>
                        <div class="form-group" style="margin-top: 0.75rem;">
                            <label style="display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Parkschein (optional)</label>
                            <input type="file" name="receipt" id="parking-receipt-{{ $stat['car']->id }}" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            @if ($canScanParking)
                                <p id="parking-scan-status-{{ $stat['car']->id }}" style="display: none; font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;"></p>
                            @endif
                        </div>
                        <button type="submit" class="btn-premium" style="width: 100%; margin-top: 1rem;">Parkticket speichern</button>
                    </form>
                </div>

                <div style="margin-top: 0.75rem;">
                    <a href="{{ route('cars.show', $stat['car']) }}" class="nav-link" style="justify-content: center; margin: 0; background: rgba(99, 102, 241, 0.05); color: var(--accent);">
                        <i data-lucide="info" style="width: 18px; height: 18px;"></i> Vollständige Details & Historie
                    </a>
                </div>
            </div>
        @empty
            <div class="glass-panel" style="grid-column: 1 / -1; text-align: center; padding: 5rem;">
                <i data-lucide="car" style="width: 64px; height: 64px; color: var(--text-secondary); margin-bottom: 1.5rem;"></i>
                <p style="color: var(--text-secondary); font-size: 1.25rem;">Deine Garage ist noch leer.</p>
                <a href="{{ route('cars.create') }}" class="btn-premium" style="margin-top: 2rem;">Erstes Auto hinzufügen</a>
            </div>
        @endforelse
    </div>
@endsection

@push('scripts')
<script>
    function toggleForm(id) {
        const form = document.getElementById(id);
        const isOpen = form.style.display === 'block';
        
        // Close all other forms first if you want
        // document.querySelectorAll('[id^="fuel-"], [id^="repair-"]').forEach(f => f.style.display = 'none');
        
        form.style.display = isOpen ? 'none' : 'block';
    }
</script>
@if ($canScanReceipts || $canScanRepairs || $canScanParking)
<script src="{{ asset('js/receipt-scanner.js') }}"></script>
<script>
    // One set of quick forms per car, so every scanner is wired per car id.
    document.addEventListener('DOMContentLoaded', function () {
        @foreach ($stats as $stat)
            @php($id = $stat['car']->id)

            @if ($canScanReceipts)
                wireReceiptScanner({
                    form: 'fuel-form-{{ $id }}',
                    file: 'fuel-receipt-{{ $id }}',
                    status: 'fuel-scan-status-{{ $id }}',
                    url: '{{ route('receipts.scan.fueling') }}',
                    fields: receiptScannerFields.fueling,
                });
            @endif

            @if ($canScanRepairs)
                wireReceiptScanner({
                    form: 'repair-form-{{ $id }}',
                    file: 'repair-receipt-{{ $id }}',
                    status: 'repair-scan-status-{{ $id }}',
                    url: '{{ route('receipts.scan.repair') }}',
                    fields: receiptScannerFields.repair,
                });
            @endif

            @if ($canScanParking)
                wireReceiptScanner({
                    form: 'parking-form-{{ $id }}',
                    file: 'parking-receipt-{{ $id }}',
                    status: 'parking-scan-status-{{ $id }}',
                    url: '{{ route('receipts.scan.parking') }}',
                    fields: receiptScannerFields.parking,
                    holdsSeveral: parkingHoldsSeveral,
                    severalMessage: parkingSeveralMessage,
                });
            @endif
        @endforeach
    });
</script>
@endif
@endpush

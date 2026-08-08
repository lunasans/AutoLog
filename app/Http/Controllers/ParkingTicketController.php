<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\ParkingTicket;
use App\Services\Receipts\ExtractedParkingSession;
use App\Services\Receipts\ParkingExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ParkingTicketController extends Controller
{
    public function store(Request $request, Car $car)
    {
        Gate::authorize('update', $car);

        $validated = $request->validate([
            'date' => 'required|date',
            'location' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'start_time' => 'nullable|date_format:H:i',
            // Parking across midnight is normal, so the end is not required to
            // come after the start.
            'end_time' => 'nullable|date_format:H:i',
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $ticket = $car->parkingTickets()->create(collect($validated)->except('receipt')->all());

        if ($request->hasFile('receipt')) {
            $ticket->storeReceipt($request->file('receipt'));
            $ticket->save();
        }

        return redirect()->route('dashboard')->with('success', 'Parkticket gespeichert.');
    }

    /**
     * Reads uploaded parking documents and creates one entry per parking
     * session found. A provider invoice (EasyPark and the like) bills a whole
     * month at once, so a single upload can turn into many entries; a single
     * ticket simply yields one.
     *
     * The document is filed with every entry it produced - each of them needs
     * to stand on its own as proof, and one shared copy would vanish with the
     * first deletion.
     */
    public function import(Request $request, Car $car, ParkingExtractor $extractor)
    {
        Gate::authorize('update', $car);

        abort_unless($extractor->isAvailable(), 404);

        $validated = $request->validate([
            'receipts' => 'required|array|max:12',
            'receipts.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $created = 0;
        $skipped = 0;
        $foreign = 0;
        $unreadable = [];

        foreach ($validated['receipts'] as $file) {
            $sessions = array_filter(
                $extractor->extract($file),
                fn (ExtractedParkingSession $session) => $session->isComplete(),
            );

            if ($sessions === []) {
                $unreadable[] = $file->getClientOriginalName();

                continue;
            }

            // One account often covers several cars. A session that names a
            // different plate belongs to one of them, not to this car - which
            // is worth reporting rather than silently importing.
            $mine = array_filter($sessions, fn (ExtractedParkingSession $s) => $this->belongsTo($s, $car));
            $foreign += count($sessions) - count($mine);
            $sessions = $mine;

            $filed = null;

            foreach ($sessions as $session) {
                // Importing the same invoice twice shouldn't double the log.
                $exists = $car->parkingTickets()
                    ->where('date', $session->date)
                    ->where('cost', $session->total())
                    ->where('location', $session->location)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $ticket = $car->parkingTickets()->create($session->toAttributes());

                // The upload can only be stored once - every further entry off
                // the same document gets its own copy of what was filed.
                $filed === null ? $ticket->storeReceipt($file) : $ticket->copyReceiptFrom($filed);
                $ticket->save();

                $filed ??= $ticket;

                $created++;
            }
        }

        return redirect()->route('cars.show', $car)
            ->with('success', $this->importSummary($created, $skipped, $foreign, $unreadable));
    }

    /**
     * Whether a session was parked by this car. A document that names no plate
     * is taken at face value - the user picked the car, and refusing a plain
     * paper ticket for lack of a plate would help nobody.
     */
    private function belongsTo(ExtractedParkingSession $session, Car $car): bool
    {
        if ($session->licensePlate === null) {
            return true;
        }

        // "GLMS141" and "GL-MS 141" are the same plate written two ways.
        $normalize = fn (string $plate) => strtoupper(preg_replace('/[^\p{L}\p{N}]/u', '', $plate));

        return $normalize($session->licensePlate) === $normalize($car->license_plate);
    }

    /** @param  list<string>  $unreadable */
    private function importSummary(int $created, int $skipped, int $foreign, array $unreadable): string
    {
        $parts = [$created === 1 ? '1 Parkvorgang angelegt' : "{$created} Parkvorgänge angelegt"];

        if ($skipped > 0) {
            $parts[] = $skipped === 1
                ? '1 Parkvorgang war schon erfasst'
                : "{$skipped} Parkvorgänge waren schon erfasst";
        }

        if ($foreign > 0) {
            $parts[] = $foreign === 1
                ? '1 Parkvorgang gehört zu einem anderen Kennzeichen'
                : "{$foreign} Parkvorgänge gehören zu anderen Kennzeichen";
        }

        if ($unreadable !== []) {
            $parts[] = 'nicht lesbar: '.implode(', ', $unreadable);
        }

        return implode(' · ', $parts).'. Bitte die Einträge kurz prüfen.';
    }

    public function receipt(ParkingTicket $parkingTicket)
    {
        Gate::authorize('view', $parkingTicket);

        abort_unless($parkingTicket->hasReceipt(), 404);

        return Storage::disk('local')->download($parkingTicket->receipt_path, $parkingTicket->receipt_name);
    }

    public function destroy(ParkingTicket $parkingTicket)
    {
        Gate::authorize('delete', $parkingTicket);

        $parkingTicket->delete();

        return redirect()->back()->with('success', 'Parkticket wurde gelöscht.');
    }
}

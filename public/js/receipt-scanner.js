/**
 * Reads a receipt as soon as it is picked and fills in whatever it could find.
 * Only empty fields are touched, so anything already typed wins.
 *
 * Used by both the entry forms on the car page and the quick forms on the
 * dashboard - the dashboard shows one set per car, hence the element ids
 * rather than a single hardcoded form.
 */
window.wireReceiptScanner = function (options) {
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

        // A document can hold more than one entry - filling the form with the
        // first would quietly drop the rest.
        if (options.holdsSeveral && options.holdsSeveral(data)) {
            show(options.severalMessage);
            return;
        }

        const filled = [];

        for (const [name, label] of Object.entries(options.fields)) {
            const input = form.querySelector('[name="' + name + '"]');
            if (!input) continue;

            // A date input is prefilled with today, so it counts as empty for
            // our purposes - the receipt knows better.
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
};

/** The three document kinds, so both pages describe them the same way. */
window.receiptScannerFields = {
    fueling: { date: 'Datum', liters: 'Liter', price_total: 'Gesamtpreis' },
    repair: { date: 'Datum', description: 'Beschreibung', cost: 'Kosten', odometer_reading: 'Kilometerstand' },
    parking: { date: 'Datum', location: 'Ort', cost: 'Kosten', start_time: 'Beginn', end_time: 'Ende' },
};

window.parkingHoldsSeveral = (data) => data.sessions > 1;

window.parkingSeveralMessage =
    'Der Beleg enthält mehrere Parkvorgänge – nimm dafür auf der Fahrzeugseite '
    + '"Anbieter-Rechnung einlesen", dann wird jeder ein eigener Eintrag.';

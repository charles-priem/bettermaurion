<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation de Salle</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .reservation-page {
            max-width: 1200px;
            margin: 30px auto 60px;
            padding: 0 20px;
        }

        .reservation-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 32px;
        }

        .reservation-card h1 {
            margin-bottom: 8px;
            font-size: 28px;
            color: #1a1a1a;
        }

        .helper {
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            margin-bottom: 20px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 220px;
            flex: 1;
        }

        .field label {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .field select,
        .field input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: 'Roboto', serif;
            background: #fff;
            outline: none;
        }

        .field select:focus,
        .field input:focus {
            border-color: #1a1a1a;
            box-shadow: 0 0 0 2px rgba(26, 26, 26, 0.1);
        }

        .actions {
            display: flex;
            align-items: flex-end;
        }

        .search-btn {
            height: 44px;
            padding: 0 20px;
            border: none;
            border-radius: 5px;
            background-color: #1a1a1a;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .search-btn:hover {
            opacity: 0.8;
        }

        .search-btn:focus-visible {
            outline: 2px solid rgba(26, 26, 26, 0.18);
            outline-offset: 2px;
        }

        .search-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .message {
            display: none;
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 14px;
        }

        .message.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .selection-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin: 16px 0 20px;
            padding: 14px 16px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        .selection-summary {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .selection-count {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .selection-hint {
            font-size: 13px;
            color: #666;
        }

        .selection-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .secondary-btn {
            background: #f5f0f0;
            color: #1a1a1a;
            border: 1px solid #ddd;
        }

        .secondary-btn:hover {
            opacity: 0.8;
        }

        .results {
            margin-top: 10px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 10px 10px 0 0;
        }

        .results-header h2 {
            margin: 0;
            font-size: 20px;
            color: #1a1a1a;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f5f0f0;
            color: #1a1a1a;
            font-weight: 600;
            font-size: 13px;
        }

        .table-wrap {
            max-height: 60vh;
            overflow: auto;
            border: 1px solid #ddd;
            border-radius: 0 0 10px 10px;
            border-top: none;
            background: #fff;
            scrollbar-width: thin;
            scrollbar-color: #1a1a1a #f5f0f0;
        }

        .table-wrap::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .table-wrap::-webkit-scrollbar-track {
            background: #f5f0f0;
        }

        .table-wrap::-webkit-scrollbar-thumb {
            background: #1a1a1a;
            border-radius: 999px;
            border: 2px solid #f5f0f0;
        }

        .rooms-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .rooms-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            text-align: left;
            padding: 14px 16px;
            font-size: 13px;
            color: #1a1a1a;
            background: #f5f0f0;
            border-bottom: 1px solid #ddd;
        }

        .rooms-table tbody td {
            padding: 16px;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }

        .rooms-table tbody tr:hover {
            background: #fafafa;
        }

        .room-name {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .room-meta {
            margin-top: 4px;
            color: #666;
            font-size: 13px;
        }

        .slot-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .slot-chip {
            appearance: none;
            border: 1px solid #ddd;
            background: #f5f0f0;
            color: #1a1a1a;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s ease, background-color 0.2s ease, color 0.2s ease;
        }

        .slot-chip:hover {
            opacity: 0.85;
        }

        .slot-chip.is-selected {
            background: #1a1a1a;
            border-color: #1a1a1a;
            color: #fff;
        }

        .slot-chip--reserved {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
            cursor: not-allowed;
        }

        .empty-state,
        .loading-state,
        .error-state {
            padding: 28px 18px !important;
            text-align: center;
            color: #666;
        }

        @media (max-width: 768px) {
            .reservation-card {
                padding: 20px;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .field {
                min-width: 0;
            }

            .actions {
                align-items: stretch;
            }

            .search-btn {
                width: 100%;
            }

            .results-header,
            .selection-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .rooms-table {
                min-width: 760px;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <main class="reservation-page">
        <section class="reservation-card">
            <h1>Réservation de Salle</h1>
            <p class="helper">Choisissez un bâtiment et une date, puis cliquez sur plusieurs créneaux pour les réserver ensemble.</p>

            <div class="filters">
                <div class="field">
                    <label for="building-select">Bâtiment</label>
                    <select id="building-select" name="building">
                        <option value="IC1">IC1</option>
                        <option value="IC2">IC2</option>
                        <option value="ALG">ALG</option>
                    </select>
                </div>

                <div class="field">
                    <label for="date-select">Date</label>
                    <input type="date" id="date-select" name="date" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="actions">
                    <button id="search-button" class="search-btn" type="button">Rechercher</button>
                </div>
            </div>

            <div id="reservation-message" class="message" aria-live="polite"></div>

            <div class="selection-bar">
                <div class="selection-summary">
                    <div id="selection-count" class="selection-count">Aucun créneau sélectionné</div>
                    <div class="selection-hint">Les créneaux choisis seront vérifiés en base avant enregistrement.</div>
                </div>
                <div class="selection-actions">
                    <button id="clear-selection" class="search-btn secondary-btn" type="button" disabled>Vider</button>
                    <button id="reserve-selection" class="search-btn" type="button" disabled>Réserver la sélection</button>
                </div>
            </div>

            <p class="helper">Horaires pris en compte : 08h00 à 18h00 avec une pause le midi, puis mode spécial jusqu'à 20h.</p>

            <div class="results">
                <div class="results-header">
                    <h2>Créneaux disponibles</h2>
                    <span class="badge" id="results-count">0 salle</span>
                </div>
                <div class="table-wrap">
                    <table class="rooms-table">
                        <thead>
                            <tr>
                                <th>Salle</th>
                                <th>Créneaux libres</th>
                            </tr>
                        </thead>
                        <tbody id="available-rooms-body">
                            <tr>
                                <td colspan="2" class="empty-state">Lancez une recherche pour afficher les salles disponibles.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script>
        const searchButton = document.getElementById('search-button');
        const buildingSelect = document.getElementById('building-select');
        const dateSelect = document.getElementById('date-select');
        const resultsBody = document.getElementById('available-rooms-body');
        const resultsCount = document.getElementById('results-count');
        const selectionCount = document.getElementById('selection-count');
        const clearSelectionButton = document.getElementById('clear-selection');
        const reserveSelectionButton = document.getElementById('reserve-selection');
        const reservationMessage = document.getElementById('reservation-message');

        const selectedSlots = new Map();

        function clearMessage() {
            reservationMessage.className = 'message';
            reservationMessage.textContent = '';
        }

        function showMessage(type, text) {
            reservationMessage.className = `message ${type}`;
            reservationMessage.textContent = text;
        }

        function updateSelectionSummary() {
            const selectedCount = selectedSlots.size;
            const selectedRooms = new Set(Array.from(selectedSlots.values()).map(slot => slot.room)).size;

            if (selectedCount === 0) {
                selectionCount.textContent = 'Aucun créneau sélectionné';
            } else {
                selectionCount.textContent = `${selectedCount} créneau${selectedCount > 1 ? 'x' : ''} sélectionné${selectedCount > 1 ? 's' : ''} sur ${selectedRooms} salle${selectedRooms > 1 ? 's' : ''}`;
            }

            clearSelectionButton.disabled = selectedCount === 0;
            reserveSelectionButton.disabled = selectedCount === 0;
        }

        function clearSelection() {
            selectedSlots.clear();
            document.querySelectorAll('.slot-chip.is-selected').forEach(chip => {
                chip.classList.remove('is-selected');
            });
            updateSelectionSummary();
        }

        function setTableState(className, message) {
            resultsBody.innerHTML = '';

            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 2;
            cell.className = className;
            cell.textContent = message;
            row.appendChild(cell);
            resultsBody.appendChild(row);
        }

        function formatTime(value) {
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function toggleChipSelection(chip) {
            const slot = {
                room: chip.dataset.room,
                start: chip.dataset.start,
                end: chip.dataset.end,
            };
            const key = `${slot.room}|${slot.start}|${slot.end}`;

            if (selectedSlots.has(key)) {
                selectedSlots.delete(key);
                chip.classList.remove('is-selected');
            } else {
                selectedSlots.set(key, slot);
                chip.classList.add('is-selected');
            }

            updateSelectionSummary();
        }

        function bindChipEvents() {
            document.querySelectorAll('.slot-chip').forEach(chip => {
                chip.addEventListener('click', () => toggleChipSelection(chip));
            });
        }

        async function loadRooms() {
            const building = buildingSelect.value;
            const date = dateSelect.value;

            clearMessage();
            clearSelection();
            resultsCount.textContent = 'Recherche...';
            setTableState('loading-state', 'Recherche en cours...');

            try {
                const response = await fetch('../php/get_available_rooms.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                    },
                    body: new URLSearchParams({ building, date }).toString()
                });

                if (!response.ok) {
                    throw new Error(`Erreur HTTP ${response.status}`);
                }

                const data = await response.json();

                if (data.error) {
                    resultsCount.textContent = 'Erreur';
                    setTableState('error-state', data.error);
                    return;
                }

                const rooms = data.available_slots_by_room || {};
                const roomNames = Object.keys(rooms);

                if (roomNames.length === 0) {
                    resultsCount.textContent = '0 salle';
                    setTableState('empty-state', 'Aucune salle libre pour ce bâtiment à cette date.');
                    return;
                }

                resultsBody.innerHTML = '';

                roomNames.forEach(room => {
                    const slots = rooms[room] || [];
                    const row = document.createElement('tr');

                    const roomCell = document.createElement('td');
                    const roomName = document.createElement('div');
                    roomName.className = 'room-name';
                    roomName.textContent = room;

                    const roomMeta = document.createElement('div');
                    roomMeta.className = 'room-meta';
                    roomMeta.textContent = `Bâtiment ${building}`;

                    roomCell.appendChild(roomName);
                    roomCell.appendChild(roomMeta);

                    const slotsCell = document.createElement('td');
                    const slotList = document.createElement('div');
                    slotList.className = 'slot-list';

                    if (slots.length === 0) {
                        const emptySlot = document.createElement('span');
                        emptySlot.className = 'room-meta';
                        emptySlot.textContent = 'Aucun créneau libre.';
                        slotList.appendChild(emptySlot);
                    } else {
                        slots.forEach(slot => {
                            const chip = document.createElement('button');
                            chip.type = 'button';
                            chip.className = 'slot-chip';
                            chip.dataset.room = room;
                            chip.dataset.start = slot.start;
                            chip.dataset.end = slot.end;

                            const startLabel = formatTime(slot.start);
                            const endLabel = formatTime(slot.end);
                            const durationLabel = slot.duration ? ` · ${slot.duration}h` : '';
                            chip.textContent = `${startLabel} - ${endLabel}${durationLabel}`;

                            slotList.appendChild(chip);
                        });
                    }

                    slotsCell.appendChild(slotList);

                    row.appendChild(roomCell);
                    row.appendChild(slotsCell);
                    resultsBody.appendChild(row);
                });

                resultsCount.textContent = `${roomNames.length} salle${roomNames.length > 1 ? 's' : ''}`;
                bindChipEvents();
            } catch (error) {
                console.error('Erreur lors de la requête AJAX:', error);
                resultsCount.textContent = 'Erreur';
                setTableState('error-state', 'Une erreur est survenue lors de la communication avec le serveur.');
            }
        }

        async function reserveSelectedSlots() {
            if (selectedSlots.size === 0) {
                showMessage('error', 'Sélectionnez au moins un créneau avant de réserver.');
                return;
            }

            reserveSelectionButton.disabled = true;
            reserveSelectionButton.textContent = 'Réservation...';
            clearMessage();

            try {
                const response = await fetch('../php/reserve_slots.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        building: buildingSelect.value,
                        date: dateSelect.value,
                        slots: Array.from(selectedSlots.values())
                    })
                });

                const data = await response.json();

                if (!response.ok || data.error) {
                    const conflictText = Array.isArray(data.conflicts) && data.conflicts.length > 0
                        ? ` Créneaux concernés : ${data.conflicts.join(', ')}.`
                        : '';
                    throw new Error((data.error || 'La réservation a échoué.') + conflictText);
                }

                clearSelection();
                await loadRooms();
                showMessage('success', data.message || 'Réservation enregistrée avec succès.');
            } catch (error) {
                showMessage('error', error.message || 'Impossible d’enregistrer la réservation.');
            } finally {
                reserveSelectionButton.textContent = 'Réserver la sélection';
                reserveSelectionButton.disabled = selectedSlots.size === 0;
            }
        }

        searchButton.addEventListener('click', loadRooms);
        buildingSelect.addEventListener('change', loadRooms);
        dateSelect.addEventListener('change', loadRooms);
        clearSelectionButton.addEventListener('click', () => {
            clearSelection();
            clearMessage();
        });
        reserveSelectionButton.addEventListener('click', reserveSelectedSlots);

        updateSelectionSummary();
        loadRooms();
    </script>
</body>
</html>

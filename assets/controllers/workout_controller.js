import { Controller } from '@hotwired/stimulus';

/**
 * workout-controller — manages exercise locking, set logging and rest timer dispatch.
 *
 * State value (JSON from server):
 * {
 *   workoutLogId:      number,
 *   currentExerciseId: number | null,
 *   exercises: [
 *     { id, seriesType, superseriesGroup, targetSets, loggedSets }
 *   ],
 *   isComplete: boolean
 * }
 *
 * Locking rules (mirror WorkoutExecutionService PHP):
 *   - normal_ts / amrap / complex  → only card with id === currentExerciseId is unlocked
 *   - superseries                  → all cards in same superseriesGroup as current are unlocked
 *                                     (currentExerciseId === null within group)
 */
export default class extends Controller {
    static targets = [
        'exerciseCard',
        'lockedOverlay',
        'setForm',
        'setTableBody',
        'completionModal',
    ];

    static values = {
        state: Object,
        logUrl: String,
    };

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    connect() {
        this._applyLocking();
    }

    stateValueChanged() {
        this._applyLocking();
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * "Registrar Serie" button click.
     * Reads form inputs for the clicked exercise card and POSTs to the server.
     */
    async logSet(event) {
        const button = event.currentTarget;
        const exerciseId = parseInt(button.dataset.exerciseId, 10);
        const measurementType = button.dataset.measurementType;

        const payload = this._collectFormData(exerciseId, measurementType);
        if (payload === null) return; // validation failed — user was alerted

        button.disabled = true;

        try {
            const logUrl = this.element.dataset.workoutLogUrl;
            const response = await fetch(logUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ sessionExerciseId: exerciseId, ...payload }),
            });

            const json = await response.json();

            if (!response.ok) {
                alert(json.error ?? 'Error al registrar la serie.');
                return;
            }

            // Append new row to the table
            this._appendSetRow(exerciseId, json.setLog, measurementType);

            // Clear form inputs
            this._clearForm(exerciseId);

            // Update state
            const updatedExercise = this.stateValue.exercises.find(e => e.id === exerciseId);
            if (updatedExercise) {
                updatedExercise.loggedSets = json.setLog.setNumber;
            }
            this.stateValue = {
                ...this.stateValue,
                currentExerciseId: json.newCurrentExerciseId,
            };

            // Dispatch rest timer event
            window.dispatchEvent(new CustomEvent('workout:setLogged', {
                detail: { restSuggested: 90, setLogId: json.setLog.id },
            }));

            // Show completion modal if all sets done
            if (json.workoutComplete) {
                this._showCompletionModal();
            }

        } catch (err) {
            console.error('logSet error:', err);
            alert('Error de red al registrar la serie.');
        } finally {
            button.disabled = false;
        }
    }

    closeModal() {
        if (this.hasCompletionModalTarget) {
            this.completionModalTarget.classList.add('hidden');
        }
    }

    // -------------------------------------------------------------------------
    // Private — locking
    // -------------------------------------------------------------------------

    _applyLocking() {
        const { currentExerciseId, exercises } = this.stateValue;

        // Determine unlocked ids
        const unlockedIds = this._getUnlockedIds(currentExerciseId, exercises);

        this.exerciseCardTargets.forEach(card => {
            const cardId = parseInt(card.dataset.exerciseId, 10);
            const isUnlocked = unlockedIds.includes(cardId);
            this._setCardLocked(card, cardId, !isUnlocked);
        });
    }

    /**
     * Returns array of exercise IDs that should be unlocked given currentExerciseId.
     */
    _getUnlockedIds(currentExerciseId, exercises) {
        if (!exercises || exercises.length === 0) return [];

        // Find exercise with currentExerciseId to check its type
        if (currentExerciseId !== null && currentExerciseId !== undefined) {
            const current = exercises.find(e => e.id === currentExerciseId);
            if (!current) return [];

            if (current.seriesType === 'superseries') {
                // Unlock all exercises in same superseriesGroup
                return exercises
                    .filter(e => e.superseriesGroup !== null && e.superseriesGroup === current.superseriesGroup)
                    .map(e => e.id);
            }

            // Sequential lock — only current
            return [currentExerciseId];
        }

        // currentExerciseId === null — check if we're in a superseries group
        // Find exercises with superseries type that are not yet complete
        const superEx = exercises.find(
            e => e.seriesType === 'superseries' && e.loggedSets < e.targetSets
        );
        if (superEx && superEx.superseriesGroup !== null) {
            return exercises
                .filter(e => e.superseriesGroup === superEx.superseriesGroup)
                .map(e => e.id);
        }

        // All done or no current exercise — unlock nothing (or everything if workout complete)
        return [];
    }

    _setCardLocked(card, cardId, locked) {
        const overlay = this.lockedOverlayTargets.find(
            o => parseInt(o.dataset.overlayExerciseId, 10) === cardId
        );

        if (locked) {
            card.classList.add('opacity-60');
            overlay?.classList.remove('hidden');

            // Disable form inputs
            const form = this.setFormTargets.find(
                f => parseInt(f.dataset.formExerciseId, 10) === cardId
            );
            if (form) {
                form.querySelectorAll('input, textarea, button').forEach(el => {
                    el.disabled = true;
                });
            }
        } else {
            card.classList.remove('opacity-60');
            overlay?.classList.add('hidden');

            // Enable form inputs
            const form = this.setFormTargets.find(
                f => parseInt(f.dataset.formExerciseId, 10) === cardId
            );
            if (form) {
                form.querySelectorAll('input, textarea, button').forEach(el => {
                    el.disabled = false;
                });
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private — form helpers
    // -------------------------------------------------------------------------

    _collectFormData(exerciseId, measurementType) {
        const getValue = (target, attr) => {
            const el = this.element.querySelector(
                `[data-workout-target="${target}"][data-form-exercise-id="${exerciseId}"]`
            );
            return el ? el.value : null;
        };

        const payload = {};

        if (measurementType === 'reps_weight') {
            const reps = parseInt(getValue('fieldReps'), 10);
            if (!reps || reps <= 0) {
                alert('El campo "Reps" es obligatorio y debe ser mayor que 0.');
                return null;
            }
            payload.reps = reps;

            const weight = parseFloat(getValue('fieldWeight'));
            if (!isNaN(weight) && weight > 0) payload.weight = weight;

            const rir = parseInt(getValue('fieldRir'), 10);
            if (!isNaN(rir) && rir >= 0) payload.rir = rir;

        } else if (measurementType === 'time_distance') {
            const duration = parseInt(getValue('fieldTimeDuration'), 10);
            if (!duration || duration <= 0) {
                alert('El campo "Duración" es obligatorio y debe ser mayor que 0.');
                return null;
            }
            payload.timeDuration = duration;

            const distance = parseFloat(getValue('fieldDistance'));
            if (!isNaN(distance) && distance > 0) payload.distance = distance;

        } else if (measurementType === 'time_kcal') {
            const duration = parseInt(getValue('fieldTimeDuration'), 10);
            if (!duration || duration <= 0) {
                alert('El campo "Duración" es obligatorio y debe ser mayor que 0.');
                return null;
            }
            payload.timeDuration = duration;

            const kcal = parseInt(getValue('fieldKcal'), 10);
            if (!isNaN(kcal) && kcal > 0) payload.kcal = kcal;
        }

        const observacion = getValue('fieldObservacion');
        if (observacion) payload.observacion = observacion;

        return payload;
    }

    _clearForm(exerciseId) {
        const selectors = [
            'fieldReps', 'fieldWeight', 'fieldRir',
            'fieldTimeDuration', 'fieldDistance', 'fieldKcal', 'fieldObservacion',
        ];
        selectors.forEach(target => {
            const el = this.element.querySelector(
                `[data-workout-target="${target}"][data-form-exercise-id="${exerciseId}"]`
            );
            if (el) el.value = '';
        });
    }

    _appendSetRow(exerciseId, setLog, measurementType) {
        const tbody = this.setTableBodyTargets.find(
            t => parseInt(t.dataset.tableExerciseId, 10) === exerciseId
        );

        if (!tbody) {
            console.warn('[workout] setTableBody target not found for exercise', exerciseId);
            return;
        }

        const tr = document.createElement('tr');
        tr.className = 'text-gray-700';

        let cells = `<td class="py-1.5 pr-3 font-semibold text-gray-400">${setLog.setNumber}</td>`;

        if (measurementType === 'reps_weight') {
            cells += `<td class="py-1.5 pr-3">${setLog.reps ?? '—'}</td>`;
            cells += `<td class="py-1.5 pr-3">${setLog.weight ?? '—'}</td>`;
            cells += `<td class="py-1.5 pr-3">${setLog.rir ?? '—'}</td>`;
        } else if (measurementType === 'time_distance') {
            const mins = Math.floor((setLog.timeDuration ?? 0) / 60);
            const secs = String((setLog.timeDuration ?? 0) % 60).padStart(2, '0');
            cells += `<td class="py-1.5 pr-3">${setLog.timeDuration ? `${mins}:${secs}` : '—'}</td>`;
            cells += `<td class="py-1.5 pr-3">${setLog.distance ?? '—'}</td>`;
        } else {
            const mins = Math.floor((setLog.timeDuration ?? 0) / 60);
            const secs = String((setLog.timeDuration ?? 0) % 60).padStart(2, '0');
            cells += `<td class="py-1.5 pr-3">${setLog.timeDuration ? `${mins}:${secs}` : '—'}</td>`;
            cells += `<td class="py-1.5 pr-3">${setLog.kcal ?? '—'}</td>`;
        }

        cells += `<td class="py-1.5">—</td>`;

        tr.innerHTML = cells;
        tbody.appendChild(tr);

        // Un-hide the table wrapper when appending the first row
        const tableWrapper = this.element.querySelector(
            `[data-workout-target="setTable"][data-exercise-id="${exerciseId}"]`
        );
        if (tableWrapper) tableWrapper.classList.remove('hidden');
    }

    _showCompletionModal() {
        if (this.hasCompletionModalTarget) {
            this.completionModalTarget.classList.remove('hidden');
        }
    }
}

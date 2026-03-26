import { Controller } from '@hotwired/stimulus';

/**
 * rest-timer-controller — countdown timer shown after logging a set.
 *
 * Usage: placed on the parent element that wraps the timer UI.
 * Listens for the `workout:setLogged` custom event dispatched by workout-controller.
 *
 * Targets:
 *   timerPanel   — the container div (hidden/shown)
 *   display      — shows MM:SS countdown
 *   progressBar  — CSS width shrinks from 100% → 0%
 *   skipButton   — stops the timer immediately
 *   doneMessage  — shown when countdown reaches 0
 */
export default class extends Controller {
    static targets = ['timerPanel', 'display', 'progressBar', 'skipButton', 'doneMessage'];
    static values  = { seconds: { type: Number, default: 90 } };

    connect() {
        this._remaining  = 0;
        this._total      = 0;
        this._interval   = null;
        this._pendingSetLogId = null;

        this._boundHandler = this._onSetLogged.bind(this);
        window.addEventListener('workout:setLogged', this._boundHandler);
    }

    disconnect() {
        window.removeEventListener('workout:setLogged', this._boundHandler);
        this._stop();
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    skip() {
        this._stop();
        this._hide();
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    _onSetLogged(event) {
        const seconds = event.detail?.restSuggested ?? this.secondsValue;
        this._pendingSetLogId = event.detail?.setLogId ?? null;
        this._start(seconds);
    }

    _start(seconds) {
        this._stop();
        this._remaining = seconds;
        this._total     = seconds;

        this._show();
        this._updateDisplay();
        this._updateProgressBar();

        this._interval = setInterval(() => this._tick(), 1000);
    }

    _tick() {
        if (this._remaining <= 0) {
            this._stop();
            this._onFinished();
            return;
        }
        this._remaining--;
        this._updateDisplay();
        this._updateProgressBar();
    }

    _stop() {
        if (this._interval !== null) {
            clearInterval(this._interval);
            this._interval = null;
        }
    }

    _onFinished() {
        this._updateDisplay();
        if (this.hasDoneMessageTarget) {
            this.doneMessageTarget.classList.remove('hidden');
        }
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = '0%';
        }
    }

    _show() {
        if (this.hasTimerPanelTarget) {
            this.timerPanelTarget.classList.remove('hidden');
        }
        if (this.hasDoneMessageTarget) {
            this.doneMessageTarget.classList.add('hidden');
        }
    }

    _hide() {
        if (this.hasTimerPanelTarget) {
            this.timerPanelTarget.classList.add('hidden');
        }
    }

    _updateDisplay() {
        if (!this.hasDisplayTarget) return;
        const mins = Math.floor(this._remaining / 60);
        const secs = String(this._remaining % 60).padStart(2, '0');
        this.displayTarget.textContent = `${mins}:${secs}`;
    }

    _updateProgressBar() {
        if (!this.hasProgressBarTarget || this._total === 0) return;
        const pct = (this._remaining / this._total) * 100;
        this.progressBarTarget.style.width = `${pct}%`;
    }
}

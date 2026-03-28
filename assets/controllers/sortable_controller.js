import { Controller } from '@hotwired/stimulus';

/**
 * Sortable controller — native drag-and-drop reordering.
 *
 * Usage:
 *   <ul data-controller="sortable"
 *       data-sortable-url-value="/sessions/reorder">
 *     <li data-sortable-target="item" data-id="1">…</li>
 *     <li data-sortable-target="item" data-id="2">…</li>
 *   </ul>
 *
 * On drop, POSTs { ids: [ordered, id, list] } as JSON to `data-sortable-url-value`.
 *
 * Cross-session drag-and-drop:
 *   When moveUrlValue, sessionIdValue, and movecsrfValue are set, exercises can
 *   be dragged from one session container to another. A shared module-level state
 *   (_globalDrag) bridges the Stimulus controller instances.
 */

// Shared drag state across all sortable controller instances on the page.
const _globalDrag = { item: null, sourceController: null };

export default class extends Controller {
    static targets = ['list', 'item'];
    static values = { url: String, csrf: String, moveUrl: String, sessionId: Number, movecsrf: String };

    connect() {
        this._draggedItem = null;

        // If a list target is declared, use it; otherwise use the root element.
        const container = this.hasListTarget ? this.listTarget : this.element;
        this._setupContainer(container);

        // The controller element itself needs dragover/drop/dragleave for cross-session drops
        // when the list target is empty (no items to relay the events).
        if (this.hasMoveUrlValue) {
            this.element.addEventListener('dragover', this._onDragOver.bind(this));
            this.element.addEventListener('drop', this._onDrop.bind(this));
            this.element.addEventListener('dragleave', this._onDragLeave.bind(this));
        }
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    _setupContainer(container) {
        container.addEventListener('dragstart', this._onDragStart.bind(this));
        container.addEventListener('dragover', this._onDragOver.bind(this));
        container.addEventListener('dragend', this._onDragEnd.bind(this));
        container.addEventListener('drop', this._onDrop.bind(this));
        container.addEventListener('dragleave', this._onDragLeave.bind(this));
    }

    _onDragStart(event) {
        event.stopPropagation();
        const item = event.target.closest('[data-sortable-target="item"]');
        if (!item) return;

        this._draggedItem = item;
        _globalDrag.item = item;
        _globalDrag.sourceController = this;

        item.style.opacity = '0.4';
        event.dataTransfer.effectAllowed = 'move';
        // Store the id so we can identify the element on drop
        event.dataTransfer.setData('text/plain', item.dataset.id);
    }

    _onDragOver(event) {
        event.stopPropagation();
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        // Within-session path (existing logic)
        if (this._draggedItem) {
            const target = event.target.closest('[data-sortable-target="item"]');
            if (!target || target === this._draggedItem) return;

            const container = this.hasListTarget ? this.listTarget : this.element;
            const items = [...container.querySelectorAll(':scope > [data-sortable-target="item"]')];
            const draggedIndex = items.indexOf(this._draggedItem);
            const targetIndex = items.indexOf(target);
            if (draggedIndex === -1 || targetIndex === -1) return;

            if (draggedIndex < targetIndex) {
                target.after(this._draggedItem);
            } else {
                target.before(this._draggedItem);
            }
            return;
        }

        // Cross-session path
        if (!_globalDrag.item || _globalDrag.sourceController === this || !this.hasMoveUrlValue) return;

        const container = this.hasListTarget ? this.listTarget : this.element;
        const target = event.target.closest('[data-sortable-target="item"]');

        if (target && target !== _globalDrag.item) {
            const rect = target.getBoundingClientRect();
            const after = event.clientY > rect.top + rect.height / 2;
            if (after) {
                target.after(_globalDrag.item);
            } else {
                target.before(_globalDrag.item);
            }
        } else if (!target || !container.contains(event.target)) {
            // Hovering over empty space or empty list — append at end
            container.appendChild(_globalDrag.item);
        }

        // Visual indicator on the receiving container
        this.element.classList.add('ring-2', 'ring-inset', 'ring-indigo-500', 'rounded-b-lg', 'sortable-cross-target');
    }

    _onDragLeave(event) {
        if (!this.element.contains(event.relatedTarget)) {
            this.element.classList.remove('ring-2', 'ring-inset', 'ring-indigo-500', 'rounded-b-lg', 'sortable-cross-target');
        }
    }

    _onDrop(event) {
        event.stopPropagation();
        event.preventDefault();

        // Cross-session drop
        if (!this._draggedItem && _globalDrag.item && _globalDrag.sourceController !== this && this.hasMoveUrlValue) {
            this.element.classList.remove('ring-2', 'ring-inset', 'ring-indigo-500', 'rounded-b-lg', 'sortable-cross-target');

            const exerciseId = _globalDrag.item.dataset.id;
            const item = _globalDrag.item;
            const sourceController = _globalDrag.sourceController;

            fetch(this.moveUrlValue.replace('__ID__', exerciseId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ _token: this.movecsrfValue }),
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Patch stale session URLs in the moved element
                    const oldId = item.dataset.sessionId;
                    const newId = String(this.sessionIdValue);
                    item.querySelectorAll('[href], [action]').forEach(el => {
                        ['href', 'action'].forEach(attr => {
                            const val = el.getAttribute(attr);
                            if (val && val.includes(`/sessions/${oldId}/`)) {
                                el.setAttribute(attr, val.replace(
                                    `/sessions/${oldId}/`,
                                    `/sessions/${newId}/`
                                ));
                            }
                        });
                    });
                    item.dataset.sessionId = newId;
                    // Restore opacity (was set in dragstart by source controller)
                    item.style.opacity = '';
                } else {
                    // Revert DOM: move item back to source controller's container
                    sourceController._reattach(item);
                }
            })
            .catch(() => {
                sourceController._reattach(item);
            })
            .finally(() => {
                _globalDrag.item = null;
                _globalDrag.sourceController = null;
            });

            return;
        }

        // Within-session drop: nothing extra needed (dragEnd persists)
    }

    _onDragEnd(event) {
        event.stopPropagation();

        // Clear global drag state
        _globalDrag.item = null;
        _globalDrag.sourceController = null;

        // Remove drop-target ring from all containers
        document.querySelectorAll('.sortable-cross-target').forEach(el => {
            el.classList.remove('ring-2', 'ring-inset', 'ring-indigo-500', 'rounded-b-lg', 'sortable-cross-target');
        });

        if (this._draggedItem) {
            this._draggedItem.style.opacity = '';
            this._draggedItem = null;
            this._persist();
        }
    }

    _reattach(item) {
        const container = this.hasListTarget ? this.listTarget : this.element;
        container.appendChild(item);
        item.style.opacity = '';
        this._persist();
    }

    _persist() {
        if (!this.urlValue) return;

        const container = this.hasListTarget ? this.listTarget : this.element;
        const ids = [...container.querySelectorAll(':scope > [data-sortable-target="item"]')]
            .map(item => item.dataset.id);

        fetch(this.urlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ids, _token: this.csrfValue }),
        }).catch(err => console.error('Sortable persist failed:', err));
    }
}

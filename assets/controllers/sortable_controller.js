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
 */
export default class extends Controller {
    static targets = ['list', 'item'];
    static values = { url: String, csrf: String };

    connect() {
        this._draggedItem = null;

        // If a list target is declared, use it; otherwise use the root element.
        const container = this.hasListTarget ? this.listTarget : this.element;
        this._setupContainer(container);
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    _setupContainer(container) {
        container.addEventListener('dragstart', this._onDragStart.bind(this));
        container.addEventListener('dragover', this._onDragOver.bind(this));
        container.addEventListener('dragend', this._onDragEnd.bind(this));
        container.addEventListener('drop', this._onDrop.bind(this));
    }

    _onDragStart(event) {
        const item = event.target.closest('[data-sortable-target="item"]');
        if (!item) return;

        this._draggedItem = item;
        item.style.opacity = '0.4';
        event.dataTransfer.effectAllowed = 'move';
        // Store the id so we can identify the element on drop
        event.dataTransfer.setData('text/plain', item.dataset.id);
    }

    _onDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        const target = event.target.closest('[data-sortable-target="item"]');
        if (!target || target === this._draggedItem) return;

        const container = this.hasListTarget ? this.listTarget : this.element;
        const items = [...container.querySelectorAll('[data-sortable-target="item"]')];
        const draggedIndex = items.indexOf(this._draggedItem);
        const targetIndex = items.indexOf(target);

        if (draggedIndex < targetIndex) {
            target.after(this._draggedItem);
        } else {
            target.before(this._draggedItem);
        }
    }

    _onDrop(event) {
        event.preventDefault();
    }

    _onDragEnd(event) {
        if (this._draggedItem) {
            this._draggedItem.style.opacity = '';
            this._draggedItem = null;
        }
        this._persist();
    }

    _persist() {
        if (!this.urlValue) return;

        const container = this.hasListTarget ? this.listTarget : this.element;
        const ids = [...container.querySelectorAll('[data-sortable-target="item"]')]
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

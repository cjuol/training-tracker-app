import { Controller } from '@hotwired/stimulus';

/**
 * measurement-pagination — fetches body measurements via JSON and renders them
 * into the table body without a full page reload.
 *
 * Values:
 *   url       (String) — the JSON endpoint, e.g. /profile/measurements/list
 *   editUrl   (String) — edit link template with __ID__ placeholder
 *   deleteUrl (String) — delete link template with __ID__ placeholder
 *
 * Targets:
 *   tableBody  — <tbody> where rows are injected
 *   emptyState — element shown when there are no items
 *   pagination — wrapper for pagination controls (hidden when empty)
 *   prevButton — "Anterior" button
 *   nextButton — "Siguiente" button
 *   pageInfo   — text showing "Página X de Y (Z registros)"
 */
export default class extends Controller {
    static targets = ['tableBody', 'emptyState', 'pagination', 'prevButton', 'nextButton', 'pageInfo'];

    static values = {
        url:       String,
        editUrl:   String,
        deleteUrl: String,
    };

    connect() {
        this._currentPage = 1;
        this._fetchPage(1);
    }

    prevPage() {
        if (this._currentPage > 1) {
            this._fetchPage(this._currentPage - 1);
        }
    }

    nextPage() {
        this._fetchPage(this._currentPage + 1);
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    async _fetchPage(page) {
        try {
            const url = `${this.urlValue}?page=${page}`;
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                console.error('Error fetching measurements:', response.status);
                return;
            }

            const data = await response.json();
            this._currentPage = data.page;

            this._renderRows(data.items);
            this._updatePagination(data);
        } catch (err) {
            console.error('measurement-pagination fetch error:', err);
        }
    }

    _renderRows(items) {
        if (!this.hasTableBodyTarget) return;

        if (items.length === 0 && this._currentPage === 1) {
            this.tableBodyTarget.innerHTML = '';
            if (this.hasEmptyStateTarget) {
                this.emptyStateTarget.classList.remove('hidden');
            }
            if (this.hasPaginationTarget) {
                this.paginationTarget.classList.add('hidden');
            }
            return;
        }

        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.add('hidden');
        }
        if (this.hasPaginationTarget) {
            this.paginationTarget.classList.remove('hidden');
        }

        this.tableBodyTarget.innerHTML = items.map(item => this._buildRow(item)).join('');
    }

    _buildRow(item) {
        const date    = item.measurement_date ?? '—';
        const weight  = item.weight_kg  !== null ? item.weight_kg  : '—';
        const chest   = item.chest_cm   !== null ? item.chest_cm   : '—';
        const waist   = item.waist_cm   !== null ? item.waist_cm   : '—';
        const hips    = item.hips_cm    !== null ? item.hips_cm    : '—';
        const arms    = item.arms_cm    !== null ? item.arms_cm    : '—';
        const notes   = item.notes ? this._truncate(item.notes, 40) : '—';

        const editUrl   = this.editUrlValue.replace('__ID__', item.id);
        const deleteUrl = this.deleteUrlValue.replace('__ID__', item.id);

        return `
            <tr class="hover:bg-gray-50">
                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">${date}</td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">${weight}</td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">${chest}</td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">${waist}</td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">${hips}</td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">${arms}</td>
                <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate" title="${this._escapeHtml(item.notes ?? '')}">${notes}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                    <a href="${editUrl}" class="text-indigo-600 hover:text-indigo-800 font-medium mr-3">Editar</a>
                    <form method="POST" action="${deleteUrl}" class="inline"
                          onsubmit="return confirm('¿Eliminar esta medición?')">
                        <input type="hidden" name="_token" value="${this._escapeHtml(item.csrf_token ?? '')}">
                        <button type="submit" class="text-red-500 hover:text-red-700 font-medium">Eliminar</button>
                    </form>
                </td>
            </tr>
        `;
    }

    _updatePagination(data) {
        const totalPages = data.perPage > 0 ? Math.ceil(data.total / data.perPage) : 1;

        if (this.hasPageInfoTarget) {
            this.pageInfoTarget.textContent = `Página ${data.page} de ${totalPages} (${data.total} registros)`;
        }

        if (this.hasPrevButtonTarget) {
            this.prevButtonTarget.disabled = data.page <= 1;
        }

        if (this.hasNextButtonTarget) {
            this.nextButtonTarget.disabled = !data.hasMore;
        }
    }

    _truncate(str, maxLen) {
        return str.length > maxLen ? str.slice(0, maxLen) + '…' : str;
    }

    _escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

}

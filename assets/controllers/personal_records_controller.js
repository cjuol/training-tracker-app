import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["container"]
  static values  = { url: String }

  async connect() {
    try {
      const res = await fetch(this.urlValue)
      if (!res.ok) {
        this._renderEmpty()
        return
      }
      const data = await res.json()
      this._render(data)
    } catch {
      this._renderEmpty()
    }
  }

  _render(records) {
    if (!Array.isArray(records) || records.length === 0) {
      this._renderEmpty()
      return
    }

    const table = document.createElement('table')
    table.className = 'min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm'

    const thead = document.createElement('thead')
    thead.innerHTML = `
      <tr>
        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ejercicio</th>
        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Peso máx.</th>
        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
      </tr>`
    table.appendChild(thead)

    const tbody = document.createElement('tbody')
    tbody.className = 'divide-y divide-gray-100 dark:divide-gray-700'

    records.forEach(r => {
      const tr = document.createElement('tr')
      tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700'

      const weight = r.maxWeight != null
        ? `${Number(r.maxWeight).toFixed(1)} kg`
        : '—'

      let dateStr = '—'
      if (r.date) {
        try {
          const d = new Date(r.date + 'T00:00:00')
          dateStr = d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
        } catch {
          dateStr = r.date
        }
      }

      tr.innerHTML = `
        <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">${this._esc(r.exercise)}</td>
        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">${weight}</td>
        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">${dateStr}</td>`
      tbody.appendChild(tr)
    })

    table.appendChild(tbody)

    const wrapper = document.createElement('div')
    wrapper.className = 'overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700'
    wrapper.appendChild(table)

    this.containerTarget.innerHTML = ''
    this.containerTarget.appendChild(wrapper)
  }

  _renderEmpty() {
    this.containerTarget.innerHTML =
      '<p class="text-sm text-gray-400 dark:text-gray-500">No hay records registrados todavía.</p>'
  }

  _esc(str) {
    const d = document.createElement('div')
    d.textContent = str ?? ''
    return d.innerHTML
  }
}

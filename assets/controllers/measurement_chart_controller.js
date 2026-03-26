import { Controller } from "@hotwired/stimulus"
import { Chart, LineController, LineElement, PointElement, LinearScale, TimeScale, Tooltip, Legend, CategoryScale } from "chart.js"

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend)

export default class extends Controller {
  static targets = ["canvas"]
  static values = { url: String, fields: Array }

  connect() {
    this._load()
  }

  disconnect() {
    if (this._chart) {
      this._chart.destroy()
      this._chart = null
    }
  }

  async _load() {
    const params = new URLSearchParams({ page: 1, limit: 50 })
    const response = await fetch(this.urlValue + '?' + params)
    if (!response.ok) return
    const data = await response.json()
    const items = data.items ?? data
    if (!items.length) return
    this._render(items.reverse()) // oldest first
  }

  _render(items) {
    const labels = items.map(i => i.measurement_date)
    const palette = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6']
    const fieldLabels = {
      weight_kg: 'Peso (kg)',
      chest_cm: 'Pecho (cm)',
      waist_cm: 'Cintura (cm)',
      hips_cm: 'Caderas (cm)',
      arms_cm: 'Brazos (cm)',
    }
    const fields = this.fieldsValue.length ? this.fieldsValue : Object.keys(fieldLabels)
    const datasets = fields
      .filter(f => items.some(i => i[f] !== null && i[f] !== undefined))
      .map((field, idx) => ({
        label: fieldLabels[field] ?? field,
        data: items.map(i => i[field] ?? null),
        borderColor: palette[idx % palette.length],
        backgroundColor: palette[idx % palette.length] + '20',
        tension: 0.3,
        spanGaps: true,
      }))

    if (this._chart) this._chart.destroy()
    this._chart = new Chart(this.canvasTarget, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          x: { ticks: { maxTicksLimit: 8 } },
          y: { beginAtZero: false },
        },
      },
    })
  }
}

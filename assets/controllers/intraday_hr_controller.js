import { Controller } from "@hotwired/stimulus"
import {
  Chart, LineController, LineElement, PointElement,
  LinearScale, CategoryScale, Tooltip, Legend
} from "chart.js"

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend)

export default class extends Controller {
  static targets = ["panel", "canvas", "empty"]
  static values  = { url: String }

  _chart  = null
  _loaded = false

  toggle() {
    const panel    = this.panelTarget
    const isHidden = panel.classList.contains("hidden")
    panel.classList.toggle("hidden", !isHidden)

    if (isHidden && !this._loaded) {
      this._loaded = true
      this._load()
    }
  }

  disconnect() {
    if (this._chart) {
      this._chart.destroy()
      this._chart = null
    }
  }

  async _load() {
    try {
      const res  = await fetch(this.urlValue)
      if (!res.ok) return
      const data = await res.json()
      this._render(data)
    } catch { /* network error */ }
  }

  _render(data) {
    if (!data.length) {
      this.emptyTarget.classList.remove("hidden")
      return
    }

    const labels  = data.map(d => d.hour)
    const values  = data.map(d => d.avgBpm)

    if (this._chart) this._chart.destroy()
    this._chart = new Chart(this.canvasTarget, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label:           'BPM promedio',
          data:            values,
          borderColor:     '#f97316',
          backgroundColor: 'rgba(249,115,22,0.15)',
          tension:         0.3,
          fill:            true,
          pointRadius:     3,
        }],
      },
      options: {
        responsive:          true,
        maintainAspectRatio: false,
        plugins: {
          legend:  { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          x: { ticks: { font: { size: 10 } } },
          y: { beginAtZero: false, ticks: { font: { size: 10 } } },
        },
      },
    })
  }
}

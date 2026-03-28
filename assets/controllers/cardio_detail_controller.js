import { Controller } from "@hotwired/stimulus"
import {
  Chart, BarController, BarElement,
  LinearScale, CategoryScale, Tooltip, Legend
} from "chart.js"

Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend)

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
      if (!data.length) {
        this.emptyTarget.classList.remove("hidden")
        return
      }
      this._render(data)
    } catch { /* network error */ }
  }

  _render(data) {
    const ZONE_COLORS = {
      'Out of Range': '#e5e7eb',
      'Fat Burn':     '#fbbf24',
      'Cardio':       '#f97316',
      'Peak':         '#ef4444',
    }
    const zoneNames = ['Out of Range', 'Fat Burn', 'Cardio', 'Peak']
    const labels    = data.map(d => d.date)

    const datasets = zoneNames.map(name => ({
      label:           name,
      data:            data.map(d => {
        const z = (d.zones ?? []).find(z => z.name === name)
        return z?.minutes ?? 0
      }),
      backgroundColor: ZONE_COLORS[name],
      stack:           's',
    }))

    if (this._chart) this._chart.destroy()
    this._chart = new Chart(this.canvasTarget, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          x: { stacked: true, ticks: { font: { size: 10 } } },
          y: { stacked: true, beginAtZero: true, ticks: { font: { size: 10 } } },
        },
      },
    })
  }
}

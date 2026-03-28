import { Controller } from "@hotwired/stimulus"
import {
  Chart, BarController, BarElement,
  LinearScale, CategoryScale, Tooltip, Legend
} from "chart.js"

Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend)

export default class extends Controller {
  static targets = ["panel", "canvas", "empty"]
  static values  = { url: String }

  _chart   = null
  _loaded  = false

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
    } catch { /* network error: show nothing */ }
  }

  _render(data) {
    const labels = data.map(d => d.date)
    const total  = data.map(d => d.durationMinutes)
    // Stacked stages — may be all zeros if no stage data
    const deep   = data.map(d => d.stages?.deep  ?? 0)
    const rem    = data.map(d => d.stages?.rem   ?? 0)
    const light  = data.map(d => d.stages?.light ?? 0)
    const wake   = data.map(d => d.stages?.wake  ?? 0)
    const hasStages = data.some(d =>
      (d.stages?.deep ?? 0) + (d.stages?.rem ?? 0) + (d.stages?.light ?? 0) > 0
    )

    // Decide between stacked-stage view vs simple total bar
    const datasets = hasStages ? [
      { label: 'Profundo',  data: deep,  backgroundColor: '#4f46e5', stack: 's' },
      { label: 'REM',       data: rem,   backgroundColor: '#7c3aed', stack: 's' },
      { label: 'Ligero',    data: light, backgroundColor: '#93c5fd', stack: 's' },
      { label: 'Despierto', data: wake,  backgroundColor: '#fca5a5', stack: 's' },
    ] : [
      { label: 'Minutos', data: total, backgroundColor: '#3b82f6' },
    ]

    if (this._chart) this._chart.destroy()
    this._chart = new Chart(this.canvasTarget, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } },
          tooltip: {
            callbacks: {
              label: ctx => {
                const m = ctx.parsed.y
                return `${ctx.dataset.label}: ${Math.floor(m / 60)}h${m % 60}m`
              },
            },
          },
        },
        scales: {
          x: { stacked: hasStages, ticks: { font: { size: 10 } } },
          y: {
            stacked: hasStages,
            beginAtZero: true,
            ticks: {
              font: { size: 10 },
              callback: v => `${Math.floor(v / 60)}h`,
            },
          },
        },
      },
    })
  }
}

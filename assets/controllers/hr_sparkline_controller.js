import { Controller } from "@hotwired/stimulus"
import {
  Chart, BarController, BarElement,
  LinearScale, CategoryScale, Tooltip
} from "chart.js"

Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip)

const COLOR_MAP = {
  green: '#22c55e',
  amber: '#f59e0b',
  red:   '#ef4444',
  gray:  '#9ca3af',
}

export default class extends Controller {
  static targets = ["canvas"]
  static values  = { chartData: String }

  _chart = null

  connect() {
    let points = []
    try {
      points = JSON.parse(this.chartDataValue)
    } catch {
      return
    }

    if (!Array.isArray(points) || points.length === 0) return

    const labels = points.map(p => {
      const d = new Date(p.date)
      return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`
    })
    const values = points.map(p => p.value)
    const colors = points.map(p => COLOR_MAP[p.color] ?? COLOR_MAP.gray)

    if (this._chart) this._chart.destroy()

    this._chart = new Chart(this.canvasTarget, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          data:            values,
          backgroundColor: colors,
          borderRadius:    2,
        }],
      },
      options: {
        responsive:          true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: (items) => points[items[0].dataIndex]?.date ?? '',
              label: (item) => `${item.raw} bpm`,
            },
          },
        },
        scales: {
          x: { display: false },
          y: { display: false, beginAtZero: false },
        },
      },
    })
  }

  disconnect() {
    if (this._chart) {
      this._chart.destroy()
      this._chart = null
    }
  }
}

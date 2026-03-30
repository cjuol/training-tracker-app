import { Controller } from "@hotwired/stimulus"

const CELL_SIZE = 14
const CELL_GAP  = 2
const STEP      = CELL_SIZE + CELL_GAP

const COLORS = ['#e5e7eb', '#bbf7d0', '#4ade80', '#16a34a']

function toYmd(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

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

  _render(data) {
    // Build date→count map
    const dates = Array.isArray(data.dates) ? data.dates : []
    const countMap = {}
    dates.forEach(d => {
      countMap[d] = (countMap[d] ?? 0) + 1
    })

    // Build grid: last 91 days (13 weeks × 7 days), starting from Monday
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    // Find the Monday of the week 13 weeks ago
    const dayOfWeek = (today.getDay() + 6) % 7 // 0=Mon, 6=Sun
    const gridStart = new Date(today)
    gridStart.setDate(today.getDate() - dayOfWeek - (13 - 1) * 7)

    // 13 cols (weeks) × 7 rows (days)
    const COLS = 13
    const ROWS = 7

    const svgW = COLS * STEP - CELL_GAP
    const svgH = ROWS * STEP - CELL_GAP

    const ns = 'http://www.w3.org/2000/svg'
    const svg = document.createElementNS(ns, 'svg')
    svg.setAttribute('width', String(svgW))
    svg.setAttribute('height', String(svgH))
    svg.setAttribute('viewBox', `0 0 ${svgW} ${svgH}`)
    svg.setAttribute('aria-label', 'Calendario de actividad')

    for (let col = 0; col < COLS; col++) {
      for (let row = 0; row < ROWS; row++) {
        const cellDate = new Date(gridStart)
        cellDate.setDate(gridStart.getDate() + col * 7 + row)

        // Only render cells up to today
        if (cellDate > today) continue

        const ymd   = toYmd(cellDate)
        const count = countMap[ymd] ?? 0
        const color = COLORS[Math.min(count, COLORS.length - 1)]

        const rect = document.createElementNS(ns, 'rect')
        rect.setAttribute('x', String(col * STEP))
        rect.setAttribute('y', String(row * STEP))
        rect.setAttribute('width',  String(CELL_SIZE))
        rect.setAttribute('height', String(CELL_SIZE))
        rect.setAttribute('rx', '2')
        rect.setAttribute('fill', color)

        const title = document.createElementNS(ns, 'title')
        title.textContent = `${ymd}: ${count} sesiones`
        rect.appendChild(title)

        svg.appendChild(rect)
      }
    }

    this.containerTarget.innerHTML = ''
    this.containerTarget.appendChild(svg)
  }

  _renderEmpty() {
    this.containerTarget.textContent = 'Sin actividad reciente'
  }
}

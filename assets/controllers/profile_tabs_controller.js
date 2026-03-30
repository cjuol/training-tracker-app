import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["tab", "panel"]
  static values  = { active: { type: String, default: "resumen" } }

  connect() {
    const hash = window.location.hash.replace("#", "")
    const initial = hash && this._findPanel(hash) ? hash : "resumen"
    this._activate(initial)
  }

  show(event) {
    const tab = event.params.tab
    if (!tab) return
    this._activate(tab)
    history.pushState(null, '', `#${tab}`)
  }

  // ─── private ────────────────────────────────────────────────────────────────

  _activate(tabName) {
    this.panelTargets.forEach(panel => {
      if (panel.dataset.tab === tabName) {
        panel.classList.remove("hidden")
      } else {
        panel.classList.add("hidden")
      }
    })

    if (this.hasTabTarget) {
      this.tabTargets.forEach(btn => {
        if (btn.dataset.profileTabsTabParam === tabName) {
          btn.classList.add("border-b-2", "border-indigo-500", "text-indigo-600", "dark:text-indigo-400")
          btn.classList.remove("text-gray-500", "hover:text-gray-700", "dark:text-gray-400")
        } else {
          btn.classList.remove("border-b-2", "border-indigo-500", "text-indigo-600", "dark:text-indigo-400")
          btn.classList.add("text-gray-500", "hover:text-gray-700", "dark:text-gray-400")
        }
      })
    }

    this.activeValue = tabName
  }

  _findPanel(tabName) {
    return this.panelTargets.find(p => p.dataset.tab === tabName) ?? null
  }
}

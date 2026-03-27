import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static values = { target: String }

  copy() {
    const el = document.querySelector(this.targetValue)
    if (!el) return
    const text = el.textContent.trim()
    navigator.clipboard.writeText(text).then(() => {
      const btn = this.element
      const original = btn.textContent
      btn.textContent = '¡Copiado!'
      setTimeout(() => { btn.textContent = original }, 1500)
    })
  }
}

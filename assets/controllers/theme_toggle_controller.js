import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["knob", "icon"]

  connect() {
    const isDark = localStorage.getItem("theme") === "dark"
    if (isDark) document.documentElement.classList.add("dark")
    this._apply(isDark)
  }

  toggle() {
    const isDark = document.documentElement.classList.toggle("dark")
    localStorage.setItem("theme", isDark ? "dark" : "light")
    this._apply(isDark)
  }

  _apply(isDark) {
    if (this.hasKnobTarget) {
      this.knobTarget.classList.toggle("translate-x-5", isDark)
      this.knobTarget.classList.toggle("translate-x-0", !isDark)
    }
  }
}

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["panel"]
  static values = { open: Boolean }

  connect() {
    this._outsideClickHandler = this._handleOutsideClick.bind(this)
    document.addEventListener("mousedown", this._outsideClickHandler)
  }

  disconnect() {
    document.removeEventListener("mousedown", this._outsideClickHandler)
  }

  toggle() {
    this.openValue = !this.openValue
  }

  openValueChanged() {
    this.panelTarget.classList.toggle("hidden", !this.openValue)
  }

  _handleOutsideClick(event) {
    if (!this.element.contains(event.target)) {
      this.openValue = false
    }
  }
}

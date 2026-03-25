import { Controller } from '@hotwired/stimulus';

/**
 * ExerciseSearch Stimulus controller
 *
 * Filters visible table rows client-side based on the exercise name.
 * No server round-trip — purely DOM manipulation.
 *
 * Usage:
 *   <div data-controller="exercise-search">
 *     <input data-exercise-search-target="input" data-action="input->exercise-search#filter" />
 *     <table data-exercise-search-target="table">
 *       <tbody>
 *         <tr data-exercise-name="press de banca">...</tr>
 *       </tbody>
 *     </table>
 *   </div>
 */
export default class extends Controller {
    static targets = ['input', 'table'];

    filter() {
        const query = this.inputTarget.value.toLowerCase().trim();
        const rows = this.tableTarget.querySelectorAll('tbody tr');

        rows.forEach((row) => {
            const name = row.dataset.exerciseName ?? '';
            row.style.display = name.includes(query) ? '' : 'none';
        });
    }
}

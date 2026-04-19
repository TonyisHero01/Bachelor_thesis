/**
 * Deletes a product by ID, removes its row from the DOM,
 * updates the ID list, and refreshes the page view.
 *
 * @param {string|number} id - Product identifier.
 */
function delete_(id) {
    fetch(`/bms/product_delete/${id}`, {
        method: 'DELETE',
    });

    const row = document.getElementById(id);
    row.remove();

    ids.splice(ids.indexOf(String(id)), 1);

    showPage();
}
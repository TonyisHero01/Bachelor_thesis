/**
 * Deletes an employee by ID, removes its row from the DOM,
 * updates the employee ID list, and refreshes the page view.
 *
 * @param {string|number} id - Employee identifier.
 */
function delete_(id) {
    fetch(`employee_delete/${id}`, {
        method: 'DELETE',
    });

    const row = document.getElementById(id);
    row.remove();

    employeeIds.splice(employeeIds.indexOf(String(id)), 1);

    showPage();
}
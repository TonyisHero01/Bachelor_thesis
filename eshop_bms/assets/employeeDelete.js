function delete_(id) {
    const response = fetch("employee_delete/" + id, {
        method: "DELETE"
    });
    const row = document.getElementById(id);
    row.remove();
    employeeIds.splice(employeeIds.indexOf(id + ""), 1);

    showPage();
}
function delete_(id) {
    var response = fetch("employee_delete/" + id, {
        method: "DELETE"
    });
    var row = document.getElementById(id);
    row.remove();
    employeeIds.splice(employeeIds.indexOf(id+""), 1);

    showPage();
}
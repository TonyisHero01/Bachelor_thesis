function filterByRole() {
    const selectedRole = document.getElementById('roleFilter').value;
    const rows = document.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const roles = row.getAttribute('data-roles') || '';
        if (!selectedRole || roles.includes(selectedRole)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function resetRoleFilter() {
    document.getElementById('roleFilter').value = '';
    filterByRole();
}
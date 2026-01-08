(function () {
    /**
     * Redirects to the edit page for the given employee ID.
     *
     * @param {string|number} id - Employee identifier.
     */
    window.edit = function (id) {
        const el = document.getElementById(String(id));
        const editRoute = el?.dataset?.editRoute;

        if (editRoute) {
            window.location.href = editRoute;
        } else {
            console.error(`Edit route not found for ID ${id}`);
        }
    };

    /**
     * Saves employee data to the server and redirects to the employee list page.
     *
     * @returns {Promise<void>}
     */
    window.save_ = async function () {
        const employeeId = document
            .getElementById('employeeId')
            ?.dataset?.employeeId;

        const surnameEl = document.getElementById('surname');
        const nameEl = document.getElementById('name');
        const phoneEl = document.getElementById('phoneNumber');
        const emailEl = document.getElementById('email');

        if (!employeeId) {
            console.error('employeeId not found on page');
            alert('Employee ID is missing.');
            return;
        }

        const selectedRoles = Array.from(
            document.querySelectorAll("input[name='roles[]']:checked"),
        ).map((cb) => cb.value);

        const payload = {
            surname: surnameEl?.value ?? '',
            name: nameEl?.value ?? '',
            phoneNumber: phoneEl?.value ?? '',
            email: emailEl?.value ?? '',
            roles: selectedRoles,
        };

        try {
            const res = await fetch(
                `/employee_save/${encodeURIComponent(employeeId)}`,
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                },
            );

            if (!res.ok) {
                const text = await res.text().catch(() => '');
                console.error('Save failed:', res.status, text);
                alert('Save failed. Please try again.');
                return;
            }

            const listRoute =
                document.getElementById('routeData')?.dataset
                    ?.employee_listRoute
                || '/employee_list';

            window.location.href = listRoute;
        } catch (e) {
            console.error('Network error while saving:', e);
            alert('Network error. Please try again.');
        }
    };

    /**
     * Redirects back to the employee list page.
     */
    window.backToEmployees = function () {
        const listRoute =
            document.getElementById('routeData')?.dataset
                ?.employee_listRoute
            || '/employee_list';

        window.location.href = listRoute;
    };
}());
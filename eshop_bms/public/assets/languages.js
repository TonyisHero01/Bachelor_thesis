/**
 * Handles submission of the add-language form and sends a request
 * to create a new language entry.
 *
 * @param {Event} e - Form submit event.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('add-language-form');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const langInput = document.getElementById('language-input');
        const tokenInput = document.getElementById('csrf-token');

        if (!langInput || !tokenInput) {
            alert('Missing form data.');
            return;
        }

        const lang = langInput.value.trim();
        const token = tokenInput.value;

        if (!lang) {
            return;
        }

        fetch('/translation/add-language', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                language: lang,
                _token: token,
            }),
        })
            .then((response) => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    return response.text().then((text) => {
                        alert(text || 'Failed to add language.');
                    });
                }
            })
            .catch(() => {
                alert('Network error.');
            });
    });
});
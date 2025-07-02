document.getElementById('add-language-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const lang = document.getElementById('language-input').value.trim();
    if (!lang) return;

    fetch('/translation/add-language', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ language: lang })
    })
    .then(response => {
        if (response.ok) {
            window.location.reload();
        } else {
            alert("Failed to add language.");
        }
    });
});
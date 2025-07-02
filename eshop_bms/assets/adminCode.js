function getAdminCode() {
    const apiElement = document.querySelector('.admin-code');
    const fetchUrl = apiElement?.dataset.api;

    if (!fetchUrl) {
        console.error('API URL not found in .admin-code element.');
        return;
    }

    fetch(fetchUrl)
        .then(response => response.json())
        .then(data => {
            document.getElementById('adminCodeDisplay').innerText = data.code;
            document.getElementById('codeModal').style.display = 'block';
            document.getElementById('modalOverlay').style.display = 'block';
        });
}

function closeModal() {
    document.getElementById('codeModal').style.display = 'none';
    document.getElementById('modalOverlay').style.display = 'none';
}

function copyCode() {
    const code = document.getElementById('adminCodeDisplay').innerText;
    navigator.clipboard.writeText(code);
    alert("已复制：" + code);
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('adminCodeBtn')?.addEventListener('click', getAdminCode);
});
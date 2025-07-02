document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("markAsCompletedBtn");
    if (!btn) return;

    const apiUrl = btn.dataset.api;
    const redirectUrl = btn.dataset.redirectUrl;

    btn.addEventListener("click", () => {
        if (!confirm("Are you sure you want to mark this order as completed?")) return;

        fetch(apiUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Order marked as completed!");
                window.location.href = redirectUrl;
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(error => console.error("Error:", error));
    });
});
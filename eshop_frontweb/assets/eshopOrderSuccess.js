document.addEventListener("DOMContentLoaded", function() {
    fetch("/cart/clear_after_success", {
        method: "POST",
        headers: { "Content-Type": "application/json" }
    })
    .then(response => response.json())
    .catch(error => console.error("Request error:", error));
});
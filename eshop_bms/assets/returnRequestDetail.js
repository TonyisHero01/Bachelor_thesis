function processReturnRequest(requestId, status) {
    if (!confirm(`Are you sure you want to ${status.replace(/ed$/, '')} this return request?`)) {
        return;
    }

    fetch(`/warehouse/return-request/${requestId}/process`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Return request has been ${status.replace(/ed$/, '')}ed.`);
            window.location.reload();
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
}
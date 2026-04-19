/**
 * Processes a return request by updating its status after user confirmation.
 *
 * @param {string|number} requestId - Return request identifier.
 * @param {string} status - Target status (e.g. "approved", "rejected").
 */
function processReturnRequest(requestId, status) {
    if (
        !confirm(
            `Are you sure you want to ${status.replace(/ed$/, '')} this return request?`,
        )
    ) {
        return;
    }

    fetch(`/warehouse/return-request/${requestId}/process`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                alert(
                    `Return request has been ${status.replace(/ed$/, '')}ed.`,
                );
                window.location.reload();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch((error) => console.error('Error:', error));
}
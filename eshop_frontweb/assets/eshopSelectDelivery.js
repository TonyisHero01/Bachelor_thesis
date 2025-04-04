document.querySelectorAll('input[name="deliveryMethod"]').forEach((radio) => {
    radio.addEventListener("change", function() {
        const addressFields = document.getElementById("addressFields");
        const inputs = addressFields.querySelectorAll("input");

        if (this.value === "delivery") {
            addressFields.style.display = "block";
            inputs.forEach(input => input.setAttribute("required", "true"));
        } else {
            addressFields.style.display = "none";
            inputs.forEach(input => input.removeAttribute("required"));
        }
    });
});

document.getElementById("deliveryForm").addEventListener("submit", function(event) {
    event.preventDefault();

    const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;
    const fullName = document.getElementById("fullName").value.trim();
    const phoneNumber = document.getElementById("phoneNumber").value.trim();
    const orderNote = document.getElementById("orderNote").value.trim();

    if (!fullName || !phoneNumber) {
        alert("Please fill in all required contact information.");
        return;
    }

    let addressString = `Full Name: ${fullName}; Phone: ${phoneNumber};`;

    if (deliveryMethod === "delivery") {
        const country = document.getElementById("country").value.trim();
        const city = document.getElementById("city").value.trim();
        const street = document.getElementById("street").value.trim();
        const houseNumber = document.getElementById("houseNumber").value.trim();
        const postalCode = document.getElementById("postalCode").value.trim();

        if (!country || !city || !street || !houseNumber || !postalCode) {
            alert("Please complete all delivery address fields.");
            return;
        }

        addressString += `; Country: ${country}; City: ${city}; Street: ${street}; House Number: ${houseNumber}; Postal Code: ${postalCode}`;
    } else {
        addressString += "; Pickup at Store";
    }

    fetch("/order/create", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ 
            deliveryMethod, 
            address: addressString,
            notes: orderNote  
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = `/order/success/${data.orderId}`;
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
});
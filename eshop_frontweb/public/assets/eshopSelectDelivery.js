/**
 * Initializes delivery form behavior after the DOM is fully loaded.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('deliveryForm');
    if (!form) return;

    const locale = form.getAttribute('data-locale')
        || document.documentElement.lang
        || 'en';

    const createApi = form.getAttribute('data-create-api')
        || '/order/create';

    const payUrl = form.getAttribute('data-pay-url')
        || `/${locale}/checkout/mock-gateway`;

    const addressFields = document.getElementById('addressFields');

    /**
     * Shows/hides address fields based on selected delivery method.
     *
     * @param {string} selectedValue - Selected delivery method value.
     */
    const applyAddressToggle = (selectedValue) => {
        const inputs = addressFields.querySelectorAll('input');

        if (selectedValue === 'delivery') {
            addressFields.style.display = 'block';
            inputs.forEach((input) => input.setAttribute('required', 'true'));
        } else {
            addressFields.style.display = 'none';
            inputs.forEach((input) => input.removeAttribute('required'));
        }
    };

    document
        .querySelectorAll('input[name="deliveryMethod"]')
        .forEach((radio) => {
            radio.addEventListener('change', function () {
                applyAddressToggle(this.value);
            });
        });

    const initiallyChecked = document.querySelector(
        'input[name="deliveryMethod"]:checked',
    );

    applyAddressToggle(initiallyChecked ? initiallyChecked.value : 'pickup');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const deliveryMethod = document.querySelector(
            'input[name="deliveryMethod"]:checked',
        ).value;

        const fullName = document.getElementById('fullName').value.trim();
        const phoneNumber = document.getElementById('phoneNumber').value.trim();
        const orderNote = document.getElementById('orderNote').value.trim();

        if (!fullName || !phoneNumber) {
            alert('Please fill in all required contact information.');
            return;
        }

        let addressString = `Full Name: ${fullName}; Phone: ${phoneNumber};`;

        if (deliveryMethod === 'delivery') {
            const country = document.getElementById('country').value.trim();
            const city = document.getElementById('city').value.trim();
            const street = document.getElementById('street').value.trim();
            const houseNumber = document.getElementById('houseNumber').value.trim();
            const postalCode = document.getElementById('postalCode').value.trim();

            if (!country || !city || !street || !houseNumber || !postalCode) {
                alert('Please complete all delivery address fields.');
                return;
            }

            addressString += `; Country: ${country}; City: ${city}; Street: ${street}; House Number: ${houseNumber}; Postal Code: ${postalCode}`;
        } else {
            addressString += '; Pickup at Store';
        }

        try {
            console.log('[OrderCreate] submit payload', {
                createApi,
                locale,
                deliveryMethod,
                address: addressString,
                notes: orderNote,
            });

            const res = await fetch(createApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    deliveryMethod,
                    address: addressString,
                    notes: orderNote,
                }),
            });

            const data = await res.json();

            if (data.success) {
                const orderId = data.orderId;
                const sep = payUrl.includes('?') ? '&' : '?';
                window.location.href = `${payUrl}${sep}order=${encodeURIComponent(orderId)}`;
            } else {
                alert(`Error: ${data.message || 'Failed to create order.'}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error when creating order.');
        }
    });
});
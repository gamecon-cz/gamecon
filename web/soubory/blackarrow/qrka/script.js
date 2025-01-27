
function copyQrCode(qrCodeId) {
    const qrCodeImg = document.getElementById(qrCodeId);
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    // Create an image object to load the QR code
    const img = new Image();
    img.src = qrCodeImg.src;
    img.onload = function () {
        // Set canvas dimensions and draw the image
        canvas.width = img.width;
        canvas.height = img.height;
        context.drawImage(img, 0, 0);

        // Convert the canvas content to a Blob and copy it to clipboard
        canvas.toBlob((blob) => {
            const item = new ClipboardItem({ "image/png": blob });
            navigator.clipboard.write([item])
                .then(() => {
                    showNotification(qrCodeId + " skopírován");
                })
                .catch((err) => {
                    showNotification("Chyba při kopírování QR kódu: " + err, true);
                });
        });
    };
}

function showNotification(message, isError = false) {
    const notification = document.createElement('div');
    notification.textContent = message;

    // Apply styling to the notification
    Object.assign(notification.style, {
        position: 'fixed',
        bottom: '20px',
        right: '20px',
        padding: '10px 20px',
        borderRadius: '5px',
        backgroundColor: isError ? '#ff4d4f' : '#4caf50',
        color: '#fff',
        boxShadow: '0px 4px 6px rgba(0, 0, 0, 0.1)',
        zIndex: '1000',
        fontFamily: 'Arial, sans-serif',
        fontSize: '14px',
    });

    // Add the notification to the DOM
    document.body.appendChild(notification);

    // Remove the notification after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

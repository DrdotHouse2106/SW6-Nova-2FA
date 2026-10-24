/*
 * Nova 2FA – QR-Code für die Einrichtung im Browser erzeugen (otpauth-Adresse bleibt im Gerät,
 * kein externer QR-Dienst).
 */
import qrcode from 'qrcode-generator';

document.querySelectorAll('[data-nova-2fa-qr]').forEach((el) => {
    const qr = qrcode(0, 'M');
    qr.addData(el.getAttribute('data-nova-2fa-qr'));
    qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
});

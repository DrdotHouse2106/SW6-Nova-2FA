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

/*
 * Wiederherstellungscodes drucken bzw. als PDF speichern (Druckdialog → „Als PDF speichern“).
 */
const escapeHtml = (value) => String(value).replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[char]);

document.querySelectorAll('[data-nova-2fa-print]').forEach((button) => {
    button.addEventListener('click', () => {
        const codes = JSON.parse(button.getAttribute('data-nova-2fa-print') || '[]');
        const title = button.getAttribute('data-nova-2fa-print-title') || '';
        const items = codes.map((code) => `<li>${escapeHtml(code.slice(0, 5) + '-' + code.slice(5))}</li>`).join('');
        const win = window.open('', '_blank', 'width=720,height=800');
        if (!win) {
            window.print();
            return;
        }
        win.document.open();
        win.document.write(`<!doctype html><html lang="de"><head><meta charset="utf-8"><title>${escapeHtml(title)}</title>
<style>body{font-family:system-ui,sans-serif;margin:32px;max-width:640px;color:#111}h1{font-size:20px;margin:0 0 4px}
.meta{color:#555;font-size:13px}ol{columns:2;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:18px;letter-spacing:.06em;line-height:2}
.note{border:1px solid #bbb;border-radius:6px;padding:10px 14px;font-size:13px}</style></head><body>
<h1>${escapeHtml(title)}</h1><p class="meta">${escapeHtml(button.getAttribute('data-nova-2fa-print-account') || '')} · ${escapeHtml(new Date().toLocaleString())}</p>
<ol>${items}</ol><p class="note">${escapeHtml(button.getAttribute('data-nova-2fa-print-note') || '')}</p>
<script>window.onload=function(){window.print();};<\/script></body></html>`);
        win.document.close();
    });
});

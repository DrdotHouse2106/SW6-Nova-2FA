/**
 * Wiederherstellungscodes drucken bzw. als PDF speichern (Druckdialog → „Als PDF speichern“).
 * Eigenes, schlichtes Druckfenster – die Codes gehen an keinen Server.
 */
export default function printRecoveryCodes(codes, { title, account, note, date }) {
    const escape = (value) => String(value).replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[char]);
    const items = codes.map((code) => `<li>${escape(code.length === 10 ? `${code.slice(0, 5)}-${code.slice(5)}` : code)}</li>`).join('');
    const html = `<!doctype html><html lang="de"><head><meta charset="utf-8"><title>${escape(title)}</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#111;margin:32px;max-width:640px}
h1{font-size:20px;margin:0 0 4px}.meta{color:#555;font-size:13px;margin:0 0 20px}
ol{columns:2;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:18px;letter-spacing:.06em;padding-left:24px;line-height:2}
.note{border:1px solid #bbb;border-radius:6px;padding:10px 14px;font-size:13px;line-height:1.5}
@media print{body{margin:16mm}}
</style></head><body>
<h1>${escape(title)}</h1><p class="meta">${escape(account)} · ${escape(date)}</p>
<ol>${items}</ol><p class="note">${escape(note)}</p>
<script>window.onload=function(){window.print();};<\/script></body></html>`;

    const win = window.open('', '_blank', 'noopener=no,width=720,height=800');
    if (!win) {
        return false;
    }
    win.document.open();
    win.document.write(html);
    win.document.close();

    return true;
}

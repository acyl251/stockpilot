/**
 * Thermal receipt printing utilities.
 * Supports 58mm and 80mm paper widths (stored in localStorage).
 */

export type PrinterWidth = '58mm' | '80mm'

export function getPrinterWidth(): PrinterWidth {
  return (localStorage.getItem('printer_width') as PrinterWidth) ?? '80mm'
}

export function setPrinterWidth(w: PrinterWidth) {
  localStorage.setItem('printer_width', w)
}

// ─── Internal helper ──────────────────────────────────────────────────────────

function openPrint(html: string) {
  const win = window.open('', '_blank', 'width=350,height=700')
  if (!win) {
    alert('Popup bloqué — autorisez les popups pour imprimer.')
    return
  }
  win.document.write(html)
  win.document.close()
  win.focus()
  setTimeout(() => { win.print(); win.close() }, 250)
}

function fmt(v: any): string {
  return Number(v ?? 0).toFixed(3)
}

// ─── Reçu client ─────────────────────────────────────────────────────────────

export interface ReceiptOpts {
  orgNom?:       string
  orgAdresse?:   string
  orgTelephone?: string
  cashierName?:  string
  tableLabel?:   string   // ex: "Table 3" ou "À emporter"
}

export function printReceipt(sale: any, opts: ReceiptOpts = {}) {
  const w = getPrinterWidth()

  const dateStr = new Date(sale.date_vente).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })

  const lignes = (sale.items ?? []).map((it: any) => `
<div class="row">
  <span class="item-name">${Number(it.quantite)}× ${it.designation}</span>
  <span>${fmt(it.total_ligne_ttc)}</span>
</div>
<div class="sub">${Number(it.quantite)} × ${fmt(it.prix_unitaire_ttc)} DT</div>`).join('\n')

  const remise = Number(sale.remise_montant ?? 0) > 0
    ? `<div class="row"><span>Remise</span><span>- ${fmt(sale.remise_montant)} DT</span></div>`
    : ''

  const modePay =
    sale.mode_paiement === 'carte'  ? 'Carte bancaire' :
    sale.mode_paiement === 'credit' ? 'Crédit client'  : 'Espèces'

  const payBlock = sale.mode_paiement === 'especes' && sale.montant_paye
    ? `<div class="row"><span>Espèces reçus</span><span>${fmt(sale.montant_paye)} DT</span></div>
       <div class="row bold"><span>Rendu monnaie</span><span>${fmt(sale.monnaie_rendue)} DT</span></div>`
    : sale.mode_paiement === 'carte'
    ? `<div class="row"><span>Paiement carte</span><span>✓</span></div>${sale.reference_carte ? `<div class="sub">Réf. TPE : ${sale.reference_carte}</div>` : ''}`
    : sale.mode_paiement === 'credit'
    ? `<div class="row"><span>Client</span><span>${sale.client?.nom ?? ''}</span></div>
       ${Number(sale.montant_regle ?? 0) > 0 ? `<div class="row"><span>Acompte versé</span><span>${fmt(sale.montant_regle)} DT</span></div>` : ''}
       <div class="row bold red"><span>Reste à payer</span><span>${fmt(sale.reste_a_payer)} DT</span></div>`
    : ''

  const metaRows = [
    opts.cashierName  ? `<div class="row"><span>Caissier</span><span>${opts.cashierName}</span></div>` : '',
    opts.tableLabel   ? `<div class="row"><span>Table</span><span>${opts.tableLabel}</span></div>` : '',
  ].filter(Boolean).join('\n')

  const html = `<!DOCTYPE html><html><head>
<meta charset="UTF-8">
<title>${sale.numero ?? 'Reçu'}</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  @page{size:${w} auto;margin:2mm}
  body{font-family:'Courier New',Courier,monospace;font-size:12px;width:${w};padding:3mm;color:#000}
  .center{text-align:center}
  .bold{font-weight:bold}
  .large{font-size:16px;font-weight:bold;text-align:center;margin:4px 0}
  .hr{border:none;border-top:1px dashed #000;margin:5px 0}
  .row{display:flex;justify-content:space-between;align-items:flex-start;margin:2px 0;gap:4px}
  .item-name{flex:1;word-break:break-word}
  .sub{font-size:10px;color:#555;margin-left:8px;margin-bottom:3px}
  .red{color:#c00}
  .footer{text-align:center;font-size:10px;margin-top:8px;color:#666}
  .shop-name{font-size:15px;font-weight:bold;text-align:center}
</style>
</head><body>
<div class="shop-name">${opts.orgNom ?? ''}</div>
${opts.orgAdresse   ? `<div class="center">${opts.orgAdresse}</div>` : ''}
${opts.orgTelephone ? `<div class="center">Tél : ${opts.orgTelephone}</div>` : ''}
<div class="hr"></div>
<div class="row"><span>Ticket N°</span><span>${sale.numero ?? `#${sale.id}`}</span></div>
<div class="row"><span>Date</span><span>${dateStr}</span></div>
${metaRows}
<div class="hr"></div>
${lignes}
<div class="hr"></div>
${remise}
<div class="row"><span>Total HT</span><span>${fmt(sale.total_ht)} DT</span></div>
<div class="row"><span>TVA</span><span>${fmt(sale.total_tva)} DT</span></div>
<div class="hr"></div>
<div class="large">TOTAL : ${fmt(sale.total_ttc)} DT</div>
<div class="hr"></div>
<div class="row"><span>Mode de paiement</span><span>${modePay}</span></div>
${payBlock}
<div class="hr"></div>
<div class="footer">Merci de votre visite !</div>
<div class="footer">Propulsé par StockPilot</div>
</body></html>`

  openPrint(html)
}

// ─── Ticket cuisine ───────────────────────────────────────────────────────────

export function printKitchenTicket(order: any) {
  const w = getPrinterWidth()
  const heure = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  const tableLabel = order.table ? `TABLE ${order.table.numero}` : 'À EMPORTER'

  const lignes = (order.items ?? []).map((i: any) => `
<div class="item"><strong>${i.quantite}×</strong>&nbsp;${String(i.designation).toUpperCase()}</div>
${i.note_ligne ? `<div class="note">→ ${i.note_ligne}</div>` : ''}`).join('\n')

  const html = `<!DOCTYPE html><html><head>
<meta charset="UTF-8">
<title>Bon cuisine</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  @page{size:${w} auto;margin:2mm}
  body{font-family:'Courier New',Courier,monospace;font-size:14px;width:${w};padding:3mm}
  .title{font-size:20px;font-weight:bold;text-align:center;margin-bottom:4px}
  .table{font-size:18px;font-weight:bold;text-align:center;border:2px solid #000;padding:4px;margin:4px 0}
  .heure{font-size:13px;text-align:center;color:#444;margin-bottom:6px}
  .hr{border:none;border-top:2px solid #000;margin:6px 0}
  .item{font-size:16px;font-weight:bold;margin:6px 0}
  .note{font-size:13px;margin-left:20px;font-style:italic;margin-bottom:4px}
  .global-note{font-size:12px;font-style:italic;margin-top:4px}
</style>
</head><body>
<div class="title">BON DE CUISINE</div>
<div class="table">${tableLabel}</div>
<div class="heure">${heure}</div>
<div class="hr"></div>
${lignes}
${order.note ? `<div class="hr"></div><div class="global-note">Note générale : ${order.note}</div>` : ''}
</body></html>`

  openPrint(html)
}

function l(){return localStorage.getItem("printer_width")??"80mm"}function b(e){localStorage.setItem("printer_width",e)}function c(e){const t=window.open("","_blank","width=350,height=700");if(!t){alert("Popup bloqué — autorisez les popups pour imprimer.");return}t.document.write(e),t.document.close(),t.focus(),setTimeout(()=>{t.print(),t.close()},250)}function n(e){return Number(e??0).toFixed(3)}function h(e,t={}){var p;const s=l(),o=new Date(e.date_vente).toLocaleString("fr-FR",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"}),r=(e.items??[]).map(a=>`
<div class="row">
  <span class="item-name">${Number(a.quantite)}× ${a.designation}</span>
  <span>${n(a.total_ligne_ttc)}</span>
</div>
<div class="sub">${Number(a.quantite)} × ${n(a.prix_unitaire_ttc)} DT</div>`).join(`
`),d=Number(e.remise_montant??0)>0?`<div class="row"><span>Remise</span><span>- ${n(e.remise_montant)} DT</span></div>`:"",i=e.mode_paiement==="carte"?"Carte bancaire":e.mode_paiement==="credit"?"Crédit client":"Espèces",m=e.mode_paiement==="especes"&&e.montant_paye?`<div class="row"><span>Espèces reçus</span><span>${n(e.montant_paye)} DT</span></div>
       <div class="row bold"><span>Rendu monnaie</span><span>${n(e.monnaie_rendue)} DT</span></div>`:e.mode_paiement==="carte"?`<div class="row"><span>Paiement carte</span><span>✓</span></div>${e.reference_carte?`<div class="sub">Réf. TPE : ${e.reference_carte}</div>`:""}`:e.mode_paiement==="credit"?`<div class="row"><span>Client</span><span>${((p=e.client)==null?void 0:p.nom)??""}</span></div>
       ${Number(e.montant_regle??0)>0?`<div class="row"><span>Acompte versé</span><span>${n(e.montant_regle)} DT</span></div>`:""}
       <div class="row bold red"><span>Reste à payer</span><span>${n(e.reste_a_payer)} DT</span></div>`:"",v=[t.cashierName?`<div class="row"><span>Caissier</span><span>${t.cashierName}</span></div>`:"",t.tableLabel?`<div class="row"><span>Table</span><span>${t.tableLabel}</span></div>`:""].filter(Boolean).join(`
`),g=`<!DOCTYPE html><html><head>
<meta charset="UTF-8">
<title>${e.numero??"Reçu"}</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  @page{size:${s} auto;margin:2mm}
  body{font-family:'Courier New',Courier,monospace;font-size:12px;width:${s};padding:3mm;color:#000}
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
<div class="shop-name">${t.orgNom??""}</div>
${t.orgAdresse?`<div class="center">${t.orgAdresse}</div>`:""}
${t.orgTelephone?`<div class="center">Tél : ${t.orgTelephone}</div>`:""}
<div class="hr"></div>
<div class="row"><span>Ticket N°</span><span>${e.numero??`#${e.id}`}</span></div>
<div class="row"><span>Date</span><span>${o}</span></div>
${v}
<div class="hr"></div>
${r}
<div class="hr"></div>
${d}
<div class="row"><span>Total HT</span><span>${n(e.total_ht)} DT</span></div>
<div class="row"><span>TVA</span><span>${n(e.total_tva)} DT</span></div>
<div class="hr"></div>
<div class="large">TOTAL : ${n(e.total_ttc)} DT</div>
<div class="hr"></div>
<div class="row"><span>Mode de paiement</span><span>${i}</span></div>
${m}
<div class="hr"></div>
<div class="footer">Merci de votre visite !</div>
<div class="footer">Propulsé par StockPilot</div>
</body></html>`;c(g)}function u(e){const t=l(),s=new Date().toLocaleTimeString("fr-FR",{hour:"2-digit",minute:"2-digit"}),o=e.table?`TABLE ${e.table.numero}`:"À EMPORTER",r=(e.items??[]).map(i=>`
<div class="item"><strong>${i.quantite}×</strong>&nbsp;${String(i.designation).toUpperCase()}</div>
${i.note_ligne?`<div class="note">→ ${i.note_ligne}</div>`:""}`).join(`
`),d=`<!DOCTYPE html><html><head>
<meta charset="UTF-8">
<title>Bon cuisine</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  @page{size:${t} auto;margin:2mm}
  body{font-family:'Courier New',Courier,monospace;font-size:14px;width:${t};padding:3mm}
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
<div class="table">${o}</div>
<div class="heure">${s}</div>
<div class="hr"></div>
${r}
${e.note?`<div class="hr"></div><div class="global-note">Note générale : ${e.note}</div>`:""}
</body></html>`;c(d)}export{h as a,l as g,u as p,b as s};

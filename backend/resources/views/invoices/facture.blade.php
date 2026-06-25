<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
@php
    $money = fn($v) => number_format((float) $v, 3, ',', ' ') . ' TND';
@endphp
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 12px; color: #1e293b; margin: 0; }
    .wrap { padding: 32px 36px; }
    .header { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .header td { vertical-align: top; }
    .brand { font-size: 20px; font-weight: bold; color: #0f172a; }
    .muted { color: #64748b; }
    .title-box { text-align: right; }
    .title { font-size: 26px; font-weight: bold; color: #b45309; letter-spacing: 1px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .parties { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .parties td { width: 50%; vertical-align: top; padding: 10px 12px; border: 1px solid #e2e8f0; }
    .label { text-transform: uppercase; font-size: 10px; color: #94a3b8; letter-spacing: .5px; margin-bottom: 4px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th { background: #0f172a; color: #fff; padding: 7px 8px; text-align: left; font-size: 11px; }
    table.items td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
    .r { text-align: right; }
    .c { text-align: center; }
    .totals { width: 45%; margin-left: 55%; margin-top: 12px; border-collapse: collapse; }
    .totals td { padding: 5px 8px; }
    .totals .grand { background: #0f172a; color: #fff; font-weight: bold; font-size: 14px; }
    .lettres { margin-top: 16px; padding: 8px 10px; background: #fef3c7; border-left: 3px solid #b45309; font-style: italic; }
    .foot { margin-top: 28px; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    .credit { margin-top: 12px; padding: 8px 10px; border: 1px solid #fca5a5; background: #fef2f2; color: #b91c1c; }
</style>
</head>
<body>
<div class="wrap">

    <table class="header">
        <tr>
            <td>
                <div class="brand">{{ $org->nom }}</div>
                @if($org->adresse)<div class="muted">{{ $org->adresse }}</div>@endif
                @if($org->telephone)<div class="muted">Tél : {{ $org->telephone }}</div>@endif
                @if($org->email_contact)<div class="muted">{{ $org->email_contact }}</div>@endif
                @if($org->matricule_fiscal)<div class="muted">M.F : {{ $org->matricule_fiscal }}</div>@endif
            </td>
            <td class="title-box">
                <div class="title">FACTURE</div>
                <div style="margin-top:6px;"><strong>{{ $sale->numero_facture }}</strong></div>
                <div class="muted">Date : {{ \Carbon\Carbon::parse($sale->date_vente)->format('d/m/Y') }}</div>
                <div class="muted">Réf. ticket : {{ $sale->numero }}</div>
                @if($sale->statut === 'annulee')
                    <div class="badge" style="background:#fee2e2;color:#b91c1c;margin-top:4px;">ANNULÉE</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Émetteur</div>
                <strong>{{ $org->nom }}</strong><br>
                @if($org->matricule_fiscal)M.F : {{ $org->matricule_fiscal }}@endif
            </td>
            <td>
                <div class="label">Client</div>
                @if($sale->client)
                    <strong>{{ $sale->client->nom }}</strong><br>
                    @if($sale->client->telephone)Tél : {{ $sale->client->telephone }}@endif
                @else
                    <strong>Client comptoir</strong>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="c">Qté</th>
                <th class="r">P.U HT</th>
                <th class="c">TVA</th>
                <th class="r">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $it)
                <tr>
                    <td>{{ $it->designation }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format((float)$it->quantite, 3, ',', ' '), '0'), ',') }}</td>
                    <td class="r">{{ $money($it->prix_unitaire_ht) }}</td>
                    <td class="c">{{ (float) $it->taux_tva }}%</td>
                    <td class="r">{{ $money((float)$it->prix_unitaire_ht * (float)$it->quantite) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total HT</td>
            <td class="r">{{ $money($sale->total_ht) }}</td>
        </tr>
        @foreach($tvaParTaux as $t)
            <tr>
                <td class="muted">TVA {{ $t['taux'] }}%</td>
                <td class="r muted">{{ $money($t['montant']) }}</td>
            </tr>
        @endforeach
        @if((float) $sale->remise_montant > 0)
            <tr>
                <td style="color:#b91c1c;">Remise</td>
                <td class="r" style="color:#b91c1c;">− {{ $money($sale->remise_montant) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>TOTAL TTC</td>
            <td class="r">{{ $money($sale->total_ttc) }}</td>
        </tr>
    </table>

    <div class="lettres">
        Arrêtée la présente facture à la somme de : <strong>{{ $enLettres }}</strong>.
    </div>

    @if($sale->mode_paiement === 'credit' && (float) $sale->reste_a_payer > 0)
        <div class="credit">
            Paiement à crédit — Déjà réglé : {{ $money($sale->montant_regle) }} ·
            <strong>Reste à payer : {{ $money($sale->reste_a_payer) }}</strong>
        </div>
    @else
        <div style="margin-top:12px;" class="muted">
            Mode de règlement :
            {{ $sale->mode_paiement === 'credit' ? 'Crédit' : ($sale->mode_paiement === 'carte' ? 'Carte bancaire' : 'Espèces') }}
        </div>
    @endif

    <div class="foot">
        {{ $org->nom }}@if($org->matricule_fiscal) — M.F : {{ $org->matricule_fiscal }}@endif
        · Facture générée par StockPilot
    </div>

</div>
</body>
</html>

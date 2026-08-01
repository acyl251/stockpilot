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
    .signatures { width: 100%; border-collapse: collapse; margin-top: 48px; }
    .signatures td { width: 50%; vertical-align: top; padding-top: 24px; border-top: 1px solid #94a3b8; text-align: center; color: #64748b; }
    .accord { margin-top: 8px; text-align: center; font-style: italic; color: #94a3b8; font-size: 11px; }
    .foot { margin-top: 28px; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
</style>
</head>
<body>
<div class="wrap">

    <table class="header">
        <tr>
            <td>
                <div class="brand">{{ $org->nom ?? 'StockPilot' }}</div>
                @if($org->adresse ?? null)<div class="muted">{{ $org->adresse }}</div>@endif
                @if($org->telephone ?? null)<div class="muted">Tél : {{ $org->telephone }}</div>@endif
            </td>
            <td class="title-box">
                <div class="title">BON DE LIVRAISON</div>
                <div style="margin-top:6px;"><strong>N° : {{ $commande->numero_bon }}</strong></div>
                <div class="muted">Date : {{ $commande->created_at->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Livré à</div>
                <strong>{{ $commande->client->nom ?? $commande->nom_client }}</strong><br>
                @if($commande->client->telephone ?? $commande->telephone_client)
                    Tél : {{ $commande->client->telephone ?? $commande->telephone_client }}<br>
                @endif
                @if($commande->adresse_livraison)
                    Adresse : {{ $commande->adresse_livraison }}
                @endif
            </td>
            <td>
                <div class="label">Émetteur</div>
                <strong>{{ $org->nom ?? 'StockPilot' }}</strong>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="c">N°</th>
                <th>Produit</th>
                <th class="c">Quantité</th>
                <th class="r">Prix unitaire</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($commande->items as $i => $it)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $it->product->nom ?? '—' }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format((float)$it->quantite, 3, ',', ' '), '0'), ',') }} {{ $it->product->unite_mesure ?? '' }}</td>
                    <td class="r">{{ $money($it->prix_unitaire) }}</td>
                    <td class="r">{{ $money($it->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total HT</td>
            <td class="r">{{ $money($commande->total_ttc) }}</td>
        </tr>
        <tr>
            <td class="muted">TVA (0%)</td>
            <td class="r muted">{{ $money(0) }}</td>
        </tr>
        <tr class="grand">
            <td>TOTAL TTC</td>
            <td class="r">{{ $money($commande->total_ttc) }}</td>
        </tr>
    </table>

    @if($commande->note)
        <div style="margin-top:16px;" class="muted">Note : {{ $commande->note }}</div>
    @endif

    <table class="signatures">
        <tr>
            <td>Signature livreur</td>
            <td>Signature client</td>
        </tr>
    </table>
    <div class="accord">« Bon pour accord »</div>

    <div class="foot">
        Propulsé par StockPilot — 27 650 255
    </div>

</div>
</body>
</html>

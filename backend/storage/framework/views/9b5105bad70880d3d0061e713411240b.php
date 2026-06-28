<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<?php
    $money = fn($v) => number_format((float) $v, 3, ',', ' ') . ' TND';
?>
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
                <div class="brand"><?php echo e($org->nom); ?></div>
                <?php if($org->adresse): ?><div class="muted"><?php echo e($org->adresse); ?></div><?php endif; ?>
                <?php if($org->telephone): ?><div class="muted">Tél : <?php echo e($org->telephone); ?></div><?php endif; ?>
                <?php if($org->email_contact): ?><div class="muted"><?php echo e($org->email_contact); ?></div><?php endif; ?>
                <?php if($org->matricule_fiscal): ?><div class="muted">M.F : <?php echo e($org->matricule_fiscal); ?></div><?php endif; ?>
            </td>
            <td class="title-box">
                <div class="title">FACTURE</div>
                <div style="margin-top:6px;"><strong><?php echo e($sale->numero_facture); ?></strong></div>
                <div class="muted">Date : <?php echo e(\Carbon\Carbon::parse($sale->date_vente)->format('d/m/Y')); ?></div>
                <div class="muted">Réf. ticket : <?php echo e($sale->numero); ?></div>
                <?php if($sale->statut === 'annulee'): ?>
                    <div class="badge" style="background:#fee2e2;color:#b91c1c;margin-top:4px;">ANNULÉE</div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Émetteur</div>
                <strong><?php echo e($org->nom); ?></strong><br>
                <?php if($org->matricule_fiscal): ?>M.F : <?php echo e($org->matricule_fiscal); ?><?php endif; ?>
            </td>
            <td>
                <div class="label">Client</div>
                <?php if($sale->client): ?>
                    <strong><?php echo e($sale->client->nom); ?></strong><br>
                    <?php if($sale->client->telephone): ?>Tél : <?php echo e($sale->client->telephone); ?><?php endif; ?>
                <?php else: ?>
                    <strong>Client comptoir</strong>
                <?php endif; ?>
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
            <?php $__currentLoopData = $sale->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($it->designation); ?></td>
                    <td class="c"><?php echo e(rtrim(rtrim(number_format((float)$it->quantite, 3, ',', ' '), '0'), ',')); ?></td>
                    <td class="r"><?php echo e($money($it->prix_unitaire_ht)); ?></td>
                    <td class="c"><?php echo e((float) $it->taux_tva); ?>%</td>
                    <td class="r"><?php echo e($money((float)$it->prix_unitaire_ht * (float)$it->quantite)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total HT</td>
            <td class="r"><?php echo e($money($sale->total_ht)); ?></td>
        </tr>
        <?php $__currentLoopData = $tvaParTaux; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td class="muted">TVA <?php echo e($t['taux']); ?>%</td>
                <td class="r muted"><?php echo e($money($t['montant'])); ?></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if((float) $sale->remise_montant > 0): ?>
            <tr>
                <td style="color:#b91c1c;">Remise</td>
                <td class="r" style="color:#b91c1c;">− <?php echo e($money($sale->remise_montant)); ?></td>
            </tr>
        <?php endif; ?>
        <tr class="grand">
            <td>TOTAL TTC</td>
            <td class="r"><?php echo e($money($sale->total_ttc)); ?></td>
        </tr>
    </table>

    <div class="lettres">
        Arrêtée la présente facture à la somme de : <strong><?php echo e($enLettres); ?></strong>.
    </div>

    <?php if($sale->mode_paiement === 'credit' && (float) $sale->reste_a_payer > 0): ?>
        <div class="credit">
            Paiement à crédit — Déjà réglé : <?php echo e($money($sale->montant_regle)); ?> ·
            <strong>Reste à payer : <?php echo e($money($sale->reste_a_payer)); ?></strong>
        </div>
    <?php else: ?>
        <div style="margin-top:12px;" class="muted">
            Mode de règlement :
            <?php echo e($sale->mode_paiement === 'credit' ? 'Crédit' : ($sale->mode_paiement === 'carte' ? 'Carte bancaire' : 'Espèces')); ?>

        </div>
    <?php endif; ?>

    <div class="foot">
        <?php echo e($org->nom); ?><?php if($org->matricule_fiscal): ?> — M.F : <?php echo e($org->matricule_fiscal); ?><?php endif; ?>
        · Facture générée par StockPilot
    </div>

</div>
</body>
</html>
<?php /**PATH C:\Users\roias\OneDrive\Desktop\gestion de stockpilot\backend\resources\views/invoices/facture.blade.php ENDPATH**/ ?>
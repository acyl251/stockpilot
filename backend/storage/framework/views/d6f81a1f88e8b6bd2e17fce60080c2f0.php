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
    .title { font-size: 24px; font-weight: bold; color: #b45309; letter-spacing: 1px; }
    .parties { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .parties td { vertical-align: top; padding: 10px 12px; border: 1px solid #e2e8f0; }
    .label { text-transform: uppercase; font-size: 10px; color: #94a3b8; letter-spacing: .5px; margin-bottom: 4px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th { background: #0f172a; color: #fff; padding: 7px 8px; text-align: left; font-size: 11px; }
    table.items td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
    .r { text-align: right; }
    .c { text-align: center; }
    .totals { width: 45%; margin-left: 55%; margin-top: 12px; border-collapse: collapse; }
    .totals td { padding: 5px 8px; }
    .totals .grand { background: #0f172a; color: #fff; font-weight: bold; font-size: 14px; }
    .arrete { margin-top: 18px; text-align: center; font-style: italic; color: #64748b; }
    .foot { margin-top: 28px; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
</style>
</head>
<body>
<div class="wrap">

    <table class="header">
        <tr>
            <td>
                <div class="brand"><?php echo e($org->nom ?? 'StockPilot'); ?></div>
                <?php if($org->adresse ?? null): ?><div class="muted"><?php echo e($org->adresse); ?></div><?php endif; ?>
                <?php if($org->telephone ?? null): ?><div class="muted">Tél : <?php echo e($org->telephone); ?></div><?php endif; ?>
            </td>
            <td class="title-box">
                <div class="title">RELEVÉ DE COMPTE</div>
                <div class="muted">Du <?php echo e($debut->format('d/m/Y')); ?> au <?php echo e($fin->format('d/m/Y')); ?></div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Client</div>
                <strong><?php echo e($client->nom); ?></strong><br>
                <?php if($client->telephone): ?>Tél : <?php echo e($client->telephone); ?><?php endif; ?>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Date</th>
                <th>N° facture</th>
                <th class="r">Montant</th>
                <th class="r">Réglé</th>
                <th class="r">Reste dû</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $sales; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e(\Carbon\Carbon::parse($s->date_vente)->format('d/m/Y')); ?></td>
                    <td><?php echo e($s->numero_facture ?? $s->numero); ?></td>
                    <td class="r"><?php echo e($money($s->total_ttc)); ?></td>
                    <td class="r"><?php echo e($money($s->montant_regle)); ?></td>
                    <td class="r"><?php echo e($money((float)$s->total_ttc - (float)$s->montant_regle)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="c muted">Aucune vente à crédit sur cette période.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand">
            <td>TOTAL DÛ</td>
            <td class="r"><?php echo e($money($solde)); ?></td>
        </tr>
    </table>

    <div class="arrete">Arrêté de compte au <?php echo e($fin->format('d/m/Y')); ?>.</div>

    <div class="foot">
        Relevé généré par StockPilot
    </div>

</div>
</body>
</html>
<?php /**PATH C:\Users\roias\OneDrive\Desktop\gestion de stockpilot\backend\resources\views/clients/releve.blade.php ENDPATH**/ ?>
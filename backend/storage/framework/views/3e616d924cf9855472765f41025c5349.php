<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rapport hebdomadaire</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;color:#1e293b;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  
  <tr>
    <td style="background:#1F3A5F;border-radius:12px 12px 0 0;padding:28px 32px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:#C8860A;width:40px;height:40px;border-radius:8px;text-align:center;vertical-align:middle;">
                  <span style="color:#fff;font-size:22px;font-weight:900;">S</span>
                </td>
                <td style="padding-left:12px;vertical-align:middle;">
                  <span style="color:#fff;font-size:20px;font-weight:700;">StockPilot</span>
                </td>
              </tr>
            </table>
          </td>
          <td align="right" style="vertical-align:middle;">
            <span style="color:#93c5fd;font-size:12px;">📊 Rapport hebdomadaire</span>
          </td>
        </tr>
      </table>
      <div style="margin-top:20px;">
        <div style="color:#fff;font-size:22px;font-weight:700;"><?php echo e($org->nom); ?></div>
        <div style="color:#93c5fd;font-size:13px;margin-top:4px;">
          Semaine du <?php echo e($debut->locale('fr')->isoFormat('D MMMM')); ?> au <?php echo e($fin->locale('fr')->isoFormat('D MMMM YYYY')); ?>

        </div>
        <div style="color:#cbd5e1;font-size:13px;margin-top:2px;">
          Bonjour <?php echo e($admin->prenom ?? $admin->nom); ?> 👋
        </div>
      </div>
    </td>
  </tr>

  
  <tr>
    <td style="background:#fff;padding:0 32px 8px;">

      <?php if($data['nb_ventes'] === 0): ?>
      
      <div style="margin:28px 0;padding:20px;background:#f8fafc;border-left:4px solid #94a3b8;border-radius:0 8px 8px 0;">
        <div style="font-size:15px;color:#64748b;">Aucune vente enregistrée cette semaine.</div>
      </div>
      <?php else: ?>

      
      <div style="margin-top:28px;margin-bottom:8px;">
        <div style="font-size:11px;font-weight:700;color:#C8860A;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
          📈 Performance de la semaine
        </div>
        <table width="100%" cellpadding="0" cellspacing="8">
          <tr>
            <td width="48%" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-align:center;">
              <div style="font-size:24px;font-weight:800;color:#1F3A5F;"><?php echo e($fmt($data['ca_semaine'])); ?></div>
              <div style="font-size:11px;color:#64748b;margin-top:4px;text-transform:uppercase;letter-spacing:.5px;">CA semaine</div>
              <div style="margin-top:8px;font-size:13px;font-weight:700;color:<?php echo e($varCouleur); ?>;"><?php echo e($varTexte); ?></div>
              <div style="font-size:11px;color:#94a3b8;">vs semaine précédente</div>
            </td>
            <td width="4%"></td>
            <td width="48%">
              <table width="100%" cellpadding="0" cellspacing="6">
                <tr>
                  <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;">
                    <div style="font-size:12px;color:#64748b;">Nombre de ventes</div>
                    <div style="font-size:18px;font-weight:700;color:#1F3A5F;"><?php echo e($data['nb_ventes']); ?></div>
                  </td>
                </tr>
                <tr>
                  <td style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;">
                    <div style="font-size:12px;color:#64748b;">Ticket moyen</div>
                    <div style="font-size:18px;font-weight:700;color:#1F3A5F;"><?php echo e($fmt($data['ticket_moyen'])); ?></div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        <?php if($data['meilleurJour']): ?>
        <div style="margin-top:8px;padding:10px 14px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;">
          <span style="font-size:12px;color:#92400e;">🏆 Meilleur jour : <strong><?php echo e($data['meilleurJour']); ?></strong></span>
        </div>
        <?php endif; ?>
      </div>

      <?php endif; ?> 

      
      <?php if($data['topPlats']->isNotEmpty()): ?>
      <div style="margin-top:24px;">
        <div style="font-size:11px;font-weight:700;color:#C8860A;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
          🍽 Top 5 plats de la semaine
        </div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr style="background:#f8fafc;">
            <th style="padding:8px 12px;text-align:left;font-size:11px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">#</th>
            <th style="padding:8px 12px;text-align:left;font-size:11px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">Plat</th>
            <th style="padding:8px 12px;text-align:right;font-size:11px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">Qté vendues</th>
            <th style="padding:8px 12px;text-align:right;font-size:11px;color:#64748b;font-weight:600;border-bottom:1px solid #e2e8f0;">CA généré</th>
          </tr>
          <?php $__currentLoopData = $data['topPlats']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $plat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr style="background:<?php echo e($loop->even ? '#f8fafc' : '#fff'); ?>;">
            <td style="padding:9px 12px;font-size:13px;color:#94a3b8;border-bottom:1px solid #f1f5f9;"><?php echo e($i + 1); ?></td>
            <td style="padding:9px 12px;font-size:13px;font-weight:600;color:#1e293b;border-bottom:1px solid #f1f5f9;"><?php echo e($plat->nom); ?></td>
            <td style="padding:9px 12px;font-size:13px;color:#1e293b;text-align:right;border-bottom:1px solid #f1f5f9;"><?php echo e(number_format($plat->nb_vendus, 0, ',', ' ')); ?></td>
            <td style="padding:9px 12px;font-size:13px;font-weight:600;color:#1F3A5F;text-align:right;border-bottom:1px solid #f1f5f9;"><?php echo e($fmt($plat->ca_genere)); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
      </div>
      <?php endif; ?>

      
      <?php if($data['stockAlerte']->isNotEmpty()): ?>
      <div style="margin-top:24px;">
        <div style="font-size:11px;font-weight:700;color:#C8860A;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
          📦 Stock à commander
        </div>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr style="background:#fef2f2;">
            <th style="padding:8px 12px;text-align:left;font-size:11px;color:#64748b;font-weight:600;border-bottom:1px solid #fecaca;">Produit</th>
            <th style="padding:8px 12px;text-align:right;font-size:11px;color:#64748b;font-weight:600;border-bottom:1px solid #fecaca;">Stock actuel</th>
            <th style="padding:8px 12px;text-align:right;font-size:11px;color:#64748b;font-weight:600;border-bottom:1px solid #fecaca;">Seuil alerte</th>
          </tr>
          <?php $__currentLoopData = $data['stockAlerte']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $pct = $prod->seuil_alerte > 0 ? ($prod->quantite / $prod->seuil_alerte) * 100 : 0;
            $couleur = $pct <= 25 ? '#dc2626' : '#d97706';
          ?>
          <tr style="background:<?php echo e($loop->even ? '#fff7f7' : '#fff'); ?>;">
            <td style="padding:9px 12px;font-size:13px;font-weight:600;color:#1e293b;border-bottom:1px solid #fef2f2;"><?php echo e($prod->nom); ?></td>
            <td style="padding:9px 12px;font-size:13px;text-align:right;border-bottom:1px solid #fef2f2;">
              <span style="color:<?php echo e($couleur); ?>;font-weight:700;"><?php echo e(number_format($prod->quantite, 3, ',', ' ')); ?></span>
              <span style="color:#94a3b8;font-size:11px;"> <?php echo e($prod->unite_mesure); ?></span>
            </td>
            <td style="padding:9px 12px;font-size:13px;color:#64748b;text-align:right;border-bottom:1px solid #fef2f2;"><?php echo e(number_format($prod->seuil_alerte, 3, ',', ' ')); ?> <?php echo e($prod->unite_mesure); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
      </div>
      <?php endif; ?>

      
      <div style="margin-top:24px;">
        <div style="font-size:11px;font-weight:700;color:#C8860A;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
          👥 Clients
        </div>
        <table width="100%" cellpadding="0" cellspacing="8">
          <tr>
            <td width="32%" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;text-align:center;">
              <div style="font-size:22px;font-weight:800;color:#16a34a;"><?php echo e($data['nbNouveauxClients']); ?></div>
              <div style="font-size:11px;color:#64748b;margin-top:2px;">Nouveaux clients</div>
            </td>
            <td width="2%"></td>
            <td width="32%" style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px;text-align:center;">
              <div style="font-size:22px;font-weight:800;color:#d97706;"><?php echo e($data['nbClientsArdoise']); ?></div>
              <div style="font-size:11px;color:#64748b;margin-top:2px;">Clients en ardoise</div>
            </td>
            <td width="2%"></td>
            <td width="32%" style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;text-align:center;">
              <div style="font-size:16px;font-weight:800;color:#dc2626;"><?php echo e($fmt($data['totalArdoises'])); ?></div>
              <div style="font-size:11px;color:#64748b;margin-top:2px;">Total ardoises</div>
            </td>
          </tr>
        </table>
      </div>

      
      <?php if(!empty($data['conseils'])): ?>
      <div style="margin-top:24px;">
        <div style="font-size:11px;font-weight:700;color:#C8860A;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
          💡 Conseils automatiques
        </div>
        <?php $__currentLoopData = $data['conseils']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $conseil): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $bg = match($conseil['type']) {
            'success' => ['#f0fdf4','#bbf7d0'],
            'danger'  => ['#fef2f2','#fecaca'],
            default   => ['#fffbeb','#fcd34d'],
          };
        ?>
        <div style="margin-bottom:8px;padding:12px 16px;background:<?php echo e($bg[0]); ?>;border-left:4px solid <?php echo e($bg[1]); ?>;border-radius:0 8px 8px 0;font-size:13px;color:#1e293b;line-height:1.5;">
          <?php echo e($conseil['texte']); ?>

        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <?php endif; ?>

      <div style="height:24px;"></div>
    </td>
  </tr>

  
  <tr>
    <td style="background:#1F3A5F;border-radius:0 0 12px 12px;padding:20px 32px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <div style="color:#93c5fd;font-size:12px;">StockPilot — Rapport généré automatiquement</div>
            <div style="color:#64748b;font-size:11px;margin-top:4px;">27 650 255 — noreply@stockpilot.tn</div>
          </td>
          <td align="right" style="vertical-align:middle;">
            <div style="color:#475569;font-size:11px;">Envoyé le <?php echo e(now()->locale('fr')->isoFormat('D MMM YYYY')); ?></div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
<?php /**PATH C:\Users\roias\OneDrive\Desktop\gestion de stockpilot\backend\resources\views/emails/weekly-report.blade.php ENDPATH**/ ?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="460" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.06);">
                    <tr>
                        <td style="background:#0f172a;padding:24px 32px;text-align:center;">
                            <span style="display:inline-block;width:36px;height:36px;line-height:36px;background:#C9A84C;color:#fff;font-weight:bold;border-radius:9px;font-size:18px;">S</span>
                            <div style="color:#fff;font-size:18px;font-weight:bold;margin-top:8px;">StockPilot</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="font-size:18px;margin:0 0 12px;">Confirmez votre adresse email</h1>
                            <p style="font-size:14px;color:#475569;line-height:1.6;margin:0 0 20px;">
                                Bienvenue sur StockPilot<?php echo e($orgName ? ' — ' . $orgName : ''); ?> !
                                Pour activer votre compte et accéder à la plateforme, saisissez le code de confirmation ci-dessous :
                            </p>
                            <div style="text-align:center;margin:24px 0;">
                                <span style="display:inline-block;background:#f8fafc;border:1px dashed #C9A84C;border-radius:12px;padding:16px 28px;font-size:32px;font-weight:bold;letter-spacing:8px;color:#0f172a;">
                                    <?php echo e($code); ?>

                                </span>
                            </div>
                            <p style="font-size:13px;color:#94a3b8;line-height:1.6;margin:0;">
                                Ce code est valable pendant 30 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px;border-top:1px solid #e2e8f0;text-align:center;color:#94a3b8;font-size:11px;">
                            © <?php echo e(date('Y')); ?> StockPilot — ISET Sousse
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
<?php /**PATH C:\Users\roias\OneDrive\Desktop\gestion de stockpilot\backend\resources\views/emails/verification-code.blade.php ENDPATH**/ ?>
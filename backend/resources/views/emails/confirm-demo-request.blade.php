<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirmez votre demande StockPilot</title>
  <style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .header { background: #1F3A5F; padding: 32px 40px; text-align: center; }
    .header-logo { display: inline-flex; align-items: center; gap: 10px; }
    .logo-icon { width: 40px; height: 40px; background: #C8860A; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 20px; line-height: 1; }
    .logo-text { color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
    .body { padding: 40px; }
    .greeting { font-size: 18px; font-weight: 600; color: #1F3A5F; margin: 0 0 16px; }
    .text { font-size: 15px; color: #475569; line-height: 1.7; margin: 0 0 16px; }
    .btn-wrap { text-align: center; margin: 32px 0; }
    .btn { display: inline-block; background: #C8860A; color: #ffffff !important; text-decoration: none; font-size: 16px; font-weight: 700; padding: 14px 36px; border-radius: 8px; letter-spacing: 0.2px; }
    .expiry { background: #fef9ec; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 18px; font-size: 13px; color: #92400e; margin: 24px 0; }
    .divider { border: none; border-top: 1px solid #e2e8f0; margin: 28px 0; }
    .link-fallback { font-size: 12px; color: #94a3b8; word-break: break-all; }
    .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 24px 40px; text-align: center; }
    .footer p { font-size: 13px; color: #94a3b8; margin: 4px 0; }
    .footer strong { color: #1F3A5F; }
  </style>
</head>
<body>
  <div class="wrapper">

    <div class="header">
      <div class="header-logo">
        <div class="logo-icon">S</div>
        <span class="logo-text">StockPilot</span>
      </div>
    </div>

    <div class="body">
      <p class="greeting">Bonjour {{ $demo->prenom }},</p>

      <p class="text">
        Merci pour votre demande d'accès à <strong>StockPilot</strong>. Pour valider votre demande,
        veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous.
      </p>

      <div class="btn-wrap">
        <a href="{{ $verifyUrl }}" class="btn">Confirmer mon email →</a>
      </div>

      <div class="expiry">
        ⏱ Ce lien expire dans <strong>48 heures</strong>. Passé ce délai, vous devrez soumettre une nouvelle demande.
      </div>

      <hr class="divider">

      <p class="text" style="font-size:13px; color:#94a3b8;">
        Si vous n'avez pas soumis cette demande, ignorez cet email. Aucun compte ne sera créé sans votre confirmation.
      </p>

      <p class="link-fallback">
        Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
        <a href="{{ $verifyUrl }}" style="color:#C8860A;">{{ $verifyUrl }}</a>
      </p>
    </div>

    <div class="footer">
      <p><strong>L'équipe StockPilot</strong></p>
      <p>📞 27 650 255 &nbsp;·&nbsp; noreply@stockpilot.tn</p>
    </div>

  </div>
</body>
</html>

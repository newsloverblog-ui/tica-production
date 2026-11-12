<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lock payouts · Tica</title>
  <style>
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#071124;color:#f8fafc;font-family:Inter,ui-sans-serif,system-ui,-apple-system,sans-serif}.card{width:min(100%,520px);padding:36px;border:1px solid rgba(255,255,255,.12);border-radius:24px;background:#0d1930;box-shadow:0 28px 70px rgba(0,0,0,.35)}.eyebrow{color:#fe9b59;font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:12px}h1{font-size:clamp(28px,6vw,42px);line-height:1.05;margin:14px 0}p{color:#aeb9ca;line-height:1.65}.account{margin:24px 0;padding:16px;border-radius:14px;background:#111f39}.account strong{display:block;color:#fff;margin-bottom:4px}button{width:100%;min-height:52px;border:0;border-radius:14px;background:#fe9b59;color:#071124;font:800 16px inherit;cursor:pointer}button:hover{background:#ffad76}.note{font-size:13px;margin-top:16px}
  </style>
</head>
<body>
  <main class="card">
    <div class="eyebrow">Security check</div>
    <h1>Lock this payout account?</h1>
    <p>Use this only if you did not make the change. Locking stops new withdrawals and transfers until Tica support completes a security review.</p>
    <div class="account"><strong>{{ $payoutAccount->bank_name }}</strong>{{ $payoutAccount->maskedNumber() }}</div>
    <form method="post" action="{{ URL::temporarySignedRoute('organizer.payout-account.lock', now()->addMinutes(30), ['account' => $payoutAccount->public_id]) }}">
      @csrf
      <button type="submit">Lock payouts now</button>
    </form>
    <p class="note">This action is immediate and is recorded in the security audit log.</p>
  </main>
</body>
</html>

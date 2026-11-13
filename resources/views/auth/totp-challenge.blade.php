<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enter your code · TICA</title>
<style>:root{color-scheme:light;font-family:Inter,system-ui,sans-serif;color:#102342;background:#f3f6fb}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 15% 0,#e9f0ff 0,transparent 36%),#f3f6fb}.card{width:min(440px,100%);background:#fff;border:1px solid #dbe4f1;border-radius:20px;padding:32px;box-shadow:0 18px 55px #14366d18}.mark{display:inline-flex;padding:8px 12px;border-radius:10px;background:#eaf1ff;color:#235fca;font-weight:800;letter-spacing:.08em}h1{font-size:26px;margin:22px 0 8px}p{color:#617089;line-height:1.55;margin:0}.field{display:grid;gap:8px;margin:24px 0 16px}label{font-weight:700}input{width:100%;height:54px;border:1px solid #cdd8e8;border-radius:12px;padding:0 16px;font:700 24px/1 ui-monospace,monospace;letter-spacing:.3em;text-align:center;color:#102342}input.rc{font-size:18px;letter-spacing:.12em}input:focus{outline:3px solid #2b67d329;border-color:#2b67d3}.btn{width:100%;height:50px;border:0;border-radius:12px;background:#2b67d3;color:#fff;font-weight:750;font-size:16px;cursor:pointer}.alt{display:block;width:100%;margin-top:16px;border:0;background:none;color:#235fca;font-weight:650;font-size:15px;cursor:pointer}.cancel{display:block;text-align:center;margin-top:12px;color:#53647e;font-size:14px}.err{margin-top:16px;padding:11px 13px;border-radius:10px;background:#fff2f1;color:#a33831}</style></head>
<body><main class="card"><span class="mark">TICA</span>
<h1 id="h">Enter your code</h1>
<p id="lead">Open your authenticator app and enter the 6-digit code for TICA.</p>
@if($errors->any())<p class="err" role="alert">{{ $errors->first() }}</p>@endif
<form method="post" action="{{ route('totp.login.verify') }}">@csrf
<div class="field"><label for="code" id="lbl">Code from your app</label><input id="code" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" autofocus required></div>
<button class="btn">Sign in</button></form>
<button type="button" class="alt" id="swap" aria-controls="code">Lost your phone? Use a recovery code</button>
<a class="cancel" href="{{ url('/login') }}">Cancel and go back</a>
</main>
<script>
document.getElementById('swap').addEventListener('click',function(){
  var i=document.getElementById('code'),rc=i.classList.toggle('rc');
  i.value='';i.maxLength=rc?11:7;i.inputMode=rc?'text':'numeric';i.autocomplete=rc?'off':'one-time-code';
  document.getElementById('lbl').textContent=rc?'Recovery code':'Code from your app';
  document.getElementById('h').textContent=rc?'Use a recovery code':'Enter your code';
  document.getElementById('lead').textContent=rc?'Enter one of the recovery codes you saved when you turned on two-factor, like abcde-12345. Each code works once.':'Open your authenticator app and enter the 6-digit code for TICA.';
  this.textContent=rc?'Use the code from your app instead':'Lost your phone? Use a recovery code';i.focus();
});
</script>
</body></html>

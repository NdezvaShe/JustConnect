<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Sign In - JustConnect</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<style>
body{min-height:100vh;display:grid;place-items:center;background:#f7f8f5;font-family:'DM Sans',Arial,sans-serif}
.mfa-card{width:min(440px,calc(100vw - 32px));background:#fff;border:1px solid rgba(26,71,49,.1);border-radius:18px;box-shadow:0 18px 50px rgba(26,71,49,.12);padding:30px}
.mfa-title{font-size:26px;font-weight:900;color:#1a4731;margin-bottom:8px}
.mfa-sub{color:#667169;line-height:1.55;margin-bottom:22px}
.mfa-error{padding:12px 14px;border-radius:12px;background:#fde8e8;color:#b42318;margin-bottom:16px;font-weight:700}
.mfa-actions{display:flex;gap:10px;align-items:center;margin-top:18px}
.mfa-link{color:#2d6a4f;text-decoration:none;font-weight:800}
.mfa-tabs{display:flex;gap:8px;margin-bottom:18px}
.mfa-tab{border:1px solid rgba(26,71,49,.18);background:#fff;color:#1a4731;border-radius:999px;padding:8px 12px;font-weight:800;cursor:pointer}
.mfa-tab.active{background:#1a4731;color:#fff}
.mfa-panel.hidden{display:none}
</style>
</head>
<body>
  <form class="mfa-card" method="POST" action="{{ route('mfa.verify') }}">
    @csrf
    <div class="mfa-title">Verify Sign In</div>
    <div class="mfa-sub">Enter the 6-digit code sent to your email address, or use your backup security question if email is unavailable.</div>

    @if($errors->any())
      <div class="mfa-error">{{ $errors->first() }}</div>
    @endif

    <input type="hidden" name="method" id="mfaMethod" value="code">

    <div class="mfa-tabs">
      <button class="mfa-tab active" type="button" data-mfa-tab="code" onclick="switchMfaMethod('code')">Email code</button>
      @if($securityQuestion)
        <button class="mfa-tab" type="button" data-mfa-tab="security_question" onclick="switchMfaMethod('security_question')">Security question</button>
      @endif
    </div>

    <div class="mfa-panel" id="mfaPanelCode">
      <div class="form-group">
        <label class="form-label">Code</label>
        <input class="form-input" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus>
      </div>
    </div>

    @if($securityQuestion)
      <div class="mfa-panel hidden" id="mfaPanelSecurity">
        <div class="form-group">
          <label class="form-label">{{ $securityQuestion }}</label>
          <input class="form-input" name="security_answer" type="password" autocomplete="off">
        </div>
      </div>
    @endif

    <div class="mfa-actions">
      <button class="btn btn-primary" type="submit">Verify</button>
      <a class="mfa-link" href="{{ route('login') }}">Back to login</a>
    </div>
  </form>
<script>
function switchMfaMethod(method) {
  document.getElementById('mfaMethod').value = method;
  document.querySelectorAll('[data-mfa-tab]').forEach(tab => tab.classList.toggle('active', tab.dataset.mfaTab === method));
  document.getElementById('mfaPanelCode').classList.toggle('hidden', method !== 'code');
  const securityPanel = document.getElementById('mfaPanelSecurity');
  if (securityPanel) securityPanel.classList.toggle('hidden', method !== 'security_question');
}
</script>
</body>
</html>

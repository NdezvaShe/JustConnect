<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - JustConnect</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-side">
    <div class="auth-side-brand">
      <a href="{{ route('landing') }}" class="brand">
        <div class="brand-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#f4c430" stroke-width="2.2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <div><div class="brand-name" style="color:white">JustConnect</div><div class="brand-tag">Smarter Legal Decisions - NLP</div></div>
      </a>
    </div>
    <h2>Create a new <span>secure password</span>.</h2>
    <p>Use the 6-digit code sent to your email address. Codes expire after 10 minutes.</p>
  </div>
  <div class="auth-form-side">
    <div class="auth-box">
      <h3>Reset password</h3>
      <div class="auth-sub">Enter the OTP sent to {{ $email }}</div>
      @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
      @endif
      @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
      @endif
      <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="email" value="{{ old('email', $email) }}">
        <div class="form-group">
          <label class="form-label">Verification Code</label>
          <input type="text" name="otp" class="form-input @error('otp') err @enderror" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" value="{{ old('otp') }}" required autofocus>
          @error('otp')<div class="form-err show">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="password" id="resetPassword" class="form-input @error('password') err @enderror" placeholder="New password" required>
          @error('password')<div class="form-err show">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="password_confirmation" id="resetPasswordConfirm" class="form-input" placeholder="Repeat new password" required>
        </div>
        <div class="form-check-row">
          <label class="form-check"><input type="checkbox" id="showResetPassword"> Show password</label>
          <button type="submit" class="mfa-link" style="border:0;background:transparent;cursor:pointer" formaction="{{ route('password.otp.resend') }}" formmethod="POST" formnovalidate>Resend code</button>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:8px">Update password</button>
      </form>
      <div class="auth-switch" style="margin-top:18px"><a href="{{ route('login') }}">Back to sign in</a></div>
    </div>
  </div>
</div>
<script>
document.getElementById('showResetPassword')?.addEventListener('change', event => {
  const type = event.target.checked ? 'text' : 'password';
  const password = document.getElementById('resetPassword');
  const confirm = document.getElementById('resetPasswordConfirm');
  if (password) password.type = type;
  if (confirm) confirm.type = type;
});
</script>
</body>
</html>

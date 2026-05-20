<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - JustConnect</title>
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
    <h2>Reset access to your <span>legal workspace</span>.</h2>
    <p>Enter the email address linked to your JustConnect account. We will send a 6-digit verification code if the account exists.</p>
  </div>
  <div class="auth-form-side">
    <div class="auth-box">
      <h3>Forgot password</h3>
      <div class="auth-sub">Request a reset code for an existing account</div>
      @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
      @endif
      @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
      @endif
      <form method="POST" action="{{ route('password.otp.send') }}">
        @csrf
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-input @error('email') err @enderror" placeholder="you@example.com" value="{{ old('email') }}" required autofocus>
          @error('email')<div class="form-err show">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:8px">Send reset code</button>
      </form>
      <div class="auth-switch" style="margin-top:18px"><a href="{{ route('login') }}">Back to sign in</a></div>
    </div>
  </div>
</div>
</body>
</html>

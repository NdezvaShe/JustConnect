{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — JustConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-side">
    <div class="auth-side-brand">
      <a href="{{ route('landing') }}" class="brand">
        <div class="brand-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#f4c430" stroke-width="2.2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <div><div class="brand-name" style="color:white">JustConnect</div><div class="brand-tag">Smarter Legal Decisions · NLP</div></div>
      </a>
    </div>
    <h2>Zimbabwe's leading <span>NLP legal intelligence</span> platform.</h2>
    <p>Upload any legal document and generate structured summaries powered by Natural Language Processing and a fine-tuned BART model trained on ZASCA and Zimbabwean data.</p>
    <div class="auth-pills">
      <div class="auth-pill"><div class="auth-pill-dot"></div>Named Entity Recognition for ZW courts</div>
      <div class="auth-pill"><div class="auth-pill-dot"></div>Document classification — 10+ legal types</div>
      <div class="auth-pill"><div class="auth-pill-dot"></div>Fine-tuned BART on ZASCA and Zimbabwean data</div>
    </div>
  </div>
  <div class="auth-form-side">
    <div class="auth-box">
      <h3>Welcome back</h3>
      <div class="auth-sub">Sign in to your JustConnect account</div>
      @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
      @endif
      <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-input @error('email') err @enderror" placeholder="you@example.com" value="{{ old('email') }}" required autofocus>
          @error('email')<div class="form-err show">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input @error('password') err @enderror" placeholder="Your password" required>
          @error('password')<div class="form-err show">{{ $message }}</div>@enderror
        </div>
        <div class="form-check-row">
          <label class="form-check"><input type="checkbox" name="remember"> Remember me</label>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:8px">Sign In</button>
      </form>
      <div class="auth-switch" style="margin-top:18px">Don't have an account? <a href="{{ route('register') }}">Create one</a></div>
      <div class="auth-switch" style="margin-top:8px"><a href="{{ route('landing') }}">← Back to home</a></div>
    </div>
  </div>
</div>
</body>
</html>

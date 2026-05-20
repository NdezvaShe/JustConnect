<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account - JustConnect</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
<div id="toast-container"></div>
<div class="auth-wrap auth-wrap-signup">
  <div class="auth-side">
    <div class="auth-side-brand">
      <a href="{{ route('landing') }}" class="brand">
        <div class="brand-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#f4c430" stroke-width="2.2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <div><div class="brand-name" style="color:white">JustConnect</div><div class="brand-tag">Smarter Legal Decisions · NLP</div></div>
      </a>
    </div>
    <h2>Create your <span>guided legal workspace</span>.</h2>
    <p>JustConnect now onboards people in the same step-by-step way modern apps do: identify yourself first, then verify your email and step straight into your legal research workspace.</p>
    <div class="auth-pills">
      <div class="auth-pill"><div class="auth-pill-dot"></div>Multi-step signup with live progress</div>
      <div class="auth-pill"><div class="auth-pill-dot"></div>OTP verification built directly into the flow</div>
      <div class="auth-pill"><div class="auth-pill-dot"></div>Designed for professionals, students, and the public</div>
    </div>
  </div>

  <div class="auth-form-side">
    <div class="auth-box auth-box-wide signup-box" id="regForm">
      <div class="signup-progress" aria-hidden="true">
        <div class="signup-progress-bar"><span id="signupProgressFill"></span></div>
        <div class="signup-progress-steps">
          <div class="signup-progress-step active" id="progressStep1"><span>1</span>Identity</div>
          <div class="signup-progress-step" id="progressStep2"><span>2</span>Verify</div>
        </div>
      </div>

      <section class="signup-panel active" id="signupPanel1">
        <div class="signup-kicker">Step 1 of 2</div>
        <h3>Tell us who you are</h3>
        <div class="auth-sub">We will use this to personalise your experience from the first screen onward.</div>

        <div class="signup-identity-grid">
          <div class="signup-spotlight">
            <div class="signup-spotlight-label">Live account preview</div>
            <div class="signup-spotlight-name" id="identityPreviewName">Your full name will appear here</div>
            <div class="signup-spotlight-role" id="identityPreviewRole">Choose a designation to tailor your onboarding</div>
          </div>

          <div class="form-group signup-name-group">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-input" id="signupFullName" placeholder="Tanaka Moyo" autocomplete="name">
            <div class="form-err" id="errFullName">Enter at least a first and last name.</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Designation</label>
          <div class="signup-role-field">
            <div class="signup-select-shell">
              <span class="signup-select-icon" aria-hidden="true">ID</span>
              <select class="form-input form-select signup-role-select" id="signupRoleSelect" required>
                <option value="">Select your designation</option>
                <option value="Legal Professional">Legal Professional</option>
                <option value="Law Student">Law Student</option>
                <option value="Other">Member of the Public</option>
              </select>
            </div>
            <div class="signup-role-detail" id="signupRoleDetail">
              Select a designation so JustConnect can tune the first screens to your work.
            </div>
          </div>
          <div class="form-err" id="errRole">Choose the designation that fits you best.</div>
        </div>

        <div class="signup-note" id="identityHint">Use your real name so reports and downloads stay clearly attributed to you.</div>

        <div class="signup-actions">
          <a href="{{ route('landing') }}" class="btn btn-ghost btn-lg">← Back to home</a>
          <button type="button" class="btn btn-primary btn-lg" id="stepOneBtn">Continue</button>
        </div>
      </section>

      <section class="signup-panel" id="signupPanel2">
        <div class="signup-kicker">Step 2 of 2</div>
        <h3>Verify your email and secure your account</h3>
        <div class="auth-sub">We will send a one-time code to the email below as soon as your details are ready.</div>

        <div class="signup-summary">
          <div class="signup-summary-chip" id="summaryNameChip">Name pending</div>
          <div class="signup-summary-chip" id="summaryRoleChip">Designation pending</div>
          <button type="button" class="signup-summary-link" id="editIdentityBtn">Edit identity</button>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-input" id="signupEmail" placeholder="you@example.com" autocomplete="email">
          <div class="form-err" id="errEmail">Please enter a valid email address.</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" class="form-input" id="signupPass" placeholder="Create a strong password" autocomplete="new-password">
            <div class="pw-strength" id="pwStrength">
              <div class="pw-bar-track"><div class="pw-bar-fill" id="pwBar"></div></div>
              <div class="pw-label" id="pwLabel"></div>
            </div>
            <div class="form-err" id="errPass">Use 8+ characters with an uppercase letter, number, and symbol.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" class="form-input" id="signupConfirm" placeholder="Repeat your password" autocomplete="new-password">
            <div class="form-err" id="errConfirm">Passwords do not match.</div>
          </div>
        </div>

        <div class="signup-note">Your code will appear in the panel below as soon as your email is accepted.</div>

        <div class="signup-actions">
          <button type="button" class="btn btn-ghost btn-lg" id="stepBackBtn">Back</button>
          <button type="button" class="btn btn-primary btn-lg" id="regBtn">Send verification code</button>
        </div>

        <div class="otp-panel signup-otp-panel" id="otpPanel">
          <div class="otp-title">Verify your email</div>
          <div class="otp-sub">We sent a 6-digit code to <strong id="otpEmailDisplay"></strong></div>
          <div class="otp-inputs">
            @for($i=0;$i<6;$i++)
              <input class="otp-input" id="otp{{$i}}" maxlength="1" type="text" inputmode="numeric">
            @endfor
          </div>
          <div class="otp-resend">Didn’t receive it? <a href="#" id="resendLink">Resend code</a></div>
          <button type="button" class="btn btn-yellow btn-lg" style="width:100%;justify-content:center" id="verifyBtn">Verify &amp; Enter JustConnect</button>
        </div>

        <div class="auth-switch" style="margin-top:16px">Already have an account? <a href="{{ route('login') }}">Sign in</a></div>
      </section>
    </div>
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const ROLE_LABELS = {
  'Legal Professional': 'Legal Professional',
  'Law Student': 'Law Student',
  Other: 'Member of the Public',
};
const ROLE_HINTS = {
  'Legal Professional': 'Built for fast legal analysis, drafting support, and client-facing work.',
  'Law Student': 'Great for cases, coursework, revision, and building legal reasoning skills.',
  Other: 'A simple way to understand documents and legal issues in plain language.',
};

let currentStep = 1;
let pendingEmail = '';
let selectedRole = '';
let otpSent = false;

const fullNameInput = document.getElementById('signupFullName');
const emailInput = document.getElementById('signupEmail');
const passwordInput = document.getElementById('signupPass');
const confirmInput = document.getElementById('signupConfirm');
const roleSelect = document.getElementById('signupRoleSelect');

function showErr(id, show) {
  document.getElementById(id).classList.toggle('show', show);
}

function markInput(id, err) {
  document.getElementById(id).classList.toggle('err', err);
}

function checkPwStrength(pass) {
  let s = 0;
  if (pass.length >= 8) s++;
  if (/[A-Z]/.test(pass)) s++;
  if (/[0-9]/.test(pass)) s++;
  if (/[^A-Za-z0-9]/.test(pass)) s++;
  if (pass.length >= 12) s++;

  const lvls = [
    { w: '20%', c: '#e74c3c', t: 'Very Weak' },
    { w: '40%', c: '#e67e22', t: 'Weak' },
    { w: '60%', c: '#f4c430', t: 'Fair' },
    { w: '80%', c: '#74c69d', t: 'Strong' },
    { w: '100%', c: '#40916c', t: 'Very Strong' },
  ];

  const l = lvls[Math.max(0, Math.min(s - 1, 4))];
  document.getElementById('pwBar').style.width = l.w;
  document.getElementById('pwBar').style.background = l.c;
  document.getElementById('pwLabel').textContent = l.t;
  document.getElementById('pwLabel').style.color = l.c;
  document.getElementById('pwStrength').classList.toggle('show', pass.length > 0);
}

function parseFullName(value) {
  const pieces = value.trim().split(/\s+/).filter(Boolean);
  if (pieces.length < 2) return null;

  return {
    first_name: pieces.shift(),
    last_name: pieces.join(' '),
  };
}

function updateIdentityPreview() {
  const fullName = fullNameInput.value.trim();
  const roleLabel = selectedRole ? ROLE_LABELS[selectedRole] : '';
  document.getElementById('identityPreviewName').textContent = fullName || 'Your full name will appear here';
  document.getElementById('identityPreviewRole').textContent = roleLabel ? ROLE_HINTS[selectedRole] : 'Choose a designation to tailor your onboarding';
  document.getElementById('summaryNameChip').textContent = fullName || 'Name pending';
  document.getElementById('summaryRoleChip').textContent = roleLabel || 'Designation pending';
  document.getElementById('signupRoleDetail').textContent = selectedRole
    ? ROLE_HINTS[selectedRole]
    : 'Select a designation so JustConnect can tune the first screens to your work.';
  document.getElementById('identityHint').textContent = fullName && roleLabel
    ? `${fullName.split(/\s+/)[0]}, ${ROLE_HINTS[selectedRole]}`
    : 'Use your real name so reports and downloads stay clearly attributed to you.';
}

function syncStepUI() {
  document.getElementById('signupPanel1').classList.toggle('active', currentStep === 1);
  document.getElementById('signupPanel2').classList.toggle('active', currentStep === 2);
  document.getElementById('progressStep1').classList.toggle('active', currentStep === 1);
  document.getElementById('progressStep1').classList.toggle('complete', currentStep === 2);
  document.getElementById('progressStep2').classList.toggle('active', currentStep === 2);
  document.getElementById('signupProgressFill').style.width = currentStep === 1 ? '50%' : '100%';
}

function validateIdentityStep() {
  const parsed = parseFullName(fullNameInput.value);
  let ok = true;

  if (!parsed) {
    markInput('signupFullName', true);
    showErr('errFullName', true);
    ok = false;
  } else {
    markInput('signupFullName', false);
    showErr('errFullName', false);
  }

  roleSelect.classList.toggle('err', !selectedRole);
  showErr('errRole', !selectedRole);

  if (!selectedRole) ok = false;

  return ok ? parsed : null;
}

function validateCredentialStep() {
  const email = emailInput.value.trim();
  const pass = passwordInput.value;
  const confirm = confirmInput.value;
  let ok = true;

  if (!email || !/\S+@\S+\.\S+/.test(email)) {
    markInput('signupEmail', true);
    showErr('errEmail', true);
    ok = false;
  } else {
    markInput('signupEmail', false);
    showErr('errEmail', false);
  }

  if (pass.length < 8 || !/[A-Z]/.test(pass) || !/[0-9]/.test(pass) || !/[^A-Za-z0-9]/.test(pass)) {
    markInput('signupPass', true);
    showErr('errPass', true);
    ok = false;
  } else {
    markInput('signupPass', false);
    showErr('errPass', false);
  }

  if (pass !== confirm || !confirm) {
    markInput('signupConfirm', true);
    showErr('errConfirm', true);
    ok = false;
  } else {
    markInput('signupConfirm', false);
    showErr('errConfirm', false);
  }

  return ok;
}

function goToStep(step) {
  if (step === 2 && !validateIdentityStep()) return;

  currentStep = step;
  syncStepUI();

  const target = step === 1 ? fullNameInput : emailInput;
  setTimeout(() => target.focus(), 150);
}

function resetOtpState() {
  pendingEmail = '';
  otpSent = false;
  document.getElementById('otpPanel').classList.remove('show');
  document.getElementById('regBtn').disabled = false;
  document.getElementById('regBtn').textContent = 'Send verification code';
  [0,1,2,3,4,5].forEach(i => {
    document.getElementById('otp' + i).value = '';
    document.getElementById('otp' + i).classList.remove('otp-err');
  });
}

async function handleSignup() {
  const parsed = validateIdentityStep();
  const credentialsOk = validateCredentialStep();
  if (!parsed || !credentialsOk) return;

  const btn = document.getElementById('regBtn');
  btn.disabled = true;
  btn.textContent = otpSent ? 'Resending...' : 'Sending...';

  try {
    const data = await sendOtpRequest({
      ...parsed,
      role: selectedRole,
      email: emailInput.value.trim(),
      password: passwordInput.value,
      password_confirmation: confirmInput.value,
    });

    if (!data.success) {
      toast(data.message || 'Registration failed.', 'error');
      btn.disabled = false;
      btn.textContent = otpSent ? 'Resend verification code' : 'Send verification code';
      return;
    }

    if (data.redirect) {
      toast(data.message || 'Account created! Redirecting...', 'success', 3000);
      setTimeout(() => window.location.href = data.redirect, 1000);
      return;
    }

    pendingEmail = emailInput.value.trim();
    otpSent = true;
    document.getElementById('otpEmailDisplay').textContent = pendingEmail;
    document.getElementById('otpPanel').classList.add('show');
    btn.disabled = false;
    btn.textContent = 'Resend verification code';
    toast('Verification code sent!', 'success', 8000);
    setTimeout(() => document.getElementById('otp0').focus(), 300);
  } catch (e) {
    toast('Network error. Please try again.', 'error');
    btn.disabled = false;
    btn.textContent = otpSent ? 'Resend verification code' : 'Send verification code';
  }
}

async function sendOtpRequest(payload) {
  const res = await fetch('{{ route("register") }}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
    body: JSON.stringify(payload),
  });

  const data = await res.json();
  if (!res.ok) {
    const msgs = Object.values(data.errors || {}).flat();
    return {
      success: false,
      message: msgs[0] || data.message || 'Registration failed.',
    };
  }

  return data;
}

async function verifyOTP() {
  const otp = [0,1,2,3,4,5].map(i => document.getElementById('otp' + i).value).join('');
  if (otp.length < 6) {
    toast('Enter all 6 digits.', 'error');
    return;
  }

  try {
    const res = await fetch('{{ route("otp.verify") }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
      body: JSON.stringify({ email: pendingEmail, otp }),
    });
    const data = await res.json();
    if (data.success) {
      toast('Account created! Redirecting...', 'success', 3000);
      setTimeout(() => window.location.href = data.redirect, 1000);
    } else {
      toast(data.message || 'Invalid OTP.', 'error');
      [0,1,2,3,4,5].forEach(i => {
        document.getElementById('otp' + i).value = '';
        document.getElementById('otp' + i).classList.add('otp-err');
      });
      setTimeout(() => [0,1,2,3,4,5].forEach(i => document.getElementById('otp' + i).classList.remove('otp-err')), 1600);
      document.getElementById('otp0').focus();
    }
  } catch (e) {
    toast('Network error.', 'error');
  }
}

function toast(msg, type = 'info', dur = 5000) {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = msg + '<button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';
  c.appendChild(t);
  setTimeout(() => {
    t.classList.add('dismissing');
    setTimeout(() => t.remove(), 400);
  }, dur);
}

document.addEventListener('DOMContentLoaded', () => {
  updateIdentityPreview();
  syncStepUI();

  fullNameInput.addEventListener('input', () => {
    updateIdentityPreview();
    if (fullNameInput.value.trim()) {
      markInput('signupFullName', false);
      showErr('errFullName', false);
    }
  });

  roleSelect.addEventListener('change', () => {
    selectedRole = roleSelect.value;
    roleSelect.classList.remove('err');
    showErr('errRole', false);
    updateIdentityPreview();
  });

  emailInput.addEventListener('input', () => {
    markInput('signupEmail', false);
    showErr('errEmail', false);
    if (pendingEmail && pendingEmail !== emailInput.value.trim()) resetOtpState();
  });

  passwordInput.addEventListener('input', () => {
    checkPwStrength(passwordInput.value);
    markInput('signupPass', false);
    showErr('errPass', false);
    if (otpSent) resetOtpState();
  });

  confirmInput.addEventListener('input', () => {
    markInput('signupConfirm', false);
    showErr('errConfirm', false);
    if (otpSent) resetOtpState();
  });

  document.getElementById('stepOneBtn').addEventListener('click', () => goToStep(2));
  document.getElementById('stepBackBtn').addEventListener('click', () => goToStep(1));
  document.getElementById('editIdentityBtn').addEventListener('click', () => goToStep(1));
  document.getElementById('regBtn').addEventListener('click', handleSignup);
  document.getElementById('verifyBtn').addEventListener('click', verifyOTP);

  for (let i = 0; i < 6; i++) {
    const el = document.getElementById('otp' + i);
    el.addEventListener('input', () => {
      el.value = el.value.replace(/[^0-9]/g, '');
      if (el.value && i < 5) document.getElementById('otp' + (i + 1)).focus();
      if (i === 5 && el.value) verifyOTP();
    });
    el.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !el.value && i > 0) {
        const prev = document.getElementById('otp' + (i - 1));
        prev.value = '';
        prev.focus();
      }
    });
  }

  document.getElementById('resendLink').addEventListener('click', async e => {
    e.preventDefault();
    if (!pendingEmail) return;
    await handleSignup();
  });
});
</script>
</body>
</html>

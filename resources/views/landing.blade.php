<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>JustConnect — Smarter Legal Decisions Powered by NLP</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="landing-body">

<nav class="landing-nav">
  <a href="{{ route('landing') }}" class="brand">
    <div class="brand-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#f4c430" stroke-width="2.2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="12" y2="17"/></svg>
    </div>
    <div>
      <div class="brand-name">JustConnect</div>
      <div class="brand-tag">Smarter Legal Decisions · NLP</div>
    </div>
  </a>
  <div class="nav-links">
    <a href="{{ route('login') }}" class="btn btn-ghost">Sign In</a>
    <a href="{{ route('register') }}" class="btn btn-primary">Get Started</a>
  </div>
</nav>

<section class="hero">
  <div class="hero-left">
    <div class="hero-pill">Zimbabwe Legal Intelligence Platform</div>
    <h1>Smarter Legal<br>Decisions <span>Powered<br>by NLP</span></h1>
    <p>Upload any Zimbabwean legal document — contracts, judgments, statutes, agreements — and generate structured summaries powered by Natural Language Processing and a fine-tuned BART model trained on ZASCA and Zimbabwean data.</p>
    <div class="hero-actions">
      <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Start Analysing Free</a>
      <a href="{{ route('login') }}" class="btn btn-outline btn-lg">Sign In</a>
    </div>
    <div class="hero-trust">
      <span class="trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> NLP Entity Recognition</span>
      <span class="trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> Fine-tuned BART on ZASCA + Zimbabwe data</span>
      <span class="trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> PDF Export</span>
    </div>
  </div>
  <div class="hero-right">
    <div class="hero-card">
      <div class="hero-float">Summary Generated ✓</div>
      <div class="hero-card-header">
        <div class="hero-card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round" width="22" height="22"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div>
          <div style="font-size:11px;color:var(--grey-400)">Analysed document</div>
          <div style="font-weight:600;font-size:14px">High Court Judgment · HC 2024</div>
        </div>
      </div>
      <div class="skel-lines">
        <div class="skel-line" style="width:95%;background:var(--green-pale)"></div>
        <div class="skel-line" style="width:80%"></div>
        <div class="skel-line" style="width:88%;background:var(--yellow-light)"></div>
        <div class="skel-line" style="width:65%"></div>
        <div class="skel-line" style="width:75%;background:var(--green-pale)"></div>
      </div>
      <div class="card-tags">
        <span class="card-tag green">Court Judgment</span>
        <span class="card-tag yellow">3 Entities</span>
        <span class="card-tag blue">Contract Law</span>
      </div>
      <div class="stats-row">
        <div class="stat"><div class="stat-num">1.2s</div><div class="stat-lbl">NLP Time</div></div>
        <div class="stat"><div class="stat-num">94</div><div class="stat-lbl">Readability</div></div>
        <div class="stat"><div class="stat-num">100%</div><div class="stat-lbl">Coverage</div></div>
      </div>
    </div>
  </div>
</section>

<section class="features-section">
  <div class="features-inner">
    <div class="features-title">Everything your legal practice needs, <span>powered by NLP</span></div>
    <div class="features-grid">
      <div class="feat-card">
        <div class="feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#1a4731" stroke-width="2.2" stroke-linecap="round" width="24" height="24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
        <div class="feat-title">Named Entity Recognition</div>
        <div class="feat-desc">Automatically identifies persons, organisations, dates, monetary amounts, and Zimbabwean courts from any legal document using our NLP pipeline.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#1a4731" stroke-width="2.2" stroke-linecap="round" width="24" height="24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/></svg></div>
        <div class="feat-title">Extractive Summarisation</div>
        <div class="feat-desc">TF-IDF keyword extraction and sentence scoring produce concise executive summaries, key findings, and obligation lists without hallucination.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#1a4731" stroke-width="2.2" stroke-linecap="round" width="24" height="24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg></div>
        <div class="feat-title">Document Classification</div>
        <div class="feat-desc">Instantly classifies documents as Court Judgments, Acts, Lease Agreements, Employment Contracts, Sale Agreements, and more — Zimbabwe-specific.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#1a4731" stroke-width="2.2" stroke-linecap="round" width="24" height="24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
        <div class="feat-title">Natural Language Processing</div>
        <div class="feat-desc">Our NLP pipeline cleans legal text, extracts entities, classifies documents, and prepares structured inputs for reliable summary generation.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#1a4731" stroke-width="2.2" stroke-linecap="round" width="24" height="24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>
        <div class="feat-title">PDF Summary Export</div>
        <div class="feat-desc">Download polished PDF reports for every analysis — structured, branded, and ready to share with clients, colleagues, or the court.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#1a4731" stroke-width="2.2" stroke-linecap="round" width="24" height="24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <div class="feat-title">Fine-tuned BART Summaries</div>
        <div class="feat-desc">Generate legal summaries using a fine-tuned BART workflow informed by ZASCA judgments and Zimbabwean legal data for more relevant local outputs.</div>
      </div>
    </div>
  </div>
</section>

<footer class="landing-footer">
  <div class="footer-brand">JustConnect</div>
  <div>© {{ date('Y') }} JustConnect · Smarter Legal Decisions Powered by NLP · Zimbabwe</div>
  <div>Built with Laravel &amp; PHP NLP</div>
</footer>

</body>
</html>

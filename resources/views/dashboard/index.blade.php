<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — JustConnect</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<meta name="csrf-token" content="{{ csrf_token() }}">
@php($isLegalProfessional = auth()->user()->role === 'Legal Professional')
@php($isAdmin = $isAdmin ?? false)
@php($adminAnalytics = $adminAnalytics ?? null)
<style>
.trace-list{margin-top:18px;display:grid;gap:12px}
.trace-title,.risk-title{font:700 12px/1.4 'DM Sans',sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#2d6a4f;margin-bottom:8px}
.trace-card,.risk-block{padding:14px 16px;border:1px solid rgba(45,106,79,.12);border-radius:16px;background:#fbfcf8}
.trace-card.compact{margin-top:10px}
.trace-meta{font-size:11px;color:#6c7a71;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
.trace-claim{font-weight:600;color:#173d2b;margin-bottom:8px}
.trace-quote{padding:10px 12px;border-left:3px solid #f4c430;background:#fffdf3;color:#38443d;border-radius:10px;margin-bottom:8px}
.trace-reason{font-size:13px;color:#58645c}
.risk-block{margin-top:14px}
.risk-list{margin:10px 0 0;padding-left:18px;display:grid;gap:8px;color:#38443d}
.insight-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.95fr);gap:22px;align-items:start}
.insight-stack{display:grid;gap:18px}
.insight-panel{background:#fff;border:1px solid rgba(26,71,49,.08);border-radius:18px;box-shadow:var(--shadow-sm);overflow:hidden}
.insight-panel.soft{background:linear-gradient(180deg,#fffdf3 0%,#ffffff 100%)}
.insight-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid rgba(26,71,49,.08)}
.insight-panel-title{font:700 13px/1.3 'DM Sans',sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#2d6a4f}
.insight-panel-sub{font-size:12px;color:#7a877d}
.insight-panel-body{padding:18px}
.summary-switch{display:flex;gap:8px;flex-wrap:wrap}
.summary-pill{padding:7px 12px;border-radius:999px;border:1px solid rgba(45,106,79,.14);background:#fff;color:#2d6a4f;font-size:12px;font-weight:700;letter-spacing:.02em}
.summary-pill.active{background:#1a4731;color:#fff;border-color:#1a4731}
.summary-copy{font-size:15px;line-height:1.78;color:#354238;white-space:pre-wrap}
.case-result-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.result-card{border-radius:18px;padding:18px;border:1px solid rgba(26,71,49,.08);box-shadow:var(--shadow-sm);background:#fff;position:relative;overflow:hidden}
.result-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:5px;background:#2d6a4f}
.result-card.warning::before{background:#c9a000}
.result-card.info::before{background:#1565c0}
.result-card.danger::before{background:#c0392b}
.result-card.neutral::before{background:#64748b}
.result-card-title{font:700 13px/1.3 'DM Sans',sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#1a4731;margin-bottom:10px}
.result-card-list{display:grid;gap:9px}
.result-card-item{display:flex;gap:10px;align-items:flex-start;color:#39463d;font-size:14px;line-height:1.55}
.result-card-item-mark{width:22px;height:22px;border-radius:50%;background:#edf8f1;color:#2d6a4f;display:flex;align-items:center;justify-content:center;font-weight:800;flex-shrink:0}
.panel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.info-card{border:1px solid rgba(26,71,49,.08);border-radius:16px;background:#f9fbf8;padding:16px}
.info-card-title{font:700 12px/1.4 'DM Sans',sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#6a7a71;margin-bottom:10px}
.info-kv{display:grid;gap:10px}
.info-kv-row{display:flex;justify-content:space-between;gap:14px;padding-bottom:8px;border-bottom:1px dashed rgba(26,71,49,.1)}
.info-kv-row:last-child{border-bottom:none;padding-bottom:0}
.info-kv-label{font-size:12px;color:#718076;text-transform:uppercase;letter-spacing:.06em}
.info-kv-value{font-size:14px;color:#1f2f25;font-weight:600;text-align:right}
.chip-cloud{display:flex;gap:9px;flex-wrap:wrap}
.law-chip{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:#eef5ef;color:#20452f;border:1px solid rgba(45,106,79,.12);font-size:12px;font-weight:700}
.law-chip.gold{background:#fff6d2;color:#785d00;border-color:rgba(201,160,0,.22)}
.law-chip.blue{background:#eaf3ff;color:#1554a0;border-color:rgba(21,84,160,.15)}
.passage-list{display:grid;gap:12px}
.passage-card{padding:14px 15px;border-radius:14px;border:1px solid rgba(26,71,49,.08);background:#fff}
.passage-meta{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#728076;margin-bottom:8px}
.passage-text{font-size:14px;line-height:1.65;color:#39463d}
.source-pane{min-height:320px;max-height:960px;overflow:auto;border-radius:16px;background:#18261f;color:#dfe7e2;padding:18px;font-size:13px;line-height:1.7;white-space:pre-wrap}
.evidence-grid{display:grid;gap:14px}
.risk-badges{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.risk-badge{padding:7px 11px;border-radius:999px;font-size:12px;font-weight:700}
.risk-badge.high{background:#fde8e8;color:#c0392b}
.risk-badge.medium{background:#fff6d2;color:#8a6b00}
.risk-badge.low{background:#edf8f1;color:#2d6a4f}
.mini-note{font-size:12px;color:#7c887f}
.record-issue-cloud{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.record-score{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;background:#eef5ef;color:#2d6a4f;font-size:11px;font-weight:700;margin-top:8px}
.summary-simple{display:grid;gap:16px;padding:18px;background:#f8faf7}
.summary-complete-banner{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-radius:14px;background:#edf8f1;border:1px solid rgba(45,106,79,.16);color:#1a4731;font-weight:700}
.summary-complete-banner span{font-size:12px;font-weight:600;color:#557066}
.summary-simple-card{background:#fff;border:1px solid rgba(26,71,49,.08);border-radius:14px;padding:18px;box-shadow:var(--shadow-sm)}
.summary-simple-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
.summary-simple-title{font:700 13px/1.3 'DM Sans',sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#2d6a4f}
.summary-simple-sub{font-size:12px;color:#7a877d;margin-top:3px}
.summary-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
.summary-meta-item{padding:12px;border-radius:12px;background:#f7f7f5;border:1px solid rgba(26,71,49,.07)}
.summary-meta-label{font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:#718076;margin-bottom:5px}
.summary-meta-value{font-size:14px;font-weight:700;color:#1a4731}
.summary-two-col{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.summary-mode-grid{display:grid;gap:12px}
.summary-section-card{padding:14px;border:1px solid rgba(26,71,49,.08);border-radius:12px;background:#fbfcf8}
.summary-list{margin:0;padding-left:18px;display:grid;gap:9px;color:#39463d;font-size:14px;line-height:1.55}
.summary-source-details{border:1px solid rgba(26,71,49,.1);border-radius:14px;background:#fff;overflow:hidden}
.summary-source-details summary{cursor:pointer;padding:14px 16px;font-weight:700;color:#1a4731}
.summary-source-details .source-pane{border-radius:0;max-height:360px}
.admin-badge{display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:5px 9px;border-radius:999px;background:#fff6d2;color:#755b00;border:1px solid rgba(244,196,48,.35);font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
.admin-card{background:#fff;border:1px solid rgba(26,71,49,.08);border-radius:14px;padding:16px;box-shadow:var(--shadow-sm)}
.admin-card-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#718076;font-weight:800;margin-bottom:8px}
.admin-card-value{font-size:30px;line-height:1;color:#1a4731;font-weight:900}
.admin-card-sub{font-size:12px;color:#7a877d;margin-top:7px}
.admin-analytics-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.8fr);gap:18px;align-items:start;margin-top:18px}
.admin-list{display:grid;gap:10px}
.admin-list-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:10px 0;border-bottom:1px dashed rgba(26,71,49,.1)}
.admin-list-row:last-child{border-bottom:none}
.admin-list-label{font-size:13px;color:#344238;font-weight:700}
.admin-list-value{font-size:13px;color:#1a4731;font-weight:900}
.admin-status-bar{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.admin-status-pill{padding:12px;border-radius:12px;background:#f7f8f5;border:1px solid rgba(26,71,49,.08)}
.admin-status-pill strong{display:block;color:#1a4731;font-size:20px}
.admin-status-pill span{display:block;margin-top:3px;color:#718076;font-size:11px;text-transform:uppercase;letter-spacing:.07em;font-weight:800}
.btn-email{background:#1a4731!important;color:#fff!important;border:1px solid #1a4731!important;box-shadow:0 8px 18px rgba(26,71,49,.18)}
.btn-email:hover{background:#1a4731!important;border-color:#1a4731!important}
.btn-email svg{stroke:#f4c430}
@media (max-width: 1100px){.insight-layout{grid-template-columns:1fr}.source-pane{max-height:420px}}
@media (max-width: 1100px){.admin-analytics-layout{grid-template-columns:1fr}}
@media (max-width: 768px){.summary-two-col{grid-template-columns:1fr}.summary-complete-banner{align-items:flex-start;flex-direction:column}.admin-status-bar{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
</head>
<body class="app-body">

<div id="toast-container"></div>

<!-- ═══════════════ MOBILE OVERLAY ═══════════════ -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="app-sidebar" id="appSidebar">
  <div class="sb-brand">
    <div class="brand-icon" style="width:36px;height:36px;border-radius:8px">
      <svg viewBox="0 0 24 24" fill="none" stroke="#f4c430" stroke-width="2.2" stroke-linecap="round" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    </div>
    <div>
      <div class="sb-brand-name">JustConnect</div>
      <div class="sb-brand-sub">Smarter Legal Decisions · NLP</div>
    </div>
  </div>
  <div class="sb-profile">
    <div class="sb-profile-card">
      <div class="sb-avatar">{{ auth()->user()->initials }}</div>
      <div>
        <div class="sb-name">{{ auth()->user()->full_name }}</div>
        <div class="sb-email">{{ auth()->user()->email }}</div>
        @if($isAdmin)
          <div class="admin-badge">Admin</div>
        @endif
      </div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="sb-section-lbl">Main</div>
    <div class="sb-item active" id="nav-dashboard" onclick="goTab('dashboard')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard
    </div>
    <div class="sb-item" id="nav-new-job" onclick="goTab('new-job')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>New Job
      <span class="sb-badge">+</span>
    </div>
    <div class="sb-section-lbl">Library</div>
    <div class="sb-item" id="nav-records" onclick="goTab('records')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Records
      <span class="sb-badge" id="recBadge">{{ $docCount }}</span>
    </div>
    <div class="sb-item" id="nav-downloads" onclick="goTab('downloads')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Downloads
    </div>
    @if($isLegalProfessional)
      <div class="sb-section-lbl">Legal Tools</div>
      <div class="sb-item" id="nav-legal-search" onclick="goTab('legal-search')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Search
      </div>
      <div class="sb-item" id="nav-clause-extractor" onclick="goTab('clause-extractor')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16v16H4z"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>Clause Extractor
      </div>
    @endif
    <div class="sb-item" id="nav-help" onclick="goTab('help')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.82 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Help
    </div>
    <div class="sb-section-lbl">Account</div>
    <div class="sb-item" id="nav-settings" onclick="goTab('settings')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1.82V22a2 2 0 0 1-4 0v-.18A1.65 1.65 0 0 0 9 20a1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1.82-.33H2a2 2 0 0 1 0-4h.18A1.65 1.65 0 0 0 4 9a1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1.82V2a2 2 0 0 1 4 0v.18A1.65 1.65 0 0 0 15 4a1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.15.33.37.61.6 1 .42.66.42 1.34 0 2-.23.39-.45.67-.6 1z"/></svg>Settings
    </div>
  </nav>
  <div class="sb-bottom">
    <form method="POST" action="{{ route('logout') }}" id="logoutForm">
      @csrf
      <button type="submit" class="sb-signout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign Out
      </button>
    </form>
  </div>
</aside>

<!-- ═══════════════ MAIN CONTENT ═══════════════ -->
<div class="app-main" id="appMain">

  <!-- Mobile topbar hamburger -->
  <div class="mobile-topbar">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <div class="brand-name" style="font-family:'Playfair Display',serif;font-size:18px;color:var(--green-deep)">JustConnect</div>
    <div style="width:40px"></div>
  </div>

  <!-- ─── TAB: DASHBOARD ─── -->
  <div id="tab-dashboard">
    <div class="topbar">
      <div>
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-sub">Welcome back, {{ auth()->user()->first_name }} — here's your overview</div>
      </div>
      <div class="topbar-right">
        <button class="btn btn-yellow" onclick="goTab('new-job')">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>New Job
        </button>
      </div>
    </div>
    <div class="content">
      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
          <div class="stat-card-num" id="sdDocs">{{ $docCount }}</div>
          <div class="stat-card-lbl">Documents Analysed</div>
          <div class="stat-card-trend">↑ Your library</div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>
          <div class="stat-card-num" id="sdDownloads">{{ $dlCount }}</div>
          <div class="stat-card-lbl">Summaries Downloaded</div>
          <div class="stat-card-trend">↑ PDF exports</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div class="stat-card-num">BART</div>
          <div class="stat-card-lbl">Summary Engine</div>
          <div class="stat-card-trend">✓ ZASCA + Zimbabwe data</div>
        </div>
      </div>
      @if($isAdmin && $adminAnalytics)
        <div class="panel" style="margin-bottom:18px">
          <div class="panel-head">
            <div>
              <div class="panel-title">Admin Analytics</div>
              <div class="panel-sub">Platform-wide activity, usage, and NLP_BART performance</div>
            </div>
            <span class="admin-badge">Admin Console</span>
          </div>
          <div class="insight-panel-body">
            <div class="admin-grid">
              <div class="admin-card">
                <div class="admin-card-label">Users</div>
                <div class="admin-card-value">{{ number_format($adminAnalytics['totals']['users'] ?? 0) }}</div>
                <div class="admin-card-sub">{{ number_format($adminAnalytics['totals']['legal_professionals'] ?? 0) }} legal professionals</div>
              </div>
              <div class="admin-card">
                <div class="admin-card-label">Documents</div>
                <div class="admin-card-value">{{ number_format($adminAnalytics['totals']['documents'] ?? 0) }}</div>
                <div class="admin-card-sub">{{ number_format($adminAnalytics['totals']['documents_7d'] ?? 0) }} uploaded in 7 days</div>
              </div>
              <div class="admin-card">
                <div class="admin-card-label">Summaries</div>
                <div class="admin-card-value">{{ number_format($adminAnalytics['totals']['summaries'] ?? 0) }}</div>
                <div class="admin-card-sub">{{ number_format($adminAnalytics['totals']['summaries_today'] ?? 0) }} generated today</div>
              </div>
              <div class="admin-card">
                <div class="admin-card-label">Downloads</div>
                <div class="admin-card-value">{{ number_format($adminAnalytics['totals']['downloads'] ?? 0) }}</div>
                <div class="admin-card-sub">PDF export activity</div>
              </div>
              <div class="admin-card">
                <div class="admin-card-label">Corpus Size</div>
                <div class="admin-card-value">{{ number_format($adminAnalytics['totals']['words'] ?? 0) }}</div>
                <div class="admin-card-sub">{{ number_format($adminAnalytics['totals']['pages'] ?? 0) }} pages processed</div>
              </div>
              <div class="admin-card">
                <div class="admin-card-label">NLP_BART Avg Time</div>
                <div class="admin-card-value">{{ ($adminAnalytics['totals']['avg_processing_ms'] ?? 0) > 0 ? number_format(($adminAnalytics['totals']['avg_processing_ms'] ?? 0) / 1000, 1) . 's' : 'N/A' }}</div>
                <div class="admin-card-sub">Average completed analysis time</div>
              </div>
              <div class="admin-card">
                <div class="admin-card-label">ROUGE-1</div>
                <div class="admin-card-value">{{ isset($adminAnalytics['rouge_scores']['rouge_1']) ? $adminAnalytics['rouge_scores']['rouge_1'] . '%' : 'N/A' }}</div>
                <div class="admin-card-sub">Source-overlap score</div>
              </div>
              <div class="admin-card">
                <div class="admin-card-label">ROUGE-2</div>
                <div class="admin-card-value">{{ isset($adminAnalytics['rouge_scores']['rouge_2']) ? $adminAnalytics['rouge_scores']['rouge_2'] . '%' : 'N/A' }}</div>
                <div class="admin-card-sub">Bigram source overlap</div>
              </div>
              <div class="admin-card">
                <div class="admin-card-label">ROUGE-L</div>
                <div class="admin-card-value">{{ isset($adminAnalytics['rouge_scores']['rouge_l']) ? $adminAnalytics['rouge_scores']['rouge_l'] . '%' : 'N/A' }}</div>
                <div class="admin-card-sub">{{ number_format($adminAnalytics['rouge_scores']['sample_size'] ?? 0) }} sampled summaries</div>
              </div>
            </div>

            <div class="admin-analytics-layout">
              <div class="insight-stack">
                <div class="admin-card">
                  <div class="admin-card-label">Document Status</div>
                  <div class="admin-status-bar">
                    <div class="admin-status-pill"><strong>{{ number_format($adminAnalytics['documents_by_status']['done'] ?? 0) }}</strong><span>Done</span></div>
                    <div class="admin-status-pill"><strong>{{ number_format($adminAnalytics['documents_by_status']['processing'] ?? 0) }}</strong><span>Processing</span></div>
                    <div class="admin-status-pill"><strong>{{ number_format($adminAnalytics['documents_by_status']['pending'] ?? 0) }}</strong><span>Pending</span></div>
                    <div class="admin-status-pill"><strong>{{ number_format($adminAnalytics['documents_by_status']['failed'] ?? 0) }}</strong><span>Failed</span></div>
                  </div>
                </div>
                <div class="admin-card">
                  <div class="admin-card-label">Recent Platform Summaries</div>
                  @if($adminAnalytics['recent_summaries']->isEmpty())
                    <div class="empty-state">No summaries generated yet.</div>
                  @else
                    <table class="rec-table">
                      <thead><tr><th>Document</th><th>User</th><th>Type</th><th>Date</th></tr></thead>
                      <tbody>
                      @foreach($adminAnalytics['recent_summaries'] as $summary)
                        <tr>
                          <td><strong>{{ $summary->document?->original_name ?? 'Document #' . $summary->document_id }}</strong></td>
                          <td>{{ $summary->user?->email ?? 'Unknown' }}</td>
                          <td><span class="type-badge {{ catBadge($summary->document_type) }}">{{ $summary->document_type ?? 'Legal Document' }}</span></td>
                          <td>{{ $summary->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                      @endforeach
                      </tbody>
                    </table>
                  @endif
                </div>
              </div>

              <div class="insight-stack">
                <div class="admin-card">
                  <div class="admin-card-label">NLP_BART Engine Mix</div>
                  <div class="admin-list">
                    @forelse($adminAnalytics['provider_mix'] as $item)
                      <div class="admin-list-row"><span class="admin-list-label">{{ $item['label'] }}</span><span class="admin-list-value">{{ number_format($item['total']) }}</span></div>
                    @empty
                      <div class="mini-note">No engine usage recorded yet.</div>
                    @endforelse
                  </div>
                </div>
                <div class="admin-card">
                  <div class="admin-card-label">User Roles</div>
                  <div class="admin-list">
                    @forelse($adminAnalytics['roles'] as $item)
                      <div class="admin-list-row"><span class="admin-list-label">{{ $item['label'] }}</span><span class="admin-list-value">{{ number_format($item['total']) }}</span></div>
                    @empty
                      <div class="mini-note">No role data yet.</div>
                    @endforelse
                  </div>
                </div>
                <div class="admin-card">
                  <div class="admin-card-label">Summary Modes</div>
                  <div class="admin-list">
                    @forelse($adminAnalytics['summary_modes'] as $item)
                      <div class="admin-list-row"><span class="admin-list-label">{{ str_replace('_', ' ', $item['label']) }}</span><span class="admin-list-value">{{ number_format($item['total']) }}</span></div>
                    @empty
                      <div class="mini-note">No summary modes recorded yet.</div>
                    @endforelse
                  </div>
                </div>
                <div class="admin-card">
                  <div class="admin-card-label">Top Document Types</div>
                  <div class="admin-list">
                    @forelse($adminAnalytics['document_types'] as $item)
                      <div class="admin-list-row"><span class="admin-list-label">{{ $item['label'] }}</span><span class="admin-list-value">{{ number_format($item['total']) }}</span></div>
                    @empty
                      <div class="mini-note">No document types classified yet.</div>
                    @endforelse
                  </div>
                </div>
                <div class="admin-card">
                  <div class="admin-card-label">Top Courts</div>
                  <div class="admin-list">
                    @forelse($adminAnalytics['courts'] as $item)
                      <div class="admin-list-row"><span class="admin-list-label">{{ $item['label'] }}</span><span class="admin-list-value">{{ number_format($item['total']) }}</span></div>
                    @empty
                      <div class="mini-note">No court data extracted yet.</div>
                    @endforelse
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      @endif
      <div class="panel">
        <div class="panel-head">
          <div><div class="panel-title">Recent Documents</div><div class="panel-sub">Your most recently analysed documents</div></div>
          <button class="btn btn-ghost btn-sm" onclick="goTab('records')">View all →</button>
        </div>
        <div id="dashRecent">
          @if($recent->isEmpty())
            <div class="empty-state"><div class="empty-icon">📄</div><div class="empty-title">No documents yet</div><div class="empty-sub">Upload your first legal document to get started</div><button class="btn btn-primary" onclick="goTab('new-job')">Upload Document</button></div>
          @else
            <table class="rec-table">
              <thead><tr><th>Document</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead>
              <tbody>
              @foreach($recent as $s)
                <tr>
                  <td><strong>{{ $s->document?->original_name }}</strong></td>
                  <td><span class="type-badge {{ catBadge($s->document_type) }}">{{ $s->document_type ?? '—' }}</span></td>
                  <td>{{ $s->created_at?->format('d M Y') }}</td>
                  <td>
                    <button class="tbl-btn" onclick='openSummaryModal({{ $s->id }})'>View</button>
                    <button class="tbl-btn" onclick='downloadPDF({{ $s->id }})'>PDF</button>
                  </td>
                </tr>
              @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    </div>
  </div>

  <!-- ─── TAB: NEW JOB ─── -->
  <div id="tab-new-job" class="hidden">
    <div class="topbar"><div><div class="topbar-title">New Job</div><div class="topbar-sub">Upload a legal document to generate a summary using Natural Language Processing and a fine-tuned BART model trained on ZASCA and Zimbabwean data</div></div></div>
    <div class="content">
      <div class="job-grid">
        <div>
          <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
            <input type="file" id="fileInput" accept=".pdf,.txt,.doc,.docx" style="display:none" onchange="onFileChosen(event)">
            <div class="upload-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round" width="36" height="36"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </div>
            <div class="upload-title">Upload Legal Document</div>
            <div class="upload-sub">Drag &amp; drop or click to browse</div>
            <div class="upload-tags">
              <span class="upload-tag">PDF</span><span class="upload-tag">DOC</span><span class="upload-tag">DOCX</span><span class="upload-tag">TXT</span>
            </div>
          </div>
          <div class="panel mt-16 file-info-panel" id="fileInfoPanel" style="display:none">
            <div class="file-info-inner">
              <div class="file-info-left">
                <div class="file-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <div><div class="file-name" id="fileName">—</div><div class="file-meta" id="fileMeta">—</div></div>
              </div>
              <button class="btn btn-primary" id="summariseBtn" onclick="doSummarise()" disabled>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Generate Summary
              </button>
            </div>
            <div class="extract-status" id="extractStatus">Waiting...</div>
          </div>
        </div>
        <div>
          <div class="extract-text-panel">
            <div class="extract-header">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#f4c430" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <h3>Extracted Document Text</h3>
              <span style="color:rgba(255,255,255,.5);font-size:12px" id="wordCount"></span>
            </div>
            <div class="extract-body" id="extractBody"><div class="extract-placeholder">Upload a document to extract and preview its text here.</div></div>
          </div>
        </div>
      </div>

      <!-- Progress bar -->
      <div class="progress-wrap hidden" id="progressWrap">
        <div class="progress-ring" id="progressRing" aria-hidden="true">
          <div class="progress-ring-inner"><span id="progressPercent">0%</span></div>
        </div>
        <div class="progress-copy">
          <div class="progress-kicker">Generating Summary</div>
          <div class="progress-label" id="progressLabel">Initialising NLP pipeline...</div>
          <div class="progress-status" id="progressStatus">Keep this page open while JustConnect analyses the document.</div>
          <div class="progress-steps" id="progressSteps"></div>
        </div>
      </div>

      <!-- Summary card -->
      <div class="summary-card hidden" id="summaryCard">
        <div class="summary-head">
          <div><h3>Legal Insight Workspace</h3><div class="summary-head-sub" id="summaryDocName">—</div></div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <button class="btn btn-yellow btn-sm" onclick="downloadCurrentExport('pdf')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Export PDF
            </button>
            <button class="btn btn-email btn-sm" id="emailSummaryBtn" onclick="emailCurrentSummary()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>Email me
            </button>
          </div>
        </div>
        <div class="summary-body" id="summaryBody"></div>
      </div>
    </div>
  </div>

  <!-- ─── TAB: RECORDS ─── -->
  <div id="tab-records" class="hidden">
    <div class="topbar"><div><div class="topbar-title">Records</div><div class="topbar-sub">All your analysed legal documents</div></div><button class="btn btn-primary" onclick="goTab('new-job')">+ New Job</button></div>
    <div class="content">
      <div class="search-bar">
        <div class="search-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input class="search-inp" id="recSearch" placeholder="Semantic search: unfair dismissal, property dispute, constitutional rights..." oninput="filterRecs()"></div>
        <select class="filter-sel" id="recFilter" onchange="filterRecs()">
          <option value="">All Types</option>
          <option value="Contract">Contracts</option>
          <option value="Judgment">Judgments</option>
          <option value="Act">Acts</option>
          <option value="Lease">Leases</option>
          <option value="Employment">Employment</option>
        </select>
        <select class="filter-sel" id="recCourt" onchange="filterRecs()">
          <option value="">All Courts</option>
          <option value="High Court">High Court</option>
          <option value="Supreme Court">Supreme Court</option>
          <option value="Constitutional Court">Constitutional Court</option>
          <option value="Labour Court">Labour Court</option>
        </select>
        <select class="filter-sel" id="recIssue" onchange="filterRecs()">
          <option value="">All Issues</option>
          <option value="Labour dispute">Labour dispute</option>
          <option value="Unfair dismissal">Unfair dismissal</option>
          <option value="Breach of contract">Breach of contract</option>
          <option value="Property dispute">Property dispute</option>
          <option value="Constitutional rights">Constitutional rights</option>
          <option value="Administrative review">Administrative review</option>
        </select>
      </div>
      <div class="panel"><div class="panel-head"><div><div class="panel-title">Document Library</div><div class="panel-sub" id="recCount">Loading...</div></div></div><div id="recTable"><div class="loading-state">Loading records...</div></div></div>
    </div>
  </div>

  <!-- ─── TAB: DOWNLOADS ─── -->
  <div id="tab-downloads" class="hidden">
    <div class="topbar"><div><div class="topbar-title">Downloads</div><div class="topbar-sub">Generated summary PDFs</div></div></div>
    <div class="content"><div id="dlContainer"><div class="loading-state">Loading downloads...</div></div></div>
  </div>

  <!-- ─── TAB: PROFILE ─── -->
  <!-- Legal professional tools -->
  @if($isLegalProfessional)
  <div id="tab-legal-search" class="hidden">
    <div class="topbar"><div><div class="topbar-title">Legal Search</div><div class="topbar-sub">Search your saved summaries by legal issue, party, court, statute, or outcome</div></div></div>
    <div class="content">
      <div class="legal-tool-panel">
        <div class="legal-search-row">
          <div class="search-wrap legal-search-input">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="search-inp" id="legalSearchInput" placeholder="Search: ratio decidendi, audit powers, unfair dismissal..." onkeydown="if(event.key==='Enter') runLegalSearch()">
          </div>
          <button class="btn btn-primary" onclick="runLegalSearch()">Search</button>
        </div>
        <div class="legal-quick-row">
          <button type="button" class="law-chip" onclick="quickLegalSearch('ratio decidendi')">ratio decidendi</button>
          <button type="button" class="law-chip" onclick="quickLegalSearch('court order')">court order</button>
          <button type="button" class="law-chip" onclick="quickLegalSearch('statutory duty')">statutory duty</button>
          <button type="button" class="law-chip" onclick="quickLegalSearch('remedy')">remedy</button>
        </div>
      </div>
      <div class="panel mt-16">
        <div class="panel-head"><div><div class="panel-title">Search Results</div><div class="panel-sub" id="legalSearchCount">Enter a query to search your library</div></div></div>
        <div id="legalSearchResults"><div class="empty-state">Search results will appear here.</div></div>
      </div>
    </div>
  </div>

  <div id="tab-clause-extractor" class="hidden">
    <div class="topbar"><div><div class="topbar-title">Clause Extractor</div><div class="topbar-sub">Extract operative clauses, sections, orders, definitions, and obligations from legal text</div></div></div>
    <div class="content">
      <div class="legal-tool-grid">
        <div class="legal-tool-panel">
          <div class="panel-title">Source Text</div>
          <div class="panel-sub">Paste text, upload a PDF, or use the currently extracted upload text</div>
          <input type="file" id="clauseFileInput" accept=".pdf,application/pdf" style="display:none" onchange="onClauseFileChosen(event)">
          <div class="legal-actions">
            <button class="btn btn-ghost" type="button" onclick="document.getElementById('clauseFileInput').click()">Upload PDF</button>
            <button class="btn btn-ghost" type="button" onclick="useCurrentExtractedText()">Use extracted text</button>
          </div>
          <div class="mini-note" id="clauseUploadStatus">PDF text can be extracted here without creating a new summary job.</div>
          <textarea class="legal-textarea" id="clauseInput" placeholder="Paste legal text here..."></textarea>
          <div class="legal-actions">
            <button class="btn btn-primary" onclick="extractClauses()">Extract clauses</button>
          </div>
        </div>
        <div class="legal-tool-panel">
          <div class="panel-title">Extracted Clauses</div>
          <div class="panel-sub" id="clauseCount">No clauses extracted yet</div>
          <div id="clauseOutput" class="clause-output"><div class="empty-state">Extracted clauses will appear here.</div></div>
        </div>
      </div>
    </div>
  </div>
  @endif

  <div id="tab-help" class="hidden">
    <div class="topbar">
      <div>
        <div class="topbar-title">Help &amp; Instructions</div>
        <div class="topbar-sub">Use JustConnect to upload, analyse, review, search, and export legal summaries</div>
      </div>
    </div>
    <div class="content">
      <div class="insight-stack">
        <div class="insight-panel soft">
          <div class="insight-panel-head">
            <div>
              <div class="insight-panel-title">How To Use The System</div>
              <div class="insight-panel-sub">A simple end-to-end workflow for legal document summarisation</div>
            </div>
          </div>
          <div class="insight-panel-body">
            <div class="passage-list">
              <div class="passage-card">
                <div class="passage-meta">Step 1</div>
                <div class="passage-text">Open <strong>New Job</strong> and upload a PDF, DOC, DOCX, or TXT legal document.</div>
              </div>
              <div class="passage-card">
                <div class="passage-meta">Step 2</div>
                <div class="passage-text">Wait for the extracted text preview, then quickly review it so you know the document was captured correctly.</div>
              </div>
              <div class="passage-card">
                <div class="passage-meta">Step 3</div>
                <div class="passage-text">Click <strong>Generate Summary</strong>. Analysis now runs directly in the app, so a queue worker is not required for normal summaries.</div>
              </div>
              <div class="passage-card">
                <div class="passage-meta">Step 4</div>
                <div class="passage-text">Read the result cards, structured case panels, and supporting passages to understand the outcome and the legal reasoning.</div>
              </div>
              <div class="passage-card">
                <div class="passage-meta">Step 5</div>
                <div class="passage-text">Switch between the <strong>Professional</strong> and <strong>Citizen</strong> summaries depending on whether you want legal drafting language or plain English.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="panel-grid">
          <div class="info-card">
            <div class="info-card-title">Research &amp; Search</div>
            <div class="chip-cloud">
              <span class="law-chip">Use Records to search by legal concept</span>
              <span class="law-chip blue">Filter by court, issue, and document type</span>
              <span class="law-chip gold">Open saved summaries to compare cases</span>
            </div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Verification</div>
            <div class="chip-cloud">
              <span class="law-chip">Check supporting passages for grounding</span>
              <span class="law-chip blue">Use the original text pane to confirm facts</span>
              <span class="law-chip gold">Review names, dates, and sections when accuracy matters</span>
            </div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Exports</div>
            <div class="chip-cloud">
              <span class="law-chip">Export PDF for sharing</span>
              <span class="law-chip gold">Downloads keeps your export history</span>
            </div>
          </div>
        </div>
        <div class="insight-panel">
          <div class="insight-panel-head">
            <div>
              <div class="insight-panel-title">Helpful Tips</div>
              <div class="insight-panel-sub">Small habits that improve summary quality</div>
            </div>
          </div>
          <div class="insight-panel-body">
            <ul class="risk-list">
              <li>Use text-based PDFs where possible. Scanned image PDFs may extract less cleanly.</li>
              <li>Look at the extracted text first if the final summary seems incomplete or oddly phrased.</li>
              <li>Try searches like <strong>unfair dismissal</strong>, <strong>property dispute</strong>, or <strong>constitutional rights</strong> in Records.</li>
              <li>Use the source-linked passages before relying on important legal findings.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="tab-settings" class="hidden">
    <div class="topbar"><div><div class="topbar-title">Settings</div><div class="topbar-sub">Manage your profile, password, and multifactor authentication</div></div></div>
    <div class="content">
      <div class="profile-grid">
        <div>
          <div class="profile-side-card">
            <div class="profile-av">{{ auth()->user()->initials }}</div>
            <div class="profile-name-lg">{{ auth()->user()->full_name }}</div>
            <div class="profile-role-lbl">{{ auth()->user()->role === 'Other' ? 'Member of the Public' : auth()->user()->role }} · Zimbabwe</div>
            <div class="profile-stats">
              <div class="profile-stat"><div class="profile-stat-num">{{ $docCount }}</div><div class="profile-stat-lbl">Docs</div></div>
              <div class="profile-stat"><div class="profile-stat-num">{{ $dlCount }}</div><div class="profile-stat-lbl">Downloads</div></div>
            </div>
          </div>
        </div>
        <div>
          <div class="profile-form-card">
            <div class="panel-title" style="margin-bottom:22px">Profile Settings</div>
            <div class="form-row">
              <div class="form-group"><label class="form-label">First Name</label><input type="text" class="form-input" id="profFirst" value="{{ auth()->user()->first_name }}"></div>
              <div class="form-group"><label class="form-label">Last Name</label><input type="text" class="form-input" id="profLast" value="{{ auth()->user()->last_name }}"></div>
            </div>
            <div class="form-group"><label class="form-label">Email Address</label><input type="email" class="form-input" value="{{ auth()->user()->email }}" readonly style="background:var(--grey-50)"></div>
            <div class="form-group"><label class="form-label">Organisation / Firm</label><input type="text" class="form-input" id="profOrg" value="{{ auth()->user()->organisation }}" placeholder="e.g. Kantor &amp; Immerman"></div>
            <div class="form-group">
              <label class="form-label">Role</label>
              <select class="form-input" id="profRole">
                @foreach([
                  'Legal Professional' => 'Legal Professional',
                  'Law Student' => 'Law Student',
                  'Researcher' => 'Researcher',
                  'Business Owner' => 'Business Owner',
                  'Other' => 'Member of the Public',
                ] as $r => $label)
                  <option value="{{ $r }}" {{ auth()->user()->role === $r ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <button class="btn btn-primary" onclick="saveProfile()">Save Changes</button>
            <div style="margin-top:30px;padding-top:26px;border-top:1px solid var(--grey-100)">
              <div class="panel-title" style="margin-bottom:18px;font-size:16px">Multifactor Authentication</div>
              <div class="info-card" style="margin-bottom:16px">
                <div class="info-card-title">Sign-in Verification</div>
                <div class="passage-text">Require an email verification code when signing in. This uses the same email address on your profile.</div>
              </div>
              <label class="form-label" style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                <input type="checkbox" id="mfaEnabled" {{ auth()->user()->mfa_enabled ? 'checked' : '' }}>
                Enable email multifactor authentication
              </label>
              <div class="form-group">
                <label class="form-label">Verification Method</label>
                <select class="form-input" id="mfaChannel">
                  <option value="email" {{ auth()->user()->mfa_channel === 'email' ? 'selected' : '' }}>Email code</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Backup Security Question</label>
                <input type="text" class="form-input" id="mfaSecurityQuestion" value="{{ auth()->user()->mfa_security_question }}" placeholder="Example: What was your first law firm or school?">
              </div>
              <div class="form-group">
                <label class="form-label">Backup Answer</label>
                <input type="password" class="form-input" id="mfaSecurityAnswer" placeholder="{{ auth()->user()->mfa_security_answer_hash ? 'Leave blank to keep current answer' : 'Set an answer for account recovery' }}">
                <div class="mini-note" style="margin-top:8px">Use an answer only you know. It will be stored securely and can be used if email codes are unavailable.</div>
              </div>
              <button class="btn btn-outline" onclick="saveMfaSettings()">Save MFA Settings</button>
            </div>
            <div style="margin-top:30px;padding-top:26px;border-top:1px solid var(--grey-100)">
              <div class="panel-title" style="margin-bottom:18px;font-size:16px">Password Settings</div>
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" class="form-input" id="profNewPass" placeholder="New password" oninput="checkPwStrengthProf(this.value)">
                <div class="pw-strength" id="profPwStrength"><div class="pw-bar-track"><div class="pw-bar-fill" id="profPwBar"></div></div><div class="pw-label" id="profPwLabel"></div></div>
              </div>
              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" class="form-input" id="profConfPass" placeholder="Repeat new password">
              </div>
              <button class="btn btn-outline" onclick="changePassword()">Update Password</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /app-main -->

<!-- ─── SUMMARY MODAL ─── -->
<div class="modal-backdrop" id="summaryModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Summary</h3>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const IS_LEGAL_PROFESSIONAL = @json($isLegalProfessional);
const ENDPOINTS = {
  upload: @json(route('documents.upload')),
  extract: @json(route('documents.extract')),
  summarise: @json(route('documents.summarise')),
  records: @json(route('records')),
  downloads: @json(route('downloads')),
  downloadsLog: @json(route('downloads.log')),
  profile: @json(route('profile.update')),
  profilePassword: @json(route('profile.password')),
  mfa: @json(route('settings.mfa')),
  documentsBase: @json(url('/documents')),
};
const PIPELINE_STEPS = [
  { label: 'Upload stored', threshold: 3 },
  { label: 'Preparing analysis', threshold: 5 },
  { label: 'Preparing text', threshold: 15 },
  { label: 'Running NLP_BART', threshold: 40 },
  { label: 'Saving results', threshold: 80 },
  { label: 'Completed', threshold: 100 },
];

// ══ STATE ══════════════════════════════════
const S = {
  file: null, extractedText: '', currentDocId: null,
  currentSummary: null, records: [], downloads: [],
};

// ══ TOAST ══════════════════════════════════
function toast(msg, type = 'info', dur = 5000) {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = msg + '<button class="toast-close" onclick="this.parentElement.remove()">×</button>';
  c.appendChild(t);
  setTimeout(() => { t.classList.add('dismissing'); setTimeout(() => t.remove(), 400); }, dur);
}

// ══ TAB NAVIGATION ═════════════════════════
const TABS = ['dashboard', 'new-job', 'records', 'downloads', ...(IS_LEGAL_PROFESSIONAL ? ['legal-search', 'clause-extractor'] : []), 'help', 'settings'];

function goTab(name) {
  TABS.forEach(t => {
    document.getElementById('tab-' + t).classList.toggle('hidden', t !== name);
    const nav = document.getElementById('nav-' + t);
    if (nav) nav.classList.toggle('active', t === name);
  });
  closeSidebar();
  if (name === 'records')   loadRecords();
  if (name === 'downloads') loadDownloads();
  if (name === 'legal-search') runLegalSearch(false);
}

// ══ MOBILE SIDEBAR ═════════════════════════
function toggleSidebar() {
  document.getElementById('appSidebar').classList.toggle('open');
  document.getElementById('sbOverlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('appSidebar').classList.remove('open');
  document.getElementById('sbOverlay').classList.remove('show');
}

// ══ FILE UPLOAD ════════════════════════════
function onFileChosen(e) {
  const file = e.target.files[0];
  if (!file) return;
  S.file = file; S.extractedText = ''; S.currentSummary = null; S.currentDocId = null;

  document.getElementById('summaryCard').classList.add('hidden');
  document.getElementById('progressWrap').classList.add('hidden');
  document.getElementById('extractBody').innerHTML = '<div class="extract-placeholder">⏳ Reading document...</div>';
  document.getElementById('fileInfoPanel').style.display = 'block';
  document.getElementById('fileName').textContent = file.name;
  document.getElementById('fileMeta').textContent = fmtSize(file.size) + ' · ' + (file.type || 'document');
  document.getElementById('uploadZone').classList.add('has-file');
  setSumBtn(false, 'extracting');
  setExtractStatus('⏳ Extracting text from document...');

  if (file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) extractPDF(file);
  else if (/\.(doc|docx)$/i.test(file.name)) extractWordDocument(file);
  else extractText(file);
}

function fmtSize(b) {
  if (b < 1024) return b + ' B';
  if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
  return (b / (1024 * 1024)).toFixed(1) + ' MB';
}

function setSumBtn(enabled, state) {
  const btn = document.getElementById('summariseBtn');
  btn.disabled = !enabled;
  const icons = {
    extracting: `<svg class="spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Extracting...`,
    ready:      `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Generate Summary`,
    working:    `<svg class="spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Generating Summary...`,
    failed:     `Extraction Failed`,
  };
  btn.innerHTML = icons[state] || icons.ready;
}

function setExtractStatus(msg, ok) {
  const el = document.getElementById('extractStatus');
  el.textContent = msg;
  el.style.background = ok === false ? '#fde8e8' : 'var(--green-pale)';
  el.style.color      = ok === false ? '#c0392b' : 'var(--green-deep)';
}

async function extractPDF(file) {
  try {
    const buf = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
    const total = pdf.numPages;
    let txt = '';
    for (let i = 1; i <= total; i++) {
      setExtractStatus('📄 Reading page ' + i + ' of ' + total + '...');
      const page = await pdf.getPage(i);
      const content = await page.getTextContent();
      txt += '\n─── PAGE ' + i + ' ───\n\n' + buildPdfPageText(content.items) + '\n';
    }
    S.extractedText = normaliseExtractedText(txt.trim());
    showExtractedText(S.extractedText);
    onExtractDone(total + ' pages', true);
  } catch (err) {
    document.getElementById('extractBody').innerHTML = '<div class="extract-placeholder" style="color:#e74c3c">⚠️ Could not extract PDF text. Try a text-based PDF.</div>';
    onExtractDone(null, false);
    toast('PDF extraction failed. Try a different file.', 'error', 6000);
  }
}

function extractText(file) {
  const r = new FileReader();
  r.onload = e => {
    S.extractedText = normaliseExtractedText(e.target.result || '');
    showExtractedText(S.extractedText);
    onExtractDone('text file', true);
  };
  r.onerror = () => { onExtractDone(null, false); toast('Could not read file.', 'error', 5000); };
  r.readAsText(file);
}

async function extractWordDocument(file) {
  try {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_token', CSRF);

    const res = await fetch(ENDPOINTS.extract, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: fd,
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Word document extraction failed.');
    }

    S.extractedText = normaliseExtractedText(data.text || '');
    showExtractedText(S.extractedText);
    onExtractDone((data.word_count || S.extractedText.trim().split(/\s+/).filter(Boolean).length).toLocaleString() + ' words', true);
  } catch (err) {
    document.getElementById('extractBody').innerHTML = '<div class="extract-placeholder" style="color:#e74c3c">Could not extract text from this Word document.</div>';
    onExtractDone(null, false);
    toast(err.message || 'Word document extraction failed.', 'error', 6000);
  }
}

function onExtractDone(info, success) {
  if (success) { setSumBtn(true, 'ready'); setExtractStatus('✅ Ready to analyse — ' + info, true); }
  else { setSumBtn(false, 'failed'); setExtractStatus('⚠️ Extraction failed', false); }
}

function showExtractedText(txt) {
  const words = txt.trim().split(/\s+/).filter(Boolean).length;
  document.getElementById('wordCount').textContent = words.toLocaleString() + ' words';
  document.getElementById('extractBody').textContent = txt || 'No text found.';
}

function buildPdfPageText(items) {
  const lines = [];

  for (const item of items || []) {
    const text = String(item.str || '').replace(/\s+/g, ' ').trim();
    if (!text) continue;

    const x = Number(item.transform?.[4] || 0);
    const y = Number(item.transform?.[5] || 0);
    const height = Math.abs(Number(item.height || item.transform?.[0] || 0)) || 10;
    const width = Number(item.width || 0);
    const tolerance = Math.max(2.5, height * 0.35);

    let line = lines.find(entry => Math.abs(entry.y - y) <= tolerance);
    if (!line) {
      line = { y, items: [], height };
      lines.push(line);
    }

    line.height = Math.max(line.height, height);
    line.items.push({ text, x, width });
  }

  lines.sort((a, b) => b.y - a.y || (a.items[0]?.x || 0) - (b.items[0]?.x || 0));

  const renderedLines = lines
    .map(line => ({
      y: line.y,
      height: line.height,
      text: renderPdfLine(line.items),
    }))
    .filter(line => line.text);

  return linesToParagraphs(renderedLines);
}

function renderPdfLine(items) {
  const sorted = [...items].sort((a, b) => a.x - b.x);
  let line = '';
  let lastEnd = null;

  for (const item of sorted) {
    const gap = lastEnd === null ? 0 : item.x - lastEnd;
    const startsWithPunctuation = /^[,.;:!?)}\]]/.test(item.text);
    const startsWithQuote = /^["'“”‘’]/.test(item.text);
    const lineEndsWithOpen = /[-/(\["“‘]$/.test(line);
    const needsSpace = line !== '' && gap > 2.5 && !lineEndsWithOpen && !startsWithPunctuation && !startsWithQuote;
    line += (needsSpace ? ' ' : '') + item.text;
    lastEnd = item.x + Math.max(item.width, item.text.length * 3.2);
  }

  return cleanExtractedLine(line);
}

function linesToParagraphs(lines) {
  const paragraphs = [];
  let current = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    current.push(line.text);

    const next = lines[i + 1];
    if (!next) break;

    const gap = line.y - next.y;
    const paragraphBreak = gap > Math.max(line.height * 1.45, 12);
    if (paragraphBreak) {
      paragraphs.push(joinParagraphLines(current));
      current = [];
    }
  }

  if (current.length) {
    paragraphs.push(joinParagraphLines(current));
  }

  return paragraphs
    .map(paragraph => paragraph.trim())
    .filter(Boolean)
    .join('\n\n');
}

function joinParagraphLines(lines) {
  let paragraph = '';

  for (const rawLine of lines) {
    const line = cleanExtractedLine(rawLine);
    if (!line) continue;

    if (!paragraph) {
      paragraph = line;
      continue;
    }

    if (/-$/.test(paragraph)) {
      paragraph = paragraph.replace(/-$/, '') + line.replace(/^\s+/, '');
      continue;
    }

    const preserveLineBreak = isDisplayLine(paragraph) || isDisplayLine(line) || endsWithQuoteCue(paragraph);
    paragraph += preserveLineBreak ? '\n' + line : ' ' + line;
  }

  return cleanExtractedLine(paragraph);
}

function cleanExtractedLine(text) {
  return String(text || '')
    .replace(/\s+([,.;:!?])/g, '$1')
    .replace(/(["'”’])\s+([,.;:!?])/g, '$1$2')
    .replace(/([“‘])\s+/g, '$1')
    .replace(/\(\s+/g, '(')
    .replace(/\s+\)/g, ')')
    .replace(/[ \t]{2,}/g, ' ')
    .trim();
}

function normaliseExtractedText(text) {
  return String(text || '')
    .replace(/\r\n?/g, '\n')
    .replace(/\u00ad/g, '')
    .replace(/[“”]/g, '"')
    .replace(/[‘’]/g, "'")
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n[ \t]+/g, '\n')
    .replace(/[ ]{2,}/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

function isDisplayLine(line) {
  const trimmed = String(line || '').trim();
  return trimmed !== '' && (
    /^[A-Z0-9 ,.'&()/-]{4,}$/.test(trimmed) ||
    /^["']/.test(trimmed) ||
    /^[•*-]\s+/.test(trimmed)
  );
}

function endsWithQuoteCue(line) {
  return /[:;]["']?$/.test(String(line || '').trim());
}

// ══ ANALYSE / SUMMARISE ════════════════════
async function doSummarise() {
  if (!S.extractedText || S.extractedText.trim().length < 20) {
    toast('No text available. Wait for extraction.', 'error'); return;
  }
  setSumBtn(false, 'working');
  document.getElementById('summaryCard').classList.add('hidden');

  const pw = document.getElementById('progressWrap');
  pw.classList.remove('hidden');
  pw.classList.remove('complete');
  updateProgressUi({ progress: 3, step: 'Uploading source file...' });

  try {
    const fd = new FormData();
    fd.append('file', S.file);
    fd.append('_token', CSRF);
    const upRes = await fetch(ENDPOINTS.upload, { method: 'POST', body: fd });
    const upData = await upRes.json();
    if (!upData.success) throw new Error('Upload failed');
    S.currentDocId = upData.document_id;

    const words = S.extractedText.trim().split(/\s+/).length;
    const pages = (S.extractedText.match(/─── PAGE \d+ ───/g) || []).length || null;

    updateProgressUi({ progress: 5, step: 'Preparing direct analysis...' });
    const sumRes = await fetch(ENDPOINTS.summarise, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify({
        document_id: S.currentDocId,
        text: S.extractedText,
        word_count: words,
        page_count: pages,
      }),
    });
    const sumData = await sumRes.json();
    if (!sumData.success) throw new Error(sumData.message || 'Analysis failed');

    let statusData = sumData;
    if (!sumData.summary && sumData.document_id) {
      updateProgressUi({ progress: 10, step: 'Waiting for analysis result...' });
      statusData = await pollAnalysis(sumData.document_id);
    }
    if (!statusData.summary) throw new Error('No summary was returned.');

    S.currentSummary = statusData.summary;
    updateProgressUi({ progress: 100, step: 'Summary completed.' });
    pw.classList.add('complete');
    renderSummary(statusData.summary);
    setSumBtn(true, 'ready');
    toast('Summary completed.', 'success', 4000);
    setTimeout(() => pw.classList.add('hidden'), 1400);

    document.getElementById('recBadge').textContent = parseInt(document.getElementById('recBadge').textContent || '0') + 1;
    document.getElementById('sdDocs').textContent   = parseInt(document.getElementById('sdDocs').textContent || '0') + 1;
  } catch (err) {
    pw.classList.add('hidden');
    pw.classList.remove('complete');
    toast('Analysis failed: ' + err.message, 'error', 6000);
    setSumBtn(true, 'ready');
  }
}

async function pollAnalysis(documentId) {
  for (let attempt = 0; attempt < 180; attempt++) {
    const res = await fetch(`${ENDPOINTS.documentsBase}/${documentId}/status`, { headers: { Accept: 'application/json' } });
    const data = await res.json();

    if (!data.success) {
      throw new Error('Could not read analysis progress.');
    }

    updateProgressUi(data.progress || { progress: 10, step: 'Waiting for analysis result...' });

    if (data.status === 'done' && data.summary) {
      updateProgressUi({ progress: 100, step: 'Analysis complete.' });
      return data;
    }

    if (data.status === 'failed') {
      throw new Error(data.progress?.message || 'Analysis failed.');
    }

    await new Promise(resolve => setTimeout(resolve, 1500));
  }

  throw new Error('Analysis timed out before a summary was returned.');
}

function updateProgressUi(progress) {
  const value = Math.max(0, Math.min(progress?.progress ?? 0, 100));
  document.getElementById('progressLabel').textContent = progress?.step || 'Working...';
  document.getElementById('progressPercent').textContent = Math.round(value) + '%';
  document.getElementById('progressRing').style.background = `conic-gradient(var(--green-bright) ${value}%, var(--grey-100) 0)`;
  document.getElementById('progressStatus').textContent = value >= 100
    ? 'Summary completed. Moving you to the result now.'
    : 'Keep this page open while JustConnect analyses the document.';
  document.getElementById('progressSteps').innerHTML = PIPELINE_STEPS
    .map(step => `<div class="prog-step ${value >= step.threshold ? 'done' : ''}">${step.label}</div>`)
    .join('');
}

// ══ RENDER SUMMARY ═════════════════════════
function renderSummary(s) {
  const card = document.getElementById('summaryCard');
  card.classList.remove('hidden');
  document.getElementById('summaryDocName').textContent = S.file?.name || 'Document';

  const entities = normaliseEntityList(s.entities_involved);
  const resultCards = Array.isArray(s.result_cards) ? s.result_cards : [];
  const panels = s.structured_panels || {};
  const clauses = Array.isArray(s.clauses) ? s.clauses : (Array.isArray(panels.key_clauses) ? panels.key_clauses : []);
  const executiveSummary = s.executive_summary || s.summary || s.professional_summary || '--';
  const professionalSummary = s.professional_summary || executiveSummary;
  const citizenSummary = s.citizen_summary || executiveSummary;
  const support = s.supporting_evidence || {};
  const urgency = (support.legal_risk?.urgency || 'low').toLowerCase();

  document.getElementById('summaryBody').innerHTML = `
    <div class="insight-layout">
      <div class="insight-stack">
        ${renderResultCards(resultCards)}
        <div class="insight-panel soft">
          <div class="insight-panel-head">
            <div>
              <div class="insight-panel-title">Dual Summary System</div>
              <div class="insight-panel-sub">Professional legal analysis and plain-English explanation</div>
            </div>
            <div class="summary-switch">
              <button class="summary-pill" type="button" data-summary-switch="professional" onclick="switchSummaryMode('professional')">Professional</button>
              <button class="summary-pill active" type="button" data-summary-switch="citizen" onclick="switchSummaryMode('citizen')">Plain English</button>
            </div>
          </div>
          <div class="insight-panel-body">
            <div class="summary-copy" id="summaryCopyProfessional" style="display:none">${esc(professionalSummary)}</div>
            <div class="summary-copy" id="summaryCopyCitizen">${esc(citizenSummary)}</div>
          </div>
        </div>
        <div class="panel-grid">
          <div class="info-card">
            <div class="info-card-title">Case Information</div>
            <div class="info-kv">
              ${infoRow('Court', panels.case_information?.Court || s.court || '—')}
              ${infoRow('Judge', panels.case_information?.Judge || s.judge || '—')}
              ${infoRow('Case Number', panels.case_information?.['Case Number'] || s.case_number || '—')}
              ${infoRow('Date', panels.case_information?.Date || s.date_of_document || '—')}
            </div>
          </div>
          <div class="info-card">
            <div class="info-card-title">People & Organisations</div>
            <div class="chip-cloud">${chipList(panels.people_and_organisations || entities, 'blue')}</div>
          </div>
        </div>
        <div class="panel-grid">
          <div class="info-card">
            <div class="info-card-title">Key Legal Issues</div>
            <div class="chip-cloud">${chipList(panels.key_legal_issues || [], '')}</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Important Legal References</div>
            <div class="chip-cloud">${chipList(panels.important_legal_references || [], 'gold')}</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Constitutional Rights Affected</div>
            <div class="chip-cloud">${chipList(panels.constitutional_rights_affected || [], 'blue')}</div>
          </div>
        </div>
        <div class="insight-panel">
          <div class="insight-panel-head">
            <div>
              <div class="insight-panel-title">Key Clauses</div>
              <div class="insight-panel-sub">Important clauses and operative sections extracted from the source text</div>
            </div>
          </div>
          <div class="insight-panel-body">
            <div class="passage-list">${renderClauses(clauses)}</div>
          </div>
        </div>
        <div class="insight-panel">
          <div class="insight-panel-head">
            <div>
              <div class="insight-panel-title">Supporting Passages</div>
              <div class="insight-panel-sub">Grounding excerpts linked to the summary and legal issues</div>
            </div>
          </div>
          <div class="insight-panel-body">
            <div class="passage-list">${renderPassages(s.supporting_passages || [])}</div>
          </div>
        </div>
        <div class="insight-panel">
          <div class="insight-panel-head">
            <div>
              <div class="insight-panel-title">Evidence & Risk</div>
              <div class="insight-panel-sub">Source-linked findings and action cues</div>
            </div>
          </div>
          <div class="insight-panel-body">
            <div class="risk-badges">
              <span class="risk-badge ${urgency}">Urgency: ${esc((support.legal_risk?.urgency || 'low').toUpperCase())}</span>
              <span class="law-chip">${esc(s.document_type || 'Legal Document')}</span>
            </div>
            <div class="evidence-grid">
              ${renderEvidenceBlock('Outcome evidence', support.evidence?.outcome || [])}
              ${renderEvidenceBlock('Finding evidence', support.evidence?.findings || [])}
              ${renderSimpleListBlock('Risks detected', support.legal_risk?.risky_clauses || [], 'text')}
              ${renderSimpleListBlock('Deadlines', support.legal_risk?.deadlines || [], 'text')}
              ${renderSimpleListBlock('Recommended actions', support.legal_risk?.recommended_actions || [], null)}
              ${renderSimpleListBlock('Missing information', support.legal_risk?.missing_information || [], null)}
            </div>
          </div>
        </div>
      </div>
      <div class="insight-stack">
        <div class="insight-panel">
          <div class="insight-panel-head">
            <div>
              <div class="insight-panel-title">Original Text</div>
              <div class="insight-panel-sub">Side-by-side source material for verification</div>
            </div>
          </div>
          <div class="insight-panel-body">
            <div class="source-pane">${esc(s.original_text || 'No extracted text available.')}</div>
          </div>
        </div>
      </div>
    </div>`;

  card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

renderSummary = function(s) {
  const card = document.getElementById('summaryCard');
  card.classList.remove('hidden');

  const documentName = S.file?.name || s.document_name || 'Document';
  document.getElementById('summaryDocName').textContent = documentName;

  const emailBtn = document.getElementById('emailSummaryBtn');
  if (emailBtn) {
    emailBtn.disabled = !s.id;
    emailBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>Email me';
  }

  const panels = s.structured_panels || {};
  const executiveSummary = s.executive_summary || s.summary || s.professional_summary || '--';
  const professionalSummary = s.professional_summary || executiveSummary;
  const citizenSummary = s.citizen_summary || executiveSummary;
  const entities = normaliseEntityList(s.entities_involved);
  const support = s.supporting_evidence || {};
  const urgency = (support.legal_risk?.urgency || 'low').toLowerCase();
  const findings = listFromValue(s.key_findings).concat(resultCardItems(s.result_cards)).slice(0, 6);
  const obligations = listFromValue(s.key_obligations).concat(listFromValue(s.practical_implications)).slice(0, 6);
  const risks = listFromValue(support.legal_risk?.risky_clauses, 'text')
    .concat(listFromValue(support.legal_risk?.deadlines, 'text'))
    .slice(0, 6);
  const summaryType = s.summary_type || 'general_user';
  const isLegalProfessionalSummary = summaryType === 'legal_professional';
  const modeSections = panels.mode_sections || {};

  document.getElementById('summaryBody').innerHTML = `
    <div class="summary-simple">
      <div class="summary-complete-banner">
        <div>Summary completed</div>
        <span>${esc(documentName)}</span>
      </div>

      ${modeSummaryHtml(summaryType, modeSections, {
        citizenSummary,
        professionalSummary,
        findings,
        obligations,
        panels,
        s,
      })}

      ${isLegalProfessionalSummary ? citedInstrumentsHtml(panels.cited_instruments || []) : ''}

      <div class="summary-simple-card">
        <div class="summary-simple-title">Document Details</div>
        <div class="summary-meta-grid" style="margin-top:12px">
          ${summaryMeta('Type', s.document_type || 'Legal Document')}
          ${summaryMeta('Court', panels.case_information?.Court || s.court || 'Not stated')}
          ${summaryMeta('Judge', panels.case_information?.Judge || s.judge || 'Not stated')}
          ${summaryMeta('Case Number', panels.case_information?.['Case Number'] || s.case_number || 'Not stated')}
          ${summaryMeta('Date', panels.case_information?.Date || s.date_of_document || 'Not stated')}
          ${summaryMeta('Risk', urgency.toUpperCase())}
        </div>
      </div>

      ${isLegalProfessionalSummary ? `<div class="summary-simple-card">
        <div class="summary-simple-title">Issues, People And References</div>
        <div class="chip-cloud" style="margin-top:12px">
          ${chipList(panels.key_legal_issues || [], '')}
          ${chipList(panels.cited_instruments || [], 'gold')}
          ${chipList(entities, 'blue')}
          ${chipList(panels.important_legal_references || [], 'gold')}
          ${chipList(panels.constitutional_rights_affected || [], 'blue')}
        </div>
      </div>` : ''}

      ${isLegalProfessionalSummary ? `<div class="summary-two-col">
        <div class="summary-simple-card">
          <div class="summary-simple-title">Supporting Passages</div>
          <div class="passage-list" style="margin-top:12px">${renderPassages((s.supporting_passages || []).slice(0, 3))}</div>
        </div>
        <div class="summary-simple-card">
          <div class="summary-simple-title">Risks And Deadlines</div>
          ${simpleList(risks, 'No urgent risks or deadlines were detected.')}
        </div>
      </div>` : `<div class="summary-simple-card">
        <div class="summary-simple-title">Risks And Deadlines</div>
        ${simpleList(risks, 'No urgent risks or deadlines were detected.')}
      </div>`}

      <details class="summary-source-details">
        <summary>View extracted source text</summary>
        <div class="source-pane">${esc(s.original_text || 'No extracted text available.')}</div>
      </details>
    </div>`;

  card.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

function summaryMeta(label, value) {
  return `<div class="summary-meta-item"><div class="summary-meta-label">${esc(label)}</div><div class="summary-meta-value">${esc(value || 'Not stated')}</div></div>`;
}

function modeSummaryHtml(summaryType, sections, fallback) {
  if (summaryType === 'legal_professional') {
    const legalSections = {
      'Document Type': sections['Document Type'] || fallback.s.document_type || 'Legal Document',
      'Citation / Court Details': sections['Citation / Court Details'] || [fallback.s.case_number, fallback.s.court, fallback.s.judge, fallback.s.date_of_document].filter(Boolean).join(' | '),
      'Facts': sections.Facts || fallback.professionalSummary,
      'Legal Issues': sections['Legal Issues'] || fallback.panels.key_legal_issues || [],
      'Holding / Decision': sections['Holding / Decision'] || fallback.s.outcome,
      'Ratio Decidendi': sections['Ratio Decidendi'] || fallback.s.legal_principles,
      'Orders / Remedies': sections['Orders / Remedies'] || fallback.obligations,
      'Authorities Cited': sections['Authorities Cited'] || fallback.panels.important_legal_references || [],
      'Cited Instruments': sections['Cited Instruments'] || fallback.panels.cited_instruments || [],
    };

    return `<div class="summary-simple-card">
      <div class="summary-simple-head">
        <div>
          <div class="summary-simple-title">Legal Professional Summary</div>
          <div class="summary-simple-sub">Formal legal analysis for legal professionals and students.</div>
        </div>
      </div>
      <div class="summary-mode-grid">${sectionCards(legalSections)}</div>
    </div>`;
  }

  const generalSections = {
    'What Happened': sections['What Happened'] || fallback.citizenSummary,
    'Main Issue': sections['Main Issue'] || fallback.panels.key_legal_issues || fallback.findings,
    'Decision / Outcome': sections['Decision / Outcome'] || fallback.s.outcome,
    'What This Means': sections['What This Means'] || fallback.s.practical_implications,
  };

  return `<div class="summary-simple-card">
    <div class="summary-simple-head">
      <div>
        <div class="summary-simple-title">General User Summary</div>
        <div class="summary-simple-sub">Simple language for ordinary citizens and non-lawyers.</div>
      </div>
    </div>
    <div class="summary-mode-grid">${sectionCards(generalSections)}</div>
  </div>`;
}

function citedInstrumentsHtml(instruments) {
  const list = Array.isArray(instruments) ? instruments.filter(Boolean) : [];
  return `<div class="summary-simple-card">
    <div class="summary-simple-head">
      <div>
        <div class="summary-simple-title">Cited Instruments</div>
        <div class="summary-simple-sub">Laws, regulations, rules, codes, and statutory instruments cited in the document.</div>
      </div>
    </div>
    ${simpleList(list, 'No cited laws or regulations were confidently detected.')}
  </div>`;
}

function sectionCards(sections) {
  return Object.entries(sections).map(([title, value]) => {
    const body = Array.isArray(value)
      ? simpleList(value, 'Not clearly stated.')
      : `<div class="summary-copy">${esc(value || 'Not clearly stated.')}</div>`;

    return `<div class="summary-section-card">
      <div class="summary-simple-title">${esc(title)}</div>
      <div style="margin-top:10px">${body}</div>
    </div>`;
  }).join('');
}

function simpleList(items, emptyText) {
  const list = Array.isArray(items) ? items.filter(Boolean) : [];
  if (!list.length) return `<div class="mini-note" style="margin-top:12px">${esc(emptyText)}</div>`;
  return `<ul class="summary-list" style="margin-top:12px">${list.map(item => `<li>${esc(item)}</li>`).join('')}</ul>`;
}

function listFromValue(value, field = null) {
  if (!value) return [];
  if (Array.isArray(value)) {
    return value.map(item => {
      if (typeof item === 'string') return item.trim();
      if (field && item && typeof item === 'object') return String(item[field] || '').trim();
      if (item && typeof item === 'object') return String(item.text || item.title || item.value || '').trim();
      return '';
    }).filter(Boolean);
  }
  return String(value)
    .split(/\n+|;\s+/)
    .map(item => item.replace(/^[-*]\s*/, '').trim())
    .filter(Boolean);
}

function resultCardItems(cards) {
  if (!Array.isArray(cards)) return [];
  return cards.flatMap(card => Array.isArray(card.items) ? card.items : []).map(item => String(item).trim()).filter(Boolean);
}

function ki(label, val) {
  return `<div class="ki-item"><div class="ki-label">${label}</div><div class="ki-value">${esc(val || '—')}</div></div>`;
}
function infoRow(label, val) {
  return `<div class="info-kv-row"><div class="info-kv-label">${esc(label)}</div><div class="info-kv-value">${esc(val || '—')}</div></div>`;
}
function normaliseEntityList(value) {
  if (!Array.isArray(value)) return [];
  return value
    .map(item => typeof item === 'string' ? item.trim() : '')
    .filter(Boolean);
}
function switchSummaryMode(mode) {
  const isCitizen = mode === 'citizen';
  document.querySelectorAll('[data-summary-switch]').forEach(btn => btn.classList.toggle('active', btn.dataset.summarySwitch === mode));
  const prof = document.getElementById('summaryCopyProfessional');
  const cit = document.getElementById('summaryCopyCitizen');
  if (prof) prof.style.display = isCitizen ? 'none' : 'block';
  if (cit) cit.style.display = isCitizen ? 'block' : 'none';
}
function renderResultCards(cards) {
  if (!cards.length) return '';
  return `<div class="insight-panel">
    <div class="insight-panel-head">
      <div><div class="insight-panel-title">Case Result Cards</div><div class="insight-panel-sub">Highlighted operative outcomes and legal effects</div></div>
    </div>
    <div class="insight-panel-body">
      <div class="case-result-grid">
        ${cards.map(card => `<div class="result-card ${esc(card.tone || '')}">
          <div class="result-card-title">${esc(card.title || 'Insight')}</div>
          <div class="result-card-list">
            ${(Array.isArray(card.items) ? card.items : []).map(item => `<div class="result-card-item"><span class="result-card-item-mark">✓</span><span>${esc(item)}</span></div>`).join('')}
          </div>
        </div>`).join('')}
      </div>
    </div>
  </div>`;
}
function chipList(items, tone) {
  const list = Array.isArray(items) ? items.filter(Boolean) : [];
  if (!list.length) return '<div class="mini-note">No clear items detected.</div>';
  return list.map(item => `<span class="law-chip ${tone || ''}">${esc(item)}</span>`).join('');
}
function renderPassages(passages) {
  if (!Array.isArray(passages) || !passages.length) {
    return '<div class="mini-note">No supporting passages were linked.</div>';
  }
  return passages.map(p => `<div class="passage-card">
    <div class="passage-meta">Passage ${esc(p.id || '—')} · Page ${esc(p.page || '—')} · Relevance ${esc(p.score || '0')}</div>
    <div class="passage-text">${esc(p.text || '')}</div>
  </div>`).join('');
}
function renderClauses(clauses) {
  if (!Array.isArray(clauses) || !clauses.length) {
    return '<div class="mini-note">No important clauses were confidently extracted.</div>';
  }
  return clauses.map(clause => `<div class="passage-card">
    <div class="passage-meta">${esc(clause.clause_type || 'GENERAL_CLAUSE')}</div>
    <div class="trace-claim">${esc(clause.heading || 'Clause')}</div>
    <div class="passage-text">${esc(clause.content || '')}</div>
  </div>`).join('');
}
function renderEvidenceBlock(title, items) {
  if (!Array.isArray(items) || !items.length) return '';
  return `<div class="trace-card compact">
    <div class="trace-title">${esc(title)}</div>
    ${items.map(item => `<div style="margin-top:10px">
      ${item.text ? `<div class="trace-claim">${esc(item.text)}</div>` : ''}
      ${item.finding ? `<div class="trace-claim">${esc(item.finding)}</div>` : ''}
      ${item.quote ? `<div class="trace-quote">${esc(item.quote)}</div>` : ''}
      <div class="mini-note">${esc(item.reason || '')}${item.page ? ` · page ${esc(item.page)}` : ''}</div>
    </div>`).join('')}
  </div>`;
}
function renderSimpleListBlock(title, items, field) {
  if (!Array.isArray(items) || !items.length) return '';
  const listItems = items.map(item => {
    const value = field ? item?.[field] : item;
    const suffix = field && item?.page ? ` (page ${item.page})` : '';
    return `<li>${esc(String(value || ''))}${suffix}</li>`;
  }).join('');
  return `<div class="risk-block">
    <div class="risk-title">${esc(title)}</div>
    <ul class="risk-list">${listItems}</ul>
  </div>`;
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ══ RECORDS ════════════════════════════════
async function runLegalSearch(showEmptyToast = true) {
  if (!IS_LEGAL_PROFESSIONAL) return;
  const input = document.getElementById('legalSearchInput');
  const resultsEl = document.getElementById('legalSearchResults');
  const countEl = document.getElementById('legalSearchCount');
  if (!input || !resultsEl || !countEl) return;

  const query = input.value.trim();
  if (!query) {
    countEl.textContent = 'Enter a query to search your library';
    resultsEl.innerHTML = '<div class="empty-state">Search results will appear here.</div>';
    if (showEmptyToast) toast('Enter a search query.', 'error');
    return;
  }

  resultsEl.innerHTML = '<div class="loading-state">Searching saved summaries...</div>';
  try {
    const params = new URLSearchParams({ search: query });
    const res = await fetch(`${ENDPOINTS.records}?${params.toString()}`, { headers: { Accept: 'application/json' } });
    renderLegalSearchResults(await res.json(), query);
  } catch {
    countEl.textContent = 'Search failed';
    resultsEl.innerHTML = '<div class="empty-state">Could not run legal search.</div>';
  }
}

function quickLegalSearch(query) {
  const input = document.getElementById('legalSearchInput');
  if (!input) return;
  input.value = query;
  runLegalSearch();
}

function renderLegalSearchResults(list, query) {
  list = Array.isArray(list) ? list : [];
  const resultsEl = document.getElementById('legalSearchResults');
  const countEl = document.getElementById('legalSearchCount');
  countEl.textContent = `${list.length} result${list.length === 1 ? '' : 's'} for "${query}"`;

  if (!list.length) {
    resultsEl.innerHTML = '<div class="empty-state">No matching summaries found.</div>';
    return;
  }

  resultsEl.innerHTML = `<div class="legal-result-list">${list.map(s => `
    <div class="legal-result-card">
      <div class="legal-result-main">
        <div class="legal-result-title">${esc(s.document_name || 'Document')}</div>
        <div class="legal-result-meta">${esc(s.document_type || 'Legal Document')}${s.court ? ' · ' + esc(s.court) : ''}${s.created_at ? ' · ' + esc(s.created_at) : ''}</div>
        <div class="legal-result-snippet">${esc(searchSnippet(s, query))}</div>
        ${Array.isArray(s.structured_panels?.key_legal_issues) ? `<div class="record-issue-cloud">${s.structured_panels.key_legal_issues.slice(0, 4).map(issue => `<span class="law-chip">${esc(issue)}</span>`).join('')}</div>` : ''}
      </div>
      <div class="legal-result-actions">
        <button class="btn btn-ghost btn-sm" onclick="openSummaryModal(${s.id})">View</button>
        <button class="btn btn-yellow btn-sm" onclick="downloadExportById(${s.id},'pdf')">PDF</button>
      </div>
    </div>`).join('')}</div>`;
}

function searchSnippet(summary, query) {
  const haystacks = [summary.professional_summary, summary.executive_summary, summary.summary, summary.citizen_summary, summary.outcome, summary.legal_principles].filter(Boolean).map(String);
  const found = haystacks.find(text => text.toLowerCase().includes(query.toLowerCase())) || haystacks[0] || '';
  if (!found) return 'No preview available.';
  const index = Math.max(0, found.toLowerCase().indexOf(query.toLowerCase()));
  return found.slice(Math.max(0, index - 110), Math.max(0, index - 110) + 260).trim();
}

async function onClauseFileChosen(event) {
  if (!IS_LEGAL_PROFESSIONAL) return;
  const file = event.target.files?.[0];
  const status = document.getElementById('clauseUploadStatus');
  const input = document.getElementById('clauseInput');
  if (!file || !status || !input) return;

  if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
    status.textContent = 'Please upload a PDF file for clause extraction.';
    toast('Clause extractor currently accepts PDF uploads only.', 'error');
    event.target.value = '';
    return;
  }

  status.textContent = 'Reading PDF...';
  input.value = '';
  try {
    const extracted = await extractClausePdfText(file, (page, total) => {
      status.textContent = 'Reading PDF page ' + page + ' of ' + total + '...';
    });
    input.value = normaliseExtractedText(extracted.text);
    status.textContent = 'PDF text extracted from ' + extracted.pages + ' page' + (extracted.pages === 1 ? '' : 's') + '.';
    extractClauses();
  } catch (error) {
    status.textContent = 'Could not extract text from this PDF. Try a text-based PDF.';
    toast('PDF text extraction failed.', 'error', 6000);
  } finally {
    event.target.value = '';
  }
}

async function extractClausePdfText(file, onPage) {
  const buf = await file.arrayBuffer();
  const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
  const total = pdf.numPages;
  let text = '';

  for (let pageNo = 1; pageNo <= total; pageNo++) {
    if (typeof onPage === 'function') onPage(pageNo, total);
    const page = await pdf.getPage(pageNo);
    const content = await page.getTextContent();
    text += '\n--- PAGE ' + pageNo + ' ---\n\n' + buildPdfPageText(content.items) + '\n';
  }

  return { text: text.trim(), pages: total };
}

function useCurrentExtractedText() {
  if (!IS_LEGAL_PROFESSIONAL) return;
  const input = document.getElementById('clauseInput');
  if (!input) return;
  if (!S.extractedText) {
    toast('Upload and extract a document first, or paste text manually.', 'error');
    return;
  }
  input.value = S.extractedText;
  extractClauses();
}

function extractClauses() {
  if (!IS_LEGAL_PROFESSIONAL) return;
  const input = document.getElementById('clauseInput');
  const output = document.getElementById('clauseOutput');
  const count = document.getElementById('clauseCount');
  if (!input || !output || !count) return;

  const text = input.value.trim();
  if (text.length < 20) {
    count.textContent = 'No clauses extracted yet';
    output.innerHTML = '<div class="empty-state">Paste more legal text to extract clauses.</div>';
    return;
  }

  const clauses = detectClauses(text);
  count.textContent = `${clauses.length} clause${clauses.length === 1 ? '' : 's'} extracted`;
  output.innerHTML = clauses.length
    ? `<div class="passage-list">${renderClauses(clauses)}</div>`
    : '<div class="empty-state">No clear clause headings were detected.</div>';
}

function detectClauses(text) {
  const lines = text.replace(/\r/g, '').split('\n').map(line => line.trim()).filter(Boolean);
  const clauses = [];
  let current = null;
  const headingPattern = /^((section|clause|article|part|chapter|schedule)\s+)?\d+[A-Z]?(\.\d+)*\s*[-.)]?\s+.{3,160}$|^(definitions?|interpretation|orders?|remedies|obligations?|termination|confidentiality|liability|indemnity|jurisdiction|governing law|payment|salary|duties|powers|offences|penalties)\b.*$/i;

  for (const line of lines) {
    const isHeading = headingPattern.test(line) || (/^[A-Z][A-Z\s,()/&-]{8,}$/.test(line) && line.length < 140);
    if (isHeading) {
      if (current && current.content.length) clauses.push(current);
      current = { clause_type: classifyClause(line), heading: line, content: '' };
      continue;
    }
    if (current) current.content += (current.content ? ' ' : '') + line;
  }
  if (current && current.content.length) clauses.push(current);

  return clauses.filter(clause => clause.content.length > 20).slice(0, 24);
}

function classifyClause(heading) {
  const h = heading.toLowerCase();
  if (/definition|interpretation/.test(h)) return 'DEFINITION';
  if (/order|remed/.test(h)) return 'ORDER_OR_REMEDY';
  if (/obligation|duty|duties|powers/.test(h)) return 'DUTY_OR_POWER';
  if (/termination|liability|indemnity|confidentiality/.test(h)) return 'CONTRACT_CLAUSE';
  if (/offence|penalt|fine|sentence/.test(h)) return 'OFFENCE_OR_PENALTY';
  if (/jurisdiction|governing law|court/.test(h)) return 'JURISDICTION';
  return 'GENERAL_CLAUSE';
}

async function loadRecords() {
  try {
    const params = new URLSearchParams();
    const search = document.getElementById('recSearch')?.value?.trim();
    const type = document.getElementById('recFilter')?.value;
    const court = document.getElementById('recCourt')?.value;
    const issue = document.getElementById('recIssue')?.value;
    if (search) params.set('search', search);
    if (type) params.set('type', type);
    if (court) params.set('court', court);
    if (issue) params.set('issue', issue);
    const url = params.toString() ? `${ENDPOINTS.records}?${params.toString()}` : ENDPOINTS.records;
    const res  = await fetch(url, { headers: { Accept: 'application/json' } });
    S.records  = await res.json();
    renderRecords(S.records);
  } catch { document.getElementById('recTable').innerHTML = '<div class="empty-state">Could not load records.</div>'; }
}

function renderRecords(list) {
  const el = document.getElementById('recTable');
  document.getElementById('recCount').textContent = list.length + ' document' + (list.length !== 1 ? 's' : '');
  if (!list.length) {
    el.innerHTML = '<div class="empty-state"><div class="empty-icon">📂</div><div class="empty-title">No documents yet</div><div class="empty-sub">Upload your first document to begin</div></div>';
    return;
  }
  el.innerHTML = `<table class="rec-table"><thead><tr><th>Document</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead><tbody>
  ${list.map(s => `<tr>
    <td><strong>${esc(s.document_name || '—')}</strong></td>
    <td><span class="type-badge ${catBadge(s.document_type)}">${esc(s.document_type || 'Unknown')}</span></td>
    <td>${s.created_at || '—'}</td>
    <td>
      <button class="tbl-btn" onclick='openSummaryModal(${s.id})'>View</button>
      <button class="tbl-btn" onclick='downloadPDFById(${s.id})'>PDF</button>
      <button class="tbl-btn" onclick='deleteDoc(${s.document_id},${s.id})' style="color:#e74c3c">Delete</button>
    </td>
  </tr>`).join('')}</tbody></table>`;
}

function filterRecs() {
  const q  = document.getElementById('recSearch').value.toLowerCase();
  const tp = document.getElementById('recFilter').value.toLowerCase();
  const filtered = S.records.filter(s => {
    const nameMatch = (s.document_name || '').toLowerCase().includes(q) || (s.document_type || '').toLowerCase().includes(q);
    const typeMatch = !tp || (s.document_type || '').toLowerCase().includes(tp);
    return nameMatch && typeMatch;
  });
  renderRecords(filtered);
}

function catBadge(type) {
  if (!type) return 'other';
  const t = type.toLowerCase();
  if (/contract|agreement|lease/.test(t)) return 'contract';
  if (/judgment|court|ruling/.test(t))    return 'judgment';
  if (/act|regulation|statute/.test(t))   return 'act';
  return 'other';
}

async function deleteDoc(docId, sumId) {
  if (!confirm('Delete this document and its analysis?')) return;
  try {
    await fetch(ENDPOINTS.documentsBase + '/' + docId, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
    S.records = S.records.filter(r => r.id !== sumId);
    renderRecords(S.records);
    toast('Document deleted.', 'info');
    document.getElementById('recBadge').textContent = Math.max(0, parseInt(document.getElementById('recBadge').textContent || '1') - 1);
  } catch { toast('Could not delete document.', 'error'); }
}

// ══ DOWNLOADS ══════════════════════════════
async function loadDownloads() {
  try {
    const res = await fetch(ENDPOINTS.downloads, { headers: { Accept: 'application/json' } });
    S.downloads = await res.json();
    renderDownloads(S.downloads);
  } catch { document.getElementById('dlContainer').innerHTML = '<div class="empty-state">Could not load downloads.</div>'; }
}

function renderDownloads(list) {
  const el = document.getElementById('dlContainer');
  if (!list.length) {
    el.innerHTML = '<div class="empty-state"><div class="empty-icon">📥</div><div class="empty-title">No downloads yet</div><div class="empty-sub">Download PDF summaries from the Records tab</div></div>';
    return;
  }
  el.innerHTML = list.map(d => `
    <div class="dl-card">
      <div class="dl-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round" width="22" height="22"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
      <div class="dl-info">
        <div class="dl-name">${esc(d.document_name || 'Document')}</div>
        <div class="dl-meta">${esc(d.document_type || 'Legal Document')} · ${d.downloaded_at || ''}</div>
      </div>
      <button class="btn btn-yellow btn-sm" onclick="downloadPDFById(${d.summary_id})">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Re-download
      </button>
    </div>`).join('');
}

// ══ MODAL ══════════════════════════════════
async function openSummaryModal(summaryId) {
  document.getElementById('summaryModal').classList.add('show');
  document.getElementById('modalBody').innerHTML = '<div class="loading-state">Loading analysis...</div>';
  try {
    const res  = await fetch(ENDPOINTS.documentsBase + '/' + summaryId, { headers: { Accept: 'application/json' } });
    const data = await res.json();
    document.getElementById('modalTitle').textContent = data.document_name || 'Summary';
    // Reuse renderSummary but output to modal
    const prev = S.currentSummary; const prevFile = S.file;
    S.currentSummary = data; S.file = { name: data.document_name };
    // Temporarily swap target element
    const orig = document.getElementById('summaryBody');
    const clone = orig.cloneNode(false);
    clone.id = 'summaryBodyModal';
    const card = document.getElementById('summaryCard');
    card.appendChild(clone);
    const origBodyId = document.getElementById('summaryBody').id;
    document.getElementById('summaryBody').id = '_hide';
    clone.id = 'summaryBody';
    renderSummary(data);
    document.getElementById('modalBody').innerHTML = clone.innerHTML;
    clone.remove();
    document.getElementById('_hide').id = 'summaryBody';
    S.currentSummary = prev; S.file = prevFile;
  } catch (e) {
    document.getElementById('modalBody').innerHTML = '<div class="empty-state">Could not load summary.</div>';
  }
}

function closeModal() {
  document.getElementById('summaryModal').classList.remove('show');
}

// ══ PDF GENERATION ═════════════════════════
function downloadCurrentPDF() {
  if (!S.currentSummary) return;
  if (S.currentSummary.id) {
    startDownload(S.currentSummary.id, 'pdf');
    return;
  }

  generatePDF(S.currentSummary, S.file?.name || 'document');
}

async function downloadPDF(summaryId) {
  startDownload(summaryId, 'pdf');
}

async function downloadPDFById(summaryId) {
  await downloadPDF(summaryId);
}

async function logDownload(summaryId) {
  try {
    await fetch(ENDPOINTS.downloadsLog, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ summary_id: summaryId }),
    });
    document.getElementById('sdDownloads').textContent = parseInt(document.getElementById('sdDownloads').textContent || '0') + 1;
  } catch {}
}

function generatePDF(s, filename) {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const W = 210, M = 14, CW = W - M * 2;
  let y = 14;

  // Header
  doc.setFillColor(26, 71, 49); doc.rect(0, 0, W, 55, 'F');
  doc.setFillColor(244, 196, 48); doc.rect(0, 53, W, 2, 'F');
  doc.setTextColor(244, 196, 48); doc.setFont('helvetica', 'bold'); doc.setFontSize(22);
  doc.text('JustConnect', M, 22);
  doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'normal'); doc.setFontSize(9);
  doc.text('Smarter Legal Decisions Powered by NLP', M, 29);
  doc.setFontSize(8); doc.setTextColor(180, 220, 200);
  doc.text('CONFIDENTIAL CONCISE SUMMARY', W - M, 22, { align: 'right' });
  doc.text('Generated: ' + new Date().toLocaleDateString(), W - M, 29, { align: 'right' });
  doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(12);
  doc.text('LEGAL DOCUMENT SUMMARY', M, 43);
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(200, 230, 215);
  doc.text(String(filename).substring(0, 60), M, 49);
  y = 64;

  function newPage() { if (y > 276) { doc.addPage(); y = 20; } }
  function secTitle(t) { newPage(); doc.setFillColor(26,71,49); doc.rect(M,y,3,7,'F'); doc.setFillColor(244,196,48); doc.rect(M+4,y+3,CW-4,.5,'F'); doc.setFont('helvetica','bold'); doc.setFontSize(9); doc.setTextColor(26,71,49); doc.text(t.toUpperCase(),M+7,y+5.5); y+=12; }
  function bodyTxt(t) { if(!t) return; doc.setFont('helvetica','normal'); doc.setFontSize(10); doc.setTextColor(60,60,55); doc.splitTextToSize(String(t),CW).forEach(l=>{newPage();doc.text(l,M,y);y+=5.5;}); y+=4; }
  function infoGrid(items) { const cw=(CW-4)/2; let col=0; items.forEach(([lbl,val])=>{ if(!val||val==='—') return; newPage(); const x=M+col*(cw+4); doc.setFillColor(247,247,245); doc.roundedRect(x,y,cw,15,2,2,'F'); doc.setDrawColor(210,220,215); doc.roundedRect(x,y,cw,15,2,2,'S'); doc.setFillColor(116,198,157); doc.rect(x,y,2.5,15,'F'); doc.setFont('helvetica','bold'); doc.setFontSize(7); doc.setTextColor(130,140,135); doc.text(lbl.toUpperCase(),x+5,y+5.5); doc.setFont('helvetica','bold'); doc.setFontSize(9); doc.setTextColor(26,71,49); doc.text(String(val).substring(0,38),x+5,y+11.5); col++; if(col===2){col=0;y+=18;} }); if(col!==0) y+=18; y+=3; }

  const entities = normaliseEntityList(s.entities_involved);
  secTitle('Case Details');
  infoGrid([
    ['Date of Judgment', s.date_of_document || 'N/A'],
    ['Court Name', s.court || 'N/A'],
    ['Judge Name', s.judge || 'N/A'],
  ]);
  bodyTxt('Entities Involved: ' + (entities.length ? entities.join(', ') : '—'));
  secTitle('Summary');
  bodyTxt(s.summary || s.executive_summary || '—');

  // Footer
  const pages = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pages; i++) {
    doc.setPage(i);
    doc.setFillColor(247,247,245); doc.rect(0,285,W,12,'F');
    doc.setFillColor(244,196,48); doc.rect(0,285,W,.5,'F');
    doc.setFont('helvetica','normal'); doc.setFontSize(7.5); doc.setTextColor(140,140,130);
    doc.text('JustConnect · Smarter Legal Decisions Powered by NLP · Confidential', M, 291);
    doc.text('Page '+i+' of '+pages, W-M, 291, {align:'right'});
  }

  const safe = String(filename).replace(/\.[^.]+$/,'').replace(/[^a-zA-Z0-9_-]/g,'_');
  doc.save('JustConnect_NLP_' + safe + '.pdf');
  toast('PDF downloaded!', 'success', 4000);
}

// ══ PROFILE ════════════════════════════════
async function loadRecords() {
  try {
    const params = new URLSearchParams();
    const search = document.getElementById('recSearch')?.value?.trim();
    const type = document.getElementById('recFilter')?.value;
    const court = document.getElementById('recCourt')?.value;
    const issue = document.getElementById('recIssue')?.value;
    if (search) params.set('search', search);
    if (type) params.set('type', type);
    if (court) params.set('court', court);
    if (issue) params.set('issue', issue);
    const url = params.toString() ? `${ENDPOINTS.records}?${params.toString()}` : ENDPOINTS.records;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    S.records = await res.json();
    renderRecords(S.records);
  } catch {
    document.getElementById('recTable').innerHTML = '<div class="empty-state">Could not load records.</div>';
  }
}

function renderRecords(list) {
  const el = document.getElementById('recTable');
  document.getElementById('recCount').textContent = list.length + ' document' + (list.length !== 1 ? 's' : '');
  if (!list.length) {
    el.innerHTML = '<div class="empty-state"><div class="empty-icon">📂</div><div class="empty-title">No matching cases</div><div class="empty-sub">Try a broader semantic query or clear some filters.</div></div>';
    return;
  }

  el.innerHTML = `<table class="rec-table"><thead><tr><th>Document</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead><tbody>
  ${list.map(s => `<tr>
    <td><strong>${esc(s.document_name || '—')}</strong></td>
    <td><span class="type-badge ${catBadge(s.document_type)}">${esc(s.document_type || 'Unknown')}</span></td>
    <td>${s.created_at || '—'}${s.court ? `<div style="font-size:11px;color:var(--grey-400);margin-top:6px">${esc(s.court)}</div>` : ''}</td>
    <td>
      <button class="tbl-btn" onclick='openSummaryModal(${s.id})'>View</button>
      <button class="tbl-btn" onclick='downloadExportById(${s.id},"pdf")'>PDF</button>
      <button class="tbl-btn" onclick='deleteDoc(${s.document_id},${s.id})' style="color:#e74c3c">Delete</button>
    </td>
  </tr>`).join('')}</tbody></table>`;
}

function filterRecs() {
  loadRecords();
}

function renderDownloads(list) {
  const el = document.getElementById('dlContainer');
  if (!list.length) {
    el.innerHTML = '<div class="empty-state"><div class="empty-icon">📥</div><div class="empty-title">No downloads yet</div><div class="empty-sub">Export PDF reports from the Records tab.</div></div>';
    return;
  }
  el.innerHTML = list.map(d => `
    <div class="dl-card">
      <div class="dl-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round" width="22" height="22"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
      <div class="dl-info">
        <div class="dl-name">${esc(d.document_name || 'Document')}</div>
        <div class="dl-meta">${esc(d.document_type || 'Legal Document')} · ${d.downloaded_at || ''}</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-yellow btn-sm" onclick="downloadExportById(${d.summary_id},'pdf')">PDF</button>
      </div>
    </div>`).join('');
}

function startDownload(summaryId, format) {
  if (format !== 'pdf') return;
  const a = document.createElement('a');
  a.href = `${ENDPOINTS.documentsBase}/${summaryId}/export/${format}`;
  a.rel = 'noopener';
  document.body.appendChild(a);
  a.click();
  a.remove();
  document.getElementById('sdDownloads').textContent = parseInt(document.getElementById('sdDownloads').textContent || '0') + 1;
  setTimeout(() => loadDownloads(), 1200);
}

function downloadCurrentExport(format) {
  if (!S.currentSummary?.id) return;
  if (format !== 'pdf') return;
  startDownload(S.currentSummary.id, format);
}

function downloadExportById(summaryId, format) {
  if (format !== 'pdf') return;
  startDownload(summaryId, format);
}

async function emailCurrentSummary() {
  if (!S.currentSummary?.id) return;

  const btn = document.getElementById('emailSummaryBtn');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = 'Sending...';

  try {
    const res = await fetch(`${ENDPOINTS.documentsBase}/${S.currentSummary.id}/email`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
      body: JSON.stringify({}),
    });
    const data = await res.json();

    if (!res.ok || !data.success) {
      throw new Error(data.message || 'Could not send email.');
    }

    toast(data.message || 'Summary sent to your account email.', 'success', 5000);
    btn.textContent = 'Sent';
  } catch (e) {
    toast(e.message || 'Could not send email.', 'error', 6000);
    btn.innerHTML = original;
    btn.disabled = false;
    return;
  }

  setTimeout(() => {
    btn.innerHTML = original;
    btn.disabled = false;
  }, 2500);
}

function downloadCurrentPDF() {
  downloadCurrentExport('pdf');
}

async function downloadPDF(summaryId) {
  startDownload(summaryId, 'pdf');
}

async function downloadPDFById(summaryId) {
  startDownload(summaryId, 'pdf');
}

async function saveProfile() {
  const data = {
    first_name:   document.getElementById('profFirst').value.trim(),
    last_name:    document.getElementById('profLast').value.trim(),
    organisation: document.getElementById('profOrg').value.trim(),
    role:         document.getElementById('profRole').value,
  };
  try {
    const res = await fetch(ENDPOINTS.profile, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify(data) });
    const d   = await res.json();
    if (d.success) toast('Profile updated!', 'success');
    else toast('Could not update profile.', 'error');
  } catch { toast('Network error.', 'error'); }
}

async function changePassword() {
  const pass    = document.getElementById('profNewPass').value;
  const confirm = document.getElementById('profConfPass').value;
  if (pass !== confirm) { toast('Passwords do not match.', 'error'); return; }
  try {
    const res = await fetch(ENDPOINTS.profilePassword, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify({password:pass,password_confirmation:confirm}) });
    const d   = await res.json();
    if (d.success) { toast('Password updated!', 'success'); document.getElementById('profNewPass').value=''; document.getElementById('profConfPass').value=''; }
    else toast(d.message || 'Update failed.', 'error');
  } catch { toast('Network error.', 'error'); }
}

async function saveMfaSettings() {
  const data = {
    mfa_enabled: document.getElementById('mfaEnabled').checked,
    mfa_channel: document.getElementById('mfaChannel').value,
    mfa_security_question: document.getElementById('mfaSecurityQuestion').value.trim(),
    mfa_security_answer: document.getElementById('mfaSecurityAnswer').value.trim(),
  };

  try {
    const res = await fetch(ENDPOINTS.mfa, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF, Accept:'application/json'},
      body: JSON.stringify(data),
    });
    const d = await res.json();
    if (d.success) {
      toast(d.message || 'MFA settings updated.', 'success');
      document.getElementById('mfaSecurityAnswer').value = '';
    }
    else toast(d.message || 'Could not update MFA settings.', 'error');
  } catch {
    toast('Network error.', 'error');
  }
}

function checkPwStrengthProf(pass) {
  let s = 0;
  if (pass.length >= 8) s++; if (/[A-Z]/.test(pass)) s++; if (/[0-9]/.test(pass)) s++; if (/[^A-Za-z0-9]/.test(pass)) s++; if (pass.length >= 12) s++;
  const lvls = [{w:'20%',c:'#e74c3c',t:'Very Weak'},{w:'40%',c:'#e67e22',t:'Weak'},{w:'60%',c:'#f4c430',t:'Fair'},{w:'80%',c:'#74c69d',t:'Strong'},{w:'100%',c:'#40916c',t:'Very Strong ✓'}];
  const l = lvls[Math.max(0,Math.min(s-1,4))];
  document.getElementById('profPwBar').style.width = l.w; document.getElementById('profPwBar').style.background = l.c;
  document.getElementById('profPwLabel').textContent = l.t; document.getElementById('profPwLabel').style.color = l.c;
  document.getElementById('profPwStrength').classList.toggle('show', pass.length > 0);
}

// ══ DRAG & DROP ════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  const uz = document.getElementById('uploadZone');
  uz.addEventListener('dragover', e => { e.preventDefault(); uz.classList.add('drag-over'); });
  uz.addEventListener('dragleave', () => uz.classList.remove('drag-over'));
  uz.addEventListener('drop', e => {
    e.preventDefault(); uz.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) onFileChosen({ target: { files: [file] } });
  });
  document.getElementById('summaryModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
  });
});
</script>
</body>
</html>




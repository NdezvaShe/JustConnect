<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Download Started - JustConnect</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<style>
body{min-height:100vh;margin:0;display:grid;place-items:center;background:#f7f8f5;font-family:'DM Sans',Arial,sans-serif;color:#1f2f25}
.download-card{width:min(520px,calc(100vw - 32px));background:#fff;border:1px solid rgba(26,71,49,.1);border-radius:22px;box-shadow:0 24px 70px rgba(26,71,49,.14);padding:34px;text-align:center;position:relative;overflow:hidden}
.download-card:before{content:'';position:absolute;inset:0 0 auto;height:7px;background:linear-gradient(90deg,#1a4731,#f4c430)}
.download-icon{width:92px;height:92px;margin:8px auto 22px;border-radius:50%;background:#edf8f1;display:grid;place-items:center;border:1px solid rgba(45,106,79,.14)}
.download-icon svg{width:44px;height:44px;stroke:#1a4731}
.download-title{font-size:30px;font-weight:900;color:#1a4731;margin-bottom:10px}
.download-sub{font-size:15px;line-height:1.65;color:#667169;margin:0 auto 22px;max-width:420px}
.download-name{padding:12px 14px;border-radius:14px;background:#fbfcf8;border:1px solid rgba(26,71,49,.08);font-weight:800;color:#2d6a4f;margin-bottom:22px;word-break:break-word}
.download-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.pulse{animation:pulse 1.4s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
iframe{display:none}
</style>
</head>
<body>
  <main class="download-card">
    <div class="download-icon pulse">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
    </div>
    <div class="download-title">Your download has started</div>
    <p class="download-sub">JustConnect is preparing your PDF report. If your browser does not start the download automatically, use the button below.</p>
    <div class="download-name">{{ $summary->document?->original_name ?? 'JustConnect summary report' }}</div>
    <div class="download-actions">
      <a class="btn btn-primary" href="{{ $downloadUrl }}">Download again</a>
      <a class="btn btn-outline" href="{{ $dashboardUrl }}">Back to dashboard</a>
    </div>
  </main>
  <iframe src="{{ $downloadUrl }}" title="PDF download"></iframe>
</body>
</html>

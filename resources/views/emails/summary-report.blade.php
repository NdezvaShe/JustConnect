<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your JustConnect PDF report</title>
</head>
<body style="margin:0;padding:0;background:#f6f1e7;font-family:Arial,sans-serif;color:#1a4731;">
    <div style="max-width:560px;margin:0 auto;padding:32px 20px;">
        <div style="background:#ffffff;border-radius:18px;padding:32px;border:1px solid #d9caa2;box-shadow:0 10px 30px rgba(26,71,49,0.08);">
            <div style="font-size:24px;font-weight:700;margin-bottom:12px;">Your PDF report is ready</div>
            <p style="margin:0 0 18px;line-height:1.6;color:#355a49;">
                Hi {{ $user->first_name ?: 'there' }}, your JustConnect analysis for
                <strong>{{ $document->original_name ?? ('Summary #' . $summary->id) }}</strong>
                has been completed.
            </p>
            <p style="margin:0 0 18px;line-height:1.6;color:#355a49;">
                We attached a concise PDF report to this email using the same address you used when signing up.
            </p>
            <div style="margin:24px 0;padding:18px 20px;background:#fff7d6;border:1px dashed #d4a514;border-radius:14px;">
                <div style="font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#8b6d00;margin-bottom:10px;">Included in the report</div>
                <div style="line-height:1.8;color:#355a49;">
                    Date of judgment<br>
                    Court name<br>
                    Judge name<br>
                    Entities involved<br>
                    Plain-English summary
                </div>
            </div>
            <p style="margin:0;line-height:1.6;color:#355a49;">JustConnect</p>
        </div>
    </div>
</body>
</html>

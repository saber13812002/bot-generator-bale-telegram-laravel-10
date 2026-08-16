<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>لاگ و سلامت ربات‌ها</title>
    <style>
        body { font-family: Tahoma, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 16px; }
        h1, h2 { margin: 0 0 12px; font-size: 18px; }
        form { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-bottom: 16px; background: #1e293b; padding: 12px; border-radius: 8px; }
        label { display: flex; flex-direction: column; font-size: 12px; gap: 4px; }
        input, select { background: #0f172a; color: #e2e8f0; border: 1px solid #334155; border-radius: 4px; padding: 6px 8px; }
        button, .btn { background: #2563eb; color: #fff; border: 0; border-radius: 4px; padding: 8px 12px; cursor: pointer; text-decoration: none; display: inline-block; }
        button.secondary { background: #334155; }
        .health { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 8px; margin-bottom: 16px; }
        .card { background: #1e293b; border-radius: 8px; padding: 12px; border-right: 6px solid #ef4444; }
        .card.green { border-right-color: #22c55e; }
        .card small { color: #94a3b8; }
        pre { background: #020617; color: #86efac; padding: 12px; border-radius: 8px; overflow: auto; max-height: 70vh; white-space: pre-wrap; word-break: break-word; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #334155; padding: 6px; text-align: right; vertical-align: top; }
        th { color: #94a3b8; }
        .muted { color: #94a3b8; font-size: 12px; margin-bottom: 12px; }
        .ok { color: #22c55e; }
        .fail { color: #f87171; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>لاگ سرور و سلامت ربات‌ها</h1>
    <p class="muted">برای فیلتر، bot_id را بزن و آخرین رکوردها را کپی کن.</p>

    <form method="get">
        <label>Bot ID
            <input type="number" name="bot_id" min="1" value="{{ $botId }}">
        </label>
        <label>تعداد
            <input type="number" name="limit" min="1" max="1000" value="{{ $limit }}">
        </label>
        <label>منبع
            <select name="source">
                <option value="db" @selected($source === 'db')>دیتابیس</option>
                <option value="file" @selected($source === 'file')>فایل laravel.log</option>
            </select>
        </label>
        <button type="submit">نمایش</button>
        <button type="button" class="secondary" id="copy-btn">کپی {{ $limit }} رکورد آخر</button>
        <a class="btn secondary" href="?bot_id={{ $botId }}&limit={{ $limit }}&source={{ $source }}&format=text">متن خام</a>
    </form>

    <h2>سلامت ربات‌ها</h2>
    <div class="health">
        @forelse ($health as $item)
            <div class="card {{ $item['is_green'] ? 'green' : '' }}">
                <strong>{{ $item['feature_key'] }} / {{ $item['platform'] }}</strong><br>
                <small>
                    آخرین وضعیت: <span class="{{ $item['last_status'] === 'ok' ? 'ok' : 'fail' }}">{{ $item['last_status'] }}</span>
                    @if($item['bot_id']) · bot_id={{ $item['bot_id'] }} @endif
                    <br>
                    آخرین رویداد: {{ $item['last_event_at']?->toDateTimeString() ?? '-' }}
                    <br>
                    آخرین ok: {{ $item['last_ok_at']?->toDateTimeString() ?? 'هرگز' }}
                    @if($item['is_green']) · امروز کار کرده @else · امروز ok نداشته @endif
                </small>
            </div>
        @empty
            <div class="card">هنوز رویداد سلامتی ثبت نشده است.</div>
        @endforelse
    </div>

    <h2>لاگ‌ها</h2>
    <textarea id="copy-source" style="position:absolute;left:-9999px;">{{ $copyText }}</textarea>

    @if($source === 'file')
        <pre>{{ $fileTail !== '' ? $fileTail : 'فایل لاگ خالی است یا خوانده نشد.' }}</pre>
    @else
        <table>
            <thead>
            <tr>
                <th>زمان</th>
                <th>سطح</th>
                <th>bot_id</th>
                <th>feature</th>
                <th>پیام</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->toDateTimeString() }}</td>
                    <td>{{ $log->level }}</td>
                    <td>{{ $log->bot_id ?? '-' }}</td>
                    <td>{{ $log->feature_key ?? '-' }}</td>
                    <td>{{ $log->message }}</td>
                </tr>
            @empty
                <tr><td colspan="5">لاگی پیدا نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
        <pre>{{ $copyText }}</pre>
    @endif
</div>
<script>
    document.getElementById('copy-btn').addEventListener('click', function () {
        var text = document.getElementById('copy-source').value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                alert('کپی شد');
            });
        } else {
            var area = document.getElementById('copy-source');
            area.style.position = 'static';
            area.select();
            document.execCommand('copy');
            area.style.position = 'absolute';
            alert('کپی شد');
        }
    });
</script>
</body>
</html>

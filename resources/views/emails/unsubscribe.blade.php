<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لغو اشتراک</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            direction: rtl;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            background-color: #ffffff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 20px;
        }
        p {
            color: #6c757d;
            line-height: 1.6;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        @if($status === 'success')
            <div class="icon">✅</div>
            <h1 class="success">لغو اشتراک موفق</h1>
            <p>اشتراک شما در دریافت ایمیل‌های گزارش نماز قضا با موفقیت لغو شد.</p>
            <p>در صورت تمایل، می‌توانید دوباره از طریق ربات تلگرام یا بله، ایمیل خود را فعال کنید.</p>
        @elseif($status === 'invalid')
            <div class="icon">⚠️</div>
            <h1 class="warning">لینک نامعتبر</h1>
            <p>لینک لغو اشتراک نامعتبر است یا منقضی شده است.</p>
        @else
            <div class="icon">❌</div>
            <h1 class="error">خطا</h1>
            <p>متأسفانه خطایی رخ داد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.</p>
        @endif
    </div>
</body>
</html>

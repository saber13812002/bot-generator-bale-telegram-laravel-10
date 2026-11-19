<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ trans('bot.email_verification_subject') }}</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
        }
        .code-box {
            background-color: #f8f9fa;
            border: 2px dashed #3498db;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #3498db;
            letter-spacing: 5px;
            font-family: 'Courier New', monospace;
        }
        .message {
            text-align: center;
            color: #7f8c8d;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            color: #95a5a6;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🕌 {{ trans('bot.email_verification_title') }}</h1>
        </div>

        <p>{{ trans('bot.email_verification_greeting') }}</p>
        
        <p>{{ trans('bot.email_verification_message') }}</p>

        <div class="code-box">
            <div class="code">{{ $code }}</div>
        </div>

        <p class="message">
            {{ trans('bot.email_verification_warning') }}
        </p>

        <div class="footer">
            <p>{{ trans('bot.email_verification_footer') }}</p>
        </div>
    </div>
</body>
</html>

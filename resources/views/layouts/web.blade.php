<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Bots'))</title>

    <!-- Fonts - Local only (CDN blocked on server) -->
    <style>
        body { font-family: Tahoma, 'Segoe UI', system-ui, -apple-system, sans-serif; }
    </style>

    <!-- Styles -->
    @stack('styles')

    <!-- Google Analytics (GA4) -->
    @if(config('analytics.google_analytics.enabled') && !empty(config('analytics.google_analytics.measurement_id')))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('analytics.google_analytics.measurement_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('analytics.google_analytics.measurement_id') }}');
    </script>
    @endif

    <!-- Microsoft Clarity -->
    @if(config('analytics.clarity.enabled') && !empty(config('analytics.clarity.project_id')))
    <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "{{ config('analytics.clarity.project_id') }}");
    </script>
    @endif

    <!-- Hotjar -->
    @if(config('analytics.hotjar.enabled') && !empty(config('analytics.hotjar.site_id')))
    <script>
        (function(h,o,t,j,a,r){
            h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
            h._hjSettings={hjid:{{ config('analytics.hotjar.site_id') }},hjsv:6};
            a=o.getElementsByTagName('head')[0];
            r=o.createElement('script');r.async=1;
            r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
            a.appendChild(r);
        })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
    </script>
    @endif
</head>
<body class="antialiased">
    @yield('content')

    <!-- Scripts -->
    @stack('scripts')
</body>
</html>

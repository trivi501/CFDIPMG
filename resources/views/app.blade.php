<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        {{-- Mientras arranca la app; lo quita resources/js/components/pantalla-cargando.tsx. --}}
        <div id="cargando-inicial" role="status" aria-live="polite">
            <div class="cargando-caja">
                <div class="cargando-anillo"></div>
                <img src="/img/logo-emblema.png" alt="">
            </div>
            <span>Cargando…</span>
        </div>
        <style>
            #cargando-inicial {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 16px;
                background: #fff;
            }
            html.dark #cargando-inicial { background: oklch(0.145 0 0); }
            #cargando-inicial .cargando-caja {
                position: relative;
                width: 120px;
                height: 120px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #cargando-inicial .cargando-anillo {
                position: absolute;
                inset: 0;
                border-radius: 50%;
                border: 4px solid rgba(122, 52, 70, .15);
                border-top-color: #7a3446;
                border-right-color: #c4956f;
                animation: cargando-girar .9s linear infinite;
            }
            #cargando-inicial img { height: 64px; }
            #cargando-inicial span { color: #7a3446; font-weight: 600; font-family: sans-serif; letter-spacing: .5px; }
            html.dark #cargando-inicial span { color: #c4956f; }
            @keyframes cargando-girar { to { transform: rotate(360deg); } }
        </style>
        <x-inertia::app />
    </body>
</html>

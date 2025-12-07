<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMS - 404 | Page Not Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #0a0a0f 0%, #18181b 50%, #0f0f14 100%);
            color: #fff;
            overflow: hidden;
            min-height: 100vh;
        }

        [lang="fa"] body {
            font-family: 'Vazirmatn', sans-serif;
            direction: rtl;
        }

        .glow-orb {
            position: fixed;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.2;
            pointer-events: none;
            animation: glow 4s ease-in-out infinite;
        }

        .orb-1 {
            background: #667eea;
            top: -100px;
            left: -100px;
        }

        .orb-2 {
            background: #764ba2;
            bottom: -100px;
            right: -100px;
            animation-delay: 2s;
        }

        .container {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
            padding: 2rem;
            z-index: 1;
        }

        .svg-container {
            margin-bottom: 2rem;
            position: relative;
        }

        .svg-container svg {
            filter: drop-shadow(0 20px 40px rgba(102, 126, 234, 0.3));
            animation: float 6s ease-in-out infinite;
        }

        .message h1 {
            font-size: 2.5rem;
            margin: 0 0 1rem;
            display: inline-flex;
            align-items: center;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .message p {
            font-size: 1.125rem;
            color: rgba(255, 255, 255, 0.7);
            margin: 0 0 2rem;
            max-width: 500px;
        }

        .btn {
            display: inline-block;
            padding: 0.875rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.4);
        }

        .btn:hover::before {
            transform: translateX(100%);
        }

        .btn:active {
            transform: translateY(0);
        }

        .cursor {
            font-size: 1.5em;
            margin-left: 0.25rem;
            animation: blink 1s step-end infinite;
        }

        @keyframes blink {
            50% { opacity: 0; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        @keyframes glow {
            0%, 100% {
                opacity: 0.15;
                transform: scale(1);
            }
            50% {
                opacity: 0.25;
                transform: scale(1.05);
            }
        }

        .eye {
            animation: blink-eye 3s infinite;
        }

        @keyframes blink-eye {
            0%, 95%, 100% { transform: scaleY(1); }
            97.5% { transform: scaleY(0.1); }
        }

        @media (max-width: 768px) {
            .message h1 {
                font-size: 2rem;
            }

            .message p {
                font-size: 1rem;
            }

            .svg-container svg {
                width: 150px;
                height: 150px;
            }

            .glow-orb {
                filter: blur(60px);
                opacity: 0.15;
            }
        }

        @media (max-width: 640px) {
            .message h1 {
                font-size: 1.75rem;
            }

            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.9375rem;
            }
        }
    </style>
</head>
<body>
<div class="glow-orb orb-1"></div>
<div class="glow-orb orb-2"></div>

<div class="container">
    <div class="svg-container">
        <svg width="200" height="200" viewBox="0 0 100 100">
            <rect x="30" y="40" width="40" height="40" fill="#667eea" rx="5"/>
            <circle cx="50" cy="30" r="20" fill="#667eea"/>
            <circle class="eye" cx="40" cy="25" r="5" fill="#fff"/>
            <circle class="eye" cx="60" cy="25" r="5" fill="#fff"/>
            <rect x="40" y="35" width="20" height="5" fill="#fff" rx="2"/>
            <rect x="20" y="50" width="10" height="20" fill="#667eea" rx="3"/>
            <rect x="70" y="50" width="10" height="20" fill="#667eea" rx="3"/>
            <rect x="35" y="80" width="10" height="15" fill="#667eea" rx="3"/>
            <rect x="55" y="80" width="10" height="15" fill="#667eea" rx="3"/>
            <line x1="50" y1="10" x2="50" y2="0" stroke="#fff" stroke-width="2"/>
            <circle cx="50" cy="0" r="3" fill="#764ba2"/>
        </svg>
    </div>

    <div class="message">
        <h1><span id="typed-text"></span><span class="cursor">|</span></h1>
        <p id="subtitle"></p>
        <a href="/dashboard" class="btn" id="btn-home"></a>
    </div>
</div>

<script>
    const translations = {
        en: {
            title: "Oops! Page not found ☹️",
            subtitle: "The page you're looking for might have been moved or doesn't exist.",
            button: "Go to Homepage"
        },
        fr: {
            title: "Oups ! Page introuvable ☹️",
            subtitle: "La page que vous recherchez a peut-être été déplacée ou n'existe pas.",
            button: "Aller à l'accueil"
        },
        fa: {
            title: "اوه! صفحه یافت نشد ☹️",
            subtitle: "صفحه‌ای که به دنبال آن هستید ممکن است جابجا شده یا وجود نداشته باشد.",
            button: "برو به صفحه اصلی"
        }
    };

    const locale = @js(app()->getLocale());
    const lang = translations[locale] || translations.en;

    document.documentElement.lang = locale === 'fa' ? 'fa' : 'en';
    document.title = `BMS - 404 | ${lang.button}`;

    const typedText = document.getElementById('typed-text');
    const subtitle = document.getElementById('subtitle');
    const btnHome = document.getElementById('btn-home');

    subtitle.textContent = lang.subtitle;
    btnHome.textContent = lang.button;

    let index = 0;
    function type() {
        if (index < lang.title.length) {
            typedText.textContent += lang.title.charAt(index);
            index++;
            setTimeout(type, 100);
        }
    }

    window.onload = type;
</script>
</body>
</html>

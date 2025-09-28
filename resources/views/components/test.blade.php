<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Business App - Striking Light Mode</title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-color: #0f172a;
            --accent-color: #2563eb;
            --accent-color-rgb: 37, 99, 235;
            --secondary-bg: #ffffff;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.2);
            --text-muted: #64748b;
        }

        body.dark {
            --bg-color: #020412;
            --text-color: #e2e8f0;
            --accent-color: #3b82f6;
            --accent-color-rgb: 59, 130, 246;
            --secondary-bg: #101223;
            --border-color: #1e293b;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', system-ui, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.5s cubic-bezier(0.4, 0, 0.2, 1), color 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            line-height: 1.6;
            overflow-x: hidden;
        }

        #background-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
            opacity: 0;
            transition: opacity 1.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #background-canvas.visible {
            opacity: 1;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            border-bottom: 1px solid transparent;
            position: fixed;
            width: 100%;
            background: rgba(248, 250, 252, 0.5);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            z-index: 100;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            top: 0;
        }

        body.dark header {
            background: rgba(2, 4, 18, 0.5);
            border-bottom-color: var(--border-color);
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            opacity: 0;
            animation: fadeIn 1s cubic-bezier(0.4, 0, 0.2, 1) forwards 0.2s;
        }

        .nav-controls {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .nav-button, .theme-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 12px;
            background: var(--secondary-bg);
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            animation: fadeIn 1s cubic-bezier(0.4, 0, 0.2, 1) forwards 0.4s;
            text-decoration: none;
            color: var(--text-color);
        }

        .nav-button:hover, .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--accent-color-rgb), 0.15);
        }

        .theme-icon-wrapper {
            position: relative;
            width: 18px;
            height: 18px;
        }

        .theme-icon {
            width: 18px;
            height: 18px;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: absolute;
            top: 50%;
            left: 50%;
        }

        .sun-icon { opacity: 1; transform: translate(-50%, -50%) rotate(0deg) scale(1); }
        .moon-icon { opacity: 0; transform: translate(-50%, -50%) rotate(-90deg) scale(0); }
        body.dark .sun-icon { opacity: 0; transform: translate(-50%, -50%) rotate(90deg) scale(0); }
        body.dark .moon-icon { opacity: 1; transform: translate(-50%, -50%) rotate(0deg) scale(1); }

        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 120px 40px 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            z-index: 1;
        }

        .hero h1 {
            font-size: clamp(3rem, 8vw, 5.5rem);
            font-weight: 800;
            margin-bottom: 24px;
            opacity: 0;
            transform: translateY(40px);
            animation: slideUp 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards 0.5s;
            letter-spacing: -0.05em;
            background: linear-gradient(135deg, var(--text-color) 30%, var(--accent-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: clamp(1rem, 4vw, 1.25rem);
            margin-bottom: 40px;
            opacity: 0;
            transform: translateY(40px);
            animation: slideUp 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards 0.7s;
            color: var(--text-muted);
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta {
            padding: 16px 36px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
            opacity: 0;
            transform: translateY(40px);
            animation: slideUp 1s cubic-bezier(0.2, 0.8, 0.2, 1) forwards 0.9s;
            box-shadow: 0 4px 20px rgba(var(--accent-color-rgb), 0.3);
        }

        .cta:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 8px 30px rgba(var(--accent-color-rgb), 0.4);
        }

        main {
            background-color: var(--bg-color);
            position: relative;
            z-index: 5;
        }

        .chat-container {
            max-width: 900px;
            width: calc(100% - 80px);
            margin: 120px auto;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            opacity: 0;
            transform: translateY(60px) scale(0.98);
            pointer-events: none;
            transition: opacity 0.8s cubic-bezier(0.2, 0.8, 0.2, 1), transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        body:not(.dark) .chat-container {
            box-shadow: 0 10px 15px -3px rgba(45, 55, 72, 0.12), 0 4px 6px -2px rgba(45, 55, 72, 0.07);
        }

        body.dark .chat-container {
            box-shadow: 0 25px 50px -12px var(--shadow-color);
        }

        .chat-container.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .scroll-top {
            position: absolute;
            top: -60px;
            right: 0;
            width: 48px;
            height: 48px;
            background: var(--accent-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
            color: white;
        }

        .chat-container.visible .scroll-top {
            opacity: 1;
            transform: scale(1);
        }

        .scroll-top:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(var(--accent-color-rgb), 0.4);
        }

        .chat-header, .chat-messages, .chat-input, footer {
            transition: background-color 0.5s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .chat-header {
            padding: 24px 32px;
            background: var(--secondary-bg);
            font-weight: 600;
            text-align: center;
            font-size: 18px;
            border-bottom: 1px solid var(--border-color);
        }

        .chat-messages {
            height: 450px;
            overflow-y: auto;
            padding: 32px;
            background: var(--bg-color);
            scroll-behavior: smooth;
        }

        .chat-messages::-webkit-scrollbar { width: 8px; }
        .chat-messages::-webkit-scrollbar-track { background: var(--secondary-bg); border-radius: 4px; }
        .chat-messages::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

        .message {
            margin-bottom: 16px;
            padding: 16px 24px;
            border-radius: 20px;
            max-width: 75%;
            animation: messageSlideIn 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
            font-size: 15px;
            line-height: 1.5;
        }

        .user-message {
            background: var(--accent-color);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 8px;
        }

        .ai-message {
            background: var(--secondary-bg);
            color: var(--text-color);
            border-bottom-left-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .chat-input {
            display: flex;
            border-top: 1px solid var(--border-color);
            background: var(--secondary-bg);
        }

        .chat-input input {
            flex: 1;
            padding: 20px 24px;
            border: none;
            background: transparent;
            color: var(--text-color);
            font-size: 16px;
            outline: none;
        }

        .chat-input input::placeholder { color: var(--text-muted); }

        .chat-input button {
            padding: 20px 24px;
            background: var(--accent-color);
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            min-width: 80px;
        }

        .chat-input button:hover { background: #1d4ed8; }

        footer {
            text-align: center;
            padding: 60px 40px;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
        }

        @keyframes fadeIn { to { opacity: 1; } }
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }
        @keyframes messageSlideIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @media (max-width: 768px) {
            header { padding: 16px 20px; }
            .nav-controls { gap: 8px; }
            .hero { padding: 100px 20px 60px; }
            .chat-container { width: calc(100% - 40px); margin: 80px auto; }
            .chat-messages { padding: 20px; height: 400px; }
            .message { max-width: 85%; }
        }
    </style>
</head>
<body>

<canvas id="background-canvas"></canvas>

<header>
    <div class="logo">Persol AI</div>
    <div class="nav-controls">
        <a href="https://example.com/dashboard" class="nav-button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
            </svg>
            Main Site
        </a>
        <div class="theme-toggle">
            <div class="theme-icon-wrapper">
                <svg class="theme-icon sun-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z"/></svg>
                <svg class="theme-icon moon-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z"/></svg>
            </div>
            <span id="theme-text">Light</span>
        </div>
    </div>
</header>

<section class="hero">
    <div class="hero-content">
        <h1>Empower Your Business with AI</h1>
        <p>Transform your enterprise with intelligent automation, seamless integration, and cutting-edge artificial intelligence solutions designed for the modern workplace.</p>
        <button class="cta" id="cta-button">Get Started</button>
    </div>
</section>

<main>
    <section id="chat-section" class="chat-container">
        <div class="scroll-top" id="scroll-top">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/>
            </svg>
        </div>
        <div class="chat-header">AI Chat Demo</div>
        <div class="chat-messages" id="messages"></div>
        <div class="chat-input">
            <input type="text" id="input" placeholder="Ask me anything about our AI solutions...">
            <button id="send-button">Send</button>
        </div>
    </section>
    <footer>&copy; 2025 Persol Business Solution. All rights reserved.</footer>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;
        const themeToggle = document.querySelector('.theme-toggle');
        const themeText = document.getElementById('theme-text');
        const ctaButton = document.getElementById('cta-button');
        const chatSection = document.getElementById('chat-section');
        const sendButton = document.getElementById('send-button');
        const messageInput = document.getElementById('input');
        const scrollTopBtn = document.getElementById('scroll-top');

        const canvas = document.getElementById('background-canvas');
        const ctx = canvas.getContext('2d');

        let stars = [];
        let particles = [];
        let animationFrameId;
        let chatInitialized = false;

        const setCanvasSize = () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        };

        const initStars = () => {
            const starCount = window.innerWidth > 768 ? 1500 : 500;
            stars = [];
            for (let i = 0; i < starCount; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    z: Math.random() * canvas.width,
                    radius: Math.random() * 1.2,
                });
            }
        };

        const drawStars = () => {
            ctx.fillStyle = "rgba(255, 255, 255, 0.8)";
            ctx.beginPath();
            stars.forEach(star => {
                const sx = (star.x - canvas.width / 2) * (canvas.width / star.z) + canvas.width / 2;
                const sy = (star.y - canvas.height / 2) * (canvas.width / star.z) + canvas.height / 2;
                if (sx >= 0 && sx <= canvas.width && sy >= 0 && sy <= canvas.height) {
                    const radius = star.radius * (canvas.width / star.z);
                    ctx.rect(sx, sy, radius, radius);
                }
            });
            ctx.fill();
        };

        const updateStars = () => {
            stars.forEach(star => {
                star.z -= 0.2;
                if (star.z <= 0) {
                    star.z = canvas.width;
                }
            });
        };

        const initParticles = () => {
            const particleCount = 100;
            particles = [];
            const accentRGB = getComputedStyle(body).getPropertyValue('--accent-color-rgb').trim();
            for (let i = 0; i < particleCount; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    radius: Math.random() * 35 + 5,
                    speedY: Math.random() * 0.4 + 0.1,
                    opacity: Math.random() * 0.2 + 0.1,
                    color: `rgba(${accentRGB}, ${Math.random() * 0.5 + 0.2})`,
                });
            }
        };

        const drawParticles = () => {
            particles.forEach(p => {
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2, false);
                ctx.fillStyle = p.color;
                ctx.globalAlpha = p.opacity;
                ctx.fill();
            });
            ctx.globalAlpha = 1;
        };

        const updateParticles = () => {
            particles.forEach(p => {
                p.y -= p.speedY;
                if (p.y < -p.radius) {
                    p.y = canvas.height + p.radius;
                    p.x = Math.random() * canvas.width;
                }
            });
        };

        const animate = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (body.classList.contains('dark')) {
                updateStars();
                drawStars();
            } else {
                updateParticles();
                drawParticles();
            }
            animationFrameId = requestAnimationFrame(animate);
        };

        const startAnimation = () => {
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
            }
            setCanvasSize();
            if (body.classList.contains('dark')) {
                initStars();
            } else {
                initParticles();
            }
            animate();
            canvas.classList.add('visible');
        };

        const easeInOutQuad = t => t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;

        const smoothScrollTo = (targetSelector, duration = 1000) => {
            const target = document.querySelector(targetSelector);
            if (!target) return;
            const targetPosition = target.getBoundingClientRect().top + window.pageYOffset;
            const startPosition = window.pageYOffset;
            const distance = targetPosition - startPosition;
            let startTime = null;
            const animation = currentTime => {
                if (startTime === null) startTime = currentTime;
                const timeElapsed = currentTime - startTime;
                const progress = Math.min(timeElapsed / duration, 1);
                const easedProgress = easeInOutQuad(progress);
                window.scrollTo(0, startPosition + distance * easedProgress);
                if (timeElapsed < duration) {
                    requestAnimationFrame(animation);
                }
            };
            requestAnimationFrame(animation);
        };

        const scrollToTop = () => {
            smoothScrollTo('.hero', 1000);
        };

        const initChat = () => {
            if (chatInitialized) return;
            chatInitialized = true;
            const messages = document.getElementById('messages');
            const welcomeMsg = document.createElement('div');
            welcomeMsg.classList.add('message', 'ai-message');
            welcomeMsg.textContent = 'Welcome! How can I help you explore our AI solutions today?';
            messages.appendChild(welcomeMsg);
            messages.scrollTop = messages.scrollHeight;
        };

        const showChat = () => {
            chatSection.classList.add('visible');
            smoothScrollTo('#chat-section', 1200);
            setTimeout(initChat, 800);
        };

        const sendMessage = () => {
            const messagesContainer = document.getElementById('messages');
            const text = messageInput.value.trim();
            if (!text) return;
            const userMsg = document.createElement('div');
            userMsg.classList.add('message', 'user-message');
            userMsg.textContent = text;
            messagesContainer.appendChild(userMsg);
            messageInput.value = '';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            setTimeout(() => {
                const aiMsg = document.createElement('div');
                aiMsg.classList.add('message', 'ai-message');
                const responses = [
                    'Our AI can boost business efficiency by up to 40%.',
                    'How can I assist in integrating AI into your workflow?',
                    'Our platform offers advanced analytics, automation, and intelligent decision-making.',
                    'Let me connect you with our team for a custom AI solution.'
                ];
                aiMsg.textContent = responses[Math.floor(Math.random() * responses.length)];
                messagesContainer.appendChild(aiMsg);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 1200);
        };

        const setInitialTheme = () => {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (prefersDark) {
                body.classList.add('dark');
            }
            themeText.textContent = body.classList.contains('dark') ? 'Dark' : 'Light';
        };

        setInitialTheme();
        startAnimation();
        window.addEventListener('resize', startAnimation);

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            themeText.textContent = body.classList.contains('dark') ? 'Dark' : 'Light';
            startAnimation();
        });

        ctaButton.addEventListener('click', showChat);
        scrollTopBtn.addEventListener('click', scrollToTop);
        sendButton.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', e => {
            if (e.key === 'Enter') sendMessage();
        });
    });
</script>
</body>
</html>

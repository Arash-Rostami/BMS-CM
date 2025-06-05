<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>App Deployment in Progress</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700;900&display=swap"
          rel="stylesheet">
    <style>
        :root {
            --primary-color: #4776E6;
            --primary-dark: #3A63C2;
            --accent-color: #FF6B6B;
            --text-color: #2C3E50;
            --light-text: #ECF0F1;
            --background-color: #FFFFFF;
            --success-color: #2ECC71;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            min-height: 150vh;
            background: linear-gradient(-45deg, #4776E6, #8E54E9, #4776E6, #8E54E9);
            background-size: 400% 400%;
            animation: gradientFlow 15s ease infinite;
            overflow-y: hidden;
            overflow-x: hidden;
        }

        body::-webkit-scrollbar {
            width: 12px;
        }

        body::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        body::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #8E54E9, var(--primary-color));
            border-radius: 10px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        body::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #8E54E9, var(--primary-color));
        }

        .main-container {
            position: relative;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .content {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            text-align: center;
            width: 90%;
            max-width: 850px;
            position: relative;
            z-index: 10;
            animation: floatIn 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            margin-top: 5vh;
            margin-bottom: 5vh;
        }

        .extra-content {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 80%;
            max-width: 700px;
            margin: 30px auto;
            color: var(--text-color);
            line-height: 1.6;
            z-index: 2;
        }

        .extra-content h2 {
            font-size: 1.8em;
            color: var(--primary-dark);
            margin-bottom: 20px;
        }

        h1 {
            font-size: 2.8em;
            font-weight: 900;
            background: linear-gradient(45deg, var(--primary-color), #8E54E9);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 20px;
            position: relative;
            animation: titleGlow 3s ease infinite;
        }

        .message-text {
            font-size: 1.2em;
            color: var(--text-color);
            line-height: 1.8;
            margin: 15px 0;
            animation: fadeInUp 0.8s ease-out 0.6s forwards;
            opacity: 0;
        }

        .emphasis {
            color: var(--accent-color);
            font-weight: 700;
        }

        .progress-container {
            margin: 40px auto;
            width: 80%;
            position: relative;
            animation: fadeInUp 0.8s ease-out 0.9s forwards;
            opacity: 0;
        }

        .progress-bar {
            height: 8px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary-color), #8E54E9);
            border-radius: 10px;
            transition: width 0.5s ease-out;
            animation: progressPulse 2s infinite;
            box-shadow: 0 0 10px rgba(71, 118, 230, 0.5);
        }

        .task-status {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin: 40px 0;
            gap: 20px;
            animation: fadeInUp 0.8s ease-out 1.2s forwards;
            opacity: 0;
        }

        .task {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            width: calc(25% - 20px);
            min-width: 150px;
            position: relative;
            transition: all 0.4s ease;
        }

        .task-icon {
            font-size: 2em;
            margin-bottom: 10px;
            color: var(--primary-color);
            opacity: 0.9;
            transition: all 0.3s ease;
        }

        .task.completed .task-icon {
            color: var(--success-color);
            animation: successPop 0.5s ease-out;
        }

        .task-label {
            font-size: 0.9em;
            color: var(--text-color);
            font-weight: 500;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent-color);
            display: block;
            margin: 10px auto 0;
            position: relative;
        }

        .status-dot::before {
            content: "";
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            border-radius: 50%;
            background: rgba(255, 107, 107, 0.3);
            animation: pulseDot 1.5s infinite;
        }

        .task.completed .status-dot {
            background: var(--success-color);
        }

        .task.completed .status-dot::before {
            background: rgba(46, 204, 113, 0.3);
            animation: none;
        }

        .task.in-progress .status-dot {
            background: var(--primary-color);
        }

        .task.in-progress .status-dot::before {
            background: rgba(71, 118, 230, 0.3);
        }

        .deployment-info {
            margin-top: 30px;
            font-size: 1em;
            color: var(--text-color);
            animation: fadeInUp 0.8s ease-out 1.5s forwards;
            opacity: 0;
        }

        .eta {
            font-weight: 700;
            color: var(--primary-dark);
            display: inline-block;
            background: rgba(71, 118, 230, 0.1);
            padding: 3px 10px;
            border-radius: 15px;
            margin-top: 10px;
        }

        .flying-elements {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
            pointer-events: none;
        }

        .element {
            position: absolute;
            border-radius: 50%;
            opacity: 0.6;
            z-index: 0;
        }

        @keyframes gradientFlow {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        @keyframes floatIn {
            0% {
                transform: translateY(100px) scale(0.8);
                opacity: 0;
            }
            70% {
                transform: translateY(-10px) scale(1.02);
                opacity: 1;
            }
            100% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulseDot {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            70% {
                transform: scale(1.5);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 0;
            }
        }

        @keyframes progressPulse {
            0% {
                opacity: 1;
                background-position: 0% 50%;
            }
            50% {
                opacity: 0.8;
                background-position: 100% 50%;
            }
            100% {
                opacity: 1;
                background-position: 0% 50%;
            }
        }

        @keyframes titleGlow {
            0%, 100% {
                text-shadow: 0 0 5px rgba(71, 118, 230, 0.3);
            }
            50% {
                text-shadow: 0 0 20px rgba(71, 118, 230, 0.7);
            }
        }

        @keyframes successPop {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.3);
            }
            100% {
                transform: scale(1);
            }
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(10deg);
            }
            100% {
                transform: translateY(0) rotate(0deg);
            }
        }

        @keyframes cloudDrift {
            0% {
                transform: translateX(-100%) translateY(0);
                opacity: 0;
            }
            10% {
                opacity: 0.8;
            }
            90% {
                opacity: 0.8;
            }
            100% {
                transform: translateX(100vw) translateY(20px);
                opacity: 0;
            }
        }

        .floating-cloud {
            position: absolute;
            width: 140px;
            height: 60px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50px;
            z-index: 0;
            filter: blur(5px);
        }

        .floating-cloud:before,
        .floating-cloud:after {
            content: '';
            position: absolute;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
        }

        .floating-cloud:before {
            width: 70px;
            height: 70px;
            top: -20px;
            left: 20px;
        }

        .floating-cloud:after {
            width: 60px;
            height: 60px;
            top: -10px;
            right: 20px;
        }

        .cloud1 {
            top: 15%;
            left: -150px;
            animation: cloudDrift 30s linear infinite;
            animation-delay: 0s;
        }

        .cloud2 {
            top: 45%;
            left: -150px;
            animation: cloudDrift 25s linear infinite;
            animation-delay: 5s;
            transform: scale(0.7);
        }

        .cloud3 {
            top: 75%;
            left: -150px;
            animation: cloudDrift 40s linear infinite;
            animation-delay: 10s;
            transform: scale(1.3);
        }

        .code-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        .code-particle {
            position: absolute;
            color: rgba(255, 255, 255, 0.5);
            font-family: monospace;
            font-size: 1em;
        }

        @media (max-width: 768px) {
            body {
                overflow-y: auto;
                overflow-x: hidden;
                min-height: 120vh;
            }

            .main-container {
                height: auto;
                min-height: auto;
                padding: 20px 0;
            }

            .content {
                transform: none !important;
                padding: 30px 20px;
                width: 85%;
                margin: 20px auto;
                max-width: none;
            }

            h1 {
                font-size: 2em;
                margin-bottom: 15px;
            }

            .message-text {
                font-size: 1em;
                margin: 10px 0 25px;
            }

            .progress-container {
                width: 90%;
                margin-top: 30px;
                margin-bottom: 30px;
            }

            .task-status {
                flex-direction: column;
                align-items: center;
                gap: 15px;
                margin: 30px 0;
            }

            .task {
                width: 90%;
                max-width: 300px;
                padding: 20px;
            }

            .task-icon {
                font-size: 2.2em;
            }

            .task-label {
                font-size: 1em;
            }

            .deployment-info {
                margin-top: 25px;
            }

            .eta {
                padding: 5px 12px;
            }

            .floating-cloud, .code-particle {
                display: none;
            }

            .extra-content {
                width: 95%;
                padding: 25px;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 20px 15px;
            }

            h1 {
                font-size: 1.8em;
            }

            .message-text {
                font-size: 0.95em;
            }

            .task {
                width: 95%;
                padding: 15px;
            }
            .extra-content {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <!-- Flying clouds background -->
    <div class="floating-cloud cloud1"></div>
    <div class="floating-cloud cloud2"></div>
    <div class="floating-cloud cloud3"></div>

    <!-- Code particles background -->
    <div class="code-particles" id="codeParticles"></div>

    <!-- Main content -->
    <div class="content">
        <h1>App Deployment in Progress</h1>
        <p class="message-text">
            I'm currently setting up and deploying your <span class="emphasis">BMS</span>.
            I'm working diligently to ensure everything is perfectly configured.
            <br><br>
            <span class="emphasis">Thank you for your patience!</span>
        </p>

        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" id="progressBar"></div>
            </div>
        </div>

        <div class="task-status">
            <div class="task" id="task1">
                <div class="task-icon">⚙️</div>
                <div class="task-label">Environment Setup</div>
                <span class="status-dot"></span>
            </div>
            <div class="task" id="task2">
                <div class="task-icon">🔄</div>
                <div class="task-label">Database Migration</div>
                <span class="status-dot"></span>
            </div>
            <div class="task" id="task3">
                <div class="task-icon">📦</div>
                <div class="task-label">Package Installation</div>
                <span class="status-dot"></span>
            </div>
            <div class="task" id="task4">
                <div class="task-icon">🚀</div>
                <div class="task-label">Final Deployment</div>
                <span class="status-dot"></span>
            </div>
        </div>

        <div class="deployment-info">
            <p>We're working hard to get everything ready for you.</p>
            <div class="eta" id="eta">Estimated completion: Calculating...</div>
        </div>
    </div>
</div>

<script>
    // Progress simulation
    let progress = 0;
    const progressBar = document.getElementById('progressBar');
    const etaDisplay = document.getElementById('eta');
    const tasks = [
        document.getElementById('task1'),
        document.getElementById('task2'),
        document.getElementById('task3'),
        document.getElementById('task4')
    ];

    // Cloud generator
    function createClouds() {
        for (let i = 0; i < 3; i++) {
            const delay = Math.random() * 15;
            const cloud = document.createElement('div');
            cloud.className = `floating-cloud cloud${i+4}`;
            cloud.style.top = `${Math.random() * 80 + 10}%`;
            cloud.style.left = '-150px';
            cloud.style.transform = `scale(${Math.random() * 0.6 + 0.6})`;
            cloud.style.animation = `cloudDrift ${Math.random() * 15 + 25}s linear infinite`;
            cloud.style.animationDelay = `${delay}s`;
            document.querySelector('.main-container').appendChild(cloud);
        }
    }

    // Code particles generator
    function createCodeParticles() {
        const container = document.getElementById('codeParticles');
        const characters = ['{', '}', '[', ']', '(', ')', '<', '>', '/', '*', '+', '-', '=', ';', ':', '"', "'", '0', '1'];

        for (let i = 0; i < 50; i++) {
            const particle = document.createElement('div');
            particle.className = 'code-particle';

            // Random position
            const x = Math.random() * 100;
            const y = Math.random() * 100;

            // Random character
            const char = characters[Math.floor(Math.random() * characters.length)];

            // Random animation properties
            const duration = Math.random() * 20 + 10;
            const delay = Math.random() * 10;

            particle.textContent = char;
            particle.style.left = `${x}%`;
            particle.style.top = `${y}%`;
            particle.style.opacity = Math.random() * 0.4 + 0.1;
            particle.style.fontSize = `${Math.random() * 1 + 0.8}em`;

            // Create keyframes for each particle
            particle.style.animation = `fadeInOut ${duration}s ease-in-out ${delay}s infinite`;

            // Add custom animation
            const keyframes = `
                @keyframes fadeInOut {
                    0% { opacity: 0; transform: translateY(${Math.random() * 100}px); }
                    50% { opacity: ${Math.random() * 0.4 + 0.1}; transform: translateY(${Math.random() * -50}px); }
                    100% { opacity: 0; transform: translateY(${Math.random() * -100 - 50}px); }
                }
            `;

            const style = document.createElement('style');
            style.textContent = keyframes;
            document.head.appendChild(style);

            container.appendChild(particle);
        }
    }

    // Simulate task completion
    function updateTask(taskIndex) {
        tasks[taskIndex].classList.add('completed');
        tasks[taskIndex].querySelector('.task-icon').innerHTML = '✅';
    }

    function startInProgressTask(taskIndex) {
        tasks[taskIndex].classList.add('in-progress');
    }

    // Update progress bar and ETA
    function updateProgress() {
        // Simulate progress starting slowly, then accelerating, then slowing down
        if (progress < 20) {
            progress += 0.02;
        } else if (progress < 80) {
            progress += 0.05;
        } else {
            progress += 0.01;
        }

        if (progress >= 100) {
            progress = 100;
            clearInterval(progressInterval);
            progressBar.style.width = '100%';
            etaDisplay.textContent = 'Deployment complete! Redirecting soon...';

            // All tasks completed
            setTimeout(() => {
                updateTask(3); // Complete the final task
            }, 500);

            // Simulate redirect
            setTimeout(() => {
                etaDisplay.textContent = 'Redirecting to your application...';
                etaDisplay.style.animation = 'pulseDot 1s infinite';
            }, 3000);

            return;
        }

        progressBar.style.width = `${progress}%`;

        // Update ETA
        const remaining = Math.ceil((100 - progress) / 0.5) * 2;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        etaDisplay.textContent = `Estimated completion for each step: ${minutes}m ${seconds}s`;

        // Update tasks based on progress
        if (progress >= 10 && !tasks[0].classList.contains('completed')) {
            updateTask(0);
            startInProgressTask(1);
        }
        if (progress >= 40 && !tasks[1].classList.contains('completed')) {
            updateTask(1);
            startInProgressTask(2);
        }
        if (progress >= 75 && !tasks[2].classList.contains('completed')) {
            updateTask(2);
            startInProgressTask(3);
        }
    }

    // Initialize
    window.addEventListener('load', () => {
        createClouds();
        createCodeParticles();

        // Start first task as in progress
        startInProgressTask(0);

        // Start progress simulation
        progressInterval = setInterval(updateProgress, 200);
    });
</script>
</body>
</html>

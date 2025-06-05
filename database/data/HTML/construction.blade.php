<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BMS Under Development</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3F51B5;
            --primary-dark: #303F9F;
            --accent-color: #E91E63;
            --text-color: #212121;
            --secondary-text-color: #757575;
            --background-color: #FFFFFF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(45deg, #7680FF, #303F9F, #A1C4FD, #C2E9FB);
            background-size: 400% 400%;
            animation: gradientParty 10s ease infinite;
        }

        .main-container {
            position: relative;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .content {
            background: rgba(255, 255, 255, 0.8);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 800px;
            animation: bounceEntrance 1s ease-out;
            position: relative;
            z-index: 2;
        }

        h1 {
            font-size: 3em;
            font-weight: 900;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .highlight-text {
            font-size: 1.2em;
            color: var(--text-color);
            line-height: 1.8;
            padding: 15px;
            background: rgba(63, 81, 181, 0.05);
            border-radius: 8px;
            margin: 15px 0;
        }

        .emphasis {
            color: var(--accent-color);
            font-weight: 700;
        }

        .launch-alert {
            display: inline-block;
            margin-top: 10px;
            color: var(--accent-color);
            font-weight: 600;
            animation: pulseGlow 1s infinite alternate;
        }

        .units {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }

        .unit {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .unit:nth-child(1) {
            animation: dropIn 0.5s ease-out 1s forwards;
            opacity: 0;
        }

        .unit:nth-child(2) {
            animation: dropIn 0.5s ease-out 1.2s forwards;
            opacity: 0;
        }

        .unit:nth-child(3) {
            animation: dropIn 0.5s ease-out 1.5s forwards;
            opacity: 0;
        }

        .unit:nth-child(4) {
            animation: dropIn 0.5s ease-out 1.8s forwards;
            opacity: 0;
        }

        .flip-container {
            position: relative;
            width: 80px;
            height: 100px;
            perspective: 400px;
        }

        .flip {
            position: absolute;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .flip-front,
        .flip-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            font-size: 3em;
            color: white;
        }

        .flip-back {
            transform: rotateX(180deg);
            background: var(--primary-dark);
        }

        .flip.flipping {
            transform: rotateX(-180deg);
        }

        .unit-label {
            margin-top: 10px;
            font-size: 0.9em;
            color: var(--secondary-text-color);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .subscribe-form {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 0;
        }

        .subscribe-form input {
            padding: 10px 15px;
            border: 1px solid #ccc;
            border-radius: 4px 0 0 4px;
            font-size: 1em;
            outline: none;
            width: 200px;
        }

        .subscribe-form input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(63, 81, 181, 0.2);
        }

        .subscribe-form button {
            padding: 10px 20px;
            border: none;
            border-radius: 0 4px 4px 0;
            background: var(--accent-color);
            color: white;
            font-size: 1em;
            cursor: pointer;
            transition: background 0.3s;
        }

        .subscribe-form button:hover {
            background: #FF4081;
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 0 15px rgba(255, 64, 129, 0.7);
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

        @keyframes dropIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes gradientParty {
            0% {
                background-position: 0% 0%;
            }
            50% {
                background-position: 100% 100%;
            }
            100% {
                background-position: 0% 0%;
            }
        }

        @keyframes bounceEntrance {
            0% {
                transform: scale(0.1) rotate(-10deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.1) rotate(5deg);
                opacity: 1;
            }
            75% {
                transform: scale(0.95) rotate(-2deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
            }
        }

        @keyframes wobbleText {
            0%, 100% {
                transform: translateX(0);
            }
            15% {
                transform: translateX(-10px) rotate(-5deg);
            }
            30% {
                transform: translateX(8px) rotate(5deg);
            }
            45% {
                transform: translateX(-6px) rotate(-3deg);
            }
            60% {
                transform: translateX(4px) rotate(3deg);
            }
            75% {
                transform: translateX(-2px) rotate(-1deg);
            }
        }

        @keyframes swingIn {
            0% {
                transform: rotateX(-90deg);
                opacity: 0;
            }
            50% {
                transform: rotateX(20deg);
                opacity: 1;
            }
            100% {
                transform: rotateX(0deg);
            }
        }

        @keyframes glowText {
            0%, 100% {
                box-shadow: 0 0 5px rgba(63, 81, 181, 0.5);
            }
            50% {
                box-shadow: 0 0 15px rgba(63, 81, 181, 0.8);
            }
        }

        @keyframes rubberBand {
            0% {
                transform: scale(1);
            }
            30% {
                transform: scaleX(1.25) scaleY(0.75);
            }
            40% {
                transform: scaleX(0.75) scaleY(1.25);
            }
            60% {
                transform: scaleX(1.15) scaleY(0.85);
            }
            100% {
                transform: scale(1);
            }
        }

        @keyframes pulseGlow {
            0% {
                transform: scale(1);
                opacity: 0.7;
                text-shadow: 0 0 5px var(--accent-color);
            }
            100% {
                transform: scale(1.05);
                opacity: 1;
                text-shadow: 0 0 15px var(--accent-color);
            }
        }

        @keyframes spinBounce {
            0% {
                transform: rotate(0deg) scale(0.5);
                opacity: 0;
            }
            50% {
                transform: rotate(180deg) scale(1.2);
                opacity: 1;
            }
            75% {
                transform: rotate(360deg) scale(0.9);
            }
            100% {
                transform: rotate(360deg) scale(1);
            }
        }

        @keyframes flipShake {
            0% {
                transform: rotateX(0deg) scale(1);
            }
            25% {
                transform: rotateX(-90deg) scale(1.15);
            }
            50% {
                transform: rotateX(-180deg) scale(1.1);
            }
            75% {
                transform: rotateX(-180deg) scale(1.05);
            }
            100% {
                transform: rotateX(-180deg) scale(1);
            }
        }

        @keyframes bounceLabel {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        @keyframes zoomIn {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
                opacity: 1;
            }
            100% {
                transform: scale(1);
            }
        }


        @keyframes pulse {
            0% {
                opacity: 0.8;
            }
            50% {
                opacity: 1;
            }
            100% {
                opacity: 0.8;
            }
        }

        @media (max-width: 600px) {
            .content {
                padding: 20px;
                max-width: 100%;
            }

            h1 {
                font-size: 2em;
            }

            .highlight-text {
                font-size: 1em;
            }

            .flip-container {
                width: 60px;
                height: 80px;
            }

            .flip-front,
            .flip-back {
                font-size: 2.5em;
            }

            .subscribe-form {
                flex-direction: column;
                align-items: center;
            }

            .subscribe-form input,
            .subscribe-form button {
                width: 130%;
                border-radius: 4px;
                margin-bottom: 10px;
            }

            .subscribe-form button {
                border-radius: 4px;
            }
        }

        .floating-image {
            position: absolute;
            top: 0;
            left: 0;
            width: auto;
            height: 40%;
            opacity: 0;
            animation: float 210s infinite 2s ease-in-out;
            z-index: 1;
        }

        @keyframes float {
            0% {
                transform: translate(-20vw, -20vh) rotate(0deg) scale(1);
                opacity: 1;
            }
            5% {
                transform: translate(85vw, 10vh) rotate(10deg) scale(1.05);
                opacity: 0.9;
            }
            10% {
                transform: translate(15vw, 80vh) rotate(-8deg) scale(0.95);
                opacity: 0.85;
            }
            15% {
                transform: translate(95vw, 35vh) rotate(12deg) scale(1.08);
                opacity: 0.95;
            }
            20% {
                transform: translate(50vw, 90vh) rotate(-10deg) scale(0.98);
                opacity: 0.9;
            }
            25% {
                transform: translate(20vw, 20vh) rotate(6deg) scale(1.03);
                opacity: 1;
            }
            30% {
                transform: translate(90vw, 75vh) rotate(-12deg) scale(0.97);
                opacity: 0.85;
            }
            35% {
                transform: translate(30vw, 15vh) rotate(9deg) scale(1.06);
                opacity: 0.9;
            }
            40% {
                transform: translate(80vw, 95vh) rotate(-15deg) scale(0.99);
                opacity: 0.95;
            }
            45% {
                transform: translate(35vw, 50vh) rotate(7deg) scale(1.04);
                opacity: 1;
            }
            50% {
                transform: translate(95vw, 25vh) rotate(-9deg) scale(0.96);
                opacity: 0.9;
            }
            55% {
                transform: translate(25vw, 70vh) rotate(11deg) scale(1.02);
                opacity: 0.85;
            }
            60% {
                transform: translate(85vw, 40vh) rotate(-7deg) scale(1.07);
                opacity: 0.95;
            }
            65% {
                transform: translate(10vw, 85vh) rotate(13deg) scale(0.98);
                opacity: 0.9;
            }
            70% {
                transform: translate(70vw, 30vh) rotate(-11deg) scale(1.05);
                opacity: 1;
            }
            75% {
                transform: translate(40vw, 90vh) rotate(8deg) scale(0.97);
                opacity: 0.85;
            }
            80% {
                transform: translate(90vw, 20vh) rotate(-13deg) scale(1.03);
                opacity: 0.9;
            }
            85% {
                transform: translate(20vw, 60vh) rotate(10deg) scale(1.09);
                opacity: 0.95;
            }
            90% {
                transform: translate(75vw, 10vh) rotate(-6deg) scale(0.96);
                opacity: 0.9;
            }
            95% {
                transform: translate(50vw, 80vh) rotate(14deg) scale(1.04);
                opacity: 1;
            }
            98% {
                transform: translate(30vw, 45vh) rotate(-8deg) scale(1.01);
                opacity: 0.95;
            }
            100% {
                transform: translate(0vw, 0vh) rotate(0deg) scale(1);
                opacity: 1;
            }
        }

        @media (max-width: 600px) {
            body {
                overflow-x: hidden;
            }

            /* Shrink and stack the content box */
            .content {
                transform: scale(0.65);
                padding: 20px;
                margin: 0 10px;
                max-width: none;
                width: auto;
            }

            /* Headline */
            h1 {
                font-size: 2em;
                line-height: 1.2;
            }

            .highlight-text {
                font-size: 1em;
                padding: 10px;
            }

            .units {

                display: flex;
                flex-wrap: nowrap;
                justify-content: center;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }


            .unit {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .flip-container {
                width: 50px;
                height: 60px;
            }

            .flip-front, .flip-back {
                font-size: 2em;
            }

            .unit-label {
                font-size: 0.8em;
            }

            .subscribe-form {
                flex-direction: column;
                gap: 10px;
                margin-top: 30px;
            }

            .subscribe-form input,
            .subscribe-form button {
                width: 100%;
                border-radius: 4px;
                font-size: 0.9em;
            }

            .floating-image {
                height: 25%;
                opacity: 0.6;
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <img src="soon.png" class="floating-image" alt="Floating Image">

    <div class="content">
        <h1>I'm Busy Building Something Awesome!</h1>
        <p class="highlight-text">
            A new <span class="emphasis">BMS</span> is charging up! âš¡<br>
            <span class="launch-alert">Launch sequence initiated...</span> ðŸš€
        </p>
        <div class="units">
            <div class="unit">
                <div class="flip-container">
                    <div class="flip" id="days-flip">
                        <div class="flip-front">00</div>
                        <div class="flip-back">00</div>
                    </div>
                </div>
                <div class="unit-label">Days</div>
            </div>
            <div class="unit">
                <div class="flip-container">
                    <div class="flip" id="hours-flip">
                        <div class="flip-front">00</div>
                        <div class="flip-back">00</div>
                    </div>
                </div>
                <div class="unit-label">Hours</div>
            </div>
            <div class="unit">
                <div class="flip-container">
                    <div class="flip" id="minutes-flip">
                        <div class="flip-front">00</div>
                        <div class="flip-back">00</div>
                    </div>
                </div>
                <div class="unit-label">Minutes</div>
            </div>
            <div class="unit">
                <div class="flip-container">
                    <div class="flip" id="seconds-flip">
                        <div class="flip-front">00</div>
                        <div class="flip-back">00</div>
                    </div>
                </div>
                <div class="unit-label">Seconds</div>
            </div>
        </div>
        <form class="subscribe-form">
            <input type="email" placeholder="Enter your email for updates" title="Enter your email for updates">
            <button type="submit"
                    onclick="alert('Come on! Seriously? I am just a walk away, yet you wish to be notified by email!!!')">
                Subscribe
            </button>
        </form>
    </div>
</div>

<script>

    const launchDate = new Date('2025-05-20T12:30:00');

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function updateFlip(element, newValue) {
        const front = element.querySelector('.flip-front');
        const back = element.querySelector('.flip-back');

        if (front.textContent !== newValue) {
            back.textContent = newValue;
            element.classList.add('flipping');

            element.addEventListener('transitionend', () => {
                front.textContent = newValue;
                element.classList.remove('flipping');
            }, {once: true});
        }
    }

    function update() {
        const now = new Date();
        const diff = launchDate - now;

        if (diff <= 0) {
            document.querySelector('.units').innerHTML = '<h2>Launched! ðŸŽ‰</h2>';
            return;
        }

        const days = pad(Math.floor(diff / 86400000));
        const hours = pad(Math.floor(diff / 3600000) % 24);
        const minutes = pad(Math.floor(diff / 60000) % 60);
        const seconds = pad(Math.floor(diff / 1000) % 60);

        updateFlip(document.getElementById('days-flip'), days);
        updateFlip(document.getElementById('hours-flip'), hours);
        updateFlip(document.getElementById('minutes-flip'), minutes);
        updateFlip(document.getElementById('seconds-flip'), seconds);
    }

    setInterval(update, 1000);
    update();
</script>
</body>
</html>

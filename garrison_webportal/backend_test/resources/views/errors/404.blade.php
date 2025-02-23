<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Lost in the Void</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <style>
        body {
            background-color: rgb(34, 34, 34); /* Dark background color */
            background-image: url('{{ asset('images/background.png') }}');
            background-size: cover;
            background-position: center;
            color: rgb(255, 255, 255); /* White text color */
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        .container {
            position: relative;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-start; 
            align-items: center;
            padding-top: 20px;
        }
        h1 {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 2rem;
            color: rgb(51, 51, 51); /* Dark text color */
            animation: glitch 1s infinite, rgbColorChange 3s infinite;
            z-index: 1; /* Ensure the h1 tag is above other elements */
            background: none; /* Ensure no background color */
        }
        @keyframes glitch {
            0% {
                transform: translate(0);
            }
            20% {
                transform: translate(-2px, 2px);
            }
            40% {
                transform: translate(-2px, -2px);
            }
            60% {
                transform: translate(2px, 2px);
            }
            80% {
                transform: translate(2px, -2px);
            }
            100% {
                transform: translate(0);
            }
        }
        @keyframes rgbColorChange {
            0% {
                color: rgb(255, 0, 0); /* Red */
            }
            33% {
                color: rgb(0, 255, 0); /* Green */
            }
            66% {
                color: rgb(0, 0, 255); /* Blue */
            }
            100% {
                color: rgb(255, 0, 0); /* Red */
            }
        }
        .lost-character {
            position: absolute;
            top: 59%;
            width: 250px;
            height: 250px;
        }
        .center-text {          
            text-align: center;
            font-family: Verdana, Geneva, Tahoma, sans-serif;
            font-weight: bold;
            font-size: 1.5rem;
            color: rgb(51, 51, 51); /* Dark text color */
            margin-top: 100px;
        }
        .home-link {
            margin-top: 20px;
            font-size: 1.2rem;
            color: rgb(255, 255, 255); /* White text color */
            text-decoration: none;
            background-color: rgb(0, 123, 255); /* Bootstrap Primary Blue */
            padding: 10px 20px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .home-link:hover {
            background-color: rgb(0, 105, 217); /* Darker Blue */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 id="glitch-text">404 - Page Not Found</h1>
        <div class="center-text">
            <p>Oh No! It appears the page was lost in transit! Help them find the page.</p>
            <a href="{{ url('/') }}" class="home-link">Go to Home Page</a>
        </div>
        <img src="{{ asset('images/lost-character.gif') }}" class="lost-character" alt="Lost Page Character">
    </div>
    <script>
        const glitchText = document.getElementById('glitch-text');
        const text = glitchText.textContent;
        glitchText.innerHTML = '';

        for (let char of text) {
            const span = document.createElement('span');
            span.textContent = char;
            span.style.display = 'inline-block';
            span.style.transition = 'transform 0.3s';
            glitchText.appendChild(span);
        }

        setInterval(() => {
            const spans = glitchText.querySelectorAll('span');
            spans.forEach(span => {
                const random = Math.random();
                if (random < 0.5) {
                    span.style.transform = 'rotateY(180deg)';
                } else {
                    span.style.transform = 'rotateY(0deg)';
                }
            });
        }, 1000);
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Player</title>
    <!-- Plyr CSS -->
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: #111; 
            overflow: hidden; 
            width: 100vw; 
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .player-container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Custom Plyr theme tweaks for a more modern look */
        :root {
            --plyr-color-main: #3b82f6;
            --plyr-video-background: transparent;
        }
        .plyr {
            width: 100%;
            height: 100%;
        }
        .plyr__video-wrapper {
            height: 100% !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        video {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>

<div class="player-container">
    <video id="player" playsinline controls>
        <source src="<?php echo htmlspecialchars($_GET['src'] ?? ''); ?>" type="video/mp4" />
        Tu navegador no soporta HTML5 video.
    </video>
</div>

<!-- Plyr JS -->
<script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
<script>
    const player = new Plyr('#player', {
        controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
        keyboard: { focused: true, global: true }
    });
</script>
</body>
</html>

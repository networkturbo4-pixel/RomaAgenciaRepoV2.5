<?php
// modules/knowledge_base/helpers.php

if (!function_exists('kb_get_youtube_id')) {
    /**
     * Extrae el ID de un video de YouTube a partir de múltiples formatos de URL
     * (youtube.com/watch?v=..., youtu.be/..., youtube.com/embed/..., youtube.com/shorts/...)
     */
    function kb_get_youtube_id($url) {
        if (empty($url)) return null;
        $url = trim($url);

        // Si ya es un ID directo de 11 caracteres alfanuméricos
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return $url;
        }

        $patterns = [
            '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}

if (!function_exists('kb_get_youtube_thumbnail')) {
    function kb_get_youtube_thumbnail($video_id, $quality = 'hqdefault') {
        if (empty($video_id)) return null;
        return "https://img.youtube.com/vi/{$video_id}/{$quality}.jpg";
    }
}

if (!function_exists('kb_get_youtube_embed')) {
    function kb_get_youtube_embed($video_id) {
        if (empty($video_id)) return null;
        return "https://www.youtube-nocookie.com/embed/{$video_id}?rel=0&modestbranding=1";
    }
}

if (!function_exists('kb_slugify')) {
    function kb_slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'articulo-' . time() : $text;
    }
}

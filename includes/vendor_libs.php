<?php
// includes/vendor_libs.php
// Librerías pesadas servidas localmente (sin CDN). Se cargan solo en los módulos que las usan;
// en el resto se cargan bajo demanda con window.romaLoadLibs([...]) (ver header.php).

if (!function_exists('roma_lib_eager')) {
    function roma_lib_eager(string $lib): bool {
        static $map = [
            'quill'    => ['design_tasks', 'knowledge_base', 'task_manager', 'work_orders'],
            'tagify'   => ['audiovisual', 'desarrollo_marca', 'task_manager'],
            'fancybox' => ['design_tasks', 'knowledge_base'],
            'flatpickr' => [],
        ];
        $module = $_GET['module'] ?? 'dashboard';
        return in_array($module, $map[$lib] ?? [], true);
    }
}

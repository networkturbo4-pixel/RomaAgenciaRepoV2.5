<?php
// includes/TaskSyncHelper.php — Sincronizador Bidireccional entre Calendario (Meses/Posts) y Tareas (tm_tasks)

class TaskSyncHelper {

    /**
     * Sincroniza el estado del mes hacia las tareas vinculadas cuando se evalúa el progreso de los posts (100%).
     * Se llama al crear, editar o actualizar el estado de los posts en month_board.
     * 
     * @param PDO $db Conexión a la base de datos
     * @param int $monthId ID del mes en project_months
     */
    public static function syncMonthPostsCompletion($db, $monthId) {
        $monthId = (int)$monthId;
        if ($monthId <= 0) return;

        try {
            $st = $db->prepare("
                SELECT COUNT(*) as total, 
                       SUM(CASE WHEN status IN ('Aprobado', 'Publicado') THEN 1 ELSE 0 END) as completed,
                       SUM(CASE WHEN status = 'Publicado' THEN 1 ELSE 0 END) as published
                FROM month_posts 
                WHERE month_id = ?
            ");
            $st->execute([$monthId]);
            $stats = $st->fetch(PDO::FETCH_ASSOC);
            $total = (int)($stats['total'] ?? 0);
            $completed = (int)($stats['completed'] ?? 0);

            if ($total > 0 && $completed >= $total) {
                // 100% de los posts están listos (Aprobados o Publicados)
                $db->prepare("UPDATE project_months SET status = 'finalizado' WHERE id = ?")->execute([$monthId]);
                // Marcar tareas vinculadas a este mes como completadas en Task Manager
                $db->prepare("
                    UPDATE tm_tasks 
                    SET status = 'completed' 
                    WHERE project_month_id = ? 
                      AND status NOT IN ('completed', 'approved', 'archived')
                ")->execute([$monthId]);
            } elseif ($total > 0 && $completed < $total) {
                // El mes tiene publicaciones pendientes: si estaba en 'finalizado', volver a 'en progreso'
                $db->prepare("
                    UPDATE project_months 
                    SET status = 'en progreso' 
                    WHERE id = ? AND LOWER(status) = 'finalizado'
                ")->execute([$monthId]);
            }
        } catch (Throwable $e) {
            error_log("TaskSyncHelper::syncMonthPostsCompletion error: " . $e->getMessage());
        }
    }

    /**
     * Sincroniza el estado explícito de un mes hacia las tareas vinculadas.
     * Se llama cuando el usuario cambia el estado del mes en project_board (editar mes o cambio rápido).
     * 
     * @param PDO $db Conexión a la base de datos
     * @param int $monthId ID del mes en project_months
     * @param string $newMonthStatus Nuevo estado del mes ('finalizado', 'en progreso', 'pendiente')
     */
    public static function syncMonthStatusToTasks($db, $monthId, $newMonthStatus) {
        $monthId = (int)$monthId;
        if ($monthId <= 0 || empty($newMonthStatus)) return;
        $statusClean = strtolower(trim($newMonthStatus));

        try {
            if (in_array($statusClean, ['finalizado', 'terminado'])) {
                $db->prepare("UPDATE project_months SET status = 'finalizado' WHERE id = ?")->execute([$monthId]);
                $db->prepare("
                    UPDATE tm_tasks 
                    SET status = 'completed' 
                    WHERE project_month_id = ? 
                      AND status NOT IN ('completed', 'approved', 'archived')
                ")->execute([$monthId]);
            } elseif (in_array($statusClean, ['en progreso', 'activo'])) {
                $db->prepare("UPDATE project_months SET status = 'en progreso' WHERE id = ?")->execute([$monthId]);
                $db->prepare("
                    UPDATE tm_tasks 
                    SET status = 'pending' 
                    WHERE project_month_id = ? 
                      AND status = 'new'
                ")->execute([$monthId]);
            } elseif (in_array($statusClean, ['pendiente'])) {
                $db->prepare("UPDATE project_months SET status = 'pendiente' WHERE id = ?")->execute([$monthId]);
                $db->prepare("
                    UPDATE tm_tasks 
                    SET status = 'new' 
                    WHERE project_month_id = ? 
                      AND status = 'pending'
                ")->execute([$monthId]);
            }
        } catch (Throwable $e) {
            error_log("TaskSyncHelper::syncMonthStatusToTasks error: " . $e->getMessage());
        }
    }

    /**
     * Sincroniza el estado de una tarea hacia el mes de calendario vinculado.
     * Se llama al mover una tarea en el Kanban (drag&drop), al guardar en el modal, o al marcar completada.
     * 
     * @param PDO $db Conexión a la base de datos
     * @param int $taskId ID de la tarea en tm_tasks
     * @param string $newTaskStatus Nuevo estado de la tarea ('completed', 'approved', 'pending', 'new')
     */
    public static function syncTaskStatusToMonth($db, $taskId, $newTaskStatus) {
        $taskId = (int)$taskId;
        if ($taskId <= 0 || empty($newTaskStatus)) return;
        $statusClean = strtolower(trim($newTaskStatus));

        try {
            $st = $db->prepare("SELECT project_month_id FROM tm_tasks WHERE id = ?");
            $st->execute([$taskId]);
            $monthId = (int)$st->fetchColumn();

            if ($monthId <= 0) return;

            if (in_array($statusClean, ['completed', 'approved'])) {
                // Verificar si quedan tareas pendientes de este mes
                $stPending = $db->prepare("
                    SELECT COUNT(*) 
                    FROM tm_tasks 
                    WHERE project_month_id = ? 
                      AND id != ? 
                      AND status NOT IN ('completed', 'approved', 'archived')
                ");
                $stPending->execute([$monthId, $taskId]);
                $pendingCount = (int)$stPending->fetchColumn();

                if ($pendingCount === 0) {
                    // Todas las tareas de este mes están finalizadas -> Marcar mes como 'finalizado'
                    $db->prepare("UPDATE project_months SET status = 'finalizado' WHERE id = ?")->execute([$monthId]);
                }
            } elseif ($statusClean === 'pending') {
                // La tarea pasó a 'En Curso' -> El mes debe reflejarse 'en progreso'
                $db->prepare("
                    UPDATE project_months 
                    SET status = 'en progreso' 
                    WHERE id = ? AND LOWER(status) != 'en progreso'
                ")->execute([$monthId]);
            } elseif ($statusClean === 'new') {
                // Si la tarea pasó a 'new' (Por Iniciar), verificar si no queda ninguna tarea en progreso o terminada
                $stActive = $db->prepare("
                    SELECT COUNT(*) 
                    FROM tm_tasks 
                    WHERE project_month_id = ? 
                      AND id != ? 
                      AND status IN ('pending', 'completed', 'approved')
                ");
                $stActive->execute([$monthId, $taskId]);
                $activeCount = (int)$stActive->fetchColumn();
                if ($activeCount === 0) {
                    $db->prepare("UPDATE project_months SET status = 'pendiente' WHERE id = ?")->execute([$monthId]);
                }
            }
        } catch (Throwable $e) {
            error_log("TaskSyncHelper::syncTaskStatusToMonth error: " . $e->getMessage());
        }
    }
}

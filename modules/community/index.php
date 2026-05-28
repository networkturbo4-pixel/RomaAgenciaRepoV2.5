<?php
// modules/community/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

require_once 'includes/header.php';

try {
    $stmtProjects = $db->query("
        SELECT p.id, w.brand_name 
        FROM projects p
        JOIN work_orders w ON p.work_order_id = w.id
        WHERE p.status = 'active'
        ORDER BY w.brand_name ASC
    ");
    $projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar proyectos: " . $e->getMessage();
}
?>
<!-- FullCalendar Scripts -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js'></script>

<style>
    .community-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-surface);
        padding: 1.5rem 2rem;
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.05);
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    /* FullCalendar Modernization */
    .fc {
        background: var(--bg-surface);
        padding: 1.5rem;
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.05);
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        font-family: var(--font-family);
    }
    .fc-theme-standard td, .fc-theme-standard th {
        border-color: var(--border-color) !important;
        border-width: 1px;
    }
    .fc-scrollgrid { border: none !important; }
    
    /* Toolbar */
    .fc .fc-toolbar-title {
        font-weight: 800;
        font-size: 1.5rem;
        letter-spacing: -0.5px;
        color: var(--color-title);
    }
    .fc .fc-button-primary {
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        font-weight: 600;
        border-radius: 12px;
        text-transform: capitalize;
        padding: 0.5rem 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        transition: all 0.2s;
    }
    .fc .fc-button-primary:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }
    .fc .fc-button-primary:not(:disabled).fc-button-active, 
    .fc .fc-button-primary:not(:disabled):active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
    }
    .fc .fc-button-group > .fc-button {
        border-radius: 0;
    }
    .fc .fc-button-group > .fc-button:first-child {
        border-top-left-radius: 12px;
        border-bottom-left-radius: 12px;
    }
    .fc .fc-button-group > .fc-button:last-child {
        border-top-right-radius: 12px;
        border-bottom-right-radius: 12px;
    }
    .fc .fc-today-button { margin-left: 0.5rem !important; border-radius: 12px !important; }

    /* Day Headers */
    .fc-col-header-cell {
        padding: 0.75rem 0;
        background: rgba(0,0,0,0.02);
    }
    [data-theme="dark"] .fc-col-header-cell { background: rgba(255,255,255,0.02); }
    .fc-col-header-cell-cushion {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        color: var(--text-muted) !important;
        text-decoration: none !important;
    }

    /* Day Numbers */
    .fc-daygrid-day-number {
        font-weight: 600;
        font-size: 0.9rem;
        padding: 8px !important;
        color: var(--text-color);
        text-decoration: none !important;
    }
    /* Highlight today's date number */
    .fc-day-today .fc-daygrid-day-top {
        justify-content: center;
        margin-top: 4px;
    }
    .fc-day-today .fc-daygrid-day-number {
        background: var(--primary-color);
        color: white;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 !important;
        box-shadow: 0 4px 8px rgba(79, 70, 229, 0.4);
    }
    .fc-day-today {
        background: transparent !important;
    }

    /* Custom Event Card Styles */
    .fc-daygrid-event-harness { margin-bottom: 8px !important; }
    .fc-event {
        background: transparent !important;
        border: none !important;
        cursor: pointer;
        padding: 0 6px;
    }
    .post-card-event {
        display: flex;
        flex-direction: column;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0,0,0,0.04);
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        min-height: 85px;
    }
    .post-card-event:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        z-index: 10;
        border-color: var(--primary-color);
    }
    .post-card-img {
        height: 65px;
        width: 100%;
        object-fit: cover;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #94a3b8;
        position: relative;
        border-bottom: 1px solid var(--border-color);
    }
    [data-theme="dark"] .post-card-img {
        background: #0f172a;
    }
    .post-card-body {
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        background: var(--bg-surface);
    }
    .post-card-title {
        font-weight: 700;
        font-size: 0.75rem;
        color: var(--color-title);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.2;
    }
    .post-card-status {
        display: inline-block;
        padding: 3px 6px;
        border-radius: 6px;
        font-size: 0.6rem;
        font-weight: 800;
        color: white;
        text-transform: uppercase;
        align-self: flex-start;
        letter-spacing: 0.5px;
    }
    .ref-badge {
        position: absolute;
        top: 6px;
        left: 6px;
        background: rgba(245, 158, 11, 0.95);
        color: white;
        font-size: 0.6rem;
        padding: 2px 6px;
        border-radius: 6px;
        font-weight: 800;
        backdrop-filter: blur(4px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    /* Inputs in header */
    .form-control {
        border-radius: 10px;
        padding: 0.6rem 1rem;
        border: 1px solid var(--border-color);
        background: var(--bg-color);
        font-weight: 500;
        color: var(--color-title);
        transition: all 0.2s;
    }
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }
</style>

<div class="community-header">
    <div>
        <h1 style="margin: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 0.5rem; color: var(--color-title);">
            <i class="ph ph-calendar-check" style="color: var(--primary-color);"></i> Community
        </h1>
        <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;">Calendario dinámico de contenidos para todos tus proyectos</p>
    </div>
    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div>
            <label for="projectFilter" style="font-weight: 600; color: var(--text-muted); font-size: 0.85rem; display: block; margin-bottom: 2px;">Proyecto:</label>
            <select id="projectFilter" class="form-control" style="width: 200px; cursor: pointer;">
                <option value="all">Todos los proyectos</option>
                <?php foreach ($projects as $proj): ?>
                    <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['brand_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="monthFilter" style="font-weight: 600; color: var(--text-muted); font-size: 0.85rem; display: block; margin-bottom: 2px;">Mes:</label>
            <select id="monthFilter" class="form-control" style="width: 200px; cursor: pointer;" disabled>
                <option value="all">Todos los meses</option>
            </select>
        </div>
    </div>
</div>

<div id='calendar'></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var projectFilter = document.getElementById('projectFilter');
    var monthFilter = document.getElementById('monthFilter');

    // Load months when project changes
    projectFilter.addEventListener('change', function() {
        var projectId = this.value;
        if (projectId === 'all') {
            monthFilter.innerHTML = '<option value="all">Todos los meses</option>';
            monthFilter.disabled = true;
            calendar.refetchEvents();
        } else {
            monthFilter.disabled = false;
            fetch('modules/community/ajax_get_project_months.php?project_id=' + projectId)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="all">Todos los meses</option>';
                        // If months exist, we also want to record their start_date to navigate the calendar
                        window.projectMonthsData = data.months; 
                        data.months.forEach(m => {
                            html += `<option value="${m.id}">${m.name}</option>`;
                        });
                        monthFilter.innerHTML = html;
                        calendar.refetchEvents();
                    }
                });
        }
    });

    monthFilter.addEventListener('change', function() {
        var monthId = this.value;
        if (monthId !== 'all' && window.projectMonthsData) {
            // Find the month and navigate the calendar to its start_date
            var selectedMonth = window.projectMonthsData.find(m => m.id == monthId);
            if (selectedMonth && selectedMonth.start_date) {
                calendar.gotoDate(selectedMonth.start_date);
            }
        }
        calendar.refetchEvents();
    });

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: 'dayGridMonth',
        height: 'calc(100vh - 220px)',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        editable: true,
        droppable: true,
        eventContent: function(arg) {
            let props = arg.event.extendedProps;
            let imgHtml = '';
            if (props.thumbnail) {
                imgHtml = `<img src="${props.thumbnail}" class="post-card-img" alt="img">`;
            } else if (props.isVideo) {
                imgHtml = `<div class="post-card-img"><i class="ph ph-video"></i></div>`;
            } else {
                imgHtml = `<div class="post-card-img"><i class="ph ph-image-square"></i></div>`;
            }

            let refHtml = props.isReference ? `<div class="ref-badge">Ref</div>` : '';

            let html = `
                <div class="post-card-event">
                    <div style="position: relative;">
                        ${imgHtml}
                        ${refHtml}
                    </div>
                    <div class="post-card-body">
                        <div class="post-card-title">${arg.event.title}</div>
                        <div class="post-card-status" style="background-color: ${props.statusColor}">${props.status}</div>
                    </div>
                </div>
            `;
            return { html: html };
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            var projectId = projectFilter.value;
            var monthId = monthFilter.value;
            fetch('modules/community/ajax_get_posts.php?project_id=' + projectId + '&month_id=' + monthId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        successCallback(data.events);
                    } else {
                        console.error('Error fetching events:', data.error);
                        failureCallback(data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    failureCallback(error);
                });
        },
        eventDrop: function(info) {
            const postId = info.event.id;
            const newDate = info.event.startStr.split('T')[0];
            
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('post_date', newDate);

            fetch('modules/community/ajax_update_post_date.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error al actualizar la fecha: ' + (data.error || 'Desconocido'));
                    info.revert();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de red al actualizar la fecha.');
                info.revert();
            });
        },
        eventClick: function(info) {
            const monthId = info.event.extendedProps.month_id;
            window.location.href = `index.php?module=month_board&id=${monthId}`;
        }
    });

    calendar.render();
});
</script>

<?php require_once 'includes/footer.php'; ?>

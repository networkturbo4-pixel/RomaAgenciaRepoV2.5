const RrhhModule = (function() {
    let employees = [];
    let filteredEmployees = [];
    let currentFilter = 'Todos';
    let searchQuery = '';

    const tbody = document.querySelector('#employees-table tbody');
    const formModal = document.getElementById('modal-employee-form');
    const deleteModal = document.getElementById('modal-delete-employee');
    const paymentsModal = document.getElementById('modal-payments');

    function init() {
        fetchEmployees();
        setupEventListeners();
    }

    function setupEventListeners() {
        // Search
        const searchInput = document.querySelector('.search-box input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                searchQuery = e.target.value.toLowerCase();
                applyFilters();
            });
        }

        // Filters
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                const target = e.currentTarget;
                target.classList.add('active');
                currentFilter = target.textContent.trim();
                applyFilters();
            });
        });
    }

    function fetchEmployees() {
        fetch('index.php?module=admin&action=ajax_get_employees')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    employees = data.data;
                    applyFilters();
                }
            })
            .catch(err => console.error(err));
    }

    function applyFilters() {
        filteredEmployees = employees.filter(emp => {
            const matchesSearch = emp.name.toLowerCase().includes(searchQuery) ||
                                  emp.email.toLowerCase().includes(searchQuery) ||
                                  emp.role.toLowerCase().includes(searchQuery);
            
            let matchesStatus = true;
            if (currentFilter === 'Activos') matchesStatus = emp.status === 'Activo';
            if (currentFilter === 'Inactivos') matchesStatus = emp.status === 'Inactivo';
            if (currentFilter === 'Pendientes') matchesStatus = emp.status === 'Pendiente';

            return matchesSearch && matchesStatus;
        });
        renderTable();
    }

    function renderTable() {
        if (!tbody) return;
        tbody.innerHTML = '';

        if (filteredEmployees.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 2rem;">No se encontraron empleados.</td></tr>`;
            return;
        }

        filteredEmployees.forEach(emp => {
            const tr = document.createElement('tr');
            
            let statusClass = 'status-' + emp.status.toLowerCase();
            let iconClass = '';
            if (emp.status === 'Activo') iconClass = 'ph-check-circle';
            if (emp.status === 'Inactivo') iconClass = 'ph-x-circle';
            if (emp.status === 'Pendiente') iconClass = 'ph-dots-three-circle';

            tr.innerHTML = `
                <td data-label="USUARIO">
                    <div class="user-cell">
                        <div class="user-avatar" style="background-color: ${emp.color};">
                            ${emp.initials}
                        </div>
                        <div class="rrhh-user-info">
                            <span class="user-name">${emp.name}</span>
                            <span class="user-email">${emp.email}</span>
                        </div>
                    </div>
                </td>
                <td data-label="ROL / DEPARTAMENTO">
                    <div class="role-info">
                        <span class="role-title">${emp.role}</span>
                        <span class="role-dept">${emp.department}</span>
                    </div>
                </td>
                <td data-label="ESTADO">
                    <span class="status-badge ${statusClass}">
                        <i class="ph ${iconClass}"></i> ${emp.status}
                    </span>
                </td>
                <td data-label="SALARIO / CONTRATACIÓN">
                    <div class="role-info">
                        <span class="role-title">S/ ${parseFloat(emp.salary).toFixed(2)}</span>
                        <span class="role-dept">${emp.hire_date}</span>
                    </div>
                </td>
                <td data-label="ACCIONES" style="text-align: right;">
                    <div class="action-buttons-rrhh">
                        <button class="btn-action-icon" title="Pagos y Boletas" onclick="RrhhModule.openPaymentsModal(${emp.id})">
                            <i class="ph ph-wallet"></i>
                        </button>
                        <button class="btn-action-icon" title="Editar Empleado" onclick="RrhhModule.openModal(${emp.id})">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <button class="btn-action-icon" title="Eliminar" style="color: var(--danger-color);" onclick="RrhhModule.confirmDelete(${emp.id})">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
        
        document.getElementById('pagination-info-text').textContent = `Mostrando ${filteredEmployees.length} empleados`;
    }

    function openModal(id = 0) {
        document.getElementById('emp_form').reset();
        document.getElementById('emp_id').value = id;
        
        if (id === 0) {
            document.getElementById('modal-title-emp').textContent = 'Nuevo Empleado';
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('emp_hire_date').value = today;
        } else {
            document.getElementById('modal-title-emp').textContent = 'Editar Empleado';
            const emp = employees.find(e => e.id == id);
            if (emp) {
                document.getElementById('emp_name').value = emp.name;
                document.getElementById('emp_dni').value = emp.dni || '';
                document.getElementById('emp_email').value = emp.email;
                document.getElementById('emp_phone').value = emp.phone || '';
                document.getElementById('emp_role').value = emp.role;
                document.getElementById('emp_department').value = emp.department;
                document.getElementById('emp_status').value = emp.status;
                document.getElementById('emp_salary').value = emp.salary;
                document.getElementById('emp_hire_date').value = emp.hire_date;
            }
        }
        formModal.classList.add('active');
    }

    function closeModal() {
        formModal.classList.remove('active');
    }

    function saveEmployee() {
        const formData = new FormData(document.getElementById('emp_form'));
        
        fetch('index.php?module=admin&action=ajax_save_employee', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal();
                fetchEmployees();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => console.error(err));
    }

    let deleteId = 0;
    function confirmDelete(id) {
        deleteId = id;
        deleteModal.classList.add('active');
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('active');
        deleteId = 0;
    }

    function deleteEmployee() {
        if (deleteId === 0) return;
        
        fetch('index.php?module=admin&action=ajax_delete_employee', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: deleteId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeDeleteModal();
                fetchEmployees();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => console.error(err));
    }

    // Payments Logic
    function openPaymentsModal(empId) {
        const emp = employees.find(e => e.id == empId);
        if (!emp) return;
        
        document.getElementById('payments-emp-name').textContent = emp.name;
        document.getElementById('pay_employee_id').value = emp.id;
        
        // Auto-fill values
        document.getElementById('payment_form').reset();
        document.getElementById('pay_amount').value = parseFloat(emp.salary).toFixed(2);
        document.getElementById('pay_date').value = new Date().toISOString().split('T')[0];
        
        loadPaymentsHistory(emp.id);
        paymentsModal.classList.add('active');
    }

    function closePaymentsModal() {
        paymentsModal.classList.remove('active');
    }

    function loadPaymentsHistory(empId) {
        const tbody = document.getElementById('payments-history-tbody');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Cargando...</td></tr>';
        
        fetch('index.php?module=admin&action=ajax_get_payments&employee_id=' + empId)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No hay pagos registrados.</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = '';
                    data.data.forEach(pay => {
                        const tr = document.createElement('tr');
                        
                        let actionsHtml = `<span style="color:var(--text-muted); font-size:0.8rem;">Sin comprobante</span>`;
                        if (pay.voucher_url) {
                            actionsHtml = `
                                <a href="${pay.voucher_url}" target="_blank" class="btn-action-icon" style="display:inline-flex;" title="Ver PDF/Imagen">
                                    <i class="ph ph-eye"></i>
                                </a>
                                <button type="button" class="btn-action-icon" style="display:inline-flex;" title="Copiar Enlace" onclick="navigator.clipboard.writeText(window.location.origin + '/' + '${pay.voucher_url}')">
                                    <i class="ph ph-share-network"></i>
                                </button>
                            `;
                        }
                        
                        tr.innerHTML = `
                            <td data-label="FECHA">${pay.payment_date}</td>
                            <td data-label="CONCEPTO">${pay.concept}</td>
                            <td data-label="MONTO" style="font-weight:600; color:var(--text-main);">S/ ${parseFloat(pay.amount).toFixed(2)}</td>
                            <td data-label="COMPROBANTE" style="text-align: right;">
                                <div style="display:flex; justify-content:flex-end; gap:0.25rem;">
                                    ${actionsHtml}
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:red;">Error al cargar pagos.</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:red;">Error de red.</td></tr>`;
            });
    }

    function savePayment() {
        const form = document.getElementById('payment_form');
        const formData = new FormData(form);
        
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Guardando...';
        btn.disabled = true;
        
        fetch('index.php?module=admin&action=ajax_save_payment', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (data.success) {
                // Refresh history
                loadPaymentsHistory(formData.get('employee_id'));
                form.reset();
                // re-fill auto fields
                const emp = employees.find(e => e.id == formData.get('employee_id'));
                if(emp) {
                    document.getElementById('pay_amount').value = parseFloat(emp.salary).toFixed(2);
                    document.getElementById('pay_date').value = new Date().toISOString().split('T')[0];
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            console.error(err);
            alert('Error de red al guardar el pago.');
        });
    }

    return {
        init,
        openModal,
        closeModal,
        saveEmployee,
        confirmDelete,
        closeDeleteModal,
        deleteEmployee,
        openPaymentsModal,
        closePaymentsModal,
        savePayment
    };
})();

document.addEventListener('DOMContentLoaded', RrhhModule.init);

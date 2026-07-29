const RrhhModule = (function() {
    let employees = [];
    let filteredEmployees = [];
    let currentFilter = 'Todos';
    let searchQuery = '';
    let currentEmpSalary = 0;
    let currentEmpId = 0;
    let currentEmployeePayments = [];

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
        
        // Calculadora de pago
        const calcDays = document.getElementById('calc_days');
        const calcExtra = document.getElementById('calc_extra_hours');
        const calcBaseSalary = document.getElementById('calc_base_salary');
        const calcBonuses = document.getElementById('calc_bonuses');
        const calcDiscounts = document.getElementById('calc_discounts');
        if (calcDays && calcExtra && calcBaseSalary) {
            calcDays.addEventListener('input', updateCalculator);
            calcExtra.addEventListener('input', updateCalculator);
            if (calcBonuses) calcBonuses.addEventListener('input', updateCalculator);
            if (calcDiscounts) calcDiscounts.addEventListener('input', updateCalculator);
            calcBaseSalary.addEventListener('input', () => {
                currentEmpSalary = parseFloat(calcBaseSalary.value) || 0;
                updateCalculator();
            });
        }
        
        // Botón Compartir Historial
        const btnShareHistory = document.getElementById('btn-share-history');
        if (btnShareHistory) {
            btnShareHistory.addEventListener('click', () => {
                if (currentEmpId > 0) {
                    let shareLink = window.location.origin + window.location.pathname + '?module=public&action=employee_history&id=' + currentEmpId;
                    navigator.clipboard.writeText(shareLink);
                    alert('Enlace del historial de pagos copiado al portapapeles');
                }
            });
        }

        // OCR for payment voucher (Desactivado para RRHH para no sobreescribir datos)
        const payVoucher = document.getElementById('pay_voucher');
        if (payVoucher) {
            payVoucher.addEventListener('change', (e) => {
                // Solo adjuntar el archivo, sin OCR
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
                document.getElementById('emp_work_start').value = emp.work_start || '';
                document.getElementById('emp_work_end').value = emp.work_end || '';
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
        
        currentEmpId = emp.id;
        currentEmpSalary = parseFloat(emp.salary) || 0;
        
        document.getElementById('payments-emp-name').textContent = emp.name;
        document.getElementById('pay_employee_id').value = emp.id;
        
        cancelPaymentEdit();
        
        loadPaymentsHistory(emp.id);
        paymentsModal.classList.add('active');
    }

    function cancelPaymentEdit() {
        document.getElementById('payment_form').reset();
        document.getElementById('pay_id').value = "0";
        document.getElementById('calc_days').value = 0;
        document.getElementById('calc_extra_hours').value = 0;
        if (document.getElementById('calc_bonuses')) document.getElementById('calc_bonuses').value = 0;
        if (document.getElementById('calc_discounts')) document.getElementById('calc_discounts').value = 0;
        document.getElementById('calc_base_salary').value = currentEmpSalary.toFixed(2);
        document.getElementById('pay_date').value = new Date().toISOString().split('T')[0];
        document.querySelector('#payment_form button[type="submit"]').innerHTML = '<i class="ph ph-check"></i> Registrar Pago';
        document.getElementById('btn-cancel-edit').style.display = 'none';
        updateCalculator();
    }

    function updateCalculator() {
        const extraDays = parseFloat(document.getElementById('calc_days').value) || 0;
        const extraHours = parseFloat(document.getElementById('calc_extra_hours').value) || 0;
        const bonuses = document.getElementById('calc_bonuses') ? parseFloat(document.getElementById('calc_bonuses').value) || 0 : 0;
        const discounts = document.getElementById('calc_discounts') ? parseFloat(document.getElementById('calc_discounts').value) || 0 : 0;
        
        const dailyRate = currentEmpSalary / 30;
        const hourlyRate = dailyRate / 8; // asumiendo 8 horas al día
        
        document.getElementById('lbl_daily_rate').textContent = 'S/ ' + dailyRate.toFixed(2);
        document.getElementById('lbl_hourly_rate').textContent = 'S/ ' + hourlyRate.toFixed(2);
        
        const extraAmount = (extraDays * dailyRate) + (extraHours * hourlyRate) + bonuses - discounts;
        const total = currentEmpSalary + extraAmount;
        
        document.getElementById('pay_extra_amount').value = extraAmount.toFixed(2);
        document.getElementById('pay_amount').value = total.toFixed(2);
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
                if(data.success) {
                    currentEmployeePayments = data.data;
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay pagos registrados.</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = '';
                    data.data.forEach(pay => {
                        const tr = document.createElement('tr');
                        
                        let actionsHtml = `
                            <button type="button" class="btn btn-outline" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border-color:transparent; color: var(--primary);" onclick="RrhhModule.editPayment(${pay.id})" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                            <button type="button" class="btn btn-outline" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border-color:transparent; color: #ef4444;" onclick="RrhhModule.deletePayment(${pay.id})" title="Eliminar"><i class="ph ph-trash"></i></button>
                        `;

                        let st = (pay.status || 'Pagado').toLowerCase();
                        let badgeClass = st === 'pendiente' ? 'background: rgba(245, 158, 11, 0.1); color: var(--warning-color);' : 'background: rgba(16, 185, 129, 0.1); color: var(--secondary-color);';
                        let icon = st === 'pendiente' ? 'ph-clock' : 'ph-check-circle';
                        let statusHtml = `<span style="display:inline-flex; align-items:center; gap:0.25rem; padding:0.25rem 0.6rem; border-radius:9999px; font-size:0.75rem; font-weight:600; ${badgeClass}"><i class="ph ${icon}"></i> ${pay.status || 'Pagado'}</span>`;
                        
                        tr.innerHTML = `
                            <td data-label="FECHA">${pay.payment_date}</td>
                            <td data-label="CONCEPTO">${pay.concept}</td>
                            <td data-label="MONTO" style="font-weight:600; color:var(--text-main);">S/ ${parseFloat(pay.amount).toFixed(2)}</td>
                            <td data-label="ESTADO">${statusHtml}</td>
                            <td data-label="ACCIONES" style="text-align: right;">
                                <div style="display:flex; justify-content:flex-end; gap:0.25rem;">
                                    ${actionsHtml}
                                </div>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:red;">Error al cargar pagos.</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:red;">Error de red.</td></tr>`;
            });
    }

    function editPayment(payId) {
        const pay = currentEmployeePayments.find(p => p.id == payId);
        if (!pay) return;

        document.getElementById('pay_id').value = pay.id;
        document.getElementById('pay_concept').value = pay.concept;
        document.getElementById('pay_date').value = pay.payment_date;
        document.getElementById('pay_status').value = pay.status || 'Pagado';
        
        document.getElementById('calc_days').value = 0;
        document.getElementById('calc_extra_hours').value = 0;
        if (document.getElementById('calc_bonuses')) document.getElementById('calc_bonuses').value = 0;
        if (document.getElementById('calc_discounts')) document.getElementById('calc_discounts').value = 0;
        
        let payAmount = parseFloat(pay.amount) || 0;
        let extraAmount = parseFloat(pay.extra_payment) || 0;
        let basePart = payAmount - extraAmount;
        
        let savedExtraDays = parseFloat(pay.extra_days) || 0;
        let savedExtraHours = parseFloat(pay.extra_hours) || 0;
        let savedBonuses = parseFloat(pay.bonuses) || 0;
        let savedDiscounts = parseFloat(pay.discounts) || 0;
        
        document.getElementById('calc_days').value = savedExtraDays;
        document.getElementById('calc_extra_hours').value = savedExtraHours;
        if (document.getElementById('calc_bonuses')) document.getElementById('calc_bonuses').value = savedBonuses;
        if (document.getElementById('calc_discounts')) document.getElementById('calc_discounts').value = savedDiscounts;
        
        if (basePart > 0) {
            document.getElementById('calc_base_salary').value = basePart.toFixed(2);
            currentEmpSalary = basePart;
        }

        updateCalculator();
        
        document.getElementById('pay_extra_amount').value = extraAmount.toFixed(2);
        document.getElementById('pay_amount').value = payAmount.toFixed(2);

        document.querySelector('#payment_form button[type="submit"]').innerHTML = '<i class="ph ph-check"></i> Actualizar Pago';
        document.getElementById('btn-cancel-edit').style.display = 'block';
    }

    function deletePayment(payId) {
        if (!confirm('¿Estás seguro de eliminar este pago? Esta acción también eliminará el registro contable en Finanzas.')) return;

        const formData = new FormData();
        formData.append('pay_id', payId);

        fetch('index.php?module=admin&action=ajax_delete_payment', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadPaymentsHistory(currentEmpId);
            } else {
                alert('Error al eliminar: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión');
        });
    }

    function savePayment() {
        const form = document.getElementById('payment_form');
        const formData = new FormData(form);
        formData.append('extra_amount', document.getElementById('pay_extra_amount').value);
        formData.append('extra_days', document.getElementById('calc_days').value);
        formData.append('extra_hours', document.getElementById('calc_extra_hours').value);
        if (document.getElementById('calc_bonuses')) formData.append('bonuses', document.getElementById('calc_bonuses').value);
        if (document.getElementById('calc_discounts')) formData.append('discounts', document.getElementById('calc_discounts').value);

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
                loadPaymentsHistory(formData.get('employee_id'));
                cancelPaymentEdit();
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
        savePayment,
        cancelPaymentEdit,
        editPayment,
        deletePayment
    };
})();

document.addEventListener('DOMContentLoaded', RrhhModule.init);

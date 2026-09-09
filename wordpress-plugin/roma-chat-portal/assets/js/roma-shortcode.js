/**
 * Roma Portal Shortcode - Controller for Soporte & Cotizador
 */

(function($) {
    'use strict';

    if (typeof window.RomaPortalConfig === 'undefined') {
        console.warn('RomaPortalConfig not found.');
        return;
    }

    const config = window.RomaPortalConfig;
    let currentQuoteSession = null;
    let currentClientData = null;

    function init() {
        bindTabs();
        loadServicesCatalog();
        bindLookupForm();
        bindQuoteForm();
        bindDirectSupport();
    }

    // Manejo de pestañas
    function bindTabs() {
        $('.roma-tab-btn').on('click', function(e) {
            e.preventDefault();
            const targetTab = $(this).data('tab');

            $('.roma-tab-btn').removeClass('active').attr('aria-selected', 'false');
            $(this).addClass('active').attr('aria-selected', 'true');

            $('.roma-tab-pane').removeClass('active');
            $('#roma-tab-' + targetTab).addClass('active');
        });
    }

    // Cargar servicios desde Roma CRM
    function loadServicesCatalog() {
        const $select = $('#quote-service');
        if (!$select.length) return;

        $.ajax({
            url: config.apiUrl,
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_services' },
            success: function(res) {
                if (res.success && Array.isArray(res.services)) {
                    $select.empty();
                    $select.append('<option value="">-- Selecciona el servicio que deseas cotizar --</option>');
                    
                    res.services.forEach(function(s) {
                        let label = s.name;
                        if (s.price && parseFloat(s.price) > 0) {
                            label += ' (' + (s.currency || 'USD') + ' ' + parseFloat(s.price).toFixed(2) + ')';
                        }
                        const $opt = $('<option>')
                            .val(s.id)
                            .text(label)
                            .attr('data-name', s.name);
                        $select.append($opt);
                    });

                    // Opción adicional para requerimiento a medida
                    $select.append('<option value="custom" data-name="Otro Servicio / Proyecto a Medida">Otro Servicio / Proyecto a Medida</option>');
                } else {
                    $select.html('<option value="general">Servicio General / Consultoría</option>');
                }
            },
            error: function() {
                $select.html('<option value="general">Servicio General / Consultoría</option>');
            }
        });

        // Actualizar hidden service_name
        $select.on('change', function() {
            const selectedName = $(this).find('option:selected').data('name') || $(this).find('option:selected').text();
            $('#quote-service-name').val(selectedName);
        });
    }

    // Búsqueda de cliente / Soporte
    function bindLookupForm() {
        const $form = $('#roma-lookup-form');
        const $loading = $('#roma-lookup-loading');
        const $error = $('#roma-lookup-error');
        const $results = $('#roma-lookup-results');

        $form.on('submit', function(e) {
            e.preventDefault();

            const query = $('#roma-lookup-query').val().trim();
            if (!query) return;

            $loading.show();
            $error.hide();
            $results.hide();

            $.ajax({
                url: config.apiUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    action: 'lookup_client',
                    query: query
                },
                success: function(res) {
                    $loading.hide();

                    if (res.success && res.found) {
                        currentClientData = res.client;
                        renderLookupResults(res);
                        $results.show();
                    } else {
                        $error.find('#roma-error-desc').text(res.message || 'No se encontraron clientes registrados con ese DNI o Teléfono.');
                        $error.show();
                    }
                },
                error: function() {
                    $loading.hide();
                    $error.find('#roma-error-desc').text('No se pudo conectar con el CRM de Roma. Por favor inténtalo nuevamente más tarde.');
                    $error.show();
                }
            });
        });
    }

    function renderLookupResults(data) {
        const client = data.client || {};
        const brands = data.brands || [];
        const projectServices = data.project_services || [];

        // Tarjeta de cliente
        $('#res-client-name').text(client.name || 'Cliente Roma');
        $('#res-client-dni strong').text(client.dni || 'No registrado');
        $('#res-client-phone strong').text(client.whatsapp || client.phone || 'No registrado');

        // Render Marcas
        const $brandsList = $('#res-brands-list');
        $('#res-brands-count').text(brands.length);
        $brandsList.empty();

        if (brands.length === 0) {
            $brandsList.html('<p style="color: #64748b; font-size: 13px; margin: 0;">No tienes marcas asociadas registradas actualmente.</p>');
        } else {
            brands.forEach(function(b) {
                let logoHtml = '';
                if (b.logo) {
                    logoHtml = `<img src="${escapeHtml(b.logo)}" alt="${escapeHtml(b.name)}">`;
                } else {
                    const initial = (b.name || 'M').charAt(0).toUpperCase();
                    const color = b.color || '#6366f1';
                    logoHtml = `<span style="color: ${color}; font-weight: 800;">${initial}</span>`;
                }

                let membershipBadge = '';
                if (b.has_membership == 1 || b.has_membership === '1') {
                    membershipBadge = `<span class="roma-badge-membership"><i class="ph ph-check-circle"></i> Con Membresía</span>`;
                }

                let servicesHtml = '';
                if (Array.isArray(b.services) && b.services.length > 0) {
                    servicesHtml = '<div class="roma-brand-services-tags">';
                    b.services.forEach(function(s) {
                        servicesHtml += `<span class="roma-service-tag">${escapeHtml(s.name)}</span>`;
                    });
                    servicesHtml += '</div>';
                }

                let actionsHtml = '';
                if (b.whatsapp_group) {
                    actionsHtml = `
                        <div class="roma-brand-actions">
                            <a href="${escapeHtml(b.whatsapp_group)}" target="_blank" rel="noopener noreferrer" class="roma-btn-whatsapp-group">
                                <i class="ph ph-whatsapp-logo"></i> Grupo de WhatsApp
                            </a>
                        </div>
                    `;
                }

                const cardHtml = `
                    <div class="roma-brand-card" style="border-left-color: ${b.color || '#6366f1'};">
                        <div class="roma-brand-header">
                            <div class="roma-brand-logo-wrap">${logoHtml}</div>
                            <div>
                                <h4 class="roma-brand-name">${escapeHtml(b.name)}</h4>
                                ${membershipBadge}
                            </div>
                        </div>
                        ${servicesHtml}
                        ${actionsHtml}
                    </div>
                `;

                $brandsList.append(cardHtml);
            });
        }

        // Render Servicios Activos
        const $servicesList = $('#res-services-list');
        const $servicesSection = $('#res-project-services-section');
        
        let allServices = [];
        if (projectServices.length > 0) {
            allServices = projectServices;
        }

        $('#res-services-count').text(allServices.length);
        $servicesList.empty();

        if (allServices.length > 0) {
            allServices.forEach(function(s) {
                const title = s.service_name || s.title || 'Servicio Contratado';
                const status = s.status || 'Activo';
                const statusClass = status.toLowerCase() === 'completado' ? 'roma-status-completed' : (status.toLowerCase().includes('progreso') ? 'roma-status-in-progress' : 'roma-status-active');
                
                let dateStr = '';
                if (s.start_date || s.due_date) {
                    dateStr = `<div class="roma-service-item-dates">📅 Inicio: ${s.start_date || '-'} | Entrega estimada: ${s.due_date || '-'}</div>`;
                }

                const sHtml = `
                    <div class="roma-service-item">
                        <div>
                            <h5 class="roma-service-item-title">${escapeHtml(title)}</h5>
                            ${dateStr}
                        </div>
                        <span class="roma-service-status-pill ${statusClass}">${escapeHtml(status)}</span>
                    </div>
                `;
                $servicesList.append(sHtml);
            });
            $servicesSection.show();
        } else {
            $servicesSection.hide();
        }
    }

    // Botón Contactar a Soporte desde ficha de cliente
    function bindDirectSupport() {
        $('#roma-btn-direct-support').on('click', function(e) {
            e.preventDefault();
            if (currentClientData) {
                if (window.RomaChatWidget && typeof window.RomaChatWidget.startWithClient === 'function') {
                    window.RomaChatWidget.startWithClient(currentClientData);
                } else {
                    // Fallback: abrir en nueva pestaña o alertar
                    alert('Conectando con soporte para ' + currentClientData.name + '...');
                }
            }
        });
    }

    // Envío del Formulario de Cotización
    function bindQuoteForm() {
        const $form = $('#roma-quote-form');
        const $success = $('#roma-quote-success');
        const $btnSubmit = $('#quote-submit-btn');

        $form.on('submit', function(e) {
            e.preventDefault();

            const name = $('#quote-name').val().trim();
            const dni = $('#quote-dni').val().trim();
            const phone = $('#quote-phone').val().trim();
            const email = $('#quote-email').val().trim();
            const serviceId = $('#quote-service').val();
            const serviceName = $('#quote-service-name').val() || $('#quote-service option:selected').text();
            const message = $('#quote-message').val().trim();

            if (!name || !phone || !serviceId) {
                alert('Por favor completa todos los campos requeridos (*)');
                return;
            }

            $btnSubmit.prop('disabled', true).html('<i class="ph ph-spinner-gap" style="animation: roma-spin 0.8s linear infinite;"></i> <span>Enviando requerimiento...</span>');

            $.ajax({
                url: config.apiUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'submit_quote',
                    name: name,
                    dni: dni,
                    phone: phone,
                    email: email,
                    service_id: serviceId,
                    service_name: serviceName,
                    message: message
                },
                success: function(res) {
                    $btnSubmit.prop('disabled', false).html('<i class="ph ph-paper-plane-tilt"></i> <span>Enviar Solicitud y Abrir Chat</span>');

                    if (res.success) {
                        currentQuoteSession = {
                            chat_id: res.chat_id,
                            chat_token: res.chat_token,
                            guest_token: res.guest_token,
                            guest_name: name
                        };

                        $form.hide();
                        $('#roma-quote-success-msg').text(res.message || 'Hemos registrado tu requerimiento con éxito.');
                        $success.show();

                        // Si el widget flotante está presente, cargarle la sesión
                        if (window.RomaChatWidget && typeof window.RomaChatWidget.setSession === 'function') {
                            window.RomaChatWidget.setSession(currentQuoteSession);
                        }
                    } else {
                        alert('Ocurrió un error al enviar la cotización: ' + (res.error || 'Intenta de nuevo'));
                    }
                },
                error: function() {
                    $btnSubmit.prop('disabled', false).html('<i class="ph ph-paper-plane-tilt"></i> <span>Enviar Solicitud y Abrir Chat</span>');
                    alert('Error de conexión con el servidor. Verifica tu conexión a internet.');
                }
            });
        });

        // Botón Continuar en el Chat en Vivo (tras cotizar)
        $('#roma-btn-open-quote-chat').on('click', function(e) {
            e.preventDefault();
            if (currentQuoteSession) {
                if (window.RomaChatWidget && typeof window.RomaChatWidget.open === 'function') {
                    window.RomaChatWidget.open();
                } else if (config.guestUrl) {
                    const url = config.guestUrl + '&token=' + encodeURIComponent(currentQuoteSession.chat_token) + 
                                '&guest_token=' + encodeURIComponent(currentQuoteSession.guest_token);
                    window.open(url, '_blank');
                }
            }
        });

        // Botón Nueva Cotización
        $('#roma-btn-new-quote').on('click', function(e) {
            e.preventDefault();
            $form[0].reset();
            $success.hide();
            $form.show();
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $(document).ready(init);

})(jQuery);

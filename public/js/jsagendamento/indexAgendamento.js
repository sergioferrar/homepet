    document.addEventListener("DOMContentLoaded", function () {
    //     // Depuração para verificar inicializações do DataTables
    //     console.log('Inicializando DataTables...');

    //     // Verificar se a tabela existe
    //     const table = $('.datatable');
    //     if (table.length) {
    //         // Se já foi inicializada, destruir a instância anterior
    //         if ($.fn.DataTable.isDataTable(table)) {
    //             console.log('DataTables já inicializado, destruindo instância anterior...');
    //             table.DataTable().destroy();
    //         }

    //         // Inicializar o DataTables
    //         console.log('Inicializando DataTables pela primeira vez...');
    //         table.DataTable({
    //             "language": {
    //                 "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json"
    //             },
    //             "paging": true,
    //             "searching": true,
    //             "info": true,
    //             "lengthChange": true,
    //             "pageLength": 10,
    //             "dom": 'lfrtip' // Define a estrutura padrão: length, filter, table, info, pagination
    //         });
    //         console.log('DataTables inicializado com sucesso.');
    //     } else {
    //         console.log('Tabela .datatable não encontrada.');
    //     }

    //     let totalOriginal = 0;

        // Função para abrir o modal de conclusão
        $('.open-concluir-modal').on('click', function(e) {
            e.preventDefault(); // Impede o comportamento padrão do link
            console.log('Botão Concluir clicado'); // Para depuração

            const rota = $(this).data('action');
            const agendamentoId = $(this).data('id'); // Definir agendamentoId aqui

            fetch(rota, {
                method: "GET"
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na requisição: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Dados recebidos:', data); // Para depuração

                if (data.status === "success") {
                    const servicosList = $('#servicos-list');
                    const pendentesList = $('#pendentes-list');
                    const totalInput = $('#total');

                    // Limpar listas
                    servicosList.empty();
                    pendentesList.empty();

                    // Preencher serviços
                    $.each(data.clientes, function(clienteNome, info) {
                        servicosList.append(`<h6>Cliente: ${clienteNome}</h6>`);
                        const ul = $('<ul></ul>');
                        $.each(info.servicos, function(index, servico) {
                            ul.append(`<li>${servico.servico_nome} para ${servico.pet_nome}: R$ ${servico.valor.toFixed(2)}</li>`);
                        });
                        if (data.agendamento.taxi_dog) {
                            const taxaTaxiDog = parseFloat(data.agendamento.taxa_taxi_dog) || 0;
                            ul.append(`<li>Táxi Dog: R$ ${taxaTaxiDog.toFixed(2)}</li>`);
                        }
                        servicosList.append(ul);
                    });

                    // Preencher pendentes
                    if (data.pendentes && data.pendentes.length > 0) {
                        const ul = $('<ul></ul>');
                        $.each(data.pendentes, function(index, pendente) {
                            const valorPendente = parseFloat(pendente.valor) || 0;
                            ul.append(`<li>${pendente.descricao}: R$ ${valorPendente.toFixed(2)}</li>`);
                        });
                        pendentesList.html(ul);
                    } else {
                        pendentesList.html('<p>Nenhum registro pendente encontrado.</p>');
                    }

                    // Definir total
                    totalOriginal = parseFloat(data.total_geral) || 0;
                    totalInput.val(totalOriginal.toFixed(2));

                    // Abrir modal usando a API nativa do Bootstrap 5
                    try {
                        const modalElement = document.getElementById('modalConcluir');
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                        console.log('Modal aberto com sucesso');
                    } catch (error) {
                        console.error('Erro ao abrir o modal:', error);
                    }

                    // Adicionar depuração para o evento de fechamento
                    const modalElement = document.getElementById('modalConcluir');
                    modalElement.addEventListener('hidden.bs.modal', function () {
                        console.log('Modal está sendo fechado');
                    });

                    // Concluir pagamento
                    $('#btn-concluir-pagamento').off('click').on('click', function() {
                        console.log('Botão Concluir Pagamento clicado'); // Para depuração
                        const desconto = parseFloat($('#desconto').val()) || 0;
                        const metodoPagamento = $('#metodo_pagamento').val();

                        const url = "{{ path('agendamento_concluir_pagamento', {'id': 'AGENDAMENTO_ID'}) }}".replace('AGENDAMENTO_ID', agendamentoId);
                        console.log('Enviando requisição para:', url); // Para depuração

                        fetch(url, {
                            method: "POST",
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                'desconto': desconto,
                                'metodo_pagamento': metodoPagamento
                            })
                        })
                        .then(response => {
                            console.log('Resposta recebida:', response); // Para depuração
                            return response.json().then(data => ({ status: response.status, data }));
                        })
                        .then(({ status, data }) => {
                            console.log('Dados da resposta:', data); // Para depuração
                            if (status === 200 && data.status === "success") {
                                Notify.toast(data.mensagem, "success");
                                const modalElement = document.getElementById('modalConcluir');
                                const modal = bootstrap.Modal.getInstance(modalElement);
                                modal.hide();
                                location.reload();
                            } else {
                                // Tratar erro 400 ou outros erros
                                const mensagemErro = data.mensagem === "Este agendamento já foi concluído anteriormente." 
                                    ? "Pagamento já foi lançado ou concluído." 
                                    : data.mensagem || "Erro ao processar o pagamento.";
                                Notify.toast(mensagemErro, "danger");
                            }
                        })
                        .catch(error => {
                            console.error("Erro na requisição AJAX:", error);
                            Notify.toast("Erro ao processar o pagamento: " + error.message, "danger");
                        });
                    });
                } else {
                    Notify.toast("Erro: " + data.mensagem, "danger");
                }
            })
            .catch(error => {
                console.error("Erro na requisição AJAX:", error);
                Notify.toast("Erro ao carregar os dados: " + error.message, "danger");
            });
        });

        // Atualizar total ao mudar o desconto
        $('#desconto').on('input', function() {
            const desconto = parseFloat($(this).val()) || 0;
            const total = totalOriginal - desconto;
            $('#total').val(total.toFixed(2));
        });

        // Outros scripts existentes
        function salvarPosicaoScroll() {
            sessionStorage.setItem("scrollPos", window.scrollY);
        }

        function restaurarPosicaoScroll() {
            let scrollPos = sessionStorage.getItem("scrollPos");
            if (scrollPos !== null) {
                window.scrollTo(0, scrollPos);
                sessionStorage.removeItem("scrollPos");
            }
        }

        $('.reload-form').on('click', function() {
            var rota = $(this).data('action');
            var acao = $(this).data('acao');
            var id = $(this).data('id');

            fetch(rota, {
                method: "POST",
                body: {}
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    Notify.toast(data.mensagem, data.status);
                } else {
                    Notify.toast("Erro: " + data.mensagem, "danger");
                }
            })
            .catch(error => console.error("Erro na requisição AJAX:", error));
        });

        $('.reload-form-select').on('change', function() {
            var rota = $(this).attr('action');
            var acao = $(this).data('acao');
            var id = $(this).data('id');

            let formData = new FormData(this);
            fetch(rota, {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "sucesso") {
                    Notify.toast(data.mensagem, 'success');
                } else {
                    Notify.toast(data.mensagem, 'danger');
                }
            })
            .catch(error => console.error("Erro na requisição AJAX:", error));
        });

        restaurarPosicaoScroll();
    });

$(function () {
    $(document).on('click', '.buscar-cliente', function () {
        var donoId = $(this).data('dono-id');
        var modalCliente = $('#modalCliente');

        if (!donoId) {
            Notify.modal("Erro: Dono ID não encontrado.", 'md', 'Mensagem do sistema');
            return;
        }

        // 👇 Pega a URL dinamicamente do HTML (definido no template com data-url-cliente)
        const url = $('#dadosGlobais').data('url-cliente');

        $.ajax({
            type: 'POST',
            url: url,
            data: { dono_id: donoId },
            dataType: 'json',
            success: function (data) {
                if (data.status === "sucesso") {
                    modalCliente.find('#clienteNome').html(data.cliente.nome || '-');
                    modalCliente.find('#clienteEmail').html(data.cliente.email || '-');
                    modalCliente.find('#clienteTelefone').html(data.cliente.telefone || data.cliente.whatsapp || '-');

                    const endereco = [
                        data.cliente.rua,
                        data.cliente.numero,
                        data.cliente.complemento
                    ].filter(Boolean).join(', ');
                    modalCliente.find('#clienteEndereco').html(endereco || '-');
                    modalCliente.find('#clienteBairro').html(data.cliente.bairro || '-');
                    modalCliente.find('#clienteCidade').html(data.cliente.cidade || '-');
                    modalCliente.find('#clienteCep').html(data.cliente.cep || '-');

                    const modal = new bootstrap.Modal(document.getElementById('modalCliente'));
                    modal.show();
                } else {
                    Notify.toast("Erro: " + data.mensagem, "danger");
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX:', xhr.responseText || error);
                Notify.toast("Erro na requisição: " + error, "danger");
            }
        });

        return false;
    });
});



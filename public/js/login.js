$(function() {
    // Abstração do Signotify para mensagem de retorno do backend
    let estado = null;
    let municipio = null;

    let ajaxFormCurstom = (form, rota, loading = '') => {
        let modalReloader = '';

        let loadingHtml = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span class=""> Aguarde...</span>`;

        $(form).on('submit', function(e) {
            e.preventDefault();
            let formBtn = form.find('button[type=submit]');
            let data = form.serialize();
            $.ajax({
                type: 'post',
                url: rota,
                data: data,
                dataType: 'json',
                beforeSend: function() {
                    formBtn.html(loadingHtml).prop('disabled', true);
                },
                success: function(result) {
                    // console.log(result);
                    if (result.error === true) {
                        Notify.modal(result.mensagem, result.status, function() {
                            formBtn.html('Entrar').prop('disabled', false)
                        });
                        return;
                    }

                    if (result.direcionaHome) {
                        Notify.toast(result.message, 'success');
                        $('.toast').on('hidden.bs.toast', function() {
                            window.location.href = result.redireciona
                        });
                    }

                    // triggerModal(result.mensagem, 'success');
                    formBtn.html('Entrar').prop('disabled', false)
                },
                error: function(error, httpRequestError) {
                    console.log(error)
                    console.log(httpRequestError)
                    formBtn.html('Entrar').prop('disabled', false)
                    // Notify.modal(error.responseJSON.message, 'danger', 'Mensagem do sistema', {}, null, 'Fechar', null, 'danger');
                    Notify.toast(error.responseJSON.message, 'danger', 50000);
                },
                done: function(teste) {}
            });
            return false; // faz com que o formulario não execute de forma tradicional, apenas pelo AJAX
        });
    }

    //esqueci minha senha
    $('.esqueci-minha-senha').on('click', function(e) {
        e.preventDefault();
        let campoEmail = '<div class="col-12">';
        campoEmail += '<div class="form-floating mb-3">';
        campoEmail += '<input type="text" class="form-control" name="esqueciMinhaSenhaParam" id="esqueciMinhaSenhaParam" placeholder="Informe seu login ou E-mail?" required>';
        campoEmail += '<label for="esqueciMinhaSenhaParam" class="form-label">Qual e-mail do seu cadastro ou login?</label>';
        campoEmail += '</div>';
        campoEmail += '</div>';

        triggerModal(campoEmail, 'secondary', function() {
            $.ajax({
                type: 'post',
                data: {
                    param: $('#esqueciMinhaSenhaParam').val()
                },
                url: url + 'login/recuperar/senha',
                dataType: 'json',
                success: function(result) {
                    let status = result.error === true ? 'danger' : 'success'
                    Utils.modal(result.message, status, function() {
                        window.location.reload();
                    });
                },
                error: function(e, b) {
                    console.log(e, b)
                }
            });
        }, 'Enviar', 'Cancelar')
        //return false;
    })

    // formulario do login
    let login = $('.user');
    let authentic = $('.form-login-data');

    ajaxFormCurstom(login, login.attr('action'));
    // ajaxFormCurstom(authentic, authentic.attr('action'));
    // Utils.chatFaleConosco();
});
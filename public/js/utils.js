const url = $('meta[name="base"]').attr('content');
var Utils = (function() {
    var url = '';
    var host = window.location.href.split('/');
    var loadingBtn = '<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span> Loading...';

    // var validaTinyMCE = function(){
    //   if (typeof tinyMCE !== 'undefined') {
    //         tinyMCE.triggerSave();
    //     }
    // }

    var load = function() {
        url = $('meta[name="base"]').attr('content')

        // loadSelect2();
        sideBarResponsivo();
        imprimir();

        if ($('.editor_basic').length) {
            as_tinyMCE_basic();
        }
        if ($('.editor').length) {
            as_tinyMCE();
        }
    }

    var goToTop = function() {
        $(".back-to-top").addClass("d-none");
        $(window).scroll(function() {
            if ($(this).scrollTop() === 0) {
                $(".back-to-top").addClass("d-none")
            } else {
                $(".back-to-top").removeClass("d-none")
            }
        });

        $('.back-to-top').click(function() {
            $('body,html').animate({
                scrollTop: 0
            }, 500);
            return false;
        });
    }

    var loadingMessage = function(content) {
        var notify = Notify.globalLoading(content);
        return notify;
    }

    var modal = function(content, status = 'secondary', callback, modalSize = 'md', mensagem = 'Mensagem do sistema', okText = "Fechar", cancelText = null, confirmationClass = null) {
        var notify = Notify.modal(content, modalSize, mensagem, callback, okText, cancelText, confirmationClass);
        $(`#${notify.id} .modal-header`).addClass(`bg-${status}`).addClass('text-white');
        return notify;
    }

    var hideModal = function(modal, timer = 600) {
        if (modal) {
            setTimeout(function() {
                modal.hide();
            }, timer);
        }
    }

    var redirect = function(rota) {
        window.location.href = rota
    }

    var formAjax = function(formulario, loadingMessagem = false, callback = null) {
        if (typeof tinyMCE !== 'undefined') {
            tinyMCE.triggerSave();
        }

        formulario.on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var forms = $(this)
            var botao = forms.find('button[type="submit"]');
            var textoBotaoDefault = botao.html();
            var loader = '';
            $.ajax({
                type: 'post',
                data: forms.serialize(),
                url: forms.attr('action'),
                dataType: 'json',
                beforeSend: function() {
                    botao.prop('disabled', true).html(loadingBtn);
                    if (loadingMessagem != false) {
                        loader = Utils.loadingMessage(loadingMessagem);
                    }
                },
                success: function(result) {
                    botao.prop('disabled', false).html(textoBotaoDefault);

                    if (loadingMessagem != false) {
                        setTimeout(function() {
                            loader.hide();
                        }, 600);
                    }

                    if (callback) {
                        callback(result);
                        return;
                    }

                    if (result.message) {
                        Utils.modal(result.message, result.status, function() {
                            if (result.redirect) {
                                window.location.href = result.redirect;
                            }

                            if (result.reload) {
                                window.location.reload();
                            }
                        });
                    }
                },
                error: function(erro, HttpRequestError) {
                    botao.prop('disabled', false).html(textoBotaoDefault);

                    if (loadingMessagem != false) {
                        setTimeout(function() {
                            loader.hide();
                        }, 600);
                    }
                    console.log(erro);


                    //console.error(erro);
                    //console.error(HttpRequestError);
                },
            });

            return false;
        });
    }

    var sideBarResponsivo = function() {

        var sidebarToggle = $('#sidebarToggle')
        sidebarToggle.on('click', function() {
            $('body').toggleClass('sb-sidenav-toggled');
            return false;
        });
    }

    var globalPostAjax = function(rota, postData = {}, returnData = {}) {
        if (typeof tinyMCE !== 'undefined') {
            tinyMCE.triggerSave();
        }
        var loading = '';
        $.ajax({
            type: 'post',
            data: postData,
            url: rota,
            dataType: 'json',
            success: function(result) {
                if (returnData) {
                    returnData(result);
                }

                if (result.message) {
                    modal(result.message, result.status, function() {
                        if (result.redirect) {
                            window.location.href = result.redirect;
                        }

                        if (result.reload) {
                            window.location.reload();
                        }
                    });
                }
                // if (result.reload) {
                //  window.location.reload();
                // }
            },
            error: function(erro, HttpRequestError) {
                setTimeout(function() {
                    loading.hide();
                }, 600);

                modal(erro.responseText, 'danger', function() {
                    console.clear();
                });

                console.error(erro);
                console.error(HttpRequestError);
                dadosAjax = false;
            },
        });
    }

    var formatarDados = function(dados) {
        if (!dados.subtext) {
            return $(`<span>${dados.text}</span>`);
        }

        return $(`<span>${dados.text} <small class="ms-1 text-muted">(${dados.subtext})</small></span>`);
    }

    //FUNCTION TINYMCE
    var as_tinyMCE = function(height = 200) {


        tinyMCE.init({
            selector: "textarea.editor",
            language: 'pt_BR',
            menubar: false,
            theme: "modern",
            height: height,
            skin: 'light',
            entity_encoding: "raw",
            theme_advanced_resizing: true,
            plugins: [
                "advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker",
                "searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
                "save table contextmenu directionality emoticons template paste textcolor media"
            ],
            //toolbar: "styleselect | forecolor | backcolor | pastetext | removeformat |  bold | italic | underline | strikethrough | bullist | numlist | alignleft | aligncenter | alignright |  link | unlink | sigcontimage | media |  outdent | indent | preview | code | fullscreen",
            toolbar: "styleselect | forecolor | backcolor | pastetext | removeformat |  bold | italic | underline | strikethrough | bullist | numlist |  link | unlink | sigcontimage | code | paste",
            content_css: url + "plugins/tinymce/tinyMCE.css",
            style_formats: [{
                title: 'Normal',
                block: 'p'
            }, {
                title: 'Titulo 3',
                block: 'h3'
            }, {
                title: 'Titulo 4',
                block: 'h4'
            }, {
                title: 'Titulo 5',
                block: 'h5'
            }, {
                title: 'Código',
                block: 'pre',
                classes: 'brush: php;'
            }],
            link_class_list: [{
                title: 'None',
                value: ''
            }, {
                title: 'Blue CTA',
                value: 'btn btn_cta_blue'
            }, {
                title: 'Green CTA',
                value: 'btn btn_cta_green'
            }, {
                title: 'Yellow CTA',
                value: 'btn btn_cta_yellow'
            }, {
                title: 'Red CTA',
                value: 'btn btn_cta_red'
            }],
            setup: function(editor) {
                // Ainda em desenvolvimento, com possibilidade de fazer upload de imagens com o uso externoa o plugin
                //console.log(editor);
                editor.addButton('sigcontimage', {
                    title: 'Enviar Imagem',
                    icon: 'image',
                    onclick: function() {
                        var inputFile = '<input type="file" name="as_loadImg"><div class="col-md-12"><img class="as_loadImg" src="" alt=""></div>';

                        var popup = modal(inputFile, 'info', function() {

                            tinyMCE.activeEditor.insertContent('<img class="img-fluid" src="' + $('.as_loadImg').attr('src') + '" alt="">');

                        }, 'md', 'Mensagem do sistema', "Enviar e inserir", "Fechar");

                        $('textarea.editor').find('.as_loadImg').attr('src', '');

                        $(document).on('change', 'input[name="as_loadImg"]', function() {
                            var input = $(this);
                            var target = $(document).find('.as_loadImg');
                            var fileDefault = target.attr('default');

                            if (this.files && (this.files[0].type.match("image/jpeg") || this.files[0].type.match("image/png"))) {
                                var reader = new FileReader();
                                reader.onload = function(e) {

                                    $(document).find('.as_loadImg').attr('src', e.target.result).width('100%'); //.fadeIn('fast');

                                };
                                reader.readAsDataURL(this.files[0]);
                            }

                        });
                    }
                });
            },
            link_title: false,
            target_list: false,
            theme_advanced_blockformats: "h1,h2,h3,h4,h5,p,pre",
            media_dimensions: false,
            media_poster: false,
            media_alt_source: false,
            media_embed: false,
            extended_valid_elements: "a[href|target=_blank|rel|class]",
            imagemanager_insert_template: '<img src="{$url}" title="{$title}" alt="{$title}" />',
            image_dimensions: true,
            relative_urls: false,
            remove_script_host: false,
            paste_as_text: true
        });
    }

    //FUNCTION TINYMCE BASIC
    var as_tinyMCE_basic = function() {
        tinyMCE.init({
            selector: "textarea.editor_basic",
            language: 'pt_BR',
            menubar: false,
            theme: "modern",
            height: 200,
            skin: 'light',
            entity_encoding: "raw",
            theme_advanced_resizing: true,
            plugins: [
                "advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker",
                "searchreplace wordcount visualblocks visualchars code insertdatetime media nonbreaking",
                "save table contextmenu directionality emoticons template paste textcolor media"
            ],
            toolbar: "styleselect | forecolor | backcolor | pastetext | removeformat |  bold | italic | underline | strikethrough | bullist | numlist |  link | unlink | image | code | paste",
            content_css: url + "plugins/tinymce/tinyMCE.css",
            style_formats: [{
                title: 'Normal',
                block: 'p'
            }, {
                title: 'Titulo 3',
                block: 'h3'
            }, {
                title: 'Titulo 4',
                block: 'h4'
            }, {
                title: 'Titulo 5',
                block: 'h5'
            }, {
                title: 'Código',
                block: 'pre',
                classes: 'brush: php;'
            }],
            link_class_list: [{
                title: 'None',
                value: ''
            }, {
                title: 'Blue CTA',
                value: 'btn btn_cta_blue'
            }, {
                title: 'Green CTA',
                value: 'btn btn_cta_green'
            }, {
                title: 'Yellow CTA',
                value: 'btn btn_cta_yellow'
            }, {
                title: 'Red CTA',
                value: 'btn btn_cta_red'
            }],
            link_title: false,
            target_list: false,
            theme_advanced_blockformats: "h1,h2,h3,h4,h5,p,pre",
            media_dimensions: false,
            media_poster: false,
            media_alt_source: false,
            media_embed: false,
            extended_valid_elements: "a[href|target=_blank|rel|class]",
            //imagemanager_insert_template: '<img src="{$url}" title="{$title}" alt="{$title}" />',
            //image_dimensions: false,
            relative_urls: false,
            remove_script_host: false,
            paste_as_text: false
        });
    }

    var number_format = function(numero, decimal, decimal_separador, milhar_separador) {
        numero = (numero + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+numero) ? 0 : +numero,
            prec = !isFinite(+decimal) ? 0 : Math.abs(decimal),
            sep = (typeof milhar_separador === 'undefined') ? ',' : milhar_separador,
            dec = (typeof decimal_separador === 'undefined') ? '.' : decimal_separador,
            s = '',
            toFixedFix = function(n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }

    var decFormatMoeda = function(value) {
        return number_format(value, 2, ',', '.');
    }

    var decFormat = function(value) {
        return number_format(value, 2, '', '.');
    }

    // var loadSelect2 = function() {

    //     var themeSelect2 = 'bootstrap-5';
    //     var select2Plugin = $('select:not(.select2_off)');
    //     var traducaoSelect2 = {
    //         errorLoading: function() {
    //             return "Os dados não puderam ser carregados."
    //         },
    //         inputTooShort: function() {
    //             return "Digite para buscar...";
    //         },
    //         searching: function() {
    //             return "Buscando...";
    //         },
    //         loadingMore: function() {
    //             return "Carregando mais dados...";
    //         },
    //         noResults: function() {
    //             return "Nenhum resultado encontrado";
    //         }
    //     }

    //     $.each(select2Plugin, function(index, value) {
    //         // console.log(value, index)
    //         var select = $(value);
    //         var select2Title = $('select').data('placeholder');
    //         var ajaxSelect2 = {};

    //         var optionSelect2 = {
    //             theme: themeSelect2,
    //             templateSelection: function(dados) {
    //                 if (dados.title != '') {
    //                     var titulo = dados.subtext ?? dados.title
    //                     return $(`<span>${dados.text} <small class="ms-1">(${titulo})</small></span>`);
    //                 }
    //                 return $(`<span>${dados.text}</span>`);
    //             },
    //             language: traducaoSelect2
    //         };

    //         if (select.data('ajax-url')) {
    //             var select2Title = $('select').data('placeholder');
    //             optionSelect2 = {
    //                 theme: themeSelect2,
    //                 ajax: {
    //                     url: select.data('ajax-url'),
    //                     dataType: "json",
    //                     delay: 250,
    //                     data: function(params) {
    //                         return {
    //                             q: params.term,
    //                             //_tipo: tipo
    //                         };
    //                     },
    //                     processResults: function(data) {
    //                         return {
    //                             results: $.map(data, function(obj) {
    //                                 return {
    //                                     id: obj.codIBGE,
    //                                     text: obj.municipio,
    //                                     subtext: obj.subtext,
    //                                 }
    //                             })
    //                         };
    //                     },
    //                     cache: false
    //                 },
    //                 placeholder: "Selecione " + select2Title,
    //                 templateResult: formatarDados,
    //                 templateSelection: function(dados) {
    //                     if (dados.title) {
    //                         var titulo = dados.subtext ?? dados.title
    //                         return $(`<span>${dados.text} <small class="ms-1">(${titulo})</small></span>`);
    //                     }

    //                     if (dados.subtext) {
    //                         var titulo = dados.subtext ?? dados.title;
    //                         console.log(dados.subtext);
    //                         return $(`<span>${dados.text} <small class="ms-1">(${titulo})</small></span>`);
    //                     }

    //                     return $(`<span>${dados.text}</span>`);
    //                 },
    //                 language: traducaoSelect2
    //             };

    //         }

    //         select.select2(optionSelect2);
    //     });
    // }

    var envioDiretoS3 = function(arquivo, callback = null) {

        url = $('meta[name="base"]').attr('content')
        var link = '';


        async function formulario() {

            var response = await fetch(url + 'drt/obter-url-s3/drt', {
                headers: {
                    'Content-type': 'application/x-www-form-urlencoded'
                },
                method: 'POST',
                body: "filename=" + encodeURIComponent(arquivo[0].name) + "&size=" + arquivo[0].size + "&cnpj=" + $('input[name="cnpj"]').val()
            });

            var retorno = await response.text();
            link = jQuery.parseJSON(retorno);

            if (callback != null) {
                callback(link);
            }

            Utils.uploadDiretoS3(link.link, link.retorno)
        }

        formulario()
        return arquivo[0].name
        // 
    }


    var uploadDiretoS3 = function(link, arquivo) {

        var result = false;
        $.ajax({
            type: 'PUT',
            async: false,
            url: link,
            contentType: 'binary/octet-stream',
            processData: false,
            data: arquivo,
            success: function(s) {
                console.log(s);
                result = true;
            },
            error: function() {
                result = false;
            },
        })
        return result;
    }


    var printPdf = function(url) {

        var iframe = this._printIframe;
        if (!this._printIframe) {
            iframe = this._printIframe = document.createElement('iframe');
            document.body.appendChild(iframe);

            iframe.style.display = 'none';
            iframe.onload = function() {
                setTimeout(function() {
                    iframe.focus();
                    iframe.contentWindow.print();
                }, 1);
            };
        }
        iframe.src = url;
    }

    var imprimir = function() {
        $('.imprimir').on('click', function() {
            printPdf($(this).attr('href'))
            return false;
        })
    }
    //Cria umma janela windowOpen ou um modal
    /***
     *openJanela = para add a classe no elemento html -> ex: <span class="exmplo openJanela"/>, <a href="#" class="openJanela" />, etc
     *tipoJanela =  (modal  ou window) = para add no elemento html / cria uma janela no tipo windowOpen ou modal-> ex: <span tipoJanela="modal"/>, <a href="#" tipoJanela="window"/>
     *urlCompleta = caminho completo da url ex:  <span urlCompleta="http://wwww.exemplo.com" />, <a href="#" urlCompleta="http://wwww.exemplo.com"/>, etc
     *O componente 'window' pode ser chamado sem nenhum outro atributo, pegando por a configuração definida como 'default' no escopo da função...
     *
     *Após adicionados os atributos (openJanela, tipoJanela e urlCompleta) no elemento html, você pode: 
     *
     *ex: <span urlCompleta="http://wwww.exemplo.com" tipoJanela="window" config="sm" />, <a href="#" urlCompleta="http://wwww.exemplo.com" tipoJanela="modal" config="xl" />
     * 
     *config = atribruto que configura o redimensionamento da janela : sm, lg e xl exmplo de uso: <span  config="sm" />, <a href="#" config="lg" />, etc
     *altura= voce pode difinir a largura em pixel para cada elemento: exmplo de uso: <span  largura="900" />, <a href="#" largura="900" />, etc
     *largura= voce pode difinir a altura em pixel para cada elemento: exmplo de uso: <span  altura="900" />, <a href="#" altura="900" />, etc
        
    ***/

    var setupJanela = function() {

        $(document).on('click', '.openJanela', function() {

            let urlCompleta = this.getAttribute('urlCompleta'),
                tipoJanela = this.getAttribute('tipoJanela'),
                config = this.getAttribute('config'),
                largura = this.getAttribute('largura'),
                altura = this.getAttribute('altura');

            switch (tipoJanela) {

                case 'window':
                    if (!largura && !altura) {
                        switch (config) {
                            case 'smm':
                                largura = 900;
                                altura = 320;
                                break;

                            case 'sm':
                                largura = 900;
                                altura = 500;
                                break;

                            case 'lg':
                                largura = 900;
                                altura = 900;
                                break;
                            case 'xl':
                                largura = 1350;
                                altura = 900;
                                break;
                            case 'notificacao':
                                largura = 1100;
                                altura = 900;
                                break;
                            default:
                                largura = 900;
                                altura = 920;
                        }

                        abreJanelaPadrao(urlCompleta, largura, altura);
                    } else {

                        abreJanelaPadrao(urlCompleta, (largura) ? largura : 900, (altura) ? altura : 920);
                    }
                    break;
                case 'modal':
                    if (config == 'full') {
                        config = 'fullscreen';
                    }
                    if (['sm', 'lg', 'xl', 'full', 'fullscreen'].indexOf(config) > -1) {
                        Notify.modal(urlCompleta, config);
                    } else {
                        Notify.modal("<h4 class='text-center'>O valor de configuração [ " + config + " ] para o attributo 'config' está indefinido. Verifique no arquivo Utils.js na função 'setupJanela'.</h4>");
                    }

                    break;
                default:
                    abreJanelaPadrao(urlCompleta, 900, 920);
            }
        })
    }
    
    // permite apenas numeros e vírgula ou ponto
    var limitaNumero = function (apenasNumeros = false) {
        $('.limitaNumero').on('keypress', function (e) {
            if (apenasNumeros) {
                if (e.which < 48 || e.which > 57) {
                    e.preventDefault();
                }
            }else{
                if ((e.which < 48 || e.which > 57) && e.which !== 44 &&  e.which !== 46) {
                    e.preventDefault();
                }
            }
        });
    }

    // Limita os imputs do tipo float, permitindo apenas números e um único ponto
    var limitaFloat = () => {

        $(document).on('input', '.limitaFloat', function (e) {
            let valorInput = $(this).val();

            let valorLimpo = valorInput.replace(/[^0-9.]/g, '');
            let partes = valorLimpo.split('.');

            if (partes.length > 1) {
                valorLimpo = partes[0] + '.' + partes.slice(1).join('');
            }

            if (valorLimpo.startsWith('.')) {
                valorLimpo = valorLimpo.substring(1);
            }

            $(this).on('change', () => {

                if (valorLimpo.endsWith('.')) {
                    valorLimpo = valorLimpo.slice(0, -1);
                }

                $(this).val(valorLimpo);
            })

            $(this).val(valorLimpo);
        });
    }

    var abreJanelaPadrao = function(url, largura = 1350, altura = 900) {
        janela = window.open(url, "", "location=center,directories=no,toolbar=no,scrollbars=yes,status=no,dialog=yes,resizable=yes,minimaze=no,fullscreen=no,width=" + largura + ",height=" + altura + ",top=100,left=200");
        janela.scroll(740, 740);
    }

    var habilitaTooltip = function(element = '*[data-bs-toggle="tooltip"], .isTooltip') {

        var tooltipTriggerList = [].slice.call(document.querySelectorAll(element));

        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    }

    var setUTF8Encode = function(element) {
        return decodeURIComponent(element);
    }

    var loadMudaMunicipio = function() {
        $('.muda-municipio').on('click', function() {
            let este = $(this);
            let idMunicipio = este.data('value');
            let nomeMunicipio = este.text();
            let postForm = {
                codIBGE: idMunicipio,
                nomeMunicipio: nomeMunicipio
            };

            let loading = null;
            $.ajax({
                url: url + 'muda-municipio-da-sessao',
                type: 'post',
                dataType: 'json',
                data: postForm,
                beforeSend: function() {
                    loading = Utils.loadingMessage("Aguarde, carregando município!");
                },
                success: function(result) {
                    Utils.hideModal(loading);
                    window.location.reload();
                },
                error: function(jqXHR, error) {
                    console.log(jqXHR, error)
                },
            });
        });
    }

    var loadMudaExercicio = function() {
        $('.muda-exercicio').on('click', function() {
            let exercicioSelecionado = $(this).data('value'),
                loading = null;

            $.ajax({
                url: url + 'muda-exercicio-da-sessao',
                type: 'post',
                dataType: 'json',
                data: {exercicio: exercicioSelecionado},
                success: function(result) {
                    
                    if (result.success){
                        window.location.reload();
                        return false;
                    }
                    Notify.toast('Houve um erro ao alterar o Ano de Aplicação!', 'danger')
                },
                error: function(jqXHR, error) {
                    console.log(jqXHR, error)
                },
            });
        });
    }

    var chatFaleConosco = function() {

        let sHost = host.indexOf('localhost') == -1 && host.indexOf('127.0.0.1') == -1;

        if (sHost) {
            var Tawk_API = Tawk_API || {},
                Tawk_LoadStart = new Date(),
                s1 = document.createElement("script"),
                s0 = document.getElementsByTagName("script")[0];

            s1.async = true;
            s1.src = 'https://embed.tawk.to/67238a924304e3196adb2d2e/1ibhdaj39';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        }
    }

    var clickHelpDesk = function() {

        $(document).on('click', 'a.nav-link', function() {

            //string letras em minúsculas -> removendo espaço 
            let title = $(this).attr('title').toLowerCase().replace(/\s/g, '');

            if (title == 'faleconosco') {
                Utils.abreJanelaPadrao('https://dashboard.tawk.to/login');
            }
        });
    }

    var alignText = function(element, orientacao, cor = 'black') {
        return element.css({
            'text-align': orientacao,
            'color': cor
        });
    }

    var formataNumNegativo = function(num, elem) {
        if (num < 0) {
            return elem.html(num).css({
                'color': 'red'
            });
        } else {
            return elem.html(num).css({
                'color': 'green'
            });
        }
    }

    var feedback = function() {

        $(document).on('click', '.isFeedback, .isClose', function() {
            $("#feedback-form").toggle(200);
        });
    }

    var alteraClass = function(elem, rmClass, addClass = '') {
        return elem.removeClass(rmClass).addClass(addClass);
    }

    var removeAcento = function(string){
        return string.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ç/g, 'c').replace(/\s+/g, '_');
    }

    //EVENTOS DEFINIDOS E JÁ CARREGADOS A SEREM UTILIZADOS EM TODO SISTEMA 
    var bindEvents = function() {
        habilitaTooltip();
        //chatFaleConosco();
        //clickHelpDesk();
        setupJanela();
        feedback();
        //imprimir();
        load();
        goToTop();
        loadMudaMunicipio();
        loadMudaExercicio()
        limitaNumero();
        limitaFloat();
    }

    return {
        as_tinyMCE: as_tinyMCE, // Versão completa do plugin "AINDA EM DESENVOLVIMENTO"
        as_tinyMCE_basic: as_tinyMCE_basic, // Versão compacta do plugin 100% funcional
        bindEvents: bindEvents,
        decFormat: decFormat,
        decFormatMoeda: decFormatMoeda,
        envioDiretoS3: envioDiretoS3,
        formAjax: formAjax,
        globalPostAjax: globalPostAjax,
        habilitaTooltip: habilitaTooltip,
        hideModal: hideModal,
        loadingMessage: loadingMessage,
        // loadSelect2: loadSelect2,
        modal: modal,
        number_format: number_format,
        redirect: redirect,
        alignText: alignText,
        setUTF8Encode: setUTF8Encode,
        alteraClass: alteraClass,
        uploadDiretoS3: uploadDiretoS3,
        chatFaleConosco: chatFaleConosco,
        formataNumNegativo: formataNumNegativo,
        abreJanelaPadrao: abreJanelaPadrao,
        limitaNumero: limitaNumero,
        removeAcento: removeAcento
    }

})();

//CARREGA FUNÇÕES GLOBAIS
$(document).ready(function() {

    Utils.bindEvents();
})
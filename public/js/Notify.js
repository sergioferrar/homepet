/**
 * Biblioteca para criação de notificações (modais, alerts, overlay cards) de maneira higiênica.
 * A biblioteca cria os componentes sem a necessidade de modificar a view: ao criar um modal pela lib,
 * por exemplo, um elemento novo de id aleatório (para evitar conflito com elementos HTML já existentes
 * na view) é concatenado no body da página, e ao fechar o modal este elemento é deletado da página.
 * A ideia é poder, apenas pelo chamar das funções da lib, criar novas notificações, sem a necessidade
 * da criação de elementos HTML na view, e sem a necessidade de referenciar modais, alerts ou cards que
 * já existam.
 */
 const Notify = (function() {
    const ANIMATION_TIME_MILLIS = 500; // Tempo de animaÃ§Ã£o de translado para os overlay cards e alerts
    const LOADING_TEXT = "Por favor, aguarde..."; // Texto default para os loadings
    const OVERLAY_DIV_HIDE_DIST = "30px"; // Distancia para a animaÃ§Ã£o de translado para os overlay cards e alerts

    // FunÃ§Ã£o para criar o id randomico
    var generateRandomId = function () {
        let arr = new Uint8Array(8);
        window.crypto.getRandomValues(arr);
        let id = "signotify__";
        arr.forEach((x) => {
            id += x.toString(32)
        });
        return id;
    }

    // Classe de handler para a notificaÃ§Ã£o
    class SignotifyElement {
        constructor(id, hideFunction) {
            this.id = id;
            this.__hideFunction = hideFunction;
        }

        hide() {
            this.__hideFunction(this.id);
        }
    }

    //---------------------------------------------------------------------------------------------
    // Componentes HTML
    //---------------------------------------------------------------------------------------------


    //Testa se o conteudo passado Ã© uma url
    var checkElementoBody = function (body) {

        var elem = '';
        try {
            body = new URL(body)

            /*elem = `<div class="embed-responsive embed-responsive-19by9">
                      <iframe class="embed-responsive-item" src="${body}"></iframe>
                </div>`;*/
            elem = `<div class="ratio ratio-21x9 ">
						<iframe src="${body}" class="embed-responsive-item" height="auto" width="auto" allowfullscreen> </iframe>
			 		</div>`;


        } catch (err) {
            //console.table(err);
            elem = body;
        }

        return elem;
    }

    var modalComponent = function (id, body, lengthModal = 'lg', title = null, okCallback = null, okText = "Fechar", cancelText = null, confirmationClass = null, status = 'default') {
        let bgmodal = status == 'default' ? '' : 'bg-' + status;
        let textModal = status == 'default' ? '' : 'text-white'
        return `
		
			<div id=${id} class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog">
				<div class="modal-dialog modal-${lengthModal} modal-dialog-centered" role="document">
					<div class="modal-content">
					<div class="modal-header  ${title == null ? 'd-none' : ''} ${bgmodal} ${textModal}">
						<h5 class="modal-title">${title}</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<p>${checkElementoBody(body)}</p>
					</div>
					<div class="modal-footer ${okText == null ? 'd-none' : ''}">
						<button type="button" class="btn btn-secondary ${cancelText == null ? 'd-none' : ''}" data-bs-dismiss="modal">${cancelText}</button>
						<button id=${id + "_btn"} name=${okText} type="button" class="btn btn-primary ${okText == null ? 'd-none' : ''} ${confirmationClass == null ? '' : confirmationClass}">${okText}</button>
					</div>
					</div>
				</div>
			</div>
		`;
    }

    var modalAlertComponent = function (id, body, title = "", status = 'default', icon='fa-check', okCallback = null) {
        let bgmodal = status == 'default' ? '' : 'bg-' + status;
        return `
		
			<div id=${id} class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog">
				<div class="modal-dialog modal-lg modal-dialog-centered modal-confirm" role="document">
					<div class="modal-content">
					<div class="modal-header justify-content-center ${bgmodal}">
						<div class="icon-box">
							<i class="fa ${icon}"></i>
						</div>
					</div>
					<div class="modal-body text-center">
					    
						<p>${checkElementoBody(body)}</p>
						<button id=${id + "_btn"} type="button" class="btn btn-outline-${status} rounded" data-bs-dismiss="modal">Fechar</button>
					</div>
					</div>
				</div>
			</div>
		`;
        let textModal = status == 'default' ? '' : 'text-white'
    }

    var loadingComponent = function (text) {
        return `
		<div class="d-flex">
			<div class="spinner-border text-secondary mx-3" role="status"></div>
			<div class="d-flex flex-column justify-content-center text-secondary">
				${text}
			</div>
		</div>
		`;
    }

    var statusCardComponent = function (id, cardType, content) {
        return `
		<div id="${id}" class="alert alert-${cardType}" role="alert">
			${content}
	  	</div>
		`;
    }

    var overlayComponent = function (id, content) {
        return `
		<div id="${id}" style="position: absolute; opacity: 0; width: 100%; height: 100%; top: ${OVERLAY_DIV_HIDE_DIST}; left: 0; right: 0; bottom: 0; z-index: 2;"
		class="d-flex justify-content-center">
			<div class="d-flex flex-column justify-content-center">
				<div class="d-flex">
					${content}
				</div>
			</div>
		</div>
		`;
    }

    var cardInsideOverlayComponent = function (text) {
        return `
		<div class="card" style="min-height: unset; height: unset; min-width: unset; width: unset;">
			<div class="card-body d-flex justify-content-center px-4">
				<div class="d-flex flex-column justify-content-center text-secondary">
					${text}
				</div>
			</div>
		</div>
		`;
    }

    var loadingInsideOverlayComponent = function (text) {
        return `
		<div class="card" style="min-height: unset; height: unset; min-width: unset; width: unset;">
			<div class="card-body d-flex justify-content-center px-4">
				<div class="spinner-border text-secondary" role="status"></div>
				<div class="px-2"></div>
				<div class="d-flex flex-column justify-content-center text-secondary">
					${text}
				</div>
			</div>
		</div>
		`;
    }

    var toastComponent = function (id, body, status = 'default') {
        let bgToast = `text-bg-${status}`;

        return `<div class="toast-container position-fixed top-0 end-0 p-3">
				  <div id=${id} class="toast align-items-center ${bgToast} border-0" role="alert" aria-live="assertive" data-bs-config='{"delay":1500}' aria-atomic="true">
				    <div class="d-flex">
					    <div class="toast-body">
					      ${body}
					    </div>
				    	<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
				    </div>
				  </div>
				</div>`;
    }

    //---------------------------------------------------------------------------------------------
    // Procedimentos principais
    //---------------------------------------------------------------------------------------------

    // Cria um card overlay sobre alguma div
    var divCard = function (divJquerySelector, text, timeout = 5) {
        let id = generateRandomId();

        $(divJquerySelector).append(overlayComponent(id, cardInsideOverlayComponent(text)));

        $('#' + id).animate({"opacity": "1", "top": "0"}, ANIMATION_TIME_MILLIS / 2);

        setTimeout(function () {
            hideOverlayDiv(id)
        }, timeout * 1000);

        return new SignotifyElement(id, hideOverlayDiv);
    }

    // Cria um loading overlay sobre alguma div
    var divLoading = function (divJquerySelector, text = LOADING_TEXT, timeout = 9999) {
        let id = generateRandomId();

        $(divJquerySelector).append(overlayComponent(id, loadingInsideOverlayComponent(text)));

        $('#' + id).animate({"opacity": "1", "top": "0"}, ANIMATION_TIME_MILLIS / 2);

        setTimeout(function () {
            hideOverlayDiv(id)
        }, timeout * 1000);

        return new SignotifyElement(id, hideOverlayDiv);
    }

    // Cria um alert no topo de alguma div
    var statusCard = function (fatherJquerySelector, content, cardType = "danger", timeout = 5) {
        let id = generateRandomId();

        $(fatherJquerySelector).prepend(statusCardComponent(id, cardType, content));

        setTimeout(function () {
            hideStatusCard(id)
        }, timeout * 1000);

        return new SignotifyElement(id, hideStatusCard);
    }

    // Cria um loading preechendo a tela inteira
    var globalLoading = function (text = LOADING_TEXT) {
        return modal(loadingComponent(text), null, null, null, null);
    }

    // Cria um modal e o abre
    var modal = function (body, lengthModal = 'lg', title = null, okCallback = null, okText = "Fechar", cancelText = null, confirmationClass = null, status = 'default') {
        let id = generateRandomId();


        $("body").append(modalComponent(id, body, lengthModal, title, okCallback, okText, cancelText, confirmationClass, status));

        $('#' + id).on('hidden.bs.modal', function (e) {
            $(this).remove();
        });

        if (okCallback != null) {
            $('#' + id + "_btn").click(function () {
                okCallback();
                $('#' + id).modal('hide');
            });
        } else {
            $('#' + id + "_btn").click(function () {
                $('#' + id).modal('hide');
            });
        }

        $('#' + id).modal('show');

        //$("#" + id).draggable(); //permite mover modal -> Obs.: necessidade da biblioteca ui e essa biblioteca Ã© incompatÃ­vel com datepicker das notificaÃ§Ãµes, etc -> verificar

        return new SignotifyElement(id, hideModal);
    }
    var modalAlert = function (body, title = null, status = 'default', icon='fa-check', okCallback = null) {
        let id = generateRandomId();
//modalAlertComponent = function (id, body, title="", status = 'default', okCallback = null)

        $("body").append(modalAlertComponent(id, body, title, status, icon, okCallback));

        $('#' + id).on('hidden.bs.modal', function (e) {
            $(this).remove();
        });

        if (okCallback != null) {
            $('#' + id + "_btn").click(function () {
                okCallback();
                $('#' + id).modal('hide');
            });
        } else {
            $('#' + id + "_btn").click(function () {
                $('#' + id).modal('hide');
            });
        }

        $('#' + id).modal('show');

        //$("#" + id).draggable(); //permite mover modal -> Obs.: necessidade da biblioteca ui e essa biblioteca Ã© incompatÃ­vel com datepicker das notificaÃ§Ãµes, etc -> verificar

        return new SignotifyElement(id, hideModal);
    }

    var toast = function (body, status) {
        let id = generateRandomId();
        $('body').append(toastComponent(id, body, status));
        //hideToast
        const toastElList = $('.toast')
        // const toastList = [...toastElList].map(toastEl => new bootstrap.Toast(toastEl))

        toastElList.toast('show');
        return new SignotifyElement(id, hideToast);
    }

    //---------------------------------------------------------------------------------------------
    // Procedimentos de hide, preferencialmente nÃ£o devem ser chamados diretamente, use o .hide()
    // do handler ao invÃ©s.
    //---------------------------------------------------------------------------------------------

    var hideModal = function (id) {
        if ($('#' + id).length == 0) {
            return;
        }

        $('#' + id).modal('hide');
    }

    var hideStatusCard = function (id) {
        if ($('#' + id).length == 0) {
            return;
        }

        $('#' + id).animate({"opacity": "0"}, ANIMATION_TIME_MILLIS);

        setTimeout(function () {
            $('#' + id).remove();
        }, ANIMATION_TIME_MILLIS * 1.01);
    }

    var hideOverlayDiv = function (id) {
        if ($('#' + id).length == 0) {
            return;
        }

        $('#' + id).animate({"opacity": "0", "top": OVERLAY_DIV_HIDE_DIST}, ANIMATION_TIME_MILLIS / 2);

        setTimeout(function () {
            $('#' + id).remove();
        }, ANIMATION_TIME_MILLIS / 2 * 1.01);
    }

    var hideToast = function (id) {
        if ($('#' + id).length == 0) {
            return;
        }

        $('#' + id).toast('hide');
    }
    
    var bindEvents = function() {
        
        /*$(function() {
            $( "#draggable" ).draggable();
        });*/       
    }
    
    return { modal: modal, hideModal: hideModal, globalLoading: globalLoading, statusCard: statusCard, toast:toast,
             hideStatusCard: hideStatusCard, divLoading: divLoading, divCard: divCard, hideOverlayDiv: hideOverlayDiv, bindEvents: bindEvents };
})();

$(document).ready(function() {
    
    Notify.bindEvents();
})

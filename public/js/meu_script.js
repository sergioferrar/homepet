document.addEventListener("DOMContentLoaded", function() {
    var itensAgendamento = document.querySelectorAll('.list-group-item');

    itensAgendamento.forEach(function(item) {
        item.addEventListener('click', function() {
            var texto = this.innerText.trim();
            alert("Você clicou em: " + texto);
        });
    });
});
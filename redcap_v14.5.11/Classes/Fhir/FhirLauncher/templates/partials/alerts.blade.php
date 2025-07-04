<div>
  {!! $alerts !!}
</div>

<script>

  (function() {

    function initCloseButton(buttonElement) {
      if(!buttonElement) return;
      buttonElement.addEventListener('click', function(_) {
        var alertElement = buttonElement.parentNode;
        if(!alertElement) return;
        var alertInstance = new bootstrap.Alert(alertElement);
        alertInstance.close();
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      var alertButtons = document.querySelectorAll('.alert button.btn-close');
      alertButtons.forEach(function(element) {
        initCloseButton(element);
      });
    });
  })()
</script>

<style type="text/css">
.btn-close {
  box-sizing: content-box;
  width: 1em;
  height: 1em;
  padding: .25em .25em;
  color: #000;
  background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center / 1em auto no-repeat;
  border: 0;
  border-radius: .375rem;
  opacity: 0.5;
  margin-left: auto;
  position: absolute;
  right: 10px;
  top: 10px;
}
.alert {
    position: relative;
}
</style>
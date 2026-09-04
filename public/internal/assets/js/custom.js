$(document).ready(function(){
    if($('.dateSelector').length > 0){
        $('.dateSelector').datepicker();
    }

    if($(".dateTimeSelector").length > 0){
        $(".dateTimeSelector").each(function(){
            var id = $(this).attr("id");
            $("#"+id).datetimepicker({
                format: 'YYYY-MM-DD HH:mm'
            });
        });
    }

    // if($('.form-select').length > 0){
    //     $('.form-select').each(function(){
    //         console.log($(this).val());
    //         $(this).prepend('<option></option>').select2({
    //             minimumResultsForSearch: -1,
    //             allowClear: true,
    //         });
    //     });
    // }

    // Searchable selects: opt-in via data-control="select2" (dynamic entity lists).
    // Tiny static enums should omit data-control and stay native.
    if (typeof $ !== 'undefined' && $.fn && typeof $.fn.select2 === 'function') {
        $('select[data-control="select2"]').each(function () {
            var $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            var placeholder = $select.data('placeholder') || 'Select an option';
            var allowClear = $select.data('allow-clear') !== false && ! $select.prop('required');

            $select.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: allowClear,
                dropdownParent: $select.closest('.modal').length
                    ? $select.closest('.modal')
                    : $(document.body),
            });
        });
    }

    if($(".divDateRangePicker").length > 0){
        $(".divDateRangePicker").each(function(){
            var id = $(this).attr("id");
            $("#"+id).datetimepicker({
                buttonClasses: ' btn',
                applyClass: 'btn-primary',
                cancelClass: 'btn-secondary',
                timePicker: true,
                timePickerIncrement: 15,
                locale: {
                    format: 'yyyy-dd-dd hh:mm'
                }
            }, function(start, end, label){
                $("#"+id).find(".form-control").val( start.format('yyyy-dd-dd hh:mm') + ' to ' + end.format('yyyy-dd-dd hh:mm'));
            });
        });
    }

    if($("[data-editor-type='editor']").length > 0){
        tinymce.init({
            selector: "[data-editor-type='editor']",
            menubar: false,
            toolbar: ["styleselect fontselect fontsizeselect",
                "undo redo | cut copy paste | bold italic | link image | alignleft aligncenter alignright alignjustify",
                "bullist numlist | outdent indent | blockquote subscript superscript | advlist | autolink | lists charmap | print preview |  code"],
            plugins : "advlist autolink link image lists charmap print preview code"
        });
    }

    if($(".ckeditor").length > 0){
        tinymce.init({
            selector: "[data-editor-type='editor']",
            menubar: false,
            toolbar: ["styleselect fontselect fontsizeselect",
                "undo redo | cut copy paste | bold italic | link image | alignleft aligncenter alignright alignjustify",
                "bullist numlist | outdent indent | blockquote subscript superscript | advlist | autolink | lists charmap | print preview |  code"],
            plugins : "advlist autolink link image lists charmap print preview code"
        });
    }
});

$(document).on("click", "[data-action-confirm-element-click='delete']",function(e) {
    e.preventDefault();
    var element = this;
    Swal.fire({
        title: ($(element).data("title") !== undefined ? $(element).data("title") : "Do you confirm this action?"),
        text: ($(element).data("description") !== undefined ? $(element).data("description") : "You won't be able to revert this!"),
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: ($(element).data("button") !== undefined ? $(element).data("button") : "Yes, do it!")
    }).then(function(result) {
        if (result.value) {
            window.location = $(element).data("href");
        }
    });
});

$(document).on("click", "[data-action-element-click='form-submit']", function(e) {
    e.preventDefault();
    formSubmit(this);
});

$(document).on("change","[data-action-dropdown-change='form-submit']",function(e) {
    e.preventDefault();
    formSubmit(this);
});

function formSubmit(element){
    var form = $($(element).data("form-id"));
    if($(element).data("confirm") !== undefined && $(element).data("confirm") == true){
        Swal.fire({
            title: ($(element).data("title") !== undefined ? $(element).data("title") : "Do you confirm this action?"),
            text: ($(element).data("description") !== undefined ? $(element).data("description") : "You won't be able to revert this!"),
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: ($(element).data("button") !== undefined ? $(element).data("button") : "Yes, do it!")
        }).then(function(result) {
            if (result.value) {
                if($(element).data("form-action") !== undefined){
                    $(form).attr("action", $(element).data("form-action"));
                }
                $(form).submit();
            }
        });
    }else{
        $(form).submit();
    }
}

$(document).on("click", "[data-action-element-click='form-ajax-submit']", function(e) {
    e.preventDefault();
    ajaxFormSubmit(this);
});

$(document).on("change","[data-action-dropdown-change='form-ajax-submit']",function(e) {
    e.preventDefault();
    ajaxFormSubmit(this);
});

function ajaxFormSubmit(element){
    var form = $(document).find("#"+$(element).data("form-id"));
    if($(element).data("confirm") !== undefined && $(element).data("confirm") == true){
        Swal.fire({
            title: ($(element).data("title") !== undefined ? $(element).data("title") : "Do you confirm this action?"),
            text: ($(element).data("description") !== undefined ? $(element).data("description") : "You won't be able to revert this!"),
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: ($(element).data("button") !== undefined ? $(element).data("button") : "Yes, do it!")
        }).then(function(result) {
            if (result.value) {
                if($(element).data("form-action") !== undefined){
                    $(form).attr("action",$(element).data("form-action"));
                }
                sendAjaxForm(form);
            }
        });
    }else{
        sendAjaxForm(form);
    }
}

function sendAjaxForm(form){
    $.ajax({
        url:$(form).attr('action'),
        method:$(form).attr('method'),
        data:$(form).serialize(),
        success:function(response){
            if(response.msg !== undefined){
                showNotification(response.status, response.msg);
            }
            if(response.updateField !== undefined){
                $(response.updateField).html(response.data);
            }
            if(response.closeModal !== undefined){
                $('#'+response.closeModal).modal('hide');
            }
            if(response.showModal !== undefined){
                $('#'+response.showModal).modal('show');
            }
            if(response.redirect !== undefined){
                window.location.href = response.redirect;
            }
            if(response.reload !== undefined){
              window.location.reload();
            }
        }
    });
}


$(document).on("click","[data-action-modal-click='modal-url']",function(e) {
    e.preventDefault();
    $($(this).data("modal-id")).find('.modal-content').html("");
    $($(this).data("modal-id")).modal('show').find('.modal-content').load($(this).data('href'));
});

function showNotification(type,msg){
    toastr.options = {
      "closeButton": true,
      "debug": false,
      "newestOnTop": true,
      "progressBar": true,
      "positionClass": "toastr-top-right",
      "preventDuplicates": true,
      "onclick": null,
      "showDuration": "300",
      "hideDuration": "1000",
      "timeOut": "5000",
      "extendedTimeOut": "1000",
      "showEasing": "swing",
      "hideEasing": "linear",
      "showMethod": "fadeIn",
      "hideMethod": "fadeOut"
    };
    switch(type){
        case "success" : toastr.success(msg); break;
        case "error" : toastr.error(msg); break;
        case "warning" : toastr.warning(msg); break;
        case "info" : toastr.info(msg); break;
    }
}

$(document).on("change",'[data-checkbox-type="group"]',function(){
    var table = $(this).parents("table");
    $(table).find('tbody [data-checkbox-type="single"]').prop('checked', this.checked);
    showActionButtons($(table).find('tbody [data-checkbox-type="single"]:checked').length);
});

$(document).on("change",'[data-checkbox-type="single"]',function(){
    var table = $(this).parents("table");

    if($(table).find('tbody [data-checkbox-type="single"]:checked').length === $(table).find('tbody [data-checkbox-type="single"]').length){
        $(table).find('thead [data-checkbox-type="group"]').prop('checked', true);
    }else{
        $(table).find('thead [data-checkbox-type="group"]').prop('checked', false);
    }
    showActionButtons($(table).find('tbody [data-checkbox-type="single"]:checked').length);
});

function showActionButtons(count){
    $('[data-update="counter"]').html(count);
    if(count > 0){
        $('[data-section="actions"]').removeClass('d-none');
        $('[data-section="filters"]').addClass('d-none');
    }else{
        $('[data-section="filters"]').removeClass('d-none');
        $('[data-section="actions"]').addClass('d-none');
    }
}


function toggleBlock(target) {
    // var blockUI = new KTBlockUI(document.querySelector(target));

    // if (blockUI.isBlocked()) {
    //     blockUI.release();
    // } else {
    //     blockUI.block();
    // }
}


$(document).on("click",'[data-action-element-click="clipboard"]',function(){

    const target = document.getElementById($(this).attr("id"));

    clipboard = new ClipboardJS(target);

    // Success action handler
    clipboard.on('success', function (e) {
        const currentLabel = target.innerHTML;

        // Exit label update when already in progress
        if (target.innerHTML === 'Copied!') {
            return;
        }

        // Update button label
        target.innerHTML = 'Copied!';

        // Revert button label after 3 seconds
        setTimeout(function () {
            target.innerHTML = currentLabel;
        }, 3000)
    });
});

function formValidate(formId, rules){
  var form = document.getElementById(formId);
  var formToValidate = FormValidation.formValidation(
    form,
    {
      fields: rules,
      plugins: {
        trigger: new FormValidation.plugins.Trigger,
        bootstrap: new FormValidation.plugins.Bootstrap5({
            rowSelector: ".row,.fv-row",
            eleInvalidClass: "",
            eleValidClass: ""
        }),
        // Validate fields when clicking the Submit button
        submitButton: new FormValidation.plugins.SubmitButton(),
        // Submit the form when all fields are valid
        defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
      }
    }
  );

  console.log(formToValidate);
  // formToValidate.validate().then((function(valid) {
  //   "Valid" == valid ? form.submit() : swal.fire({
  //     text: "Sorry, looks like there are some errors detected, please try again.",
  //     icon: "error",
  //     buttonsStyling: !1,
  //     confirmButtonText: "Ok, got it!",
  //     customClass: {
  //       confirmButton: "btn font-weight-bold btn-light-primary"
  //     }
  //   });
  // }));
}

$(document).on("click",'[data-action-element-click="display-toggle"]',function(){
  const target = document.querySelectorAll('[data-element-toggle-ref="' + $(this).data("toggle-ref") + '"]');
  target.forEach(function (element){
    element.classList.toggle("d-none");
  });
});

$(document).on("click",'[data-action-element-click="modal-form-reset-discard"]', function (e) {
  e.preventDefault();
  const element = $(this);
  // Show success message. For more info check the plugin's official documentation: https://sweetalert2.github.io/
  Swal.fire({
    text: "Are you sure you would like to cancel?",
    icon: "warning",
    showCancelButton: true,
    buttonsStyling: false,
    confirmButtonText: "Yes, cancel it!",
    cancelButtonText: "No, return",
    customClass: {
      confirmButton: "btn btn-primary",
      cancelButton: "btn btn-active-light"
    }
  }).then(function (result) {
    if (result.value) {
      document.querySelector("#" + $(element).data("form-id")) // Reset form
      $(element).parents(".modal").modal('toggle'); // Hide modal
    } else if (result.dismiss === 'cancel') {
      // Show error message.
      Swal.fire({
        text: "Your form has not been cancelled!.",
        icon: "error",
        buttonsStyling: false,
        confirmButtonText: "Ok, got it!",
        customClass: {
          confirmButton: "btn btn-primary",
        }
      });
    }
  });
});

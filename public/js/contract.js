$(function () {
    $('select').select2({placeholder: "Select", allowClear: true, theme: "classic"});
    $('.contract-form').validate();

    $('.datepicker').datetimepicker({
        timepicker: false,
        format: 'Y-m-d',
        scrollInput: false
    });

    translation();

    function translation() {
        var div = $('.translation-parent');
        if ($('.translation:checked').val() == 1) {
            div.removeClass('hide');
        }
        else {
            div.addClass('hide');
        }
    }

    $('.translation').on('change', function () {
        translation();
    });

    $(document).on('click', '.company .item .delete', function (e) {
        $(this).parent().remove();
    });

    $('.new-company').on('click', function (e) {
        e.preventDefault();
        i += 1;
        var template = $('#company-template').html();
        Mustache.parse(template);
        var rendered = Mustache.render(template, {item: i});
        $('.company .item:last-child').after(rendered);
        $('select').select2({placeholder: "Select", allowClear: true, theme: "classic"});
        $('.datepicker').datetimepicker({
            timepicker: false,
            format: 'Y-m-d',
            scrollInput: false
        });
    })

    $(document).on('click', '.concession .con-item .delete', function (e) {
        $(this).parent().remove();
    });

    $('.new-concession').on('click', function (e) {
        e.preventDefault();
        j += 1;
        var template = $('#concession-template').html();
        Mustache.parse(template);
        var rendered = Mustache.render(template, {item: j});
        $('.concession .con-item:last-child').after(rendered);
    })

    $(document).on('change', '.operator', function () {
        var val = 0;

        if (this.checked) {
            val = $(this).val();
        }
        console.log(val);
        $(this).parent().find('.hidden-operator').val(val);
    });

});


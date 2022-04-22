window.ycPay = null;
window.setupYouCanPayForm = () => {
    try {
        if (window.ycPay == null) {
            window.ycPay = new YCPay(
                youcan_pay_script_vars.key, {
                    locale: youcan_pay_script_vars.locale,
                    isSandbox: parseInt(youcan_pay_script_vars.is_test_mode) === 1,
                    formContainer: '#payment-card',
                    customCSS: '.gateway-selector{max-width:unset;}'
                }
            );

            window.ycPay.renderAvailableGateways();
        }
    } catch (error) {
        console.error(error);
    }
};

jQuery(function ($) {
    var gateways = {
        'credit_card': 1,
        'cash_plus': 2,
    };
    var $notice_group = $('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"></div>');

    function detach_loader($form, loader) {
        let target = '.woocommerce';
        if ((window.ycPay !== null) && (gateways.cash_plus === parseInt(window.ycPay.selectedGateway))) {
            target = '.wc_payment_methods';
        }

        $('html, body').animate({
            scrollTop: $(target).offset().top
        }, 400);

        $('.blockOverlay').remove();
        $('.woocommerce-notices-wrapper').remove();
        $form.removeClass('processing');

        if (loader != null) {
            clearInterval(loader);
            $('.woocommerce-NoticeGroup-checkout').remove();
        }
    }

    function process_payment($form, data) {
        if (typeof (data.token_transaction) !== 'undefined') {
            var loader = null;
            try {
                loader = setInterval(function () {
                    if (jQuery('#ycp-3ds-modal').length > 0) {
                        detach_loader($form, loader);
                    }
                }, 500);

                window.ycPay.pay(data.token_transaction)
                    .then(function ({ response }) {
                        detach_loader($form, loader);

                        if (typeof (data.redirect) !== 'undefined') {
                            let url = new URL(data.redirect);
                            url.searchParams.set('transaction_id', response.transaction_id);
                            if (gateways.cash_plus === parseInt(window.ycPay.selectedGateway)) {
                                url.searchParams.set('gateway', 'cash_plus');
                            }
                            window.location.href = url.href;
                        }
                    })
                    .catch(function ({ errorMessage }) {
                        detach_loader($form, loader);
                        display_notice($form, errorMessage);
                    });
            } catch (error) {
                detach_loader($form, loader);
                console.error(error);
            }
        }

        detach_loader($form);
    }

    function display_notice($form, message) {
        if ((message === null) || (message.length < 1)) {
            message = youcan_pay_script_vars.errors.connexion_api;
        }

        let notice = $notice_group.clone();
        notice.append('<ul class="woocommerce-error" role="alert"></ul>');
        notice.find('ul').append('<li>' + message + '</li>');
        $form.prepend(notice);
    }

    function display_notices($form, data) {
        if (typeof (data.messages) !== 'undefined') {
            let notice = $notice_group.clone();

            notice.append(data.messages);
            $form.prepend(notice);

            return true;
        }

        return false;
    }

    function capitalize_words(str) {
        let i, frags = str.split('_');
        for (i=0; i<frags.length; i++) {
            frags[i] = frags[i].charAt(0).toUpperCase() + frags[i].slice(1);
        }

        return frags.join(' ');
    }

    function validate_form($form, callback) {
        let has_error = false;
        $('.woocommerce-NoticeGroup').remove();

        let selected_gateway = $('input[name=payment_method]:checked').val();

        if (!youcan_pay_script_vars.gateways.includes(selected_gateway)) {
            callback(false, null);

            return false;
        }

        $form.find('.validate-required').each(function (index, row) {
            let $row = $(row);
            let $input = $row.find('input');
            let type = $input.attr('type');
            let value = $input.val();

            if ($input.is(':visible')) {
                switch (type) {
                    case 'checkbox':
                        has_error = !$input.is(':checked');
                        break;
                    case 'text':
                        has_error = (value.length < 1);
                        break;
                }

                if (has_error === true) {
                    detach_loader($form);
                    callback(has_error, $input);

                    return false;
                }
            }
        });

        if (has_error === false) {
            callback(false, null);
        }
    }

    function create_order_and_process_payment($form) {
        $.ajax({
            method: "POST",
            url: youcan_pay_script_vars.checkout_url,
            data: $form.serialize(),
            dataType: "json",
            beforeSend: function () {
                try {
                    $('.woocommerce-NoticeGroup-checkout').remove();
                    $form.addClass('processing');
                    $form.block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });
                } catch (error) {
                    console.error(error);
                }
            }
        }).done(function (data) {
            if (!display_notices($form, data)) {
                process_payment($form, data);
            }
        }).fail(function (data) {

        }).always(function (data) {
            if (typeof (data.token_transaction) !== 'undefined') {
                return;
            }
            detach_loader($form);
        });
    }

    function is_pre_order() {
        return parseInt(youcan_pay_script_vars.is_pre_order) === parseInt(youcan_pay_script_vars.order_actions.pre_order);
    }

    $(document).on('click', '#place_order', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var $form = $('form[name=checkout]');
        if ($form.length < 1) {
            $form = $('#order_review');
        }

        if ($form.is('.processing')) {
            return false;
        }

        validate_form($form, function (has_error, $input) {
            if (has_error === true) {
                let name = $input.attr('name');
                let translated_name = youcan_pay_script_vars.inputs[name];

                if ('undefined' === translated_name) {
                    translated_name = capitalize_words(name);
                }

                let error_message = youcan_pay_script_vars.errors.input_required.replace('%s', translated_name);

                display_notice($form, error_message);

                return false;
            }

            let selected_gateway = $('input[name=payment_method]:checked').val();
            if (selected_gateway === youcan_pay_script_vars.default_gateway) {
                if (true === is_pre_order()) {
                    process_payment($form, {
                        token_transaction: youcan_pay_script_vars.token_transaction,
                        redirect: youcan_pay_script_vars.redirect,
                    });
                } else {
                    create_order_and_process_payment($form);
                }

                return true;
            }

            $form.submit();
        });
    });
});

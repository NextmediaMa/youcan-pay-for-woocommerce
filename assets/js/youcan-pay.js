window.ycPay = null;
window.setupYouCanPayForm = () => {
    try {
        if (window.ycPay == null) {
            window.ycPay = new YCPay(youcan_pay_script_vars.key, {locale: youcan_pay_script_vars.locale});
            if (parseInt(youcan_pay_script_vars.is_test_mode) === 1) {
                window.ycPay.setSandboxMode(true);
            }
        }

        switch (jQuery('input[name=payment_method]:checked').val()) {
            case youcan_pay_script_vars.gateways.youcanpay:
                window.ycPay.renderForm('#payment-card', 'default');
                break;
            case youcan_pay_script_vars.gateways.cash_plus:
                window.ycPay.renderCashPlusForm('#container-cash-plus', 'default');
                break;
        }
    } catch (error) {
        console.error(error);
    }
};

jQuery(function ($) {
    function detach_loader($form, loader) {
        $('html, body').animate({
            scrollTop: $('.woocommerce').offset().top
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
                    .then(function (transactionId) {
                        detach_loader($form, loader);

                        if (typeof (data.redirect_url) !== 'undefined') {
                            window.location.href = data.redirect_url;
                            return;
                        }
                        if (typeof (data.redirect) !== 'undefined') {
                            let url = new URL(data.redirect);
                            url.searchParams.set('transaction_id', transactionId);
                            window.location.href = url.href;
                        }
                    })
                    .catch(function (errorMessage) {
                        detach_loader($form, loader);

                        let notice = $noticeGroup.clone();
                        notice.append('<ul class="woocommerce-error" role="alert"></ul>');
                        notice.find('ul').append('<li>' + errorMessage + '</li>');
                        $form.prepend(notice);
                    });
            } catch (error) {
                detach_loader($form, loader);
                console.error(error);
            }
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

    function display_notices($form, data) {
        if (typeof (data.messages) !== 'undefined') {
            let notice = $noticeGroup.clone();

            notice.append(data.messages);
            $form.prepend(notice);

            return true;
        }

        return false;
    }

    function is_pre_order() {
        return parseInt(youcan_pay_script_vars.is_pre_order) === parseInt(youcan_pay_script_vars.order_actions.pre_order);
    }

    var $noticeGroup = $('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"></div>');

    $(document).on('click', '#place_order', function (e) {
        e.preventDefault();

        var $form = $('form[name=checkout]');
        if ($form.length < 1) {
            $form = $('#order_review');
        }

        if ($form.is('.processing')) {
            return false;
        }

        switch ($('input[name=payment_method]:checked').val()) {
            case youcan_pay_script_vars.gateways.youcanpay:
            case youcan_pay_script_vars.gateways.cash_plus:
                if (true === is_pre_order()) {
                    process_payment($form, {
                        token_transaction: youcan_pay_script_vars.token_transaction,
                        redirect: youcan_pay_script_vars.redirect,
                    });

                    return true;
                }

                create_order_and_process_payment($form);
                break;
            case youcan_pay_script_vars.gateways.standalone:
                $form.submit();
                break;
        }
    });

    $(document).on('change', '[name=payment_method]', function (e) {
        window.setupYouCanPayForm();
    });

});
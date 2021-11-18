window.setupYouCanPayForm = () => {
    window.ycPay = new YCPay(youcan_pay_script_vars.key, {locale: youcan_pay_script_vars.youcanpay_locale});
    if (window.ycPay != null) {
        if (parseInt(youcan_pay_script_vars.is_test_mode) === 1) {
            window.ycPay.isSandboxMode = true;
        }
        window.ycPay.renderForm('#payment-card');
    }
};

jQuery(function ($) {
    var $noticeGroup = $('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"></div>');

    $(document).on('click', '#place_order', function (e) {
        e.preventDefault();

        var $form = $('form[name=checkout]');

        if ( $form.is( '.processing' ) ) {
            return false;
        }

        if ($('input[name=payment_method]:checked').val() === youcan_pay_script_vars.youcanpay) {
            $.ajax({
                method: "POST",
                url: youcan_pay_script_vars.checkout_url,
                data: $form.serialize(),
                dataType: "json",
                beforeSend: function( xhr ) {
                    try {
                        $form.addClass('processing');
                        $form.block({
                            message: null,
                            overlayCSS: {
                                background: '#fff',
                                opacity: 0.6
                            }
                        });
                        $('.woocommerce-NoticeGroup-checkout').remove();
                    } catch (error) {
                        console.error(error);
                    }
                }
            }).done(function(data) {
                if (typeof(data.messages) !== 'undefined') {
                    let notice = $noticeGroup.clone();

                    notice.append(data.messages);
                    $form.prepend(notice);
                    return;
                }
                if (typeof(data.token_transaction) !== 'undefined') {
                    window.ycPay.pay(data.token_transaction)
                        .then(function (transactionId) {
                            var url = new URL(data.redirect);
                            url.searchParams.set('transaction_id', transactionId);
                            window.location.href = url.href;
                        })
                        .catch(function (errorMessage) {
                            let notice = $noticeGroup.clone();

                            notice.append('<ul class="woocommerce-error" role="alert"></ul>');
                            notice.find('ul').append('<li>' + errorMessage + '</li>');
                            $form.prepend(notice);
                        });
                }
            }).fail(function(data) {

            }).always(function() {
                $('html, body').animate({
                    scrollTop: $('.woocommerce').offset().top
                }, 400);
                $('.blockOverlay').remove();
                $form.removeClass('processing');
            });
        } else {
            $form.submit();
        }
    });

});
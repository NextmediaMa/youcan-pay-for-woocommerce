window.setupYouCanPayForm = () => {
    window.ycPay = new YCPay(youcan_pay_script_vars.key);
    if (window.ycPay != null) {
        if (parseInt(youcan_pay_script_vars.is_test_mode) === 1) {
            window.ycPay.isSandboxMode = true;
        }

        window.ycPay.renderForm('#payment-card');
    }
};

jQuery(function ($) {

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
                    $form.addClass('processing');
                    $form.block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });
                }
            }).done(function(data) {
                if (typeof(data.token_transaction) !== 'undefined') {
                    window.ycPay.pay(data.token_transaction)
                        .then(function (transactionId) {

                            var url = new URL(data.redirect);
                            url.searchParams.set('transaction_id', transactionId);
                            window.location.href = url.href;
                        })
                        .catch(function (errorMessage) {
                            console.log(errorMessage);
                        });
                }
            }).fail(function(data) {
                console.log('Done');
                console.log(data);
            }).always(function() {
                $('html, body').animate({scrollTop: 0}, 400);
                $('.blockOverlay').remove();
                $form.removeClass('processing');
            });
        } else {
            $form.submit();
        }
    });

});
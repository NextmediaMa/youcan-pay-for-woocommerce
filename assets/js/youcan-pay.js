jQuery(function ($) {

    $(document).on('click', '#place_order', function (e) {
        e.preventDefault();
        var $form = $(this);

        if ($('input[name=payment_method]:checked').val() === youcan_pay_script_vars.youcanpay) {
            if (window.ycPay != null) {
                window.ycPay.pay(youcan_pay_script_vars.token_transaction)
                    .then(function (transactionId) {
                        $('#transaction-id').val(transactionId);
                        $form.submit();
                    })
                    .catch(function (errorMessage) {
                        console.log(errorMessage);
                    });
            }
        } else {
            $form.submit();
        }
    });

});
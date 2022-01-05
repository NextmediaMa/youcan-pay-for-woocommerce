<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_YouCanPay_Webhook_State.
 *
 * Tracks the most recent successful and unsuccessful webhooks in sandbox and production modes.
 */
class WC_YouCanPay_Webhook_State
{
    const OPTION_PRODUCTION_MONITORING_BEGAN_AT = 'wc_youcan_pay_wh_monitor_began_at';
    const OPTION_PRODUCTION_LAST_SUCCESS_AT = 'wc_youcan_pay_wh_last_success_at';
    const OPTION_PRODUCTION_LAST_FAILURE_AT = 'wc_youcan_pay_wh_last_failure_at';
    const OPTION_PRODUCTION_LAST_ERROR = 'wc_youcan_pay_wh_last_error';

    const OPTION_SANDBOX_MONITORING_BEGAN_AT = 'wc_youcan_pay_wh_sandbox_monitor_began_at';
    const OPTION_SANDBOX_LAST_SUCCESS_AT = 'wc_youcan_pay_wh_sandbox_last_success_at';
    const OPTION_SANDBOX_LAST_FAILURE_AT = 'wc_youcan_pay_wh_sandbox_last_failure_at';
    const OPTION_SANDBOX_LAST_ERROR = 'wc_youcan_pay_wh_sandbox_last_error';

    const VALIDATION_SUCCEEDED = 'validation_succeeded';

    /**
     * Gets whether YouCan Pay is in sandbox mode or not
     *
     * @return bool
     */
    public static function get_sandbox_mode()
    {
        $settings = get_option('woocommerce_youcanpay_settings', []);

        return !empty($settings['sandbox_mode']) && 'yes' === $settings['sandbox_mode'];
    }

    /**
     * Gets (and sets, if unset) the timestamp the plugin first
     * started tracking webhook failure and successes.
     *
     * @return integer UTC seconds since 1970.
     */
    public static function get_monitoring_began_at()
    {
        $option = self::get_sandbox_mode(
        ) ? self::OPTION_SANDBOX_MONITORING_BEGAN_AT : self::OPTION_PRODUCTION_MONITORING_BEGAN_AT;
        $monitoring_began_at = get_option($option, 0);
        if (0 == $monitoring_began_at) {
            $monitoring_began_at = time();
            update_option($option, $monitoring_began_at);

            // Enforce database consistency. This should only be needed if the user
            // has modified the database directly. We should not allow timestamps
            // before monitoring began.
            self::set_last_webhook_success_at(0);
            self::set_last_webhook_failure_at(0);
            self::set_last_error_reason(self::VALIDATION_SUCCEEDED);
        }

        return $monitoring_began_at;
    }

    /**
     * Sets the timestamp of the last successfully processed webhook.
     *
     * @param integer UTC seconds since 1970.
     */
    public static function set_last_webhook_success_at($timestamp)
    {
        $option = self::get_sandbox_mode(
        ) ? self::OPTION_SANDBOX_LAST_SUCCESS_AT : self::OPTION_PRODUCTION_LAST_SUCCESS_AT;
        update_option($option, $timestamp);
    }

    /**
     * Gets the timestamp of the last successfully processed webhook,
     * or returns 0 if no webhook has ever been successfully processed.
     *
     * @return integer UTC seconds since 1970 | 0.
     */
    public static function get_last_webhook_success_at()
    {
        $option = self::get_sandbox_mode(
        ) ? self::OPTION_SANDBOX_LAST_SUCCESS_AT : self::OPTION_PRODUCTION_LAST_SUCCESS_AT;

        return get_option($option, 0);
    }

    /**
     * Sets the timestamp of the last failed webhook.
     *
     * @param integer UTC seconds since 1970.
     */
    public static function set_last_webhook_failure_at($timestamp)
    {
        $option = self::get_sandbox_mode(
        ) ? self::OPTION_SANDBOX_LAST_FAILURE_AT : self::OPTION_PRODUCTION_LAST_FAILURE_AT;
        update_option($option, $timestamp);
    }

    /**
     * Gets the timestamp of the last failed webhook,
     * or returns 0 if no webhook has ever failed to process.
     *
     * @return integer UTC seconds since 1970 | 0.
     */
    public static function get_last_webhook_failure_at()
    {
        $option = self::get_sandbox_mode() ?
            self::OPTION_SANDBOX_LAST_FAILURE_AT :
            self::OPTION_PRODUCTION_LAST_FAILURE_AT;

        return get_option($option, 0);
    }

    /**
     * Sets the reason for the last failed webhook.
     *
     * @param string Reason code.
     */
    public static function set_last_error_reason($reason)
    {
        $option = self::get_sandbox_mode() ? self::OPTION_SANDBOX_LAST_ERROR : self::OPTION_PRODUCTION_LAST_ERROR;
        update_option($option, $reason);
    }

    /**
     * Returns the localized reason the last webhook failed.
     *
     * @return string Reason the last webhook failed.
     */
    public static function get_last_error_reason()
    {
        $option = self::get_sandbox_mode() ? self::OPTION_SANDBOX_LAST_ERROR : self::OPTION_PRODUCTION_LAST_ERROR;

        return get_option($option, false);
    }

    /**
     * Gets the state of webhook processing in a human-readable format.
     *
     * @return string Details on recent webhook successes and failures.
     */
    public static function get_webhook_status_message()
    {
        $monitoring_began_at = self::get_monitoring_began_at();
        $last_success_at = self::get_last_webhook_success_at();
        $last_failure_at = self::get_last_webhook_failure_at();
        $last_error = self::get_last_error_reason();
        $sandbox_mode = self::get_sandbox_mode();

        $date_format = 'Y-m-d H:i:s e';

        // Case 1 (Nominal case): Most recent = success
        if ($last_success_at > $last_failure_at) {
            return sprintf(
                $sandbox_mode ?
                    __('The most recent sandbox webhook, timestamped %s, was processed successfully.', 'youcan-pay') :
                    __('The most recent production webhook, timestamped %s, was processed successfully.', 'youcan-pay'),
                gmdate($date_format, $last_success_at)
            );
        }

        // Case 2: No webhooks received yet
        if ((0 == $last_success_at) && (0 == $last_failure_at)) {
            return sprintf(
                $sandbox_mode ?
                    __('No sandbox webhooks have been received since monitoring began at %s.', 'youcan-pay') :
                    __('No production webhooks have been received since monitoring began at %s.', 'youcan-pay'),
                gmdate($date_format, $monitoring_began_at)
            );
        }

        // Case 3: Failure after success
        if ($last_success_at > 0) {
            return sprintf(
                $sandbox_mode ?
                    __(
                        'Warning: The most recent sandbox webhook, received at %1$s, could not be processed. Reason: %2$s. (The last sandbox webhook to process successfully was timestamped %3$s.)',
                        'youcan-pay'
                    ) :
                    __(
                        'Warning: The most recent production webhook, received at %1$s, could not be processed. Reason: %2$s. (The last production webhook to process successfully was timestamped %3$s.)',
                        'youcan-pay'
                    ),
                gmdate($date_format, $last_failure_at),
                $last_error,
                gmdate($date_format, $last_success_at)
            );
        }

        // Case 4: Failure with no prior success
        return sprintf(
            $sandbox_mode ?
                __(
                    'Warning: The most recent sandbox webhook, received at %1$s, could not be processed. Reason: %2$s. (No sandbox webhooks have been processed successfully since monitoring began at %3$s.)',
                    'youcan-pay'
                ) :
                __(
                    'Warning: The most recent production webhook, received at %1$s, could not be processed. Reason: %2$s. (No production webhooks have been processed successfully since monitoring began at %3$s.)',
                    'youcan-pay'
                ),
            gmdate($date_format, $last_failure_at),
            $last_error,
            gmdate($date_format, $monitoring_began_at)
        );
    }
}

;

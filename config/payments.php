<?php

$productionReadyGateways = array_values(array_filter(array_map(
    static fn ($gateway) => strtolower(trim($gateway)),
    explode(',', (string) env('PRODUCTION_READY_PAYMENT_GATEWAYS', 'paystack,flutterwave,monnify,stripe'))
)));

return [
    /*
    | Only gateways listed here may accept online event payments in production.
    | A gateway belongs here only after it has durable intents, inventory holds,
    | server-side verification, idempotent fulfilment and recovery coverage.
    */
    'production_ready_gateways' => $productionReadyGateways,

    /*
    | Legacy gateway code may be exercised only through an explicit local or
    | test opt-in. The default is deliberately false so staging cannot expose
    | callbacks that lack the durable production payment contract.
    */
    'allow_legacy_non_production' => filter_var(
        env('ALLOW_LEGACY_NON_PRODUCTION_GATEWAYS', false),
        FILTER_VALIDATE_BOOL
    ),

    /*
    | Production-like end-to-end evidence. These values attest a completed
    | sandbox/live-mode drill; they do not execute or simulate provider calls.
    */
    'operations_owner_email' => env('PAYMENT_OPERATIONS_OWNER_EMAIL'),
    'production_drill_at' => env('PAYMENT_PRODUCTION_DRILL_AT'),
    'production_drill_reference' => env('PAYMENT_PRODUCTION_DRILL_REFERENCE'),
    'production_drill_max_age_days' => max(1, (int) env('PAYMENT_PRODUCTION_DRILL_MAX_AGE_DAYS', 180)),
    'drill_paystack_verified' => filter_var(env('PAYMENT_DRILL_PAYSTACK_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'drill_flutterwave_verified' => filter_var(env('PAYMENT_DRILL_FLUTTERWAVE_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'drill_shop_paystack_verified' => filter_var(env('PAYMENT_DRILL_SHOP_PAYSTACK_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'drill_event_checkout_verified' => filter_var(env('PAYMENT_DRILL_EVENT_CHECKOUT_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'drill_webhook_verified' => filter_var(env('PAYMENT_DRILL_WEBHOOK_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'drill_lost_callback_verified' => filter_var(env('PAYMENT_DRILL_LOST_CALLBACK_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'drill_reservation_expiry_verified' => filter_var(env('PAYMENT_DRILL_RESERVATION_EXPIRY_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'drill_refund_verified' => filter_var(env('PAYMENT_DRILL_REFUND_VERIFIED', false), FILTER_VALIDATE_BOOL),
    'drill_reconciliation_verified' => filter_var(env('PAYMENT_DRILL_RECONCILIATION_VERIFIED', false), FILTER_VALIDATE_BOOL),
];

<?php

namespace App\Services;

use App\Models\Admin;
use DomainException;

class BookingExportFieldPolicyService
{
    public const FIELDS = [
        'booking_id' => 'Booking Id', 'event' => 'Event', 'customer_name' => 'Customer Name',
        'discount' => 'Discount', 'early_bird_discount' => 'Early Bird Discount',
        'quantity' => 'Quantity', 'total' => 'Total', 'attendee_name' => 'Name',
        'email' => 'Email', 'phone' => 'Phone', 'city' => 'City', 'state' => 'State',
        'country' => 'Country', 'zip_code' => 'Zip Code', 'payment_method' => 'Payment Method',
        'payment_status' => 'Payment Status', 'ledger_gateway' => 'Ledger Gateway',
        'gateway_reference' => 'Gateway Reference', 'charged_amount' => 'Charged Amount',
        'refunded_amount' => 'Refunded Amount', 'refund_status' => 'Refund Status',
        'settlement_id' => 'Settlement ID', 'settlement_status' => 'Settlement Reconciliation',
        'settlement_date' => 'Settlement Date', 'gateway_fee' => 'Gateway Fee', 'date' => 'Date',
    ];

    public const SAFE_DEFAULT = [
        'booking_id', 'event', 'quantity', 'payment_status', 'date',
    ];

    public function forActor(string $actorType, int $actorId): array
    {
        if ($actorType === 'organizer') {
            return array_keys(self::FIELDS);
        }
        if ($actorType !== 'admin') {
            throw new DomainException('The export requester is invalid.');
        }

        $admin = Admin::query()->with('role')->find($actorId);
        if (!$admin) {
            throw new DomainException('The administrator requesting this export no longer exists.');
        }
        if ($admin->role_id === null) {
            return array_keys(self::FIELDS);
        }

        $configured = is_array($admin->role?->export_fields) ? $admin->role->export_fields : [];
        $allowed = array_values(array_intersect(array_keys(self::FIELDS), $configured));

        return $allowed !== [] ? $allowed : self::SAFE_DEFAULT;
    }

    public function validate(array $fields): array
    {
        $fields = array_values(array_unique(array_filter($fields, 'is_string')));
        $unknown = array_diff($fields, array_keys(self::FIELDS));
        if ($unknown !== []) {
            throw new DomainException('One or more export fields are not supported.');
        }
        if ($fields === []) {
            throw new DomainException('Select at least one booking export field.');
        }

        return array_values(array_intersect(array_keys(self::FIELDS), $fields));
    }
}

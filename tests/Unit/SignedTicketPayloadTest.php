<?php

namespace Tests\Unit;

use Tests\TestCase;

class SignedTicketPayloadTest extends TestCase
{
    public function test_signed_ticket_payload_round_trips(): void
    {
        config(['tickets.signing_key' => 'test-key-for-ticket-signatures']);

        $payload = signedTicketPayload('TICA-123', 42);

        $this->assertSame(
            ['booking_id' => 'TICA-123', 'event_id' => 42],
            verifySignedTicketPayload($payload)
        );
    }

    public function test_tampered_ticket_payload_is_rejected(): void
    {
        config(['tickets.signing_key' => 'test-key-for-ticket-signatures']);

        $payload = signedTicketPayload('TICA-123', 42);
        $tampered = str_replace('.42.', '.43.', $payload);

        $this->assertNull(verifySignedTicketPayload($tampered));
        $this->assertNull(verifySignedTicketPayload('TICA-123'));
    }

    public function test_app_key_rotation_does_not_break_a_dedicated_ticket_key(): void
    {
        config([
            'tickets.signing_key' => 'stable-ticket-key',
            'app.key' => 'old-app-key',
        ]);

        $payload = signedTicketPayload('TICA-456', 77);
        config(['app.key' => 'rotated-app-key']);

        $this->assertSame(
            ['booking_id' => 'TICA-456', 'event_id' => 77],
            verifySignedTicketPayload($payload)
        );
    }

    public function test_individual_ticket_payload_round_trips_and_rejects_tampering(): void
    {
        config(['tickets.signing_key' => 'stable-ticket-key']);

        $payload = signedTicketUnitPayload('5d2a17d8-95d8-4b09-81ea-a22c8e696dde', 91);
        $this->assertSame([
            'ticket_unit_id' => '5d2a17d8-95d8-4b09-81ea-a22c8e696dde',
            'event_id' => 91,
        ], verifySignedTicketPayload($payload));

        $this->assertNull(verifySignedTicketPayload(str_replace('.91.', '.92.', $payload)));
    }
}

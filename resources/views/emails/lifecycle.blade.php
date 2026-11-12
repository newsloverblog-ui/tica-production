<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ $message['subject'] }}</title>
  <style>
    body{margin:0;background:#f3f6fa;color:#111b2e;font-family:Arial,Helvetica,sans-serif}.preheader{display:none!important;max-height:0;overflow:hidden;opacity:0}.wrap{width:100%;background:#f3f6fa;padding:32px 12px}.email{width:100%;max-width:640px;margin:0 auto;background:#fff;border-collapse:separate;border-spacing:0;border-radius:20px;overflow:hidden;box-shadow:0 12px 36px rgba(6,28,61,.10)}.head{padding:24px 34px;background:#071a35;color:#fff}.logo{font-size:28px;font-weight:800;letter-spacing:-2px}.logo b{color:#fe9b59}.content{padding:36px 34px}.title{margin:0 0 14px;font-size:34px;line-height:1.08;letter-spacing:-1.2px;color:#071a35}.copy{margin:0 0 14px;color:#4e5c70;font-size:16px;line-height:1.62}.event{margin:26px 0;border:1px solid #e2e8f0;border-radius:15px;overflow:hidden}.event-image{display:block;width:100%;height:auto}.event-body{padding:18px 20px}.event-title{margin:0 0 8px;font-size:19px;color:#071a35}.event-meta{margin:4px 0;color:#5d697b;font-size:14px}.date-change{margin:24px 0;padding:20px;border-radius:14px;background:#f7f9fc}.old-date{color:#7b8798;text-decoration:line-through}.new-date{margin-top:5px;color:#071a35;font-size:20px;font-weight:800}.details{width:100%;margin:24px 0;border-collapse:collapse}.details td{padding:11px 0;border-bottom:1px solid #edf1f5;font-size:14px}.details td:first-child{color:#687588}.details td:last-child{text-align:right;color:#071a35;font-weight:700}.steps{margin:24px 0;padding:0;list-style:none}.steps li{margin:0 0 10px;padding:13px 15px;border-left:4px solid #fe9b59;background:#fff7f0;color:#26354b;font-weight:700}.tickets{margin:24px 0}.ticket{margin:0 0 12px;padding:16px;border:1px solid #dfe6ef;border-radius:14px}.ticket-table{width:100%;border-collapse:collapse}.ticket-title{font-weight:800;color:#071a35}.ticket-meta{margin-top:5px;color:#687588;font-size:13px}.qr{width:92px;height:92px;margin-left:14px}.code{margin:24px 0;padding:20px;text-align:center;border:1px dashed #fe9b59;border-radius:14px;background:#fff8f3;color:#071a35;font-size:34px;font-weight:800;letter-spacing:8px}.button{display:inline-block;margin:10px 8px 6px 0;padding:14px 21px;border-radius:10px;background:#fe9b59;color:#071a35!important;font-size:15px;font-weight:800;text-decoration:none}.button.secondary{border:1px solid #ced7e3;background:#fff;color:#071a35!important}.notice{margin:24px 0 0;padding:16px 18px;border-radius:12px;background:#edf5ff;color:#24415f;font-size:14px;line-height:1.55}.critical .notice{background:#fff0ed;color:#7c281c}.success .notice{background:#edf9f3;color:#176044}.foot{padding:22px 34px;background:#071a35;color:#aebbd0;font-size:12px;line-height:1.6}.foot a{color:#fff}@media(max-width:680px){.wrap{padding:0}.email{border-radius:0}.head,.content,.foot{padding-left:22px;padding-right:22px}.title{font-size:29px}.details td{display:block;text-align:left!important}.details td:first-child{padding-bottom:2px;border:0}.details td:last-child{padding-top:2px}.qr{width:78px;height:78px}.button{display:block;margin-right:0;text-align:center}}
  </style>
</head>
<body>
  <span class="preheader">{{ $message['preheader'] }}</span>
  <div class="wrap">
    <table role="presentation" class="email {{ $message['tone'] }}"><tr><td class="head"><div class="logo">tica<b>.</b></div></td></tr><tr><td class="content">
      <h1 class="title">{{ $message['title'] }}</h1>
      <p class="copy">{{ $message['intro'] }}</p>
      @foreach($message['body'] as $paragraph)<p class="copy">{{ $paragraph }}</p>@endforeach

      @if(!empty($message['event']))
        <div class="event">
          @if(!empty($message['event']['image']))<img class="event-image" src="{{ $message['event']['image'] }}" alt="">@endif
          <div class="event-body"><h2 class="event-title">{{ $message['event']['title'] }}</h2>
            @if(!empty($message['event']['date']) || !empty($message['event']['time']))<p class="event-meta">{{ $message['event']['date'] }} @if(!empty($message['event']['time'])) · {{ $message['event']['time'] }} @endif</p>@endif
            @if(!empty($message['event']['venue']))<p class="event-meta">{{ $message['event']['venue'] }}</p>@endif
          </div>
        </div>
      @endif

      @if($message['old_date'] || $message['new_date'])<div class="date-change"><div class="old-date">{{ $message['old_date'] }}</div><div class="new-date">{{ $message['new_date'] }}</div></div>@endif
      @if($message['code'])<div class="code">{{ $message['code'] }}</div>@endif
      @if(!empty($message['steps']))<ol class="steps">@foreach($message['steps'] as $step)<li>{{ $loop->iteration }}. {{ $step }}</li>@endforeach</ol>@endif
      @if(!empty($message['tickets']))
        <div class="tickets">
          @foreach($message['tickets'] as $ticket)
            <div class="ticket"><table role="presentation" class="ticket-table"><tr><td>
              <div class="ticket-title">{{ $ticket['label'] ?? ('Ticket '.$loop->iteration) }}</div>
              @if(!empty($ticket['holder']))<div class="ticket-meta">{{ $ticket['holder'] }}</div>@endif
              @if(!empty($ticket['reference']))<div class="ticket-meta">{{ $ticket['reference'] }}</div>@endif
            </td>
            @if(!empty($ticket['qr']))<td style="text-align:right"><img class="qr" src="{{ $ticket['qr'] }}" alt="Ticket QR code"></td>@endif
            </tr></table></div>
          @endforeach
        </div>
      @endif
      @if(!empty($message['details']))<table role="presentation" class="details">@foreach($message['details'] as $label => $value)<tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>@endforeach</table>@endif
      @if($message['primary_action'])<a class="button" href="{{ $message['primary_action']['url'] }}">{{ $message['primary_action']['label'] }}</a>@endif
      @if($message['secondary_action'])<a class="button secondary" href="{{ $message['secondary_action']['url'] }}">{{ $message['secondary_action']['label'] }}</a>@endif
      @if($message['notice'])<div class="notice">{{ $message['notice'] }}</div>@endif
    </td></tr><tr><td class="foot">Sent by Tica · Tickets and tools for real-world experiences.<br>If you did not expect this email, contact Tica support and do not share any codes or ticket QR images.</td></tr></table>
  </div>
</body>
</html>

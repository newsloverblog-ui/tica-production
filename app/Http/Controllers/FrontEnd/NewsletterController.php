<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function unsubscribe(Request $request, Subscriber $subscriber)
    {
        if ($request->isMethod('post')) {
            $subscriber->forceFill(['unsubscribed_at' => now()])->save();

            return view('frontend.newsletter.unsubscribe', ['subscriber' => $subscriber, 'complete' => true]);
        }

        return view('frontend.newsletter.unsubscribe', ['subscriber' => $subscriber, 'complete' => false]);
    }
}

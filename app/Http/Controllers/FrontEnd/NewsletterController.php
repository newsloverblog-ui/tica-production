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
            if (Subscriber::hasConsentColumn('unsubscribed_at')) {
                $subscriber->forceFill(['unsubscribed_at' => now()])->save();
            } else {
                // Older deployments can still honour an unsubscribe before
                // the consent-state migration has been applied.
                $subscriber->delete();
            }

            return view('frontend.newsletter.unsubscribe', ['subscriber' => $subscriber, 'complete' => true]);
        }

        return view('frontend.newsletter.unsubscribe', ['subscriber' => $subscriber, 'complete' => false]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Mail\ContactInquiryMail;
use App\Models\User;
use App\Support\BacProfile;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** The public Contact page: the office details and an inquiry form that reaches the BAC Admin. */
class ContactController extends Controller
{
    public const TOPICS = [
        'bidding' => 'A bidding or project',
        'registration' => 'My registration or documents',
        'award' => 'An award or contract',
        'other' => 'Something else',
    ];

    public function show()
    {
        return view('pages.contact', ['office' => BacProfile::get()['office'], 'topics' => self::TOPICS]);
    }

    public function send(Request $request)
    {
        // A hidden field only bots fill in: pretend it worked.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('contact_sent', true);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'topic' => ['required', 'in:'.implode(',', array_keys(self::TOPICS))],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'message.min' => 'Please write a little more so the BAC can help you.',
            'topic.required' => 'Choose what your question is about.',
        ]);

        $name = trim($data['full_name']);
        $email = trim($data['email']);
        $subject = trim(preg_replace('/\s+/', ' ', $data['subject']));
        $topic = self::TOPICS[$data['topic']];
        $message = trim($data['message']);

        // The bell of every BAC Admin: who, about what and the start of the message.
        try {
            SystemNotification::createForRole(
                'admin',
                'Contact inquiry: '.Str::limit($subject, 60),
                $name.' ('.$email.') · '.$topic.': '.Str::limit($message, 160),
                'system_alert',
                ['important' => true, 'url' => route('admin.notifications')]
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        // And the full message in their inbox, so replying goes straight to the sender.
        $admins = User::query()->where('role', 'admin')->where('status', 'active')->whereNotNull('email')->limit(5)->pluck('email');
        foreach ($admins as $to) {
            try {
                Mail::to($to)->send(new ContactInquiryMail($name, $email, $topic, $subject, $message));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('contact')->with('contact_sent', true);
    }
}

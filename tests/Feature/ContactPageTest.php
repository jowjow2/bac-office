<?php

use App\Mail\ContactInquiryMail;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    Mail::fake();
    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'contact-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->send = fn (array $data = []) => testCase()->post(route('contact.send'), array_merge([
        'full_name' => 'Maria Cruz', 'email' => 'maria@example.com', 'topic' => 'bidding',
        'subject' => 'Requirements for the road project', 'message' => 'What documents do I need to bring to the BAC office?',
    ], $data));
});

it('shows the office details from the About settings, and no made-up number', function () {
    testCase()->get(route('contact'))->assertOk()
        ->assertSee('Send a message')->assertSee('name="topic"', false)
        ->assertSee('Not listed yet')
        ->assertDontSee('(043) 000-0000');

    SiteSetting::write('about', ['office' => ['phone' => '(043) 457-1234', 'address' => 'Municipal Hall, San Jose', 'hours' => 'Mon to Fri, 8 AM to 5 PM', 'email' => 'bac@example.com']]);
    testCase()->get(route('contact'))->assertOk()
        ->assertSee('(043) 457-1234')->assertSee('bac@example.com')->assertSee('Mon to Fri, 8 AM to 5 PM')
        ->assertDontSee('Not listed yet');
});

it('delivers an inquiry to the BAC Admin by email and in the bell, replying to the sender', function () {
    ($this->send)()->assertRedirect(route('contact'))->assertSessionHas('contact_sent');

    Mail::assertSent(ContactInquiryMail::class, function (ContactInquiryMail $mail) {
        return $mail->hasTo('contact-admin@example.com')
            && $mail->hasReplyTo('maria@example.com')
            && str_contains($mail->render(), 'What documents do I need to bring');
    });
    expect(UserNotification::where('user_id', $this->admin->id)->where('title', 'like', 'Contact inquiry:%')->count())->toBe(1);

    testCase()->get(route('contact'))->assertSee('Message sent');
});

it('asks for the details, ignores the bot trap and limits how often it can be sent', function () {
    ($this->send)(['full_name' => '', 'email' => 'nope', 'topic' => '', 'subject' => '', 'message' => 'short'])
        ->assertSessionHasErrors(['full_name', 'email', 'topic', 'subject', 'message']);
    ($this->send)(['website' => 'http://spam.example'])->assertRedirect(route('contact'));
    Mail::assertNothingSent();

    foreach (range(1, 3) as $i) {
        ($this->send)()->assertRedirect(route('contact'));
    }
    ($this->send)()->assertStatus(429);
});

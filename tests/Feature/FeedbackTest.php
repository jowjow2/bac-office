<?php

use App\Models\Feedback;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'fb-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'BAC Staff', 'email' => 'fb-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);
    $this->send = fn (array $data = []) => testCase()->post(route('feedback.store'), array_merge([
        'category' => 'suggestion', 'rating' => 4, 'message' => 'Please add a search box to the awards page.',
    ], $data));
});

it('shows the feedback form to anyone, with a link to it on the public pages', function () {
    testCase()->get(route('feedback'))->assertOk()->assertSee('How is the portal working for you?')->assertSee('name="rating"', false)->assertSee('name="website"', false);
    testCase()->get('/procurement')->assertOk()->assertSee('class="feedback-tab"', false);
});

it('stores feedback from a visitor without keeping an IP address, and tells the admin', function () {
    ($this->send)(['name' => 'Maria Cruz', 'email' => 'maria@example.com', 'page_url' => 'https://evil.example/steal?x=1'])
        ->assertRedirect(route('feedback'))->assertSessionHas('feedback_sent');

    $feedback = Feedback::firstOrFail();
    expect($feedback->category)->toBe('suggestion')->and($feedback->rating)->toBe(4)
        ->and($feedback->name)->toBe('Maria Cruz')->and($feedback->user_id)->toBeNull()
        ->and($feedback->page_url)->toBe('/steal') // only a path on this site is kept
        ->and(array_keys($feedback->getAttributes()))->not->toContain('ip_address');
    expect(UserNotification::where('user_id', $this->admin->id)->where('title', 'New feedback')->count())->toBe(1);
});

it('asks for a rating, a kind and a message, and ignores the bot trap', function () {
    ($this->send)(['rating' => null, 'category' => null, 'message' => ''])->assertSessionHasErrors(['rating', 'category', 'message']);
    ($this->send)(['rating' => 9])->assertSessionHasErrors('rating');
    ($this->send)(['email' => 'not-an-email'])->assertSessionHasErrors('email');
    expect(Feedback::count())->toBe(0);

    ($this->send)(['website' => 'http://spam.example'])->assertRedirect(route('feedback'));
    expect(Feedback::count())->toBe(0);
});

it('limits how often one visitor can send feedback', function () {
    foreach (range(1, 5) as $i) {
        ($this->send)()->assertRedirect(route('feedback'));
    }
    ($this->send)()->assertStatus(429);
});

it('lets only the BAC Admin read, filter, mark and export feedback', function () {
    ($this->send)(['category' => 'problem', 'rating' => 2, 'message' => 'The upload button does not work on my phone.']);
    ($this->send)(['category' => 'praise', 'rating' => 5, 'message' => 'Very easy to use, thank you!']);
    $problem = Feedback::where('category', 'problem')->firstOrFail();

    testCase()->get(route('admin.feedback'))->assertRedirect();
    testCase()->actingAs($this->staff)->get(route('admin.feedback'))->assertForbidden();

    testCase()->actingAs($this->admin)->get(route('admin.feedback'))->assertOk()
        ->assertSee('The upload button does not work on my phone.')->assertSee('Very easy to use');
    testCase()->actingAs($this->admin)->get(route('admin.feedback', ['category' => 'problem']))->assertOk()
        ->assertSee('class="afb-kind afb-kind--problem"', false)->assertDontSee('class="afb-kind afb-kind--praise"', false);

    testCase()->actingAs($this->admin)->post(route('admin.feedback.review', $problem))->assertRedirect();
    expect($problem->fresh()->reviewed_at)->not->toBeNull();
    testCase()->actingAs($this->admin)->get(route('admin.feedback', ['status' => 'new']))->assertOk()->assertDontSee('class="afb-kind afb-kind--problem"', false)->assertSee('class="afb-kind afb-kind--praise"', false);
    testCase()->actingAs($this->admin)->post(route('admin.feedback.review', $problem))->assertRedirect();
    expect($problem->fresh()->reviewed_at)->toBeNull();

    $csv = testCase()->actingAs($this->admin)->get(route('admin.feedback.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('Date,Kind,Rating,Message')->toContain('The upload button does not work on my phone.');
});

<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Support\SystemNotification;
use App\Support\UserPresence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessageController extends Controller
{
    private const TYPING_TTL_SECONDS = 6;

    private const ALLOWED_ATTACHMENT_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
    ];

    private const IMAGE_ATTACHMENT_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    private const BLOCKED_ATTACHMENT_EXTENSIONS = [
        'bat',
        'cmd',
        'com',
        'exe',
        'js',
        'msi',
        'php',
        'phtml',
        'ps1',
        'sh',
        'vbs',
    ];

    public function adminIndex(Request $request): View
    {
        /** @var User $admin */
        $admin = Auth::user();

        abort_unless($admin->role === 'admin', 403);

        $bidders = User::query()
            ->where('role', 'bidder')
            ->orderByRaw("LOWER(COALESCE(NULLIF(company, ''), name))")
            ->get();

        $staffContacts = User::query()
            ->where('role', 'staff')
            ->orderBy('name')
            ->get();

        $this->attachOnlineStatuses($bidders);
        $this->attachOnlineStatuses($staffContacts);

        $requestedUserId = (int) $request->query('user', 0);
        $requestedContact = $requestedUserId > 0
            ? $bidders->merge($staffContacts)->firstWhere('id', $requestedUserId)
            : null;

        $activeTab = $requestedContact instanceof User && $requestedContact->role === 'staff'
            ? 'staff'
            : ($request->query('tab') === 'staff' ? 'staff' : 'bidders');

        $activeCounterparts = $activeTab === 'staff' ? $staffContacts : $bidders;
        $selectedBidder = $this->resolveSelectedConversation($request, $activeCounterparts);
        $bidderThreadSummaries = $this->buildThreadSummaries($admin, $bidders);
        $staffThreadSummaries = $this->buildThreadSummaries($admin, $staffContacts);
        $conversationMessages = collect();

        if ($selectedBidder instanceof User) {
            $this->markIncomingMessagesAsRead($selectedBidder->id, $admin->id);

            $conversationMessages = Message::query()
                ->betweenUsers($admin->id, $selectedBidder->id)
                ->with(['sender:id,name,company,role', 'recipient:id,name,company,role'])
                ->oldest('created_at')
                ->get();
        }

        $unreadNotificationsCount = \App\Support\SystemNotification::unreadCount($admin->id);

        return view('admin.messages', [
            'activeTab' => $activeTab,
            'threadSummaries' => $activeTab === 'staff' ? $staffThreadSummaries : $bidderThreadSummaries,
            'bidderThreadSummaries' => $bidderThreadSummaries,
            'staffThreadSummaries' => $staffThreadSummaries,
            'selectedBidder' => $selectedBidder,
            'conversationMessages' => $conversationMessages,
            'adminNotificationCount' => $unreadNotificationsCount,
            'unreadNotificationsCount' => $unreadNotificationsCount,
            'adminUnreadMessagesCount' => Message::query()
                ->where('recipient_id', $admin->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function adminStore(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        abort_unless($admin->role === 'admin', 403);

        $validated = $request->validate([
            'recipient_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['bidder', 'staff'])),
            ],
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => $this->attachmentValidationRules(),
        ], [
            'recipient_id.required' => 'Choose a staff member or bidder to message.',
            'recipient_id.exists' => 'Choose a valid staff or bidder account.',
            'body.required_without' => 'Type a message or attach a file before sending.',
            'attachment.max' => 'Attachments must be 10MB or smaller.',
            'attachment.mimes' => 'Only JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, and XLSX files are allowed.',
        ]);

        $recipient = User::query()
            ->whereKey((int) $validated['recipient_id'])
            ->whereIn('role', ['bidder', 'staff'])
            ->firstOrFail();

        $message = Message::create([
            'sender_id' => $admin->id,
            'recipient_id' => $recipient->id,
            'body' => trim((string) ($validated['body'] ?? '')),
            ...$this->storeMessageAttachment($request->file('attachment'), $admin->id),
        ]);

        if ($recipient->role === 'staff') {
            SystemNotification::createForUser(
                $recipient->id,
                'New admin message',
                ($admin->name ?: 'Admin') . ' sent you a new message.',
                'message',
                ['sender_id' => $admin->id]
            );
        } else {
            SystemNotification::createForUser(
                $recipient->id,
                'New message from BAC Office',
                'You received a new message from the BAC Office team.',
                'message',
                ['sender_id' => $admin->id]
            );
        }

        return $this->messageStoreResponse($request, $message, 'admin.messages', $recipient->id, [
            'tab' => $recipient->role === 'staff' ? 'staff' : 'bidders',
        ]);
    }

    public function bidderIndex(Request $request): View
    {
        /** @var User $bidder */
        $bidder = Auth::user();

        abort_unless($bidder->role === 'bidder', 403);

        $admins = User::query()
            ->where('role', 'admin')
            ->orderBy('name')
            ->get();

        $staffContacts = User::query()
            ->where('role', 'staff')
            ->orderBy('name')
            ->get();

        $this->attachOnlineStatuses($admins);
        $this->attachOnlineStatuses($staffContacts);

        $requestedUserId = (int) $request->query('user', 0);
        $requestedContact = $requestedUserId > 0
            ? $admins->merge($staffContacts)->firstWhere('id', $requestedUserId)
            : null;

        $activeTab = $requestedContact instanceof User && $requestedContact->role === 'staff'
            ? 'staff'
            : ($request->query('tab') === 'staff' ? 'staff' : 'admin');

        $activeCounterparts = $activeTab === 'staff' ? $staffContacts : $admins;
        $selectedAdmin = $this->resolveSelectedConversation($request, $activeCounterparts);
        $adminThreadSummaries = $this->buildThreadSummaries($bidder, $admins);
        $staffThreadSummaries = $this->buildThreadSummaries($bidder, $staffContacts);
        $conversationMessages = collect();

        if ($selectedAdmin instanceof User) {
            $this->markIncomingMessagesAsRead($selectedAdmin->id, $bidder->id);

            $conversationMessages = Message::query()
                ->betweenUsers($bidder->id, $selectedAdmin->id)
                ->with(['sender:id,name,company,role', 'recipient:id,name,company,role'])
                ->oldest('created_at')
                ->get();
        }

        return view('bidder.messages', [
            'activeTab' => $activeTab,
            'threadSummaries' => $activeTab === 'staff' ? $staffThreadSummaries : $adminThreadSummaries,
            'adminThreadSummaries' => $adminThreadSummaries,
            'staffThreadSummaries' => $staffThreadSummaries,
            'selectedAdmin' => $selectedAdmin,
            'conversationMessages' => $conversationMessages,
            'bidderNotificationCount' => SystemNotification::unreadCount($bidder->id),
            'bidderUnreadMessagesCount' => Message::query()
                ->where('recipient_id', $bidder->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function bidderStore(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $bidder */
        $bidder = Auth::user();

        abort_unless($bidder->role === 'bidder', 403);

        $validated = $request->validate([
            'recipient_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'staff'])),
            ],
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => $this->attachmentValidationRules(),
        ], [
            'recipient_id.required' => 'Choose an admin or staff contact to message.',
            'recipient_id.exists' => 'Choose a valid admin or staff account.',
            'body.required_without' => 'Type a message or attach a file before sending.',
            'attachment.max' => 'Attachments must be 10MB or smaller.',
            'attachment.mimes' => 'Only JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, and XLSX files are allowed.',
        ]);

        $recipient = User::query()
            ->whereKey((int) $validated['recipient_id'])
            ->whereIn('role', ['admin', 'staff'])
            ->firstOrFail();

        $message = Message::create([
            'sender_id' => $bidder->id,
            'recipient_id' => $recipient->id,
            'body' => trim((string) ($validated['body'] ?? '')),
            ...$this->storeMessageAttachment($request->file('attachment'), $bidder->id),
        ]);

        SystemNotification::createForUser(
            $recipient->id,
            'New bidder message',
            ($bidder->company ?: $bidder->name) . ' sent you a new message.',
            'message',
            ['sender_id' => $bidder->id]
        );

        return $this->messageStoreResponse($request, $message, 'bidder.messages', $recipient->id, [
            'tab' => $recipient->role === 'staff' ? 'staff' : 'admin',
        ]);
    }

    public function staffIndex(Request $request): View
    {
        /** @var User $staff */
        $staff = Auth::user();

        abort_unless($staff->role === 'staff', 403);

        $admins = User::query()
            ->where('role', 'admin')
            ->orderBy('name')
            ->get();

        $bidders = User::query()
            ->where('role', 'bidder')
            ->orderByRaw("LOWER(COALESCE(NULLIF(company, ''), name))")
            ->get();

        $this->attachOnlineStatuses($admins);
        $this->attachOnlineStatuses($bidders);

        $requestedUserId = (int) $request->query('user', 0);
        $requestedContact = $requestedUserId > 0
            ? $admins->merge($bidders)->firstWhere('id', $requestedUserId)
            : null;

        $activeTab = $requestedContact instanceof User && $requestedContact->role === 'bidder'
            ? 'bidders'
            : ($request->query('tab') === 'bidders' ? 'bidders' : 'admin');

        $activeCounterparts = $activeTab === 'bidders' ? $bidders : $admins;
        $selectedContact = $this->resolveSelectedConversation($request, $activeCounterparts);
        $conversationMessages = collect();

        if ($selectedContact instanceof User) {
            $this->markIncomingMessagesAsRead($selectedContact->id, $staff->id);

            $conversationMessages = Message::query()
                ->betweenUsers($staff->id, $selectedContact->id)
                ->with(['sender:id,name,company,role', 'recipient:id,name,company,role'])
                ->oldest('created_at')
                ->get();
        }

        return view('staff.messages', [
            'activeTab' => $activeTab,
            'adminThreadSummaries' => $this->buildThreadSummaries($staff, $admins),
            'bidderThreadSummaries' => $this->buildThreadSummaries($staff, $bidders),
            'selectedContact' => $selectedContact,
            'conversationMessages' => $conversationMessages,
            'staffNotificationCount' => SystemNotification::unreadCount($staff->id),
            'staffUnreadMessagesCount' => Message::query()
                ->where('recipient_id', $staff->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function staffStore(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $staff */
        $staff = Auth::user();

        abort_unless($staff->role === 'staff', 403);

        $validated = $request->validate([
            'recipient_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'bidder'])),
            ],
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => $this->attachmentValidationRules(),
        ], [
            'recipient_id.required' => 'Choose an admin or bidder to message.',
            'recipient_id.exists' => 'Choose a valid admin or bidder account.',
            'body.required_without' => 'Type a message or attach a file before sending.',
            'attachment.max' => 'Attachments must be 10MB or smaller.',
            'attachment.mimes' => 'Only JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, and XLSX files are allowed.',
        ]);

        $recipient = User::query()
            ->whereKey((int) $validated['recipient_id'])
            ->whereIn('role', ['admin', 'bidder'])
            ->firstOrFail();

        $message = Message::create([
            'sender_id' => $staff->id,
            'recipient_id' => $recipient->id,
            'body' => trim((string) ($validated['body'] ?? '')),
            ...$this->storeMessageAttachment($request->file('attachment'), $staff->id),
        ]);

        SystemNotification::createForUser(
            $recipient->id,
            'New staff message',
            ($staff->name ?: 'Staff') . ' sent you a new message.',
            'message',
            ['sender_id' => $staff->id]
        );

        return $this->messageStoreResponse($request, $message, 'staff.messages', $recipient->id, [
            'tab' => $recipient->role === 'bidder' ? 'bidders' : 'admin',
        ]);
    }

    public function attachment(Request $request, Message $message)
    {
        $userId = (int) Auth::id();

        abort_unless(
            $userId === (int) $message->sender_id || $userId === (int) $message->recipient_id,
            403
        );
        abort_unless($message->hasAttachment(), 404);

        $disk = Storage::disk($message->attachment_disk ?: config('filesystems.uploads_disk', config('filesystems.default', 'local')));
        abort_unless($disk->exists($message->attachment_path), 404);

        $contents = $disk->get($message->attachment_path);
        $downloadName = str_replace(['"', "\r", "\n"], '', $message->attachment_original_name ?: basename($message->attachment_path));
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($contents, 200, [
            'Content-Type' => $message->attachment_mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) ($message->attachment_size ?: strlen($contents)),
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function adminStatusSync(): JsonResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        abort_unless($admin->role === 'admin', 403);

        return response()->json([
            'statuses' => $this->onlineStatusesForRoles(['bidder', 'staff']),
        ]);
    }

    public function adminConversationSync(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        abort_unless($admin->role === 'admin', 403);

        $bidder = $this->resolveCounterpartFromRequest($request, ['bidder', 'staff']);

        return $this->conversationSyncResponse($request, $admin, $bidder);
    }

    public function adminTyping(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        abort_unless($admin->role === 'admin', 403);

        $bidder = $this->resolveCounterpartFromRequest($request, ['bidder', 'staff'], 'recipient_id');

        return $this->typingResponse($request, $admin, $bidder);
    }

    public function bidderStatusSync(): JsonResponse
    {
        /** @var User $bidder */
        $bidder = Auth::user();

        abort_unless($bidder->role === 'bidder', 403);

        return response()->json([
            'statuses' => $this->onlineStatusesForRoles(['admin', 'staff']),
        ]);
    }

    public function bidderConversationSync(Request $request): JsonResponse
    {
        /** @var User $bidder */
        $bidder = Auth::user();

        abort_unless($bidder->role === 'bidder', 403);

        $admin = $this->resolveCounterpartFromRequest($request, ['admin', 'staff']);

        return $this->conversationSyncResponse($request, $bidder, $admin);
    }

    public function bidderTyping(Request $request): JsonResponse
    {
        /** @var User $bidder */
        $bidder = Auth::user();

        abort_unless($bidder->role === 'bidder', 403);

        $admin = $this->resolveCounterpartFromRequest($request, ['admin', 'staff'], 'recipient_id');

        return $this->typingResponse($request, $bidder, $admin);
    }

    public function staffStatusSync(): JsonResponse
    {
        /** @var User $staff */
        $staff = Auth::user();

        abort_unless($staff->role === 'staff', 403);

        return response()->json([
            'statuses' => $this->onlineStatusesForRoles(['admin', 'bidder']),
        ]);
    }

    public function staffConversationSync(Request $request): JsonResponse
    {
        /** @var User $staff */
        $staff = Auth::user();

        abort_unless($staff->role === 'staff', 403);

        $counterpart = $this->resolveCounterpartFromRequest($request, ['admin', 'bidder']);

        return $this->conversationSyncResponse($request, $staff, $counterpart);
    }

    public function staffTyping(Request $request): JsonResponse
    {
        /** @var User $staff */
        $staff = Auth::user();

        abort_unless($staff->role === 'staff', 403);

        $counterpart = $this->resolveCounterpartFromRequest($request, ['admin', 'bidder'], 'recipient_id');

        return $this->typingResponse($request, $staff, $counterpart);
    }

    protected function resolveSelectedConversation(Request $request, Collection $counterparts): ?User
    {
        $selectedId = (int) $request->query('user', 0);

        if ($selectedId > 0) {
            $selected = $counterparts->firstWhere('id', $selectedId);

            if ($selected instanceof User) {
                return $selected;
            }
        }

        $threadCandidate = $counterparts->first(function (User $counterpart) use ($request) {
            return (int) $request->query('user', 0) === (int) $counterpart->id;
        });

        return $threadCandidate instanceof User ? $threadCandidate : $counterparts->first();
    }

    protected function buildThreadSummaries(User $currentUser, Collection $counterparts): Collection
    {
        if ($counterparts->isEmpty()) {
            return collect();
        }

        $counterpartIds = $counterparts->pluck('id')->all();

        $threadMessages = Message::query()
            ->with(['sender:id,name,company,role', 'recipient:id,name,company,role'])
            ->where(function ($query) use ($currentUser, $counterpartIds) {
                $query->where(function ($nested) use ($currentUser, $counterpartIds) {
                    $nested->where('sender_id', $currentUser->id)
                        ->whereIn('recipient_id', $counterpartIds);
                })->orWhere(function ($nested) use ($currentUser, $counterpartIds) {
                    $nested->where('recipient_id', $currentUser->id)
                        ->whereIn('sender_id', $counterpartIds);
                });
            })
            ->latest('created_at')
            ->get()
            ->groupBy(function (Message $message) use ($currentUser) {
                return $message->sender_id === $currentUser->id
                    ? $message->recipient_id
                    : $message->sender_id;
            });

        return $counterparts
            ->map(function (User $counterpart) use ($threadMessages, $currentUser) {
                $messages = $threadMessages->get($counterpart->id, collect());
                $latestMessage = $messages->first();

                return [
                    'user' => $counterpart,
                    'latest_message' => $latestMessage,
                    'unread_count' => $messages
                        ->where('recipient_id', $currentUser->id)
                        ->whereNull('read_at')
                        ->count(),
                    'sort_timestamp' => $latestMessage?->created_at?->timestamp ?? 0,
                ];
            })
            ->sortByDesc('sort_timestamp')
            ->values();
    }

    protected function attachmentValidationRules(): array
    {
        return [
            'nullable',
            'file',
            'max:10240',
            'mimes:' . implode(',', self::ALLOWED_ATTACHMENT_EXTENSIONS),
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $extension = strtolower($value->getClientOriginalExtension());

                if (
                    in_array($extension, self::BLOCKED_ATTACHMENT_EXTENSIONS, true)
                    || ! in_array($extension, self::ALLOWED_ATTACHMENT_EXTENSIONS, true)
                ) {
                    $fail('This attachment type is not allowed.');
                }
            },
        ];
    }

    protected function storeMessageAttachment(?UploadedFile $file, int $senderId): array
    {
        if (! $file) {
            return [];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $baseName = $baseName !== '' ? Str::limit($baseName, 60, '') : 'attachment';
        $filename = (string) Str::uuid() . '-' . $baseName . '.' . $extension;
        $diskName = (string) config('filesystems.uploads_disk', config('filesystems.default', 'local'));
        $path = $file->storeAs('message-attachments/' . $senderId, $filename, $diskName);

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Unable to store message attachment.');
        }

        return [
            'attachment_disk' => $diskName,
            'attachment_path' => $path,
            'attachment_original_name' => $file->getClientOriginalName(),
            'attachment_mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'attachment_size' => $file->getSize(),
            'attachment_kind' => in_array($extension, self::IMAGE_ATTACHMENT_EXTENSIONS, true) ? 'image' : 'file',
        ];
    }

    protected function messageStoreResponse(Request $request, Message $message, string $redirectRoute, int $recipientId, array $extraRouteParameters = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            $this->clearTypingState((int) $message->sender_id, (int) $message->recipient_id);

            return response()->json([
                'ok' => true,
                'message' => $this->messagePayload($message),
            ], 201);
        }

        return redirect()
            ->route($redirectRoute, ['user' => $recipientId] + $extraRouteParameters)
            ->with('success', 'Message sent successfully.');
    }

    protected function messagePayload(Message $message): array
    {
        $message->loadMissing(['sender:id,name,company,role', 'recipient:id,name,company,role']);

        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'recipient_id' => $message->recipient_id,
            'sender_name' => $message->sender?->company ?: $message->sender?->name,
            'sender_role' => $message->sender?->role,
            'recipient_name' => $message->recipient?->company ?: $message->recipient?->name,
            'body' => $message->body,
            'created_at' => $message->created_at?->format('M d, Y g:i A'),
            'created_time' => $message->created_at?->format('g:i A'),
            'created_at_iso' => $message->created_at?->toISOString(),
            'read_at' => $message->read_at?->toISOString(),
            'attachment' => $message->hasAttachment() ? [
                'kind' => $message->attachment_kind,
                'name' => $message->attachment_original_name,
                'size' => $message->attachment_size,
                'size_label' => $message->formattedAttachmentSize(),
                'mime_type' => $message->attachment_mime_type,
                'url' => route('messages.attachment', $message),
                'download_url' => route('messages.attachment', ['message' => $message, 'download' => 1]),
            ] : null,
        ];
    }

    protected function resolveCounterpartFromRequest(Request $request, string|array $roles, string $field = 'user'): User
    {
        $roleList = $this->normalizeRoles($roles);

        $validated = $request->validate([
            $field => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', $roleList)),
            ],
        ]);

        return User::query()
            ->whereKey((int) $validated[$field])
            ->whereIn('role', $roleList)
            ->firstOrFail();
    }

    protected function conversationSyncResponse(Request $request, User $currentUser, User $counterpart): JsonResponse
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $afterId = (int) ($validated['after_id'] ?? 0);

        $this->markIncomingMessagesAsRead($counterpart->id, $currentUser->id);

        $newMessages = Message::query()
            ->betweenUsers($currentUser->id, $counterpart->id)
            ->with(['sender:id,name,company,role', 'recipient:id,name,company,role'])
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->oldest('id')
            ->get();

        $latestMessageId = (int) (Message::query()
            ->betweenUsers($currentUser->id, $counterpart->id)
            ->max('id') ?? 0);

        $readStates = Message::query()
            ->where('sender_id', $currentUser->id)
            ->where('recipient_id', $counterpart->id)
            ->get(['id', 'read_at'])
            ->map(fn (Message $message) => [
                'id' => $message->id,
                'read_at' => $message->read_at?->toISOString(),
            ])
            ->values();

        $statuses = $this->onlineStatusesForIds(collect([$counterpart->id]));
        $counterpartName = $counterpart->company ?: $counterpart->name;

        return response()->json([
            'ok' => true,
            'counterpart' => [
                'id' => $counterpart->id,
                'name' => $counterpartName,
                'email' => $counterpart->email,
                'role' => $counterpart->role,
                'status' => $counterpart->status,
                'initials' => $this->initialsForName($counterpartName),
                'is_online' => $statuses[$counterpart->id] ?? false,
            ],
            'messages' => $newMessages
                ->map(fn (Message $message) => array_merge($this->messagePayload($message), [
                    'is_outgoing' => (int) $message->sender_id === (int) $currentUser->id,
                ]))
                ->values(),
            'latest_message_id' => $latestMessageId,
            'read_states' => $readStates,
            'typing' => $this->typingPayload($counterpart, $currentUser),
        ]);
    }

    protected function typingResponse(Request $request, User $sender, User $recipient): JsonResponse
    {
        $validated = $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        if ((bool) $validated['is_typing']) {
            Cache::put(
                $this->typingCacheKey($sender->id, $recipient->id),
                true,
                now()->addSeconds(self::TYPING_TTL_SECONDS)
            );
        } else {
            $this->clearTypingState($sender->id, $recipient->id);
        }

        return response()->json(['ok' => true]);
    }

    protected function typingPayload(User $sender, User $recipient): array
    {
        $isTyping = Cache::has($this->typingCacheKey($sender->id, $recipient->id));
        $name = $sender->role === 'admin'
            ? 'BAC'
            : ($sender->company ?: $sender->name);

        return [
            'is_typing' => $isTyping,
            'label' => $isTyping ? $name . ' is typing...' : '',
        ];
    }

    protected function clearTypingState(int $senderId, int $recipientId): void
    {
        Cache::forget($this->typingCacheKey($senderId, $recipientId));
    }

    protected function typingCacheKey(int $senderId, int $recipientId): string
    {
        return 'messages:typing:' . $senderId . ':' . $recipientId;
    }

    protected function initialsForName(string $name): string
    {
        $initials = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'U';
    }

    protected function normalizeRoles(string|array $roles): array
    {
        return collect((array) $roles)
            ->filter()
            ->values()
            ->all();
    }

    protected function markIncomingMessagesAsRead(int $senderId, int $recipientId): void
    {
        Message::query()
            ->where('sender_id', $senderId)
            ->where('recipient_id', $recipientId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    protected function attachOnlineStatuses(Collection $users): void
    {
        $statuses = $this->onlineStatusesForIds($users->pluck('id'));

        $users->each(function (User $user) use ($statuses) {
            $user->setAttribute('is_online', $statuses[(int) $user->id] ?? false);
        });
    }

    protected function onlineStatusesForRole(string $role): array
    {
        return $this->onlineStatusesForRoles([$role]);
    }

    protected function onlineStatusesForRoles(array $roles): array
    {
        return $this->onlineStatusesForIds(
            User::query()
                ->whereIn('role', $roles)
                ->pluck('id')
        );
    }

    protected function onlineStatusesForIds(Collection $userIds): array
    {
        $userIds = $userIds
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return UserPresence::statusesForIds($userIds);
    }
}

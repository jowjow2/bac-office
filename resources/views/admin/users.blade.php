<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page">
    @php
        $staffOffices = \App\Models\User::staffOfficeOptions();
    @endphp
    @vite(['resources/css/dashboard.css'])

     <style>
         .users-page {
             font-family: var(--ui-font);
         }

         .users-page {
             font-size: 14px;
         }

        .users-page .user-search-input::placeholder {
            color: var(--ui-subtle);
        }

        .users-page .btn-primary,
        #createUserModal .btn-primary,
        #editUserModal .btn-primary {
            background: var(--ui-info);
            color: #ffffff;
            border: 1px solid var(--ui-info);
            font-size: 12.5px;
            font-weight: 600;
            box-shadow: none;
        }

        .users-page .btn-primary:hover,
        #createUserModal .btn-primary:hover,
        #editUserModal .btn-primary:hover {
            background: #163b6d;
            border-color: #163b6d;
        }

        .users-page .btn-secondary,
        #createUserModal .btn-secondary,
        #editUserModal .btn-secondary {
            border: 1px solid var(--ui-line-strong);
            color: var(--ui-ink-2);
            background: #ffffff;
            font-size: 12.5px;
            font-weight: 500;
        }

        .users-page .btn-secondary:hover,
        #createUserModal .btn-secondary:hover,
        #editUserModal .btn-secondary:hover {
            background: var(--ui-surface-2);
        }

        .user-modal {
            display: none;
            position: fixed;
            inset: 0;
            padding: 20px;
            background: rgba(27, 36, 32, 0.42);
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .user-modal-card {
            width: min(640px, 100%);
            max-height: min(760px, calc(100dvh - 32px));
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 24px 52px rgba(27, 36, 32, 0.18);
        }

        .user-modal .um-head {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 20px 20px 16px 24px;
            border-bottom: 1px solid var(--ui-line);
        }

        .user-modal .um-head__icon {
            display: grid;
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: 12px;
            background: var(--ui-primary-soft);
            color: var(--ui-primary);
            font-size: 16px;
        }

        .user-modal .um-head__text { flex: 1; min-width: 0; }
        .user-modal .um-head h2 { margin: 0; color: var(--ui-ink); font-size: 18px; font-weight: 700; line-height: 1.3; }
        .user-modal .um-head p { margin: 3px 0 0; color: var(--ui-muted); font-size: 13px; line-height: 1.45; }

        .user-modal .um-close {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--ui-muted);
            font-size: 16px;
            cursor: pointer;
        }

        .user-modal .um-close:hover { background: var(--ui-surface-2); color: var(--ui-ink); }

        .user-modal .um-form { display: flex; flex: 1 1 auto; flex-direction: column; min-height: 0; margin: 0; }
        .user-modal .um-body { display: grid; flex: 1 1 auto; gap: 16px; min-height: 0; padding: 20px 24px 22px; overflow-y: auto; }
        .user-modal .um-body [hidden] { display: none !important; }
        .user-modal .um-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .user-modal .um-field { display: grid; gap: 6px; min-width: 0; }
        .user-modal .um-label { color: var(--ui-ink-2); font-size: 12.5px; font-weight: 600; }
        .user-modal .um-req { color: var(--ui-danger); }
        .user-modal .um-opt { color: var(--ui-subtle); font-weight: 500; }
        .user-modal .um-hint { color: var(--ui-subtle); font-size: 12px; }
        .user-modal .um-hint:empty { display: none; }
        .user-modal .um-section { margin: 4px 0 -4px; padding-top: 16px; border-top: 1px solid var(--ui-line-soft); color: var(--ui-ink); font-size: 13.5px; font-weight: 700; }
        .user-modal .um-group { display: grid; gap: 16px; }
        .user-modal .um-group[hidden] { display: none; }
        .user-modal .um-note { display: flex; gap: 8px; align-items: flex-start; margin: 0; padding: 10px 12px; border: 1px solid var(--ui-warning-line); border-radius: var(--ui-radius); background: var(--ui-warning-soft); color: var(--ui-warning); font-size: 12.5px; line-height: 1.45; }

        .user-modal .um-roles { margin: 0; padding: 0; border: 0; min-width: 0; }
        .user-modal .um-roles legend { margin-bottom: 8px; padding: 0; }
        .user-modal .um-roles__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .user-modal .um-role { position: relative; display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--ui-line-strong); border-radius: 12px; background: #ffffff; cursor: pointer; transition: border-color .15s ease, background .15s ease, box-shadow .15s ease; }
        .user-modal .um-role:hover { border-color: var(--ui-subtle); }
        .user-modal .um-role input { position: absolute; opacity: 0; pointer-events: none; }
        .user-modal .um-role__icon { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border-radius: 10px; background: var(--ui-surface-2); color: var(--ui-muted); font-size: 14px; }
        .user-modal .um-role__text { display: grid; gap: 1px; min-width: 0; }
        .user-modal .um-role__text strong { color: var(--ui-ink); font-size: 13.5px; }
        .user-modal .um-role__text small { color: var(--ui-subtle); font-size: 12px; line-height: 1.35; }
        .user-modal .um-role:has(input:checked) { border-color: var(--ui-primary); background: var(--ui-primary-soft); box-shadow: 0 0 0 1px var(--ui-primary); }
        .user-modal .um-role:has(input:checked) .um-role__icon { background: var(--ui-primary); color: #ffffff; }
        .user-modal .um-role:has(input:focus-visible) { outline: 2px solid var(--ui-primary); outline-offset: 2px; }

        .user-modal .um-password { position: relative; }
        .user-modal .um-password input { width: 100%; padding-right: 44px !important; }
        .user-modal .um-password__toggle { position: absolute; top: 50%; right: 6px; display: grid; width: 32px; height: 32px; place-items: center; border: 0; border-radius: 8px; background: transparent; color: var(--ui-muted); transform: translateY(-50%); cursor: pointer; }
        .user-modal .um-password__toggle:hover { background: var(--ui-surface-2); color: var(--ui-ink); }

        .user-modal .um-actions { display: flex; flex: 0 0 auto; justify-content: flex-end; gap: 10px; padding: 14px 24px; border-top: 1px solid var(--ui-line); background: var(--ui-surface-2); }
        .user-modal .um-actions button { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 9px 18px; border-radius: 10px; cursor: pointer; }

        :is(#createUserModal, #editUserModal) .um-body :is(.form-input, .form-select) { width: 100%; min-height: 42px; height: 42px; padding: 9px 12px; border-radius: 10px !important; font-size: 14px; box-sizing: border-box; }

        /* Beat the shared dark modal-button rules in dashboard.css. */
        :is(#createUserModal#createUserModal, #editUserModal#editUserModal) .um-actions .btn-primary { background: var(--ui-primary) !important; border: 1px solid var(--ui-primary) !important; color: #ffffff !important; -webkit-text-fill-color: #ffffff !important; font-weight: 600 !important; }
        :is(#createUserModal#createUserModal, #editUserModal#editUserModal) .um-actions .btn-primary:hover { background: var(--ui-primary-hover) !important; border-color: var(--ui-primary-hover) !important; }
        :is(#createUserModal#createUserModal, #editUserModal#editUserModal) .um-actions .btn-secondary { background: #ffffff !important; border: 1px solid var(--ui-line-strong) !important; color: var(--ui-ink) !important; -webkit-text-fill-color: var(--ui-ink) !important; font-weight: 600 !important; }
        :is(#createUserModal#createUserModal, #editUserModal#editUserModal) .um-actions .btn-secondary:hover { background: var(--ui-surface-2) !important; }
        :is(#createUserModal#createUserModal, #editUserModal#editUserModal) .um-actions { background: var(--ui-surface-2) !important; border-top: 1px solid var(--ui-line) !important; }
        :is(#createUserModal#createUserModal, #editUserModal#editUserModal) .um-role:has(input:checked) .um-role__icon :is(i, svg) { color: #ffffff !important; -webkit-text-fill-color: #ffffff !important; }
        :is(#createUserModal#createUserModal, #editUserModal#editUserModal) .um-role__icon :is(i, svg) { color: inherit !important; -webkit-text-fill-color: currentColor !important; }

        @media (max-width: 560px) {
            .user-modal { padding: 12px !important; align-items: flex-end !important; }
            .user-modal .um-grid, .user-modal .um-roles__grid { grid-template-columns: minmax(0, 1fr); }
            .user-modal .um-body { padding: 16px; }
            .user-modal .um-actions { padding: 12px 16px; }
            .user-modal .um-actions button { flex: 1; justify-content: center; }
        }

        .user-modal-alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: var(--ui-radius-lg);
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
        }

        .user-modal-alert ul {
            margin: 0;
            padding-left: 18px;
        }

        .users-page .user-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 76px;
            min-height: 36px;
            background: #ffffff;
            color: var(--ui-ink-2);
            border: 1px solid var(--ui-line-strong);
            padding: 8px 13px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .users-page .user-action-button:hover {
            background: var(--ui-surface-2);
            border-color: var(--ui-line-strong);
            color: var(--ui-ink);
        }

        .users-page .user-action-review {
            background: var(--ui-primary);
            border-color: var(--ui-primary);
            color: #ffffff;
            box-shadow: 0 10px 22px rgba(29, 79, 64, 0.18);
        }

        .users-page .user-action-review:hover {
            background: var(--ui-primary-hover);
            border-color: var(--ui-primary);
            color: #ffffff;
        }

        .users-page .user-actions-cell {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            align-items: center;
            min-width: 184px;
            max-width: 210px;
        }

        .users-page .user-actions-cell form {
            margin: 0;
        }

        .users-page .user-actions-cell .user-action-button,
        .users-page .user-actions-cell .user-action-remove {
            width: 100%;
            min-width: 0;
            white-space: normal;
            text-align: center;
            line-height: 1.2;
        }

        .users-page .user-action-remove {
            min-height: 38px;
            padding: 8px 10px !important;
        }


        .users-page .user-table-card {
            width: 100%;
            max-width: 100%;
            overflow: hidden !important;
        }

        .users-page .users-table-wrap {
            width: 100%;
            max-width: 100%;
            padding: 0 14px 16px !important;
            overflow-x: hidden !important;
            overflow-y: visible !important;
            box-sizing: border-box;
        }

        .users-page .users-table {
            width: 100% !important;
            min-width: 0 !important;
            table-layout: auto !important;
            border-collapse: collapse !important;
        }

        .users-page .users-table th,
        .users-page .users-table td {
            padding: 12px 10px !important;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }

        .users-page .users-table th:nth-child(1) { width: 12%; }
        .users-page .users-table th:nth-child(2) { width: 13%; }
        .users-page .users-table th:nth-child(3) { width: 7%; }
        .users-page .users-table th:nth-child(4) { width: 7%; }
        .users-page .users-table th:nth-child(5) { width: 9%; }
        .users-page .users-table th:nth-child(6) { width: 12%; }
        .users-page .users-table th:nth-child(7) { width: 9%; }
        .users-page .users-table th:nth-child(8) { width: 9%; }
        .users-page .users-table th:nth-child(9) { width: 18%; }



























        #createUserModal.user-modal,
        #editUserModal.user-modal {
            background: rgba(27, 36, 32, 0.42) !important;
            backdrop-filter: blur(6px) !important;
            -webkit-backdrop-filter: blur(6px) !important;
        }

        #createUserModal .user-modal-card,
        #editUserModal .user-modal-card {
            background: #ffffff !important;
            border: 1px solid var(--ui-line) !important;
            color: var(--ui-ink) !important;
            box-shadow: 0 24px 70px rgba(27, 36, 32, 0.28) !important;
        }

        #createUserModal input:not([type="checkbox"]):not([type="radio"]),
        #createUserModal select,
        #createUserModal textarea,
        #editUserModal input:not([type="checkbox"]):not([type="radio"]),
        #editUserModal select,
        #editUserModal textarea {
            background: #ffffff !important;
            border: 1px solid var(--ui-line-strong) !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            color-scheme: light !important;
        }

        #createUserModal input:not([type="checkbox"]):not([type="radio"]):focus,
        #createUserModal select:focus,
        #createUserModal textarea:focus,
        #editUserModal input:not([type="checkbox"]):not([type="radio"]):focus,
        #editUserModal select:focus,
        #editUserModal textarea:focus {
            border-color: var(--ui-primary) !important;
            box-shadow: 0 0 0 3px var(--ui-primary-soft) !important;
            outline: none !important;
        }

        #createUserModal .btn-primary,
        #editUserModal .btn-primary {
            background: var(--ui-primary) !important;
            border-color: var(--ui-primary) !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            box-shadow: none !important;
        }

        #createUserModal .btn-primary:hover,
        #editUserModal .btn-primary:hover {
            background: var(--ui-primary-hover) !important;
            border-color: var(--ui-primary-hover) !important;
        }

        #createUserModal .btn-secondary,
        #editUserModal .btn-secondary {
            background: #ffffff !important;
            border: 1px solid var(--ui-line-strong) !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            box-shadow: none !important;
        }

        #createUserModal .btn-secondary:hover,
        #editUserModal .btn-secondary:hover {
            background: var(--ui-surface-2) !important;
            border-color: var(--ui-subtle) !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
        }
        /* Final live Bidder Details modal overrides. */


















































































































        @media (max-width: 720px) {
            .user-modal-grid {
                grid-template-columns: 1fr;
            }

            .user-modal {
                padding: 12px;
            }

            .user-modal-header {
                padding-left: 16px;
                padding-right: 68px;
            }

            .user-modal-form {
                padding-left: 16px;
                padding-right: 16px;
            }

            .user-modal-actions {
                margin: 0 -16px;
                padding-left: 16px;
                padding-right: 16px;
                flex-direction: column;
            }

            .user-modal-actions .btn-primary,
            .user-modal-actions .btn-secondary {
                width: 100%;
                justify-content: center;
            }

            .users-page .user-actions-cell {
                grid-template-columns: 1fr;
                align-items: stretch;
                min-width: 0;
                max-width: none;
            }

            .users-page .user-action-button,
            .users-page .user-action-remove {
                width: 100%;
            }

            .users-page .users-table-wrap {
                padding: 0 !important;
                overflow: visible !important;
            }






        }

        #createUserModal select.form-select,
        #editUserModal select.form-select {
            appearance: auto !important;
            background-color: #ffffff !important;
            background-image: none !important;
            border: 1px solid var(--ui-line-strong) !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            color-scheme: light !important;
        }

        #createUserModal select.form-select option,
        #editUserModal select.form-select option {
            background-color: #ffffff !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
        }

        #createUserModal select.form-select:focus,
        #editUserModal select.form-select:focus {
            background-color: #ffffff !important;
            border-color: #dc2626 !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12) !important;
            outline: none !important;
        }

        /* Bidder Details modal frame. Its content (admin/bidder-review.blade.php) styles itself under .brv. */
        body.user-review-modal-open { overflow: hidden; }
        #userReviewModal.user-review-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10020;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(27, 36, 32, 0.5);
            backdrop-filter: blur(4px);
        }
        #userReviewModal .user-review-modal-card {
            display: flex;
            flex-direction: column;
            width: min(1120px, 100%);
            height: min(880px, calc(100dvh - 32px));
            overflow: hidden;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius-lg);
            background: var(--ui-surface);
            box-shadow: var(--ui-shadow-lg);
            color: var(--ui-ink);
            font-family: var(--ui-font);
        }
        #userReviewModal .user-review-modal-top {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--ui-line);
            background: var(--ui-surface);
        }
        #userReviewModal .user-review-modal-title h2 {
            margin: 0;
            color: var(--ui-ink);
            font-size: 17px;
            font-weight: 700;
            line-height: 1.3;
        }
        #userReviewModal .user-review-modal-title p {
            margin: 2px 0 0;
            color: var(--ui-muted);
            font-size: var(--ui-text-sm);
        }
        #userReviewModal .user-review-modal-close {
            display: grid;
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            place-items: center;
            padding: 0;
            border: 1px solid var(--ui-line);
            border-radius: var(--ui-radius);
            background: var(--ui-surface);
            color: var(--ui-muted);
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }
        #userReviewModal .user-review-modal-close:hover {
            background: var(--ui-surface-2);
            color: var(--ui-ink);
        }
        #userReviewModal .user-review-modal-body {
            flex: 1 1 auto;
            min-height: 0;
            padding: 20px;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            background: var(--ui-page);
        }
        #userReviewModal .user-review-loading {
            display: grid;
            min-height: 240px;
            place-items: center;
            color: var(--ui-muted);
            font-size: var(--ui-text);
        }
        @media (max-width: 640px) {
            #userReviewModal.user-review-modal { padding: 8px; }
            #userReviewModal .user-review-modal-card { height: calc(100dvh - 16px); }
            #userReviewModal .user-review-modal-top { padding: 12px 14px; }
            #userReviewModal .user-review-modal-body { padding: 14px; }
        }
    </style>

    @include('partials.admin-sidebar')

    <div class="main-area users-page">
        <x-page-header title="Manage users" subtitle="Approve bidder registrations and maintain account access">
            <x-slot:actions>
                <button type="button" class="ul-add" onclick="openCreateUserModal()"><i class="fas fa-plus" aria-hidden="true"></i> Add user</button>
            </x-slot:actions>
        </x-page-header>

        <main class="dashboard-content">
            @if(session('success'))
                <div id="successAlert" class="ul-toast" role="status">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <span>{{ session('success') }}</span>
                    <button type="button" onclick="closeSuccessAlert()" aria-label="Dismiss">&times;</button>
                </div>
            @endif

            @if(session('warning'))
                <div class="ul-alert ul-alert--warning" role="alert"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span>{{ session('warning') }}</span></div>
            @endif

            @if(!($bidderApprovalAvailable ?? false))
                <div class="ul-alert" role="status"><i class="fas fa-circle-info" aria-hidden="true"></i><span>Bidder review and approval actions are temporarily unavailable because the bidder approval table is not present in the current database.</span></div>
            @endif

            @if($errors->any() && !old('editing_user_id'))
                <div class="ul-alert ul-alert--danger" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $activeFilter = $filter ?? 'all';
                $filterTabs = [
                    'all' => ['All users', $roleCounts['all'] ?? null],
                    'admin' => ['Admin', $roleCounts['admin'] ?? null],
                    'staff' => ['Staff', $roleCounts['staff'] ?? null],
                    'bidder' => ['Bidders', $roleCounts['bidder'] ?? null],
                    'pending' => ['Pending', $statusCounts['pending'] ?? null],
                ];
                if ($bidderSanctionsAvailable ?? false) {
                    $filterTabs['suspended'] = ['Suspended', $statusCounts['suspended'] ?? null];
                    $filterTabs['blacklisted'] = ['Blacklisted', $statusCounts['blacklisted'] ?? null];
                }
                $roleMeta = [
                    'admin' => ['Admin', 'admin', 'fa-user-shield'],
                    'staff' => ['Staff', 'staff', 'fa-user-tie'],
                    'bidder' => ['Bidder', 'bidder', 'fa-briefcase'],
                ];
            @endphp

            <section class="ul-card" aria-label="User accounts">
                <div class="ul-toolbar">
                    <nav class="ul-tabs" aria-label="Filter users">
                        @foreach($filterTabs as $key => [$label, $count])
                            <a href="{{ route('admin.users', ['filter' => $key, 'search' => $search !== '' ? $search : null]) }}"
                               class="ul-tab {{ $activeFilter === $key ? 'is-active' : '' }}"
                               @if($activeFilter === $key) aria-current="page" @endif>
                                <span>{{ $label }}</span>
                                @if($count !== null)<span class="ul-tab__count {{ in_array($key, ['pending', 'suspended', 'blacklisted'], true) && $count > 0 ? 'is-alert' : '' }}">{{ $count }}</span>@endif
                            </a>
                        @endforeach
                    </nav>

                    <form method="GET" action="{{ route('admin.users') }}" class="ul-search" role="search">
                        <input type="hidden" name="filter" value="{{ $activeFilter }}">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search name, email, company or office" aria-label="Search users">
                        @if($search !== '')
                            <a href="{{ route('admin.users', ['filter' => $activeFilter !== 'all' ? $activeFilter : null]) }}" class="ul-search__clear" aria-label="Clear search"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                        @endif
                    </form>
                </div>

                <div class="ul-table-wrap">
                    <table class="ul-table">
                        <thead>
                            <tr>
                                <th scope="col">User</th>
                                <th scope="col">Role &amp; office</th>
                                <th scope="col">Status</th>
                                <th scope="col">Joined</th>
                                <th scope="col"><span class="ul-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                @php
                                    $displayStatus = $user->role === 'bidder' && ($bidderApprovalAvailable ?? false)
                                        ? ($user->status === 'active' ? 'approved' : $user->status)
                                        : $user->status;
                                    $procurementStatus = $user->role === 'bidder' && ($bidderApprovalAvailable ?? false)
                                        ? $user->bidderProcurementStatus()
                                        : $displayStatus;
                                    $displayStatusLabel = match ($displayStatus) {
                                        'active', 'approved' => $user->role === 'bidder' ? 'Approved' : 'Active',
                                        'pending' => 'Pending',
                                        'rejected' => 'Rejected',
                                        default => ucwords(str_replace('_', ' ', (string) $displayStatus)),
                                    };
                                    $statusTone = match ($displayStatus) {
                                        'active', 'approved' => 'success',
                                        'pending' => 'neutral',
                                        'suspended' => 'warning',
                                        default => 'danger',
                                    };
                                    $activeSanction = in_array($procurementStatus, ['suspended', 'blacklisted'], true)
                                        ? $user->bidderProfile?->activeSanction
                                        : null;
                                    $isNewUser = ($bidderApprovalAvailable ?? false) && $user->role === 'bidder' && $user->status === 'pending' && ($user->bidderProfile?->review_status ?: 'new') === 'new';
                                    [$roleLabel, $roleTone, $roleIcon] = $roleMeta[$user->role] ?? [ucfirst((string) $user->role), 'admin', 'fa-user'];
                                    $initials = collect(preg_split('/\s+/', trim((string) $user->name)))
                                        ->reject(fn ($part) => $part === '' || str_ends_with($part, '.'))
                                        ->take(2)
                                        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                        ->implode('') ?: '?';
                                @endphp
                                <tr>
                                    <td class="ul-cell-user">
                                        <div class="ul-user">
                                            <span class="ul-avatar ul-avatar--{{ $roleTone }}" aria-hidden="true">{{ $initials }}</span>
                                            <span class="ul-user__text">
                                                <span class="ul-user__name">
                                                    {{ $user->name }}
                                                    @if($isNewUser)
                                                        <span class="ul-new" title="Registered within the last 3 days">New</span>
                                                    @endif
                                                </span>
                                                <span class="ul-user__email">{{ $user->email }}@if($user->username) <span class="ul-dot">·</span> {{ '@' . $user->username }}@endif</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="ul-cell-role">
                                        <span class="ul-role ul-role--{{ $roleTone }}"><i class="fas {{ $roleIcon }}" aria-hidden="true"></i> {{ $roleLabel }}</span>
                                        @if($user->role === 'staff')
                                            <span class="ul-meta">{{ $user->office ?: 'No office assigned' }}@if(filled($user->position ?? null)) <span class="ul-dot">·</span> {{ $user->position }}@endif</span>
                                        @elseif($user->role === 'bidder')
                                            <span class="ul-meta">{{ $user->company ?: 'Company not set' }}@if(filled($user->registration_no)) <span class="ul-dot">·</span> <span class="ul-mono">{{ $user->registration_no }}</span>@endif</span>
                                        @elseif(filled($user->position ?? null))
                                            <span class="ul-meta">{{ $user->position }}</span>
                                        @endif
                                    </td>
                                    <td class="ul-cell-status">
                                        <span class="ul-status ul-status--{{ $statusTone }}" data-user-status="{{ $user->id }}">{{ $displayStatusLabel }}</span>
                                        @if($activeSanction)
                                            <span class="ul-meta ul-meta--sanction">Procurement access: {{ $activeSanction->status_label }} &middot; Ref {{ $activeSanction->reference_number }} &middot; Effective {{ $activeSanction->effective_date?->format('M d, Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="ul-cell-date">
                                        @if($user->created_at)
                                            <span class="ul-date">{{ $user->created_at->format('M d, Y') }}</span>
                                            <span class="ul-meta">{{ $user->created_at->diffForHumans() }}</span>
                                        @else
                                            <span class="ul-meta">—</span>
                                        @endif
                                    </td>
                                    <td class="ul-cell-actions user-actions-cell">
                                        <div class="user-actions-menu">
                                            <button type="button" class="user-actions-trigger" onclick="toggleUserActionMenu(event, this)" aria-label="Open user actions" aria-haspopup="menu" aria-expanded="false">
                                                <i class="fas fa-ellipsis-vertical" aria-hidden="true"></i>
                                            </button>
    
                                            <div class="user-actions-dropdown" role="menu">
                                                @if($user->role === 'bidder' && ($bidderApprovalAvailable ?? false))
                                                    <a href="{{ route('admin.users.review', $user) }}" class="user-actions-menu-item" role="menuitem" onclick="openUserReviewModal(event, this.href)">
                                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                                        <span>View Details</span>
                                                    </a>
                                                @endif
    
                                                @if($user->role === 'bidder')
                                                    <a href="{{ route('admin.messages', ['user' => $user->id]) }}" class="user-actions-menu-item" role="menuitem">
                                                        <i class="fas fa-message" aria-hidden="true"></i>
                                                        <span>Message</span>
                                                    </a>
                                                @endif
    
                                                @if($user->role === 'bidder' && !($bidderApprovalAvailable ?? false))
                                                    <span class="user-actions-menu-note" role="menuitem" aria-disabled="true">
                                                        <i class="fas fa-eye-slash" aria-hidden="true"></i>
                                                        <span>Review unavailable</span>
                                                    </span>
                                                @endif
    
                                                <button
                                                    type="button"
                                                    onclick="openEditUserModal(this)"
                                                    class="user-actions-menu-item"
                                                    role="menuitem"
                                                    data-id="{{ $user->id }}"
                                                    data-name="{{ e($user->name) }}"
                                                    data-email="{{ e($user->email) }}"
                                                    data-username="{{ e($user->username ?? '') }}"
                                                    data-role="{{ $user->role }}"
                                                    data-status="{{ $user->status }}"
                                                    data-office="{{ e($user->office ?? '') }}"
                                                    data-company="{{ e($user->company ?? '') }}"
                                                    data-registration="{{ e($user->registration_no ?? '') }}"
                                                    data-position="{{ e($user->position ?? '') }}"
                                                    data-contact="{{ e($user->contact_number ?: ($user->role === 'bidder' && $user->relationLoaded('bidderProfile') ? ($user->bidderProfile?->contact_number ?? '') : '')) }}"
                                                    data-address="{{ e($user->role === 'bidder' && $user->relationLoaded('bidderProfile') ? ($user->bidderProfile?->business_address ?? '') : '') }}"
                                                >
                                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                                    <span>Edit</span>
                                                </button>
    
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="openDeleteUserModal(event, this);" data-delete-user-name="{{ e($user->name) }}" class="user-actions-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="user-actions-menu-item is-danger"
                                                        role="menuitem"
                                                        {{ auth()->id() === $user->id ? 'disabled' : '' }}
                                                    >
                                                        <i class="fas fa-trash-can" aria-hidden="true"></i>
                                                        <span>Delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="ul-empty-row">
                                    <td colspan="5">
                                        <div class="ul-empty">
                                            <span class="ul-empty__icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                                            <strong>{{ $search !== '' ? 'No users matched your search.' : 'No users in this list yet.' }}</strong>
                                            @if($search !== '')
                                                <a href="{{ route('admin.users', ['filter' => $activeFilter !== 'all' ? $activeFilter : null]) }}">Clear the search</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($users->hasPages())
                    {{-- 25 accounts a page; search and the tab filter carry over. --}}
                    <nav class="users-pager ul-pager" aria-label="User pages">
                        <span>Showing {{ $users->firstItem() }}&ndash;{{ $users->lastItem() }} of {{ $users->total() }}</span>
                        <span class="ul-pager__links">
                            @if($users->onFirstPage())
                                <span class="ul-pager__btn is-disabled" aria-disabled="true"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</span>
                            @else
                                <a class="ul-pager__btn" href="{{ $users->previousPageUrl() }}" rel="prev"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</a>
                            @endif
                            <span class="ul-pager__page">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>
                            @if($users->hasMorePages())
                                <a class="ul-pager__btn" href="{{ $users->nextPageUrl() }}" rel="next">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                            @else
                                <span class="ul-pager__btn is-disabled" aria-disabled="true">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                            @endif
                        </span>
                    </nav>
                @endif
            </section>
        </main>
    </div>
</div>

<div id="userReviewModal" class="user-review-modal" aria-hidden="true">
    <div class="user-review-modal-card" role="dialog" aria-modal="true" aria-labelledby="userReviewModalTitle">
        <div class="user-review-modal-top">
            <div class="user-review-modal-title">
                <h2 id="userReviewModalTitle">Bidder Details</h2>
                <p>Review submitted bidder information, BAC documents, and approval status.</p>
            </div>
            <button type="button" onclick="closeUserReviewModal()" class="user-review-modal-close" aria-label="Close">&times;</button>
        </div>
        <div id="userReviewModalBody" class="user-review-modal-body">
            <div class="user-review-loading">Loading bidder review...</div>
        </div>
    </div>
</div>

<div id="deleteUserModal" class="delete-user-modal bac-confirm-modal" aria-hidden="true">
    <div class="delete-user-modal-card bac-confirm-card" role="dialog" aria-modal="true" aria-labelledby="deleteUserModalTitle">
        <div class="delete-user-modal-header bac-confirm-header">
            <h2 id="deleteUserModalTitle" class="delete-user-modal-title">Delete User?</h2>
            <button type="button" onclick="closeDeleteUserModal()" class="delete-user-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="delete-user-modal-body bac-confirm-body">
            <p>Are you sure you want to delete <strong id="deleteUserName">this user</strong>? This action cannot be undone.</p>
        </div>
        <div class="delete-user-modal-actions bac-confirm-actions">
            <button type="button" onclick="closeDeleteUserModal()" class="delete-user-cancel bac-confirm-cancel">Cancel</button>
            <button type="button" onclick="confirmDeleteUser()" class="delete-user-confirm bac-confirm-danger">Delete</button>
        </div>
    </div>
</div>

<div id="createUserModal" class="user-modal" aria-hidden="true">
    <div class="user-modal-card" role="dialog" aria-modal="true" aria-labelledby="createUserModalTitle">
        <div class="um-head">
            <span class="um-head__icon" aria-hidden="true"><i class="fas fa-user-plus"></i></span>
            <div class="um-head__text">
                <h2 id="createUserModalTitle">Create user</h2>
                <p>Add an account and give it the right role.</p>
            </div>
            <button type="button" onclick="closeCreateUserModal()" class="um-close" aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>

        <form id="createUserForm" action="{{ route('admin.users.store') }}" method="POST" class="um-form">
            @csrf
            <div class="um-body">
                @if($errors->any() && !old('editing_user_id'))
                    <div class="user-modal-alert" role="alert">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('admin.partials.user-form-fields', ['prefix' => 'create'])
            </div>

            <div class="um-actions">
                <button type="button" onclick="closeCreateUserModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-user-plus" aria-hidden="true"></i> Create user</button>
            </div>
        </form>
    </div>
</div>
<div id="editUserModal" class="user-modal" aria-hidden="true">
    <div class="user-modal-card" role="dialog" aria-modal="true" aria-labelledby="editUserModalTitle">
        <div class="um-head">
            <span class="um-head__icon" aria-hidden="true"><i class="fas fa-user-pen"></i></span>
            <div class="um-head__text">
                <h2 id="editUserModalTitle">Edit user</h2>
                <p>Update details or status, or set a new password.</p>
            </div>
            <button type="button" onclick="closeEditUserModal()" class="um-close" aria-label="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>

        <form id="editUserForm" method="POST" class="um-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="editing_user_id" id="edit_user_id" value="">
            <div class="um-body">
                @if($errors->any() && old('editing_user_id'))
                    <div class="user-modal-alert" role="alert">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('admin.partials.user-form-fields', ['prefix' => 'edit'])
            </div>

            <div class="um-actions">
                <button type="button" onclick="closeEditUserModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentUserReviewUrl = '';
    let pendingDeleteUserForm = null;
    let loginActivityTimer = null;
    let latestLoginActivityHtml = '';

    function closeUserActionMenus(exceptMenu = null) {
        document.querySelectorAll('.user-actions-menu.is-open').forEach(function (menu) {
            if (menu !== exceptMenu) {
                menu.classList.remove('is-open');
                const trigger = menu.querySelector('.user-actions-trigger');
                const dropdown = menu.querySelector('.user-actions-dropdown');

                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }

                if (dropdown) {
                    dropdown.classList.remove('is-dropup');
                    dropdown.style.removeProperty('top');
                    dropdown.style.removeProperty('left');
                }
            }
        });
    }

    function positionUserActionDropdown(menu) {
        const trigger = menu?.querySelector('.user-actions-trigger');
        const dropdown = menu?.querySelector('.user-actions-dropdown');

        if (!trigger || !dropdown) return;

        const viewportGap = 12;
        const triggerGap = 8;
        const triggerRect = trigger.getBoundingClientRect();

        dropdown.classList.remove('is-dropup');
        dropdown.style.setProperty('top', '0px', 'important');
        dropdown.style.setProperty('left', '0px', 'important');
        dropdown.style.setProperty('right', 'auto', 'important');

        const dropdownWidth = dropdown.offsetWidth || 190;
        const dropdownHeight = dropdown.offsetHeight || 170;
        const maxLeft = window.innerWidth - dropdownWidth - viewportGap;
        const preferredLeft = triggerRect.right - dropdownWidth;
        const left = Math.max(viewportGap, Math.min(preferredLeft, maxLeft));

        let top = triggerRect.bottom + triggerGap;

        if (top + dropdownHeight > window.innerHeight - viewportGap) {
            top = Math.max(viewportGap, triggerRect.top - dropdownHeight - triggerGap);
            dropdown.classList.add('is-dropup');
        }

        dropdown.style.setProperty('left', `${left}px`, 'important');
        dropdown.style.setProperty('top', `${top}px`, 'important');
    }

    function toggleUserActionMenu(event, button) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const menu = button?.closest('.user-actions-menu');
        if (!menu) return;

        const willOpen = !menu.classList.contains('is-open');
        closeUserActionMenus(menu);
        menu.classList.toggle('is-open', willOpen);
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

        const dropdown = menu.querySelector('.user-actions-dropdown');
        if (!dropdown || !willOpen) return;

        dropdown.classList.remove('is-dropup');
        window.requestAnimationFrame(function () {
            positionUserActionDropdown(menu);
        });
    }
    function openDeleteUserModal(event, form) {
        closeUserActionMenus();

        if (event) {
            event.preventDefault();
        }

        pendingDeleteUserForm = form;

        const modal = document.getElementById('deleteUserModal');
        const name = document.getElementById('deleteUserName');

        if (name) {
            name.textContent = form?.dataset?.deleteUserName || 'this user';
        }

        if (modal) {
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeDeleteUserModal() {
        const modal = document.getElementById('deleteUserModal');

        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }

        pendingDeleteUserForm = null;
    }

    function confirmDeleteUser() {
        const form = pendingDeleteUserForm;
        pendingDeleteUserForm = null;

        if (form) {
            form.submit();
        }
    }

    function updateUserStatusBadge(userId, status, label) {
        const badge = document.querySelector(`[data-user-status="${userId}"]`);
        if (!badge) return;

        badge.textContent = label || (status ? status.charAt(0).toUpperCase() + status.slice(1) : '');

        const tone = (status === 'approved' || status === 'active') ? 'success'
            : (status === 'pending' ? 'neutral' : (status === 'suspended' ? 'warning' : 'danger'));
        badge.className = 'ul-status ul-status--' + tone;
    }

    function showUserActionToast(message, type = 'success', retry = null) {
        let toast = document.getElementById('userActionToast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'userActionToast';
            toast.style.position = 'fixed';
            toast.style.top = '90px';
            toast.style.right = '25px';
            toast.style.zIndex = '11000';
            toast.style.minWidth = '280px';
            toast.style.padding = '15px 18px';
            toast.style.borderRadius = '10px';
            toast.style.boxShadow = '0 14px 34px rgba(27, 36, 32,0.18)';
            toast.style.fontSize = '14px';
            toast.style.fontWeight = '600';
            document.body.appendChild(toast);
        }

        const colors = {
            error: { background: '#fee2e2', color: '#991b1b' },
            warning: { background: '#fef3c7', color: '#92400e' },
            success: { background: '#dcfce7', color: '#166534' },
        };
        const palette = colors[type] || colors.success;

        toast.textContent = '';
        toast.style.background = palette.background;
        toast.style.color = palette.color;
        toast.style.display = 'block';
        toast.style.opacity = '1';

        const messageEl = document.createElement('span');
        messageEl.textContent = message;
        toast.appendChild(messageEl);

        if (retry && retry.url) {
            const retryBtn = document.createElement('button');
            retryBtn.type = 'button';
            retryBtn.textContent = retry.label || 'Retry';
            retryBtn.style.display = 'block';
            retryBtn.style.marginTop = '8px';
            retryBtn.style.padding = '6px 12px';
            retryBtn.style.border = '1px solid currentColor';
            retryBtn.style.borderRadius = '8px';
            retryBtn.style.background = 'transparent';
            retryBtn.style.color = 'inherit';
            retryBtn.style.fontWeight = '700';
            retryBtn.style.fontSize = '13px';
            retryBtn.style.cursor = 'pointer';
            retryBtn.addEventListener('click', function () {
                retryBtn.disabled = true;
                retryBtn.textContent = 'Retrying...';
                retry.onClick();
            });
            toast.appendChild(retryBtn);
        }

        clearTimeout(window.userActionToastTimer);
        window.userActionToastTimer = setTimeout(function () {
            toast.style.transition = 'opacity 0.25s ease';
            toast.style.opacity = '0';
            setTimeout(function () {
                toast.style.display = 'none';
            }, 250);
        }, retry ? 9000 : 4200);
    }

    function retryBidderRequirementsNotification(retryUrl) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        fetch(retryUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                return { ok: response.ok, data };
            })
            .then(({ ok, data }) => {
                if (ok && data.mail_sent) {
                    showUserActionToast(data.message || 'Notification email resent to the bidder.', 'success');
                } else {
                    showUserActionToast(data.message || 'Unable to resend the notification email.', 'error', {
                        label: 'Retry again',
                        onClick: function () { retryBidderRequirementsNotification(retryUrl); },
                    });
                }
            })
            .catch(function () {
                showUserActionToast('Unable to resend the notification email.', 'error', {
                    label: 'Retry again',
                    onClick: function () { retryBidderRequirementsNotification(retryUrl); },
                });
            });
    }

    function loadUserReviewModal(url) {
        const body = document.getElementById('userReviewModalBody');
        if (!body || !url) return;

        body.innerHTML = '<div class="user-review-loading">Loading bidder review...</div>';

        const reviewUrl = new URL(url, window.location.origin);
        reviewUrl.searchParams.set('modal', '1');

        fetch(reviewUrl.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to load review.');
                }

                return response.text();
            })
            .then((html) => {
                body.innerHTML = html;
                startLoginActivityPolling();
            })
            .catch(() => {
                body.innerHTML = '<div class="user-review-loading" style="color:#b91c1c;">Unable to load bidder review. Please try again.</div>';
            });
    }


    function stopLoginActivityPolling() {
        if (loginActivityTimer) {
            clearInterval(loginActivityTimer);
            loginActivityTimer = null;
        }
        latestLoginActivityHtml = '';
    }

    function refreshLoginActivityRows() {
        const section = document.getElementById('recentLoginActivity');
        const rows = document.getElementById('recentLoginActivityRows');
        const modal = document.getElementById('userReviewModal');

        if (!section || !rows || !section.dataset.loginActivityUrl || !modal || modal.style.display === 'none') {
            stopLoginActivityPolling();
            return;
        }

        fetch(section.dataset.loginActivityUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to load login activity.');
                }

                return response.text();
            })
            .then((html) => {
                const nextHtml = html.trim();
                if (nextHtml !== '' && nextHtml !== latestLoginActivityHtml) {
                    rows.innerHTML = nextHtml;
                    latestLoginActivityHtml = nextHtml;
                }
            })
            .catch(() => {
                // Keep the current rows visible if a poll briefly fails.
            });
    }

    function startLoginActivityPolling() {
        stopLoginActivityPolling();

        const rows = document.getElementById('recentLoginActivityRows');
        if (rows) {
            latestLoginActivityHtml = rows.innerHTML.trim();
        }

        refreshLoginActivityRows();
        loginActivityTimer = setInterval(refreshLoginActivityRows, 5000);
    }
    function openUserReviewModal(event, url) {
        closeUserActionMenus();

        if (event) {
            event.preventDefault();
        }

        const modal = document.getElementById('userReviewModal');
        const body = document.getElementById('userReviewModalBody');

        if (!modal || !body || !url) {
            window.location.href = url;
            return;
        }

        currentUserReviewUrl = url;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('user-review-modal-open');
        loadUserReviewModal(url);
    }

    function closeUserReviewModal() {
        const modal = document.getElementById('userReviewModal');
        const body = document.getElementById('userReviewModalBody');

        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }

        if (body) {
            body.innerHTML = '<div class="user-review-loading">Loading bidder review...</div>';
        }

        document.body.classList.remove('user-review-modal-open');
        stopLoginActivityPolling();
        currentUserReviewUrl = '';
    }

    function openCreateUserModal() {
        const modal = document.getElementById('createUserModal');

        if (!modal) return;

        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('user-modal-open');
        toggleRoleFields('create');
    }

    function closeCreateUserModal() {
        const modal = document.getElementById('createUserModal');

        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }

        document.body.classList.remove('user-modal-open');
    }

    function openEditUserModal(source) {
        closeUserActionMenus();

        const data = source && source.dataset
            ? {
                id: source.dataset.id,
                name: source.dataset.name || '',
                email: source.dataset.email || '',
                username: source.dataset.username || '',
                role: source.dataset.role || 'bidder',
                status: source.dataset.status || 'active',
                office: source.dataset.office || '',
                company: source.dataset.company || '',
                registration: source.dataset.registration || '',
                position: source.dataset.position || '',
                contact: source.dataset.contact || '',
                address: source.dataset.address || '',
            }
            : (source || {});

        const userId = data.id;
        if (!userId) return;

        document.getElementById('editUserForm').action = `/admin/users/${userId}`;
        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_name').value = data.name || '';
        document.getElementById('edit_email').value = data.email || '';
        document.getElementById('edit_username').value = data.username || '';
        setUserRole('edit', data.role || 'bidder');
        document.getElementById('edit_status').value = data.status || 'active';
        const editOffice = document.getElementById('edit_office');
        editOffice.querySelectorAll('[data-legacy-office]').forEach(function (option) { option.remove(); });
        // Keep an office that is no longer on the list, so the account can still be edited.
        if (data.office && !Array.from(editOffice.options).some(function (option) { return option.value === data.office; })) {
            const legacy = new Option(data.office, data.office);
            legacy.dataset.officeRole = data.role || '';
            legacy.dataset.legacyOffice = '1';
            editOffice.add(legacy);
        }
        editOffice.value = data.office || '';
        document.getElementById('edit_company').value = data.company || '';
        document.getElementById('edit_registration_no').value = data.registration || '';
        document.getElementById('edit_position').value = data.position || '';
        document.getElementById('edit_contact_number').value = data.contact || '';
        document.getElementById('edit_bidder_contact_number').value = data.contact || '';
        document.getElementById('edit_business_address').value = data.address || '';
        document.getElementById('edit_password').value = '';
        toggleRoleFields('edit');
        document.getElementById('editUserModal').style.display = 'flex';
        document.getElementById('editUserModal').setAttribute('aria-hidden', 'false');
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
        document.getElementById('editUserModal').setAttribute('aria-hidden', 'true');
        document.getElementById('edit_user_id').value = '';
    }

    function closeSuccessAlert() {
        const alert = document.getElementById('successAlert');
        if (alert) {
            alert.style.display = 'none';
        }
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.user-actions-menu')) {
            closeUserActionMenus();
        }
    });

    window.addEventListener('resize', closeUserActionMenus);
    window.addEventListener('scroll', closeUserActionMenus, true);

    document.getElementById('createUserModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeCreateUserModal();
        }
    });

    document.getElementById('editUserModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeEditUserModal();
        }
    });

    document.getElementById('userReviewModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeUserReviewModal();
        }
    });

    document.getElementById('deleteUserModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeDeleteUserModal();
            closeUserActionMenus();
        }
    });

    document.getElementById('userReviewModalBody').addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-async-review-action]');
        if (!form) return;

        e.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton ? submitButton.innerHTML : '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = 'Saving...';
        }

        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: new FormData(form),
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));

                if (!response.ok || data.ok === false) {
                    throw new Error(data.message || 'Unable to update bidder status.');
                }

                return data;
            })
            .then((data) => {
                if (data.user) {
                    updateUserStatusBadge(data.user.id, data.user.status, data.user.status_label);
                }

                if (data.mail_sent === false && data.retry_url) {
                    showUserActionToast(data.message || 'Bidder status updated, but the notification email failed to send.', 'warning', {
                        label: 'Retry sending email',
                        onClick: function () { retryBidderRequirementsNotification(data.retry_url); },
                    });
                } else {
                    showUserActionToast(data.message || 'Bidder status updated.');
                }

                if (currentUserReviewUrl) {
                    loadUserReviewModal(currentUserReviewUrl);
                }
            })
            .catch((error) => {
                showUserActionToast(error.message || 'Unable to update bidder status.', 'error');
            })
            .finally(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeCreateUserModal();
            closeEditUserModal();
            closeUserReviewModal();
            closeDeleteUserModal();
            closeUserActionMenus();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        ['create', 'edit'].forEach(function (prefix) {
            document.querySelectorAll('#' + prefix + 'UserForm input[name="role"]').forEach(function (radio) {
                radio.addEventListener('change', function () { toggleRoleFields(prefix); });
            });
            toggleRoleFields(prefix);
        });

        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = button.parentElement.querySelector('input');
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        });

        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(function () {
                successAlert.style.transition = 'opacity 0.5s ease';
                successAlert.style.opacity = '0';
                setTimeout(function () {
                    successAlert.style.display = 'none';
                }, 500);
            }, 5000);
        }

        @if($errors->any())
            @if(old('editing_user_id'))
                openEditUserModal({
                    id: @json(old('editing_user_id')),
                    name: @json(old('name')),
                    email: @json(old('email')),
                    username: @json(old('username', '')),
                    role: @json(old('role', 'bidder')),
                    status: @json(old('status', 'active')),
                    office: @json(old('office', '')),
                    company: @json(old('company', '')),
                    registration: @json(old('registration_no', '')),
                    position: @json(old('position', '')),
                    contact: @json(old('contact_number', '')),
                    address: @json(old('business_address', ''))
                });
            @else
                openCreateUserModal();
            @endif
        @endif
    });

    function userRole(prefix) {
        const checked = document.querySelector('#' + prefix + 'UserForm input[name="role"]:checked');
        return checked ? checked.value : '';
    }

    function setUserRole(prefix, role) {
        const radio = document.getElementById(prefix + '_role_' + role);
        if (radio) radio.checked = true;
        toggleRoleFields(prefix);
    }

    // Shows the blocks for the chosen account type (data-role-group="staff end_user ..."),
    // disables the hidden ones so they are not submitted, and words the labels for it.
    function toggleRoleFields(prefix) {
        const form = document.getElementById(prefix + 'UserForm');
        if (!form) return;
        const role = userRole(prefix);
        const has = function (element, attribute) {
            return (element.getAttribute(attribute) || '').split(' ').includes(role);
        };

        form.querySelectorAll('[data-role-group]').forEach(function (group) {
            const visible = has(group, 'data-role-group');
            group.hidden = !visible;
            group.setAttribute('aria-hidden', visible ? 'false' : 'true');
        });

        form.querySelectorAll('input, select, textarea').forEach(function (control) {
            if (control.name === 'role' || control.type === 'hidden') return;
            const hiddenGroup = control.closest('[data-role-group][hidden]');
            const roleInput = control.hasAttribute('data-role-input') && !has(control, 'data-role-input');
            control.disabled = Boolean(hiddenGroup) || roleInput;
        });

        const officeSelect = document.getElementById(prefix + '_office');
        const needsOffice = role === 'staff';
        if (officeSelect) {
            officeSelect.required = needsOffice;
            Array.from(officeSelect.options).forEach(function (option) {
                if (!option.dataset.officeRole) return;
                const matches = option.dataset.officeRole === role;
                option.hidden = !matches;
                option.disabled = !matches;
                if (!matches && option.selected) officeSelect.value = '';
            });
        }

        const company = document.getElementById(prefix + '_company');
        if (company) company.required = role === 'bidder';

        const copy = {
            admin: { heading: 'Position', office: 'Office', position: 'e.g. BAC Chairperson', contact: 'Contact no.', name: 'Full name', namePlaceholder: 'e.g. Maria Santos', hint: '' },
            staff: { heading: 'Office assignment', office: 'BAC office', position: 'e.g. BAC Secretariat Head', contact: 'Office contact no.', name: 'Full name', namePlaceholder: 'e.g. Jose Reyes', hint: 'The BAC office this staff member works in.' },
            bidder: { heading: '', office: 'Office', position: '', contact: 'Contact no.', name: 'Authorized representative', namePlaceholder: 'Owner or authorized representative', hint: '' },
        }[role] || {};

        const heading = document.getElementById(prefix + 'OfficeHeading');
        if (heading && copy.heading) heading.textContent = copy.heading;
        const officeLabel = document.getElementById(prefix + 'OfficeLabel');
        if (officeLabel) officeLabel.innerHTML = (copy.office || 'Office') + ' <span class="um-req">*</span>';
        const hint = form.querySelector('[data-office-hint]');
        if (hint) hint.textContent = copy.hint || '';
        const position = form.querySelector('[data-position-placeholder]');
        if (position) position.placeholder = copy.position || '';
        const contactLabel = form.querySelector('[data-contact-label]');
        if (contactLabel) contactLabel.innerHTML = (copy.contact || 'Contact no.') + ' <span class="um-opt">(optional)</span>';
        const nameLabel = form.querySelector('[data-name-label]');
        if (nameLabel) nameLabel.innerHTML = (copy.name || 'Full name') + ' <span class="um-req">*</span>';
        const name = form.querySelector('[data-name-placeholder]');
        if (name) name.placeholder = copy.namePlaceholder || '';
    }
</script>

<style id="users-all-bids-table-ui">
    /* Manage Users table aligned with the All Bids table language */
    .users-page .user-table-card {
        border: 1px solid var(--ui-line) !important;
        border-radius: var(--ui-radius-lg) !important;
        background: #ffffff !important;
        box-shadow: var(--ui-shadow) !important;
        overflow: hidden !important;
    }

    .users-page .user-table-card > form {
        padding: 16px 18px !important;
        border-bottom: 1px solid var(--ui-line) !important;
        background: var(--ui-surface-2) !important;
    }

    .users-page .users-table-wrap {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        overflow-x: auto !important;
        overflow-y: visible !important;
        scrollbar-color: #6b736e #e5e0d4 !important;
        scrollbar-gutter: stable !important;
    }

    .users-page .users-table-wrap::-webkit-scrollbar {
        height: 10px !important;
    }

    .users-page .users-table-wrap::-webkit-scrollbar-track {
        background: var(--ui-line) !important;
    }

    .users-page .users-table-wrap::-webkit-scrollbar-thumb {
        border: 2px solid var(--ui-line) !important;
        border-radius: 999px !important;
        background: var(--ui-muted) !important;
    }

    .users-page .users-table {
        display: table !important;
        width: 100% !important;
        min-width: 980px !important;
        table-layout: fixed !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        margin: 0 !important;
        font-size: 12px !important;
    }

    .users-page .users-table thead {
        display: table-header-group !important;
    }

    .users-page .users-table tbody {
        display: table-row-group !important;
    }

    .users-page .users-table tr {
        display: table-row !important;
    }

    .users-page .users-table th,
    .users-page .users-table td {
        display: table-cell !important;
        min-width: 0 !important;
        padding: 9px 10px !important;
        vertical-align: middle !important;
        line-height: 1.35 !important;
        overflow-wrap: normal !important;
    }

    .users-page .users-table th {
        background: var(--ui-surface-2) !important;
        color: var(--ui-muted) !important;
        -webkit-text-fill-color: var(--ui-muted) !important;
        border: 0 !important;
        font-size: 10px !important;
        font-weight: 600 !important;
        letter-spacing: normal !important;
        text-align: left !important;
        text-transform: none !important;
        white-space: nowrap !important;
    }

    .users-page .users-table th:first-child {
        border-top-left-radius: 8px !important;
    }

    .users-page .users-table th:last-child {
        border-top-right-radius: 8px !important;
        text-align: center !important;
    }

    .users-page .users-table td {
        border-bottom: 1px solid var(--ui-line) !important;
        background: #ffffff !important;
        color: var(--ui-ink) !important;
        -webkit-text-fill-color: var(--ui-ink) !important;
        font-size: 12px !important;
    }

    .users-page .users-table tbody tr:hover td {
        background: var(--ui-surface-2) !important;
    }

    .users-page .users-table tbody tr:last-child td {
        border-bottom: 0 !important;
    }

    .users-page .users-table th:nth-child(1) { width: 16% !important; }
    .users-page .users-table th:nth-child(2) { width: 18% !important; }
    .users-page .users-table th:nth-child(3) { width: 8% !important; }
    .users-page .users-table th:nth-child(4) { width: 9% !important; }
    .users-page .users-table th:nth-child(5) { width: 10% !important; }
    .users-page .users-table th:nth-child(6) { width: 14% !important; }
    .users-page .users-table th:nth-child(7) { width: 10% !important; }
    .users-page .users-table th:nth-child(8) { width: 9% !important; }
    .users-page .users-table th:nth-child(9) { width: 6% !important; }

    .users-page .users-table td:first-child {
        color: var(--ui-ink) !important;
        font-weight: 700 !important;
    }

    .users-page .users-table td:nth-child(2),
    .users-page .users-table td:nth-child(8) {
        color: var(--ui-muted) !important;
        -webkit-text-fill-color: var(--ui-muted) !important;
    }

    .users-page .users-table td:nth-child(3),
    .users-page .users-table td:nth-child(5),
    .users-page .users-table td:last-child {
        text-align: center !important;
    }

    .users-page .users-table td > div[style*="font-size: 12px"],
    .users-page .users-table td > div[style*="font-size: 11px"] {
        margin-top: 3px !important;
        color: var(--ui-muted) !important;
        -webkit-text-fill-color: var(--ui-muted) !important;
        font-size: 11px !important;
        line-height: 1.25 !important;
    }

    .users-page .users-table td span[data-user-status],
    .users-page .users-table td:nth-child(3) span {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 24px !important;
        padding: 4px 9px !important;
        border: 1px solid transparent !important;
        border-radius: 999px !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        line-height: 1 !important;
        white-space: nowrap !important;
    }

    .users-page .user-actions-cell {
        display: table-cell !important;
        width: 6% !important;
        min-width: 64px !important;
        max-width: 72px !important;
        padding: 8px 8px !important;
        text-align: center !important;
    }

    .users-page .user-actions-menu {
        display: inline-flex !important;
        position: relative !important;
    }

    .users-page .user-actions-trigger {
        width: 34px !important;
        height: 34px !important;
        min-width: 34px !important;
        min-height: 34px !important;
        padding: 0 !important;
        border: 1px solid var(--ui-line-strong) !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        color: var(--ui-ink-2) !important;
        -webkit-text-fill-color: var(--ui-ink-2) !important;
        box-shadow: none !important;
    }

    .users-page .user-actions-trigger:hover,
    .users-page .user-actions-menu.is-open .user-actions-trigger {
        border-color: var(--ui-primary) !important;
        background: var(--ui-primary-soft) !important;
        color: var(--ui-primary) !important;
        -webkit-text-fill-color: var(--ui-primary) !important;
    }

    .users-page .user-actions-dropdown {
        z-index: 10030 !important;
        width: 190px !important;
        padding: 5px !important;
        border: 1px solid var(--ui-line-strong) !important;
        border-radius: var(--ui-radius-lg) !important;
        background: #ffffff !important;
        box-shadow: 0 16px 34px rgba(27, 36, 32, .16) !important;
    }

    .users-page .user-actions-menu-item,
    .users-page .user-actions-menu-note {
        min-height: 34px !important;
        gap: 9px !important;
        padding: 8px 10px !important;
        border-radius: 7px !important;
        color: var(--ui-ink-2) !important;
        -webkit-text-fill-color: var(--ui-ink-2) !important;
        font-size: 12px !important;
        font-weight: 600 !important;
    }

    .users-page .user-actions-menu-item:hover {
        background: var(--ui-primary-soft) !important;
        color: var(--ui-primary) !important;
        -webkit-text-fill-color: var(--ui-primary) !important;
    }

    .users-page .user-actions-menu-item.is-danger:hover {
        background: #fef2f2 !important;
        color: #dc2626 !important;
        -webkit-text-fill-color: #dc2626 !important;
    }

    @media (max-width: 900px) {
        .users-page .users-table-wrap {
            overflow: visible !important;
        }

        .users-page .users-table {
            display: block !important;
            min-width: 0 !important;
        }

        .users-page .users-table colgroup,
        .users-page .users-table thead {
            display: none !important;
        }

        .users-page .users-table tbody {
            display: grid !important;
            gap: 10px !important;
            padding: 10px !important;
            background: var(--ui-surface-2) !important;
        }

        .users-page .users-table tr {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 0 14px !important;
            padding: 10px 12px !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            box-shadow: 0 3px 10px rgba(27, 36, 32, .04) !important;
        }

        .users-page .users-table td {
            display: grid !important;
            grid-template-columns: minmax(76px, 38%) minmax(0, 1fr) !important;
            gap: 8px !important;
            align-items: center !important;
            width: auto !important;
            min-width: 0 !important;
            max-width: none !important;
            padding: 7px 0 !important;
            border-bottom: 1px solid var(--ui-line-soft) !important;
            text-align: left !important;
        }

        .users-page .users-table td::before {
            display: block !important;
            color: var(--ui-subtle) !important;
            -webkit-text-fill-color: var(--ui-subtle) !important;
            font-size: 9px !important;
            font-weight: 700 !important;
            letter-spacing: normal !important;
            text-transform: none !important;
        }

        .users-page .users-table td:nth-child(1)::before { content: "Name"; }
        .users-page .users-table td:nth-child(2)::before { content: "Email"; }
        .users-page .users-table td:nth-child(3)::before { content: "Role"; }
        .users-page .users-table td:nth-child(4)::before { content: "Office"; }
        .users-page .users-table td:nth-child(5)::before { content: "Status"; }
        .users-page .users-table td:nth-child(6)::before { content: "Company"; }
        .users-page .users-table td:nth-child(7)::before { content: "Registration"; }
        .users-page .users-table td:nth-child(8)::before { content: "Created"; }
        .users-page .users-table td:nth-child(9)::before { content: "Actions"; }

        .users-page .users-table td:nth-child(1),
        .users-page .users-table td:nth-child(2),
        .users-page .users-table td:nth-child(6),
        .users-page .users-table td:nth-child(7),
        .users-page .users-table td:nth-child(8),
        .users-page .users-table td:nth-child(9) {
            grid-column: 1 / -1 !important;
        }

        .users-page .users-table td:nth-child(9) {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding-bottom: 0 !important;
            border-bottom: 0 !important;
        }

        .users-page .users-table td:nth-child(9)::before {
            flex: 0 0 auto !important;
        }

        .users-page .users-table td span[data-user-status],
        .users-page .users-table td:nth-child(3) span {
            justify-self: start !important;
        }
    }
</style>

<style id="users-final-all-bids-parity">
    /* Final Manage Users table parity with All Bids */
    @media (min-width: 901px) {
        body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card {
            padding: 0 !important;
            border: 1px solid var(--ui-line) !important;
            border-radius: var(--ui-radius-lg) !important;
            background: #ffffff !important;
            overflow: hidden !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card > form {
            padding: 16px 18px !important;
            border-bottom: 1px solid var(--ui-line) !important;
            background: var(--ui-surface-2) !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table-wrap {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow-x: hidden !important;
            overflow-y: visible !important;
            box-sizing: border-box !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table :is(th, td) {
            box-sizing: border-box !important;
            padding: 10px 8px !important;
            vertical-align: middle !important;
            line-height: 1.35 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table thead th {
            height: 42px !important;
            background: var(--ui-surface-2) !important;
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
            font-family: var(--ui-font) !important;
            font-size: 10px !important;
            font-weight: 600 !important;
            letter-spacing: normal !important;
            line-height: 1.2 !important;
            text-align: left !important;
            text-transform: none !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table thead th:last-child {
            border-top-right-radius: var(--ui-radius-lg) !important;
            text-align: center !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody td {
            background: #ffffff !important;
            color: var(--ui-ink) !important;
            -webkit-text-fill-color: var(--ui-ink) !important;
            font-family: var(--ui-font) !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            border-bottom: 1px solid var(--ui-line) !important;
            overflow-wrap: anywhere !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody tr {
            background: #ffffff !important;
            transition: background-color .16s ease !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody tr:hover td {
            background: var(--ui-surface-2) !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody tr:last-child td {
            border-bottom: 0 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table td:first-child {
            font-weight: 700 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table td:nth-child(2),
        body .admin-dashboard.admin-role-page .main-area.users-page .users-table td:nth-child(8) {
            color: var(--ui-muted) !important;
            -webkit-text-fill-color: var(--ui-muted) !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table td:nth-child(3),
        body .admin-dashboard.admin-role-page .main-area.users-page .users-table td:nth-child(5),
        body .admin-dashboard.admin-role-page .main-area.users-page .users-table td:last-child {
            text-align: center !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .user-actions-cell {
            width: 6% !important;
            min-width: 64px !important;
            max-width: 72px !important;
            padding: 8px !important;
            text-align: center !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .user-actions-menu {
            display: inline-flex !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .user-actions-trigger {
            width: 34px !important;
            height: 34px !important;
            min-width: 34px !important;
            min-height: 34px !important;
            border: 1px solid var(--ui-line-strong) !important;
            border-radius: 8px !important;
            background: #ffffff !important;
            color: var(--ui-ink-2) !important;
            -webkit-text-fill-color: var(--ui-ink-2) !important;
            box-shadow: none !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .user-actions-trigger:hover,
        body .admin-dashboard.admin-role-page .main-area.users-page .user-actions-menu.is-open .user-actions-trigger {
            border-color: var(--ui-primary) !important;
            background: var(--ui-primary-soft) !important;
            color: var(--ui-primary) !important;
            -webkit-text-fill-color: var(--ui-primary) !important;
        }
    }
</style>

<style id="users-table-edge-fix">
    /* Keep the Manage Users table flush and visually continuous like All Bids */
    @media (min-width: 901px) {
        body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card {
            padding: 0 !important;
            overflow: hidden !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card > .users-table-wrap {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: hidden !important;
            overflow-y: visible !important;
            box-sizing: border-box !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card > .users-table-wrap > .users-table {
            display: table !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            table-layout: fixed !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            box-sizing: border-box !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table thead th:first-child {
            border-top-left-radius: var(--ui-radius-lg) !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table thead th:last-child {
            border-top-right-radius: var(--ui-radius-lg) !important;
        }
    }
</style>

<style id="users-table-right-edge-fix">
    /* Extend only the table content to the card's right edge */
    @media (min-width: 901px) {
        body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card > .users-table-wrap {
            width: calc(100% + 20px) !important;
            max-width: none !important;
            margin-right: -20px !important;
            padding-right: 0 !important;
            overflow-x: hidden !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card > .users-table-wrap > .users-table {
            width: 100% !important;
            max-width: none !important;
        }
    }
</style>

<style id="users-new-badge">
    .users-page .user-name-line {
        display: flex !important;
        align-items: center !important;
        gap: 6px !important;
        min-width: 0 !important;
    }

    .users-page .user-name-line > span:first-child {
        min-width: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    .users-page .user-new-badge {
        display: inline-flex !important;
        align-items: center !important;
        flex: 0 0 auto !important;
        min-height: 18px !important;
        padding: 3px 6px !important;
        border: 1px solid var(--ui-primary-line) !important;
        border-radius: 999px !important;
        background: var(--ui-primary-soft) !important;
        color: var(--ui-primary) !important;
        -webkit-text-fill-color: var(--ui-primary) !important;
        font-family: var(--ui-font) !important;
        font-size: 9px !important;
        font-weight: 700 !important;
        letter-spacing: .04em !important;
        line-height: 1 !important;
        white-space: nowrap !important;
    }
</style>

<style id="edit-user-claude-ui">
    #editUserModal.user-modal {
        padding: 16px !important;
        background: rgba(27, 36, 32, .48) !important;
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
        box-sizing: border-box !important;
    }

    #editUserModal > .user-modal-card {
        width: min(640px, calc(100vw - 32px)) !important;
        max-height: calc(100dvh - 32px) !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        border: 1px solid var(--ui-line) !important;
        border-radius: var(--ui-radius-lg) !important;
        background: var(--ui-surface-2) !important;
        box-shadow: 0 28px 80px rgba(27, 36, 32, .22) !important;
        color: var(--ui-ink) !important;
        font-family: var(--ui-font) !important;
    }

    #editUserModal .user-modal-close {
        top: 18px !important;
        right: 20px !important;
        width: 36px !important;
        height: 36px !important;
        border: 1px solid var(--ui-line) !important;
        border-radius: var(--ui-radius-lg) !important;
        background: #ffffff !important;
        color: var(--ui-muted) !important;
        font-size: 20px !important;
        line-height: 1 !important;
        box-shadow: 0 6px 16px rgba(27, 36, 32, .06) !important;
    }

    #editUserModal .user-modal-close:hover,
    #editUserModal .user-modal-close:focus-visible {
        border-color: var(--ui-primary-line) !important;
        background: var(--ui-primary-soft) !important;
        color: var(--ui-primary) !important;
        outline: none !important;
    }

    #editUserModal .user-modal-header {
        flex: 0 0 auto !important;
        padding: 22px 76px 18px 28px !important;
        border-bottom: 1px solid #ebe7e2 !important;
        background: var(--ui-surface-2) !important;
    }

    #editUserModal .user-modal-header h2 {
        margin: 0 !important;
        color: var(--ui-ink) !important;
        -webkit-text-fill-color: var(--ui-ink) !important;
        font-size: 23px !important;
        font-weight: 700 !important;
        letter-spacing: -.02em !important;
        line-height: 1.2 !important;
    }

    #editUserModal .user-modal-header p {
        margin: 8px 0 0 !important;
        color: var(--ui-muted) !important;
        -webkit-text-fill-color: var(--ui-muted) !important;
        font-size: 13px !important;
        line-height: 1.45 !important;
    }

    #editUserModal .user-modal-form {
        min-height: 0 !important;
        display: flex !important;
        flex: 1 1 auto !important;
        flex-direction: column !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        padding: 22px 28px 0 !important;
        background: var(--ui-surface-2) !important;
        color: var(--ui-ink) !important;
        font-size: 14px !important;
    }

    #editUserModal .user-modal-grid {
        gap: 14px !important;
        margin-bottom: 0 !important;
    }

    #editUserModal .user-modal-field {
        margin-bottom: 16px !important;
    }
</style>

<style id="edit-user-claude-controls">
    #editUserModal .user-modal-field label {
        margin-bottom: 7px !important;
        color: var(--ui-ink-2) !important;
        -webkit-text-fill-color: var(--ui-ink-2) !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        letter-spacing: normal !important;
        line-height: 1.25 !important;
        text-transform: none !important;
    }

    #editUserModal .user-modal-form :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea) {
        width: 100% !important;
        min-height: 42px !important;
        height: 42px !important;
        box-sizing: border-box !important;
        border: 1px solid var(--ui-line-strong) !important;
        border-radius: var(--ui-radius) !important;
        background: #ffffff !important;
        color: var(--ui-ink) !important;
        -webkit-text-fill-color: var(--ui-ink) !important;
        font-family: inherit !important;
        font-size: 14px !important;
        font-weight: 450 !important;
        line-height: 1.35 !important;
        box-shadow: none !important;
        color-scheme: light !important;
    }

    #editUserModal .user-modal-form :is(input, select, textarea):hover {
        border-color: var(--ui-subtle) !important;
    }

    #editUserModal .user-modal-form :is(input, select, textarea):focus {
        border-color: var(--ui-primary) !important;
        box-shadow: 0 0 0 3px rgba(29, 79, 64, .13) !important;
        outline: none !important;
    }

    #editUserModal .user-modal-form select option {
        background: #ffffff !important;
        color: var(--ui-ink) !important;
    }

    #editUserModal .user-modal-form input::placeholder {
        color: var(--ui-subtle) !important;
        -webkit-text-fill-color: var(--ui-subtle) !important;
    }

    #editUserModal .user-modal-alert {
        margin: 0 0 16px !important;
        border: 1px solid #fecaca !important;
        border-radius: var(--ui-radius-lg) !important;
        background: #fff7f7 !important;
        color: #b91c1c !important;
        font-size: 12px !important;
        line-height: 1.45 !important;
    }

    #editUserModal .user-modal-actions {
        position: sticky !important;
        bottom: 0 !important;
        z-index: 3 !important;
        flex: 0 0 auto !important;
        margin: 0 -28px !important;
        padding: 16px 28px 20px !important;
        border-top: 1px solid #ebe7e2 !important;
        background: rgba(250, 249, 247, .97) !important;
        backdrop-filter: blur(8px) !important;
    }

    #editUserModal .user-modal-actions :is(.btn-primary, .btn-secondary) {
        min-width: 116px !important;
        height: 36px !important;
        padding: 0 16px !important;
        border-radius: var(--ui-radius) !important;
        font-family: inherit !important;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        line-height: 1 !important;
    }

    #editUserModal .user-modal-actions .btn-primary {
        border: 1px solid var(--ui-primary) !important;
        background: var(--ui-primary) !important;
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        box-shadow: none !important;
    }

    #editUserModal .user-modal-actions .btn-primary:hover,
    #editUserModal .user-modal-actions .btn-primary:focus-visible {
        border-color: var(--ui-primary) !important;
        background: var(--ui-primary-hover) !important;
        outline: none !important;
    }

    #editUserModal .user-modal-actions .btn-secondary {
        border: 1px solid var(--ui-line-strong) !important;
        background: #ffffff !important;
        color: var(--ui-ink-2) !important;
        -webkit-text-fill-color: var(--ui-ink-2) !important;
        box-shadow: none !important;
    }
</style>

<style id="edit-user-claude-responsive">
    #editUserModal .user-modal-actions .btn-secondary:hover,
    #editUserModal .user-modal-actions .btn-secondary:focus-visible {
        border-color: var(--ui-subtle) !important;
        background: var(--ui-page) !important;
        color: var(--ui-ink) !important;
        -webkit-text-fill-color: var(--ui-ink) !important;
        outline: none !important;
    }

    @media (max-width: 720px) {
        #editUserModal.user-modal {
            padding: 12px !important;
        }

        #editUserModal > .user-modal-card {
            width: calc(100vw - 24px) !important;
            max-height: calc(100dvh - 24px) !important;
        }

        #editUserModal .user-modal-header {
            padding-right: 68px !important;
        }

        #editUserModal .user-modal-actions {
            margin-left: -20px !important;
            margin-right: -20px !important;
            padding-left: 20px !important;
            padding-right: 20px !important;
            flex-direction: column !important;
            align-items: stretch !important;
        }

        #editUserModal .user-modal-actions :is(.btn-primary, .btn-secondary) {
            width: 100% !important;
        }
    }
</style>

<style id="edit-user-wide-alignment">
    #editUserModal > .user-modal-card {
        width: min(720px, calc(100vw - 32px)) !important;
        max-height: calc(100dvh - 24px) !important;
    }

    #editUserModal .user-modal-header {
        padding: 20px 32px 16px !important;
    }

    #editUserModal .user-modal-form {
        flex: 0 1 auto !important;
        overflow-y: visible !important;
        padding: 18px 32px 0 !important;
    }

    #editUserModal .user-modal-grid {
        align-items: start !important;
        column-gap: 18px !important;
        row-gap: 12px !important;
    }

    #editUserModal .user-modal-field {
        margin-bottom: 12px !important;
    }

    #editUserModal .user-modal-field label {
        min-height: 14px !important;
    }

    #editUserModal .user-modal-actions {
        margin: 0 -32px !important;
        padding: 14px 32px 18px !important;
    }

    @media (max-width: 720px) {
        #editUserModal > .user-modal-card {
            width: calc(100vw - 24px) !important;
        }

        #editUserModal .user-modal-header,
        #editUserModal .user-modal-form {
            padding-left: 20px !important;
            padding-right: 20px !important;
        }

        #editUserModal .user-modal-form {
            overflow-y: auto !important;
        }

        #editUserModal .user-modal-actions {
            margin-left: -20px !important;
            margin-right: -20px !important;
            padding-left: 20px !important;
            padding-right: 20px !important;
        }
    }
</style>

<style id="users-context-claude-ui">
    body .admin-dashboard.admin-role-page .main-area.users-page {
        background: var(--ui-page) !important;
        color: var(--ui-ink) !important;
        font-family: var(--ui-font) !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .dashboard-content {
        color: var(--ui-ink) !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .welcome-text {
        margin-bottom: 20px !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .welcome-text > button {
        min-height: 36px !important;
        padding: 0 18px !important;
        border: 1px solid var(--ui-primary) !important;
        border-radius: var(--ui-radius) !important;
        background: var(--ui-primary) !important;
        font-family: inherit !important;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        box-shadow: none !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .welcome-text > button:hover {
        border-color: var(--ui-primary) !important;
        background: var(--ui-primary-hover) !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page main.dashboard-content > div[style*="margin: 20px 0 14px"] {
        margin: 22px 0 16px !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page main.dashboard-content > div[style*="margin: 20px 0 14px"] > div {
        gap: 4px !important;
        padding: 4px !important;
        border: 1px solid var(--ui-line) !important;
        border-radius: var(--ui-radius-lg) !important;
        background: #eeece9 !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page main.dashboard-content > div[style*="margin: 20px 0 14px"] a {
        min-height: 38px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 16px !important;
        border-radius: var(--ui-radius-lg) !important;
        color: var(--ui-muted) !important;
        font-family: inherit !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        line-height: 1 !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page main.dashboard-content > div[style*="margin: 20px 0 14px"] a:hover {
        background: var(--ui-surface-2) !important;
        color: var(--ui-primary) !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card {
        border: 1px solid var(--ui-line) !important;
        border-radius: var(--ui-radius-lg) !important;
        background: var(--ui-surface-2) !important;
        box-shadow: var(--ui-shadow) !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .user-table-card > form {
        padding: 16px 22px !important;
        border-bottom: 1px solid #ebe7e2 !important;
        background: var(--ui-surface-2) !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-table-wrap {
        padding: 0 !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-table {
        min-width: 980px !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        font-family: inherit !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-table thead th {
        padding: 13px 12px !important;
        background: var(--ui-surface-2) !important;
        color: var(--ui-muted) !important;
        -webkit-text-fill-color: var(--ui-muted) !important;
        font-family: inherit !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        letter-spacing: normal !important;
        line-height: 1.2 !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody td {
        padding: 14px 12px !important;
        color: var(--ui-ink-2) !important;
        -webkit-text-fill-color: var(--ui-ink-2) !important;
        font-family: inherit !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        line-height: 1.4 !important;
        vertical-align: middle !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody tr:hover td {
        background: var(--ui-surface-2) !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody td:first-child {
        color: var(--ui-ink) !important;
        -webkit-text-fill-color: var(--ui-ink) !important;
        font-size: 13px !important;
        font-weight: 700 !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody td:nth-child(2),
    body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody td:nth-child(8) {
        color: var(--ui-muted) !important;
        -webkit-text-fill-color: var(--ui-muted) !important;
    }

    /* Keep the selected Manage Users filter tab color stable after reload. */
    body .admin-dashboard.admin-role-page .main-area.users-page .users-filter-tab.is-active {
        background: #ffffff !important;
        color: var(--ui-primary) !important;
        -webkit-text-fill-color: var(--ui-primary) !important;
        box-shadow: 0 1px 3px rgba(27, 36, 32, .08) !important;
        font-weight: 600 !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-filter-tab:not(.is-active) {
        background: transparent !important;
        color: var(--ui-muted) !important;
        -webkit-text-fill-color: var(--ui-muted) !important;
        font-weight: 500 !important;
    }

    body .admin-dashboard.admin-role-page .main-area.users-page .users-filter-tab.is-active:hover,
    body .admin-dashboard.admin-role-page .main-area.users-page .users-filter-tab.is-active:focus-visible {
        background: #ffffff !important;
        color: var(--ui-primary) !important;
        -webkit-text-fill-color: var(--ui-primary) !important;
    }
</style>

{{-- Users list (rebuilt): toolbar with counted tabs and search, a five-column table
     (user, role & office, status, joined, actions) and phone cards. Scoped and
     !important because dashboard.css styles every table, th and td on admin pages. --}}
<style id="users-list">
    body .admin-dashboard.admin-role-page .main-area.users-page .dashboard-content { display: grid !important; grid-template-columns: minmax(0, 1fr) !important; gap: 16px !important; }

    .users-page .ul-add { display: inline-flex !important; align-items: center !important; gap: 8px !important; height: 38px !important; padding: 0 16px !important; border: 0 !important; border-radius: 9px !important; background: var(--ui-primary) !important; color: #fff !important; -webkit-text-fill-color: #fff !important; font: inherit !important; font-size: 13.5px !important; font-weight: 600 !important; cursor: pointer !important; }
    .users-page .ul-add:hover { background: var(--ui-primary-hover) !important; }
    .users-page .ul-add i { font-size: 12px !important; color: inherit !important; -webkit-text-fill-color: currentColor !important; }

    .users-page .ul-toast { position: fixed; top: 84px; right: 24px; z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px; max-width: min(420px, calc(100vw - 32px)); padding: 13px 14px 13px 16px; border: 1px solid var(--ui-success-line); border-radius: 12px; background: #fff; box-shadow: 0 14px 34px rgba(27, 36, 32, .14); color: var(--ui-ink); font-size: 13.5px; }
    .users-page .ul-toast > i { color: var(--ui-success); -webkit-text-fill-color: var(--ui-success); font-size: 17px; }
    .users-page .ul-toast button { margin-left: auto; border: 0; background: none; color: var(--ui-muted); font-size: 18px; cursor: pointer; }

    .users-page .ul-alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid var(--ui-info-line); border-radius: 10px; background: var(--ui-info-soft); color: var(--ui-info); font-size: 13px; line-height: 1.5; }
    .users-page .ul-alert--warning { border-color: var(--ui-warning-line); background: var(--ui-warning-soft); color: var(--ui-warning); }
    .users-page .ul-alert--danger { border-color: var(--ui-danger-line); background: var(--ui-danger-soft); color: var(--ui-danger); }
    .users-page .ul-alert ul { margin: 0; padding-left: 16px; }
    .users-page .ul-alert > i { margin-top: 2px; }

    .users-page .ul-card { min-width: 0; overflow: visible; border: 1px solid var(--ui-line); border-radius: 14px; background: var(--ui-surface); box-shadow: 0 1px 2px rgba(27, 36, 32, .04); }

    .users-page .ul-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 16px; padding: 14px 16px; border-bottom: 1px solid var(--ui-line); }
    .users-page .ul-tabs { display: flex; flex-wrap: wrap; gap: 4px; min-width: 0; }
    body .admin-dashboard.admin-role-page .main-area.users-page a.ul-tab { display: inline-flex !important; align-items: center !important; gap: 7px !important; height: 34px !important; padding: 0 12px !important; border-radius: 8px !important; background: transparent !important; color: var(--ui-muted) !important; -webkit-text-fill-color: var(--ui-muted) !important; font-size: 13px !important; font-weight: 600 !important; text-decoration: none !important; white-space: nowrap !important; transition: background-color .15s ease, color .15s ease !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page a.ul-tab:hover { background: var(--ui-surface-2) !important; color: var(--ui-ink) !important; -webkit-text-fill-color: var(--ui-ink) !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page a.ul-tab.is-active { background: var(--ui-primary-soft) !important; color: var(--ui-primary) !important; -webkit-text-fill-color: var(--ui-primary) !important; }
    .users-page .ul-tab__count { display: inline-grid; min-width: 22px; height: 20px; place-items: center; padding: 0 6px; border-radius: 999px; background: var(--ui-line-soft); color: var(--ui-ink-2); -webkit-text-fill-color: var(--ui-ink-2); font-size: 11.5px; font-variant-numeric: tabular-nums; }
    .users-page .ul-tab.is-active .ul-tab__count { background: var(--ui-primary); color: #fff; -webkit-text-fill-color: #fff; }
    .users-page .ul-tab__count.is-alert { background: var(--ui-warning-soft); color: var(--ui-warning); -webkit-text-fill-color: var(--ui-warning); }

    .users-page .ul-search { position: relative; display: flex; align-items: center; flex: 0 1 320px; min-width: 220px; margin: 0; }
    .users-page .ul-search > i { position: absolute; left: 12px; color: var(--ui-subtle); -webkit-text-fill-color: var(--ui-subtle); font-size: 13px; pointer-events: none; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-search input[type="search"] { width: 100% !important; height: 38px !important; padding: 0 36px 0 34px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: 9px !important; background: var(--ui-surface) !important; color: var(--ui-ink) !important; font: inherit !important; font-size: 13.5px !important; box-shadow: none !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-search input[type="search"]:focus { border-color: var(--ui-primary) !important; outline: none !important; box-shadow: 0 0 0 3px var(--ui-primary-soft) !important; }
    .users-page .ul-search input[type="search"]::-webkit-search-cancel-button { display: none; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-search__clear { position: absolute !important; right: 6px !important; display: grid !important; width: 26px !important; height: 26px !important; place-items: center !important; border-radius: 6px !important; color: var(--ui-muted) !important; -webkit-text-fill-color: var(--ui-muted) !important; text-decoration: none !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-search__clear:hover { background: var(--ui-surface-2) !important; }

    .users-page .ul-table-wrap { overflow-x: auto; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-table { width: 100% !important; min-width: 760px !important; margin: 0 !important; border-collapse: collapse !important; table-layout: auto !important; background: transparent !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-table thead th { position: static !important; height: auto !important; padding: 10px 16px !important; border: 0 !important; border-bottom: 1px solid var(--ui-line) !important; background: var(--ui-surface-2) !important; color: var(--ui-muted) !important; -webkit-text-fill-color: var(--ui-muted) !important; font-size: 11.5px !important; font-weight: 600 !important; letter-spacing: .04em !important; text-align: left !important; text-transform: uppercase !important; white-space: nowrap !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td { width: auto !important; max-width: none !important; height: auto !important; min-height: 0 !important; padding: 12px 16px !important; border: 0 !important; border-bottom: 1px solid var(--ui-line-soft) !important; background: transparent !important; color: var(--ui-ink) !important; font-size: 13px !important; vertical-align: middle !important; text-align: left !important; white-space: normal !important; overflow: visible !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody tr:last-child td { border-bottom: 0 !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody tr:hover td { background: var(--ui-surface-2) !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td.ul-cell-actions { width: 56px !important; text-align: right !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td::before { content: none !important; display: none !important; }

    .users-page .ul-user { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .users-page .ul-avatar { display: grid; flex: 0 0 36px; width: 36px; height: 36px; place-items: center; border-radius: 50%; font-size: 12.5px; font-weight: 700; letter-spacing: .02em; }
    .users-page .ul-avatar--admin { background: #ece9fb; color: #5b45c4; -webkit-text-fill-color: #5b45c4; }
    .users-page .ul-avatar--staff { background: var(--ui-success-soft); color: var(--ui-success); -webkit-text-fill-color: var(--ui-success); }
    .users-page .ul-avatar--office { background: var(--ui-info-soft); color: var(--ui-info); -webkit-text-fill-color: var(--ui-info); }
    .users-page .ul-avatar--bidder { background: var(--ui-warning-soft); color: var(--ui-warning); -webkit-text-fill-color: var(--ui-warning); }
    .users-page .ul-user__text { display: grid; gap: 2px; min-width: 0; }
    .users-page .ul-user__name { display: flex; align-items: center; gap: 6px; color: var(--ui-ink); -webkit-text-fill-color: var(--ui-ink); font-size: 13.5px; font-weight: 600; }
    .users-page .ul-user__email { overflow: hidden; color: var(--ui-muted); -webkit-text-fill-color: var(--ui-muted); font-size: 12.5px; text-overflow: ellipsis; white-space: nowrap; }
    .users-page .ul-new { padding: 1px 6px; border-radius: 999px; background: var(--ui-danger-soft); color: var(--ui-danger); -webkit-text-fill-color: var(--ui-danger); font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .users-page .ul-dot { color: var(--ui-subtle); -webkit-text-fill-color: var(--ui-subtle); }
    .users-page .ul-mono { font-family: var(--ui-mono); font-size: 11.5px; }

    .users-page .ul-role { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .users-page .ul-role i { font-size: 10.5px; color: inherit; -webkit-text-fill-color: currentColor; }
    .users-page .ul-role--admin { background: #ece9fb; color: #5b45c4; -webkit-text-fill-color: #5b45c4; }
    .users-page .ul-role--staff { background: var(--ui-success-soft); color: var(--ui-success); -webkit-text-fill-color: var(--ui-success); }
    .users-page .ul-role--office { background: var(--ui-info-soft); color: var(--ui-info); -webkit-text-fill-color: var(--ui-info); }
    .users-page .ul-role--bidder { background: var(--ui-warning-soft); color: var(--ui-warning); -webkit-text-fill-color: var(--ui-warning); }
    .users-page .ul-meta { display: block; margin-top: 4px; color: var(--ui-muted); -webkit-text-fill-color: var(--ui-muted); font-size: 12px; line-height: 1.4; }
    .users-page .ul-meta--sanction { max-width: 260px; color: var(--ui-danger); -webkit-text-fill-color: var(--ui-danger); }
    .users-page .ul-date { color: var(--ui-ink-2); -webkit-text-fill-color: var(--ui-ink-2); white-space: nowrap; }

    .users-page .ul-status { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px 3px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
    .users-page .ul-status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .users-page .ul-status--success { background: var(--ui-success-soft); color: var(--ui-success); -webkit-text-fill-color: var(--ui-success); }
    .users-page .ul-status--neutral { background: var(--ui-line-soft); color: var(--ui-ink-2); -webkit-text-fill-color: var(--ui-ink-2); }
    .users-page .ul-status--warning { background: var(--ui-warning-soft); color: var(--ui-warning); -webkit-text-fill-color: var(--ui-warning); }
    .users-page .ul-status--danger { background: var(--ui-danger-soft); color: var(--ui-danger); -webkit-text-fill-color: var(--ui-danger); }

    .users-page .ul-empty { display: grid; justify-items: center; gap: 8px; padding: 40px 16px; text-align: center; }
    .users-page .ul-empty__icon { display: grid; width: 48px; height: 48px; place-items: center; border-radius: 12px; background: var(--ui-surface-2); color: var(--ui-subtle); -webkit-text-fill-color: var(--ui-subtle); font-size: 20px; }
    .users-page .ul-empty strong { color: var(--ui-ink); -webkit-text-fill-color: var(--ui-ink); font-size: 14px; }
    .users-page .ul-empty a { color: var(--ui-primary); -webkit-text-fill-color: var(--ui-primary); font-weight: 600; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody tr.ul-empty-row:hover td { background: transparent !important; }

    .users-page .ul-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--ui-line); color: var(--ui-muted); -webkit-text-fill-color: var(--ui-muted); font-size: 13px; }
    .users-page .ul-pager__links { display: inline-flex; align-items: center; gap: 8px; }
    .users-page .ul-pager__page { color: var(--ui-ink-2); -webkit-text-fill-color: var(--ui-ink-2); font-weight: 600; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-pager__btn { display: inline-flex !important; align-items: center !important; gap: 6px !important; height: 34px !important; padding: 0 12px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: 8px !important; background: var(--ui-surface) !important; color: var(--ui-ink) !important; -webkit-text-fill-color: var(--ui-ink) !important; font-size: 13px !important; font-weight: 600 !important; text-decoration: none !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-pager__btn:hover { border-color: var(--ui-primary) !important; }
    body .admin-dashboard.admin-role-page .main-area.users-page .ul-pager__btn.is-disabled { opacity: .45 !important; pointer-events: none !important; }
    .users-page .ul-pager__btn i { font-size: 10px; color: inherit; -webkit-text-fill-color: currentColor; }

    .users-page .ul-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    /* Phones and tablets: each user is a card. */
    @media (max-width: 900px) {
        .users-page .ul-search { flex: 1 1 100%; min-width: 0; }
        .users-page .ul-tabs { flex: 1 1 100%; min-width: 0; max-width: 100%; flex-wrap: nowrap; overflow-x: auto; margin: 0 -4px; padding: 0 4px 2px; scrollbar-width: none; }
        .users-page .ul-tabs::-webkit-scrollbar { display: none; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table { min-width: 0 !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table thead { display: none !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table, body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody { display: block !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody tr { display: grid !important; grid-template-columns: minmax(0, 1fr) auto !important; gap: 8px 12px !important; padding: 14px 16px !important; border-bottom: 1px solid var(--ui-line-soft) !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody tr:last-child { border-bottom: 0 !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td { display: block !important; min-height: 0 !important; height: auto !important; padding: 0 !important; border: 0 !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody tr:hover td { background: transparent !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td.ul-cell-user { grid-column: 1 !important; grid-row: 1 !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td.ul-cell-actions { grid-column: 2 !important; grid-row: 1 !important; width: auto !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td.ul-cell-role,
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td.ul-cell-status,
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td.ul-cell-date { grid-column: 1 / -1 !important; padding-left: 48px !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td.ul-cell-date .ul-date { display: none !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody td.ul-cell-date .ul-meta { margin-top: 0 !important; }
        body .admin-dashboard.admin-role-page .main-area.users-page .ul-table tbody tr.ul-empty-row { display: block !important; }
        .users-page .ul-toast { top: 72px; right: 16px; left: 16px; min-width: 0; }
    }
</style>

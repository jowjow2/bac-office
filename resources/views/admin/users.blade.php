<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page">
    @php
        $staffOffices = \App\Models\User::staffOfficeOptions();
        $endUserOffices = \App\Models\User::assignableEndUserOffices();
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
        <x-page-header title="Manage users" subtitle="Approve bidder registrations and maintain account access" />

        <main class="dashboard-content">
            <div class="welcome-text" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">

                <button type="button" onclick="openCreateUserModal()" style="background: #1d4f91; color: white; padding: 11px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 600;">
                    <i class="fas fa-plus" style="margin-right: 6px;"></i> Add User
                </button>
            </div>

            @if(session('success'))
                <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                    <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                    <span>{{ session('success') }}</span>
                    <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
                </div>
            @endif

            @if(session('warning'))
                <div class="error-alert" style="margin-bottom: 20px; background: #fff7ed; border-color: #fdba74; color: #9a3412;">
                    {{ session('warning') }}
                </div>
            @endif

            @if(!($bidderApprovalAvailable ?? false))
                <div class="error-alert" style="margin-bottom: 20px; background: #e8f1ec; border-color: #9fcfb9; color: #1d4f40;">
                    Bidder review and approval actions are temporarily unavailable because the bidder approval table is not present in the current database.
                </div>
            @endif

            @if($errors->any() && !old('editing_user_id'))
                <div class="error-alert" style="margin-bottom: 20px;">
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="margin: 20px 0 14px;">
                <div style="display: inline-flex; gap: 4px; padding: 4px; background: #eef2f7; border-radius: 12px; flex-wrap: wrap;">
                    <a href="{{ route('admin.users', ['filter' => 'all', 'search' => $search !== '' ? $search : null]) }}"
                       class="users-filter-tab {{ ($filter ?? 'all') === 'all' ? 'is-active' : '' }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none;">
                        All Users
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'admin', 'search' => $search !== '' ? $search : null]) }}"
                       class="users-filter-tab {{ ($filter ?? 'all') === 'admin' ? 'is-active' : '' }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none;">
                        Admin
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'staff', 'search' => $search !== '' ? $search : null]) }}"
                       class="users-filter-tab {{ ($filter ?? 'all') === 'staff' ? 'is-active' : '' }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none;">
                        Staff
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'end_user', 'search' => $search !== '' ? $search : null]) }}"
                       class="users-filter-tab {{ ($filter ?? 'all') === 'end_user' ? 'is-active' : '' }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none;">
                        End-user offices
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'bidder', 'search' => $search !== '' ? $search : null]) }}"
                       class="users-filter-tab {{ ($filter ?? 'all') === 'bidder' ? 'is-active' : '' }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none;">
                        Bidders
                    </a>
                    <a href="{{ route('admin.users', ['filter' => 'pending', 'search' => $search !== '' ? $search : null]) }}"
                       class="users-filter-tab {{ ($filter ?? 'all') === 'pending' ? 'is-active' : '' }}"
                       style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none;">
                        Pending
                    </a>
                    @if($bidderSanctionsAvailable ?? false)
                        <a href="{{ route('admin.users', ['filter' => 'suspended', 'search' => $search !== '' ? $search : null]) }}"
                           class="users-filter-tab {{ ($filter ?? 'all') === 'suspended' ? 'is-active' : '' }}"
                           style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none;">
                            Suspended {{ ($statusCounts['suspended'] ?? 0) > 0 ? '(' . $statusCounts['suspended'] . ')' : '' }}
                        </a>
                        <a href="{{ route('admin.users', ['filter' => 'blacklisted', 'search' => $search !== '' ? $search : null]) }}"
                           class="users-filter-tab {{ ($filter ?? 'all') === 'blacklisted' ? 'is-active' : '' }}"
                           style="padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none;">
                            Blacklisted {{ ($statusCounts['blacklisted'] ?? 0) > 0 ? '(' . $statusCounts['blacklisted'] . ')' : '' }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="table-container user-table-card" style="background: white; border-radius: 16px; box-shadow: 0 10px 24px rgba(27, 36, 32,0.06); overflow: hidden; border: 1px solid #e9eef5;">
                <form method="GET" action="{{ route('admin.users') }}" style="padding: 18px 20px; border-bottom: 1px solid #edf2f7; background: #ffffff;">
                    <input type="hidden" name="filter" value="{{ $filter ?? 'all' }}">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <div class="admin-search-field" style="flex: 1 1 auto; min-width: 0;">
                            <svg class="admin-search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                            <input
                                type="text"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Search users..."
                                class="user-search-input"
                            >
                        </div>
                        @if($search !== '')
                            <a href="{{ route('admin.users', ['filter' => ($filter ?? 'all') !== 'all' ? $filter : null]) }}" class="btn-secondary">Clear</a>
                        @endif
                    </div>
                </form>

                <div class="users-table-wrap" style="overflow-x:auto; padding: 0 0 20px;">
                {{-- min-width lives in CSS (see #users-table-responsive) so the mobile
                     card layout can drop it; as an inline style it can't be overridden. --}}
                <table class="users-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Name</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Email</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Role</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Office</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Status</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Company</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Registration No.</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Created</th>
                            <th style="text-align: left; padding: 14px 12px; font-size: 12px; color: #6b7280; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Actions</th>
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
                                $activeSanction = in_array($procurementStatus, ['suspended', 'blacklisted'], true)
                                    ? $user->bidderProfile?->activeSanction
                                    : null;
                                $isNewUser = ($bidderApprovalAvailable ?? false) && $user->role === 'bidder' && $user->status === 'pending' && ($user->bidderProfile?->review_status ?: 'new') === 'new';
                            @endphp
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 12px; font-size: 14px; font-weight: 600; color: #1b2420;">
                                    <div class="user-name-line">
                                        <span>{{ $user->name }}</span>
                                        @if($isNewUser)
                                            <span class="user-new-badge" title="Registered within the last 3 days">NEW</span>
                                        @endif
                                    </div>
                                    @if($user->username)
                                        <div style="margin-top: 4px; font-size: 12px; font-weight: 500; color: #6b736e;">{{ '@' . $user->username }}</div>
                                    @endif
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #6b7280;">{{ $user->email }}</td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 500;
                                        @if($user->role === 'admin') background: #ede9fe; color: #7c3aed;
                                        @elseif($user->role === 'staff') background: #dcfce7; color: #15803d;
                                        @else background: #fef3c7; color: #b45309; @endif">
                                        {{ $user->role === 'end_user' ? 'End-user office' : ucfirst($user->role) }}
                                    </span>
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #1b2420;">
                                    {{ in_array($user->role, ['staff', 'end_user'], true) ? ($user->office ?: 'Unassigned') : 'N/A' }}
                                </td>
                                <td style="padding: 12px;">
                                    <span data-user-status="{{ $user->id }}" style="padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 500;
                                        @if(in_array($displayStatus, ['active', 'approved'], true)) background: #dcfce7; color: #166534;
                                        @elseif($displayStatus === 'pending') background: #e5e7eb; color: #374151;
                                        @elseif($displayStatus === 'suspended') background: #ffedd5; color: #c2410c;
                                        @elseif($displayStatus === 'blacklisted') background: #fee2e2; color: #991b1b;
                                        @else background: #fee2e2; color: #991b1b; @endif">
                                        {{ $displayStatusLabel }}
                                    </span>
                                    @if($activeSanction)
                                        <div style="margin-top: 5px; font-size: 11px; color: #6b736e; line-height: 1.35;">
                                            Procurement access: {{ $activeSanction->status_label }} &middot; Ref {{ $activeSanction->reference_number }} &middot; Effective {{ $activeSanction->effective_date?->format('M d, Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #1b2420;">{{ $user->company ?: 'N/A' }}</td>
                                <td style="padding: 12px; font-size: 13px; color: #1b2420;">{{ $user->registration_no ?: 'N/A' }}</td>
                                <td style="padding: 12px; font-size: 13px; color: #6b7280;">{{ $user->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                                <td style="padding: 12px;" class="user-actions-cell">
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
                            <tr>
                                <td colspan="9" style="padding: 40px; text-align: center; color: #9ca3af;">
                                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                    No users matched your search.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @if($users->hasPages())
                    {{-- 25 accounts a page; search and the tab filter carry over. --}}
                    <nav class="users-pager" aria-label="User pages">
                        <span>Showing {{ $users->firstItem() }}&ndash;{{ $users->lastItem() }} of {{ $users->total() }}</span>
                        <span class="users-pager__links">
                            @if($users->onFirstPage())
                                <span class="users-pager__btn is-disabled" aria-disabled="true">Previous</span>
                            @else
                                <a class="users-pager__btn" href="{{ $users->previousPageUrl() }}" rel="prev">Previous</a>
                            @endif
                            <span class="users-pager__page">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>
                            @if($users->hasMorePages())
                                <a class="users-pager__btn" href="{{ $users->nextPageUrl() }}" rel="next">Next</a>
                            @else
                                <span class="users-pager__btn is-disabled" aria-disabled="true">Next</span>
                            @endif
                        </span>
                    </nav>
                    <style>
                        body .admin-dashboard .users-page .users-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 20px; border-top: 1px solid #ece7dc; color: #6b736e; font-size: 13px; }
                        body .admin-dashboard .users-page .users-pager__links { display: inline-flex; align-items: center; gap: 8px; }
                        body .admin-dashboard .users-page .users-pager__page { color: #3c4641; font-weight: 600; }
                        body .admin-dashboard .users-page .users-pager__btn { display: inline-flex; align-items: center; min-height: 34px; padding: 0 14px; border: 1px solid #d9d4c7; border-radius: 9px; background: #fff; color: #1b2420 !important; -webkit-text-fill-color: #1b2420 !important; font-weight: 600; text-decoration: none; }
                        body .admin-dashboard .users-page .users-pager__btn:hover { border-color: #1d4f40; }
                        body .admin-dashboard .users-page .users-pager__btn.is-disabled { opacity: .45; pointer-events: none; }
                    </style>
                @endif
            </div>
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

                @include('admin.partials.user-role-picker', ['prefix' => 'create', 'selected' => old('role', 'bidder')])

                <div id="createOfficeField" class="um-field">
                    <label for="create_office" id="createOfficeLabel" class="um-label">Office</label>
                    <select name="office" id="create_office" class="form-select">
                        <option value="">Select office</option>
                        @foreach($staffOffices as $office)
                            <option value="{{ $office }}" data-office-role="staff" {{ old('office') === $office ? 'selected' : '' }}>{{ $office }}</option>
                        @endforeach
                        @foreach($endUserOffices as $office)
                            <option value="{{ $office }}" data-office-role="end_user" {{ old('office') === $office ? 'selected' : '' }}>{{ $office }}</option>
                        @endforeach
                    </select>
                    <span class="um-hint" data-office-hint></span>
                </div>

                <div id="createBidderFields" class="um-grid" aria-hidden="{{ old('role', 'bidder') === 'bidder' ? 'false' : 'true' }}" @if(old('role', 'bidder') !== 'bidder') hidden @endif>
                    <div class="um-field">
                        <label for="create_company" class="um-label">Company</label>
                        <input type="text" name="company" id="create_company" value="{{ old('company') }}" class="form-input">
                    </div>
                    <div class="um-field">
                        <label for="create_registration_no" class="um-label">Registration No.</label>
                        <input type="text" name="registration_no" id="create_registration_no" value="{{ old('registration_no') }}" class="form-input">
                    </div>
                </div>

                <h3 class="um-section">Sign-in details</h3>
                <div class="um-field">
                    <label for="create_name" class="um-label">Name <span class="um-req">*</span></label>
                    <input type="text" name="name" id="create_name" value="{{ old('name') }}" required class="form-input" autocomplete="off">
                </div>
                <div class="um-grid">
                    <div class="um-field">
                        <label for="create_email" class="um-label">Email <span class="um-req">*</span></label>
                        <input type="email" name="email" id="create_email" value="{{ old('email') }}" required class="form-input" autocomplete="off">
                    </div>
                    <div class="um-field">
                        <label for="create_username" class="um-label">Username <span class="um-opt">(optional)</span></label>
                        <input type="text" name="username" id="create_username" value="{{ old('username') }}" class="form-input" placeholder="Email also works to sign in" autocomplete="off">
                    </div>
                </div>
                <div class="um-grid">
                    <div class="um-field">
                        <label for="create_password" class="um-label">Password <span class="um-req">*</span></label>
                        <div class="um-password">
                            <input type="password" name="password" id="create_password" required minlength="6" class="form-input" autocomplete="new-password">
                            <button type="button" class="um-password__toggle" data-password-toggle aria-label="Show password"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        </div>
                        <span class="um-hint">At least 6 characters.</span>
                    </div>
                    <div class="um-field">
                        <label for="create_status" class="um-label">Account status</label>
                        <select name="status" id="create_status" class="form-select" required>
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="rejected" {{ old('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                </div>
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

                @include('admin.partials.user-role-picker', ['prefix' => 'edit', 'selected' => 'bidder'])

                <div id="editOfficeField" class="um-field" style="display: none;">
                    <label for="edit_office" id="editOfficeLabel" class="um-label">Office</label>
                    <select name="office" id="edit_office" class="form-select">
                        <option value="">Select office</option>
                        @foreach($staffOffices as $office)
                            <option value="{{ $office }}" data-office-role="staff">{{ $office }}</option>
                        @endforeach
                        @foreach($endUserOffices as $office)
                            <option value="{{ $office }}" data-office-role="end_user">{{ $office }}</option>
                        @endforeach
                    </select>
                    <span class="um-hint" data-office-hint></span>
                </div>

                <div id="editBidderFields" class="um-grid">
                    <div class="um-field">
                        <label for="edit_company" class="um-label">Company</label>
                        <input type="text" name="company" id="edit_company" class="form-input">
                    </div>
                    <div class="um-field">
                        <label for="edit_registration_no" class="um-label">Registration No.</label>
                        <input type="text" name="registration_no" id="edit_registration_no" class="form-input">
                    </div>
                </div>

                <h3 class="um-section">Sign-in details</h3>
                <div class="um-field">
                    <label for="edit_name" class="um-label">Name <span class="um-req">*</span></label>
                    <input type="text" name="name" id="edit_name" required class="form-input" autocomplete="off">
                </div>
                <div class="um-grid">
                    <div class="um-field">
                        <label for="edit_email" class="um-label">Email <span class="um-req">*</span></label>
                        <input type="email" name="email" id="edit_email" required class="form-input" autocomplete="off">
                    </div>
                    <div class="um-field">
                        <label for="edit_username" class="um-label">Username <span class="um-opt">(optional)</span></label>
                        <input type="text" name="username" id="edit_username" class="form-input" placeholder="Email also works to sign in" autocomplete="off">
                    </div>
                </div>
                <div class="um-grid">
                    <div class="um-field">
                        <label for="edit_password" class="um-label">New password <span class="um-opt">(optional)</span></label>
                        <div class="um-password">
                            <input type="password" name="password" id="edit_password" minlength="6" class="form-input" placeholder="Leave blank to keep it" autocomplete="new-password">
                            <button type="button" class="um-password__toggle" data-password-toggle aria-label="Show password"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="um-field">
                        <label for="edit_status" class="um-label">Account status</label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
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

        if (status === 'approved' || status === 'active') {
            badge.style.background = '#dcfce7';
            badge.style.color = '#166534';
        } else if (status === 'pending') {
            badge.style.background = '#e5e7eb';
            badge.style.color = '#374151';
        } else if (status === 'suspended') {
            badge.style.background = '#ffedd5';
            badge.style.color = '#c2410c';
        } else {
            badge.style.background = '#fee2e2';
            badge.style.color = '#991b1b';
        }
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
                    registration: @json(old('registration_no', ''))
                });
            @else
                openCreateUserModal();
            @endif
        @elseif(request('create') === 'end_user')
            // Linked from the purchase request queue: add an end-user office account.
            setUserRole('create', 'end_user');
            openCreateUserModal();
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

    // Office list for staff and end-user offices, company details for bidders.
    function toggleRoleFields(prefix) {
        const role = userRole(prefix);
        const field = document.getElementById(prefix + 'OfficeField');
        const officeSelect = document.getElementById(prefix + '_office');
        const officeLabel = document.getElementById(prefix + 'OfficeLabel');
        const bidderFields = document.getElementById(prefix + 'BidderFields');
        const needsOffice = role === 'staff' || role === 'end_user';
        const isBidder = role === 'bidder';

        if (field && officeSelect) {
            field.style.display = needsOffice ? '' : 'none';
            officeSelect.required = needsOffice;
            officeSelect.disabled = !needsOffice;

            Array.from(officeSelect.options).forEach(function (option) {
                if (!option.dataset.officeRole) return;
                const matches = option.dataset.officeRole === role;
                option.hidden = !matches;
                option.disabled = !matches;
                if (!matches && option.selected) officeSelect.value = '';
            });

            const hint = field.querySelector('[data-office-hint]');
            if (hint) {
                hint.textContent = role === 'end_user'
                    ? 'Must match the end-user office of its projects, so it can file requests and record site inspections.'
                    : (role === 'staff' ? 'The BAC office this staff member works in.' : '');
            }
        }

        if (officeLabel) {
            officeLabel.innerHTML = (role === 'end_user' ? 'Assigned LGU office' : 'Office') + ' <span class="um-req">*</span>';
        }

        if (bidderFields) {
            bidderFields.hidden = !isBidder;
            bidderFields.setAttribute('aria-hidden', isBidder ? 'false' : 'true');
            bidderFields.querySelectorAll('input, select, textarea').forEach(function (control) {
                control.disabled = !isBidder;
            });
        }
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

{{-- Must stay last: several earlier blocks set `min-width: 980px !important` on
     .users-table with this same selector, so only a later rule can lift it. --}}
<style id="users-table-responsive">
    @media (max-width: 900px) {
        body .admin-dashboard.admin-role-page .main-area.users-page .users-table {
            min-width: 0 !important;
            width: 100% !important;
            table-layout: auto !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table :is(th, td),
        body .admin-dashboard.admin-role-page .main-area.users-page .users-table :is(th, td):nth-child(n) {
            width: auto !important;
            max-width: none !important;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            overflow-wrap: anywhere !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table-wrap {
            padding: 0 !important;
            overflow-x: visible !important;
        }
    }
</style>

<style id="users-table-mobile-gutters">
    /* Below 900px the rows render as stacked cards, but dashboard-content,
       users-table-wrap, tbody and td each still applied their own padding.
       That stack ate 121px of a 390px viewport and left ~269px for content,
       so emails broke mid-word. Collapse the inner layers and let the row
       supply the single gutter. */
    @media (max-width: 900px) {

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table-wrap,
        body .admin-dashboard.admin-role-page .main-area.users-page .table-container .users-table-wrap {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody {
            padding: 0 !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody tr {
            padding: 12px !important;
        }

        body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody td,
        body .admin-dashboard.admin-role-page .main-area.users-page .users-table tbody td:nth-child(n) {
            padding: 9px 0 !important;
        }
    }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard dashboard-home bidder-company-page">
    @vite(['resources/css/dashboard.css'])

    <style>
        .bidder-company-page {
            font-family: 'Inter', sans-serif;
        }

        .bidder-sidebar-badge {
            margin-left: auto;
        }

        .bidder-company-page .page-intro {
            margin-bottom: 22px;
        }

        .bidder-company-page .page-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-company-page .page-subtitle {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }

        .bidder-company-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 18px;
        }

        .bidder-panel {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .bidder-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-panel-header h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-header-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 70px;
            height: 36px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        .bidder-panel-body {
            padding: 20px;
        }

        .bidder-company-summary {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 18px;
            margin-bottom: 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-company-avatar {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            background: #fef3c7;
            color: #d97706;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .bidder-company-summary h3 {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .bidder-company-summary p {
            margin: 0 0 8px;
            font-size: 12px;
            color: #64748b;
        }

        .bidder-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 12px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 600;
        }

        .bidder-profile-grid {
            display: grid;
            gap: 16px;
        }

        .bidder-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .bidder-field label {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #64748b;
        }

        .bidder-input,
        .bidder-readonly {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            color: #111827;
            font-size: 14px;
            padding: 12px 14px;
        }

        .bidder-readonly {
            color: #0f172a;
        }

        .bidder-doc-list {
            display: grid;
        }

        .bidder-doc-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #eef2f7;
        }

        .bidder-doc-item:last-child {
            border-bottom: 0;
            padding-bottom: 18px;
        }

        .bidder-doc-item h3 {
            margin: 0 0 4px;
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
        }

        .bidder-doc-item p {
            margin: 0;
            font-size: 12px;
            color: #94a3b8;
        }

        .bidder-doc-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #dcfce7;
            color: #166534;
        }

        .bidder-doc-badge.missing {
            background: #e5e7eb;
            color: #4b5563;
        }

        .bidder-upload-doc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 40px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .bidder-upload-doc-input {
            display: none;
        }

        .bidder-upload-doc-name {
            margin-top: 10px;
            font-size: 12px;
            color: #64748b;
        }

        .bidder-alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 12px;
        }

        .bidder-alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .bidder-alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .bidder-modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.45);
            z-index: 2000;
        }

        .bidder-modal-overlay.show {
            display: flex;
        }

        .bidder-modal {
            width: min(100%, 560px);
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
            overflow: hidden;
        }

        .bidder-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 22px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bidder-modal-title {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
        }

        .bidder-modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 10px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 16px;
            cursor: pointer;
        }

        .bidder-modal-body {
            padding: 20px 22px;
        }

        .bidder-modal-form {
            display: grid;
            gap: 16px;
        }

        .bidder-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 0 22px 22px;
        }

        .bidder-cancel-btn,
        .bidder-save-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 14px;
            cursor: pointer;
        }

        .bidder-cancel-btn {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            font-weight: 500;
        }

        .bidder-save-btn {
            border: 1px solid #1d4ed8;
            background: #1d4ed8;
            color: #fff;
            font-weight: 600;
        }

        @media (max-width: 1100px) {
            .bidder-company-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $companyLabel = $user->company ?: ($user->name ?? 'Company');
        $companyInitial = strtoupper(substr($companyLabel, 0, 1));
        $documents = [
            'PhilGEPS Certificate',
            'DTI/SEC Registration',
            'Business Permit',
            'Audited Financial Statement',
            'PCAB License',
        ];
        $selectedDocumentType = old('document_type', $documents[0]);
    @endphp

        @include('partials.bidder-sidebar')

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Company Profile</h2>
                <p>Your registered company information</p>
            </div>

            <div class="nav-right">
                <div class="nav-date-chip"><span id="realtimeDate">{{ now()->format('M d, Y h:i A') }}</span></div>
                <a href="{{ route('bidder.notifications') }}" class="notification-button" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    @if(($bidderNotificationCount ?? 0) > 0)
                        <span class="notification-badge">{{ $bidderNotificationCount }}</span>
                    @endif
                </a>
            </div>
        </header>

        <main class="dashboard-content dashboard-home-content">


            @if(session('success'))
                <div class="bidder-alert bidder-alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="bidder-alert bidder-alert-error">{{ $errors->first() }}</div>
            @endif

            <section class="bidder-company-grid">
                <section class="bidder-panel">
                    <div class="bidder-panel-header">
                        <h2>Company Information</h2>
                        <button type="button" class="bidder-header-action" id="openCompanyEditModal">Edit</button>
                    </div>

                    <div class="bidder-panel-body">
                        <div class="bidder-company-summary">
                            <div class="bidder-company-avatar">{{ $companyInitial }}</div>
                            <div>
                                <h3>{{ $companyLabel }}</h3>
                                <p>{{ $user->email }}</p>
                                <span class="bidder-status-badge">{{ $user->status === 'active' ? 'Active Bidder' : ucfirst($user->status ?? 'Pending') }}</span>
                            </div>
                        </div>

                        <div class="bidder-profile-grid">
                            <div class="bidder-field">
                                <label>Company Name</label>
                                <div class="bidder-readonly">{{ $user->company ?: 'Not provided' }}</div>
                            </div>
                            <div class="bidder-field">
                                <label>Email Address</label>
                                <div class="bidder-readonly">{{ $user->email }}</div>
                            </div>
                            <div class="bidder-field">
                                <label>Registration Number</label>
                                <div class="bidder-readonly">{{ $user->registration_no ?: 'Not provided' }}</div>
                            </div>
                            <div class="bidder-field">
                                <label>Contact Number</label>
                                <div class="bidder-readonly">Not provided</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bidder-panel">
                    <div class="bidder-panel-header">
                        <h2>Documents & Eligibility</h2>
                    </div>

                    <div class="bidder-panel-body">
                        <div class="bidder-doc-list">
                            @foreach($documents as $document)
                                @php $uploadedDocument = $bidderDocuments[$document] ?? null; @endphp
                                <div class="bidder-doc-item">
                                    <div>
                                        <h3>{{ $document }}</h3>
                                        <p>
                                            @if($uploadedDocument)
                                                Uploaded {{ optional($uploadedDocument->uploaded_at)->format('Y-m-d') ?? optional($uploadedDocument->created_at)->format('Y-m-d') }} � {{ $uploadedDocument->original_name }}
                                            @else
                                                Not uploaded yet
                                            @endif
                                        </p>
                                    </div>
                                    <span class="bidder-doc-badge {{ $uploadedDocument ? '' : 'missing' }}">{{ $uploadedDocument ? ucfirst($uploadedDocument->status) : 'Not Uploaded' }}</span>
                                </div>
                            @endforeach
                        </div>

                        <form action="{{ route('bidder.documents.store') }}" method="POST" enctype="multipart/form-data" id="bidderDocumentUploadForm">
                            @csrf
                            <div class="bidder-field" style="margin-bottom: 12px;">
                                <label>Document Type</label>
                                <select name="document_type" id="bidderDocumentType" class="bidder-input">
                                    @foreach($documents as $document)
                                        <option value="{{ $document }}" @selected($selectedDocumentType === $document)>{{ $document }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <input type="file" name="document_file" id="bidderDocumentUpload" class="bidder-upload-doc-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <label for="bidderDocumentUpload" class="bidder-upload-doc-btn">Upload New Document</label>
                            <div class="bidder-upload-doc-name" id="bidderDocumentName">No file selected</div>
                        </form>
                    </div>
                </section>
            </section>
        </main>
    </div>

    <div class="bidder-modal-overlay" id="companyEditModal">
        <div class="bidder-modal">
            <div class="bidder-modal-header">
                <h2 class="bidder-modal-title">Edit Company Profile</h2>
                <button type="button" class="bidder-modal-close" id="closeCompanyEditModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="bidder-modal-body">
                <form method="POST" action="{{ route('bidder.profile.update') }}" class="bidder-modal-form" id="companyProfileEditForm">
                    @csrf
                    @method('PATCH')
                    <div class="bidder-field">
                        <label>Company Name</label>
                        <input type="text" name="company" class="bidder-input" value="{{ old('company', $user->company) }}">
                    </div>
                    <div class="bidder-field">
                        <label>Email Address</label>
                        <input type="email" class="bidder-input" value="{{ $user->email }}" disabled>
                    </div>
                    <div class="bidder-field">
                        <label>Registration Number</label>
                        <input type="text" name="registration_no" class="bidder-input" value="{{ old('registration_no', $user->registration_no) }}">
                    </div>
                    <div class="bidder-field">
                        <label>Contact Number</label>
                        <input type="text" class="bidder-input" value="Not provided" disabled>
                    </div>
                </form>
            </div>

            <div class="bidder-modal-footer">
                <button type="button" class="bidder-cancel-btn" id="cancelCompanyEditModal">Cancel</button>
                <button type="submit" form="companyProfileEditForm" class="bidder-save-btn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const realtimeDate = document.getElementById('realtimeDate');
        const bidderDocumentUpload = document.getElementById('bidderDocumentUpload');
        const bidderDocumentName = document.getElementById('bidderDocumentName');
        const bidderDocumentUploadForm = document.getElementById('bidderDocumentUploadForm');
        const companyEditModal = document.getElementById('companyEditModal');
        const openCompanyEditModal = document.getElementById('openCompanyEditModal');
        const closeCompanyEditModal = document.getElementById('closeCompanyEditModal');
        const cancelCompanyEditModal = document.getElementById('cancelCompanyEditModal');

        if (realtimeDate) {
            function updateRealtimeDate() {
                realtimeDate.textContent = new Date().toLocaleString('en-PH', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }

            updateRealtimeDate();
            setInterval(updateRealtimeDate, 1000);
        }

        if (bidderDocumentUpload && bidderDocumentName) {
            bidderDocumentUpload.addEventListener('change', function () {
                bidderDocumentName.textContent = bidderDocumentUpload.files.length
                    ? bidderDocumentUpload.files[0].name
                    : 'No file selected';

                if (bidderDocumentUpload.files.length && bidderDocumentUploadForm) {
                    bidderDocumentUploadForm.submit();
                }
            });
        }

        if (companyEditModal && openCompanyEditModal) {
            openCompanyEditModal.addEventListener('click', function () {
                companyEditModal.classList.add('show');
            });

            [closeCompanyEditModal, cancelCompanyEditModal].forEach(function (button) {
                if (!button) return;
                button.addEventListener('click', function () {
                    companyEditModal.classList.remove('show');
                });
            });

            companyEditModal.addEventListener('click', function (event) {
                if (event.target === companyEditModal) {
                    companyEditModal.classList.remove('show');
                }
            });
        }
    })();
</script>

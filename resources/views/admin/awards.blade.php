<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    <style>
        .admin-award-certificate-cell {
            min-width: 260px;
        }

        .admin-award-certificate {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-award-certificate-qr {
            display: inline-flex;
            width: 112px;
            height: 112px;
            padding: 8px;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            background: #fff;
            flex: 0 0 auto;
        }

        .admin-award-certificate-qr img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .admin-award-certificate-meta {
            min-width: 0;
        }

        .admin-award-certificate-meta strong {
            display: block;
            margin-bottom: 4px;
            color: #111827;
            font-size: 11px;
            white-space: nowrap;
        }

        .admin-award-certificate-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
    </style>

    @include('partials.admin-sidebar')

    <div class="main-area">
        <header class="navbar">
            <div class="nav-left">
                <h2>Awards & Contracts</h2>
                <p>View all awarded projects and contracts</p>
            </div>
        </header>

        <main class="dashboard-content">
            <div class="welcome-text">

            </div>

            @if(session('success'))
            <div id="successAlert" style="position: fixed; top: 90px; right: 25px; background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <span>{{ session('success') }}</span>
                <button onclick="closeSuccessAlert()" style="margin-left: auto; background: none; border: none; color: #166534; cursor: pointer; font-size: 16px;">&times;</button>
            </div>
            @endif

            @if(session('error'))
            <div id="errorAlert" style="position: fixed; top: 90px; right: 25px; background: #fee2e2; color: #991b1b; padding: 16px 20px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 10px; min-width: 280px;">
                <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
                <span>{{ session('error') }}</span>
                <button onclick="closeErrorAlert()" style="margin-left: auto; background: none; border: none; color: #991b1b; cursor: pointer; font-size: 16px;">&times;</button>
            </div>
            @endif

            @if($errors->any())
            <div id="validationAlert" style="margin: 16px 0; background: #fef2f2; color: #991b1b; padding: 14px 16px; border-radius: 8px; font-size: 13px; border: 1px solid #fecaca;">
                <strong style="display: block; margin-bottom: 6px;">Please fix the following:</strong>
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="actions" style="margin: 20px 0;">
                <a href="{{ route('admin.projects') }}" style="background: #6b7280; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Projects
                </a>
            </div>

            <div class="table-container" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Project</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Bidder</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Contract Amount</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Contract Date</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Status</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Certificate QR</th>
                            <th style="text-align: left; padding: 12px; font-size: 12px; color: #64748b; font-weight: 600;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($awards as $award)
                        @php
                            $showCertificate = $award->hasCertificateFile() && filled($award->qr_token);
                        @endphp
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 12px; font-size: 13px; font-weight: 500;">{{ $award->project->title }}</td>
                            <td style="padding: 12px; font-size: 13px;">{{ $award->bidder?->company ?: ($award->bidder?->name ?? $award->bid?->user?->name ?? 'N/A') }}</td>
                            <td style="padding: 12px; font-size: 13px;">&#8369;{{ number_format((float) $award->contract_amount, 2) }}</td>
                            <td style="padding: 12px; font-size: 13px;">{{ $award->contract_date?->format('M d, Y') }}</td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500;
                                    @if(($award->certificate_status ?: $award->status) === 'valid') background: #d1fae5; color: #065f46;
                                    @elseif(($award->certificate_status ?: $award->status) === 'revoked') background: #fee2e2; color: #991b1b;
                                    @else background: #fef3c7; color: #92400e; @endif">
                                    {{ ucfirst($award->certificate_status ?: $award->status) }}
                                </span>
                            </td>
                            <td class="admin-award-certificate-cell" style="padding: 12px;">
                                @if($showCertificate)
                                    <div class="admin-award-certificate">
                                        <div class="admin-award-certificate-qr" aria-label="Scan QR code for official award certificate">
                                            <img src="{{ $award->tokenQrUrl() }}" alt="QR code for official award certificate">
                                        </div>
                                        <div class="admin-award-certificate-meta">
                                            <strong>{{ $award->certificate_number }}</strong>
                                            <span>Scan QR to view certificate</span>
                                        </div>
                                    </div>
                                @else
                                    <span style="font-size: 12px; color: #94a3b8;">No certificate</span>
                                @endif
                            </td>
                            <td style="padding: 12px;">
                                <button type="button" onclick="loadAwardViewModal({{ $award->id }})" style="background: white; color: #374151; border: 1px solid #d1d5db; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">View</button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: #9ca3af;">
                                <i class="fas fa-trophy" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No awards yet. Award your first project from Projects page!
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-container" style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-top: 20px; overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #e5e7eb; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #111827;">Projects Ready for Award</h3>
                <p style="margin: 0; font-size: 12px; color: #94a3b8;">Projects with approved bids ready for awarding</p>
            </div>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
                        <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Project</th>
                        <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Budget</th>
                        <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Total Bids</th>
                        <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Lowest Approved Bid</th>
                        <th style="text-align: left; padding: 14px 20px; font-size: 12px; color: #64748b; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($readyProjects ?? collect()) as $project)
                        @php
                            $approvedBids = $project->bids->whereIn('status', ['approved', 'evaluated']);
                            $recommendedBid = $approvedBids->sortBy('bid_amount')->first();
                            $approvedCount = $approvedBids->count();
                        @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 18px 20px; font-size: 13px; font-weight: 500; color: #111827;">{{ $project->title }}</td>
                            <td style="padding: 18px 20px; font-size: 13px; color: #111827;">P{{ number_format((float) $project->budget, 2) }}</td>
                            <td style="padding: 18px 20px;">
                                <span style="display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #fef3c7; color: #a16207;">
                                    {{ $approvedCount }} eligible
                                </span>
                            </td>
                            <td style="padding: 18px 20px; font-size: 13px; color: #111827;">
                                {{ $recommendedBid ? 'P' . number_format((float) $recommendedBid->bid_amount, 2) : '—' }}
                            </td>
                            <td style="padding: 18px 20px;">
                                @if($recommendedBid)
                                    <button type="button" onclick="loadDeclareWinnerModal({{ $project->id }}, {{ $recommendedBid->id }})" style="display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 8px; background: #d97706; color: white; border: none; font-size: 12px; font-weight: 600; cursor: pointer;">Declare Winner</button>
                                @else
                                    <span style="font-size: 12px; color: #94a3b8;">No eligible bids</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 24px; text-align: center; color: #94a3b8; font-size: 13px;">
                                No projects with approved bids are ready for awarding.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</div>

<div id="awardViewModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10000; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 14px; width: min(720px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 20px 44px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeAwardViewModal()" style="position: absolute; top: 16px; right: 16px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 9px; font-size: 18px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="awardViewModalBody"></div>
    </div>
</div>

<div id="declareWinnerModal" style="display: none; position: fixed; inset: 0; padding: 20px; background: rgba(15, 23, 42, 0.45); z-index: 10001; justify-content: center; align-items: center; box-sizing: border-box;">
    <div style="background: white; border-radius: 18px; width: min(690px, 100%); max-height: calc(100vh - 20px); overflow-y: auto; overflow-x: hidden; position: relative; box-shadow: 0 24px 48px rgba(15, 23, 42, 0.16); box-sizing: border-box;">
        <button onclick="closeDeclareWinnerModal()" style="position: absolute; top: 16px; right: 16px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; border-radius: 10px; font-size: 20px; line-height: 1; cursor: pointer; color: #7c8ba1; z-index: 2;">&times;</button>
        <div id="declareWinnerModalBody"></div>
    </div>
</div>

<script>
    function loadAwardViewModal(id) {
        document.getElementById('awardViewModal').style.display = 'flex';
        document.getElementById('awardViewModalBody').innerHTML = '<div style="padding: 28px; color: #64748b; font-size: 14px;">Loading award details...</div>';

        fetch(`/admin/awards/${id}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('awardViewModalBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('awardViewModalBody').innerHTML = '<div style="padding: 28px; color: #b91c1c; font-size: 14px;">Error loading award details.</div>';
            });
    }

    function closeAwardViewModal() {
        document.getElementById('awardViewModal').style.display = 'none';
    }

    function loadDeclareWinnerModal(projectId, bidId) {
        document.getElementById('declareWinnerModal').style.display = 'flex';
        document.getElementById('declareWinnerModalBody').innerHTML = '<div style="padding: 30px; color: #64748b; font-size: 14px;">Loading award form...</div>';

        fetch(`/admin/projects/${projectId}/award?bid=${bidId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                document.getElementById('declareWinnerModalBody').innerHTML = html;
                // Re-initialize form validation after modal content loads
                if (typeof validateDeclareWinnerForm === 'function') {
                    validateDeclareWinnerForm();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('declareWinnerModalBody').innerHTML = '<div style="padding: 30px; color: #b91c1c; font-size: 14px;">Error loading award form.</div>';
            });
    }

    function closeDeclareWinnerModal() {
        document.getElementById('declareWinnerModal').style.display = 'none';
    }

    function selectDeclareWinnerOption(option) {
        document.querySelectorAll('#declareWinnerModal [data-bid-option]').forEach(function(item) {
            item.classList.remove('is-selected');
            const radio = item.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = false;
            }
        });

        option.classList.add('is-selected');

        const radio = option.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }

        const amountField = document.getElementById('awardContractAmount');
        if (amountField) {
            amountField.value = option.dataset.bidAmount || '';
        }

        // Update form validation state after bid selection
        if (typeof validateDeclareWinnerForm === 'function') {
            validateDeclareWinnerForm();
        }
    }

    function closeSuccessAlert() {
        const alert = document.getElementById('successAlert');
        if (alert) alert.style.display = 'none';
    }

    function closeErrorAlert() {
        const alert = document.getElementById('errorAlert');
        if (alert) alert.style.display = 'none';
    }

    function validateDeclareWinnerForm() {
        const modalBody = document.getElementById('declareWinnerModalBody');
        if (!modalBody) return;

        const fileInput = modalBody.querySelector('#certificateFile');
        const submitBtn = modalBody.querySelector('#declareWinnerSubmitBtn');
        const fileName = modalBody.querySelector('#certificateFileName');
        const fileError = modalBody.querySelector('[data-error-for="certificate_file"]');
        const formAlert = modalBody.querySelector('#awardFormAlert');

        if (!fileInput || !submitBtn) return;

        const file = fileInput.files[0] || null;
        const bidSelected = modalBody.querySelector('input[name="bid_id"]:checked') !== null;
        let fileIsValid = false;
        let error = '';

        if (fileError) fileError.textContent = '';
        if (fileName) fileName.textContent = '';
        if (formAlert) {
            formAlert.style.display = 'none';
            formAlert.textContent = '';
        }

        if (file) {
            const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
            if (!isPdf) {
                error = 'Only PDF files are allowed.';
            } else if (file.size > 5 * 1024 * 1024) {
                error = 'File size must not exceed 5MB.';
            } else {
                fileIsValid = true;
                if (fileName) {
                    fileName.textContent = 'Selected: ' + file.name;
                }
            }
        }

        if (error && fileError) {
            fileError.textContent = error;
        }

        submitBtn.disabled = !(fileIsValid && bidSelected);
    }

    function sendCertificateAction(awardId, actionType, successMsg, errorMsg) {
        let url;
        switch(actionType) {
            case 'revoke':
                url = '/admin/awards/' + awardId + '/revoke';
                break;
            case 'regenerate':
                url = '/admin/awards/' + awardId + '/regenerate-token';
                break;
            default:
                return;
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(successMsg);
                location.reload();
            } else {
                alert('Error: ' + (data.message || errorMsg));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred.');
        });
    }

    function confirmRevokeCertificate(awardId) {
        if (!confirm('Are you sure you want to revoke this certificate? This action will be logged.')) {
            return;
        }
        sendCertificateAction(awardId, 'revoke', 'Certificate revoked successfully.', 'Failed to revoke certificate.');
    }

    function regenerateToken(awardId) {
        if (!confirm('Regenerate QR token? The old QR code will become invalid. This action will be logged.')) {
            return;
        }
        sendCertificateAction(awardId, 'regenerate', 'QR token regenerated successfully.', 'Failed to regenerate QR token.');
    }

    function triggerReplaceCertificate(awardId) {
        const modalBody = document.getElementById('awardViewModalBody');
        const fileInput = modalBody.querySelector('.replace-certificate-input');
        if (fileInput) {
            fileInput.onchange = function() {
                if (this.files.length === 0) return;
                const file = this.files[0];
                // Validate PDF
                if (file.type !== 'application/pdf') {
                    alert('Only PDF files are allowed.');
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size exceeds 5MB limit.');
                    return;
                }
                if (!confirm('Replace the existing certificate with the selected PDF? This action will be logged.')) {
                    this.value = ''; // reset
                    return;
                }
                submitCertificateReplacement(awardId, file);
            };
            fileInput.click();
        }
    }

    function submitCertificateReplacement(awardId, file) {
        const formData = new FormData();
        formData.append('certificate_file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('/admin/awards/' + awardId + '/certificate/replace', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Certificate replaced successfully.');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to replace certificate.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while replacing the certificate.');
        });
    }

    document.getElementById('awardViewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAwardViewModal();
        }
    });

    document.getElementById('declareWinnerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeclareWinnerModal();
        }
    });

    document.getElementById('declareWinnerModalBody').addEventListener('submit', function(e) {
        const form = e.target.closest('.declare-award-form');
        if (!form) return;

        validateDeclareWinnerForm();

        const submitBtn = form.querySelector('#declareWinnerSubmitBtn');
        const formAlert = form.querySelector('#awardFormAlert');
        if (submitBtn && submitBtn.disabled) {
            e.preventDefault();
            if (formAlert) {
                formAlert.textContent = 'Please select an eligible winning bidder and upload a valid PDF certificate up to 5MB.';
                formAlert.style.display = 'block';
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 0.5s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.style.display = 'none', 500);
            }, 5000);
        }
    });
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-projects-wizard-page">
    @vite(['resources/css/dashboard.css', 'resources/js/app.js'])
    @include('partials.admin-sidebar')

    <!-- MAIN AREA -->
    <div class="main-area">
        <!-- NAVBAR -->
        <header class="navbar">
            <div class="nav-left">
                <h2>Create New Procurement Project</h2>
                <p>Add procurement project</p>
            </div>
        </header>

        <!-- MAIN CONTENT - Only the modal overlay -->
        <main class="dashboard-content wizard-modal-host">
            <!-- Modal Overlay -->
            <div id="wizardModalOverlay" class="wizard-modal-overlay">
                <div class="wizard-container modal-wizard">

                    <!-- Close Button -->
                    <a href="{{ route('admin.projects') }}" class="wizard-close-button" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </a>

                    <!-- Modal Header -->
                    <div class="wizard-modal-header">
                        <div class="wizard-title-row">
                            <div class="wizard-title-dot"></div>
                            <h2>Create New Procurement Project</h2>
                        </div>
                        <p>Complete all steps to publish the project for bidding.</p>
                    </div>

                    <!-- Stepper -->
                    <div class="wizard-steps">
                        <div class="wizard-step active" data-step="1" aria-current="step">
                            <div class="step-number">1</div>
                            <div class="step-label">Project Info</div>
                        </div>
                        <div class="wizard-step" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-label">Requirements</div>
                        </div>
                        <div class="wizard-step" data-step="3">
                            <div class="step-number">3</div>
                            <div class="step-label">Documents</div>
                        </div>
                        <div class="wizard-step" data-step="4">
                            <div class="step-number">4</div>
                            <div class="step-label">Dates</div>
                        </div>
                        <div class="wizard-step" data-step="5">
                            <div class="step-number">5</div>
                            <div class="step-label">Review</div>
                        </div>
                    </div>

                    <!-- Wizard Form -->
                    <form id="projectWizardForm" class="wizard-form" action="{{ route('admin.projects.wizard.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="status" id="projectStatus" value="draft">

                        <!-- Wizard Body - scrollable -->
                        <div class="wizard-body">

                            <!-- Step 1: Project Information -->
                            <div class="wizard-step-content active" data-step-content="1">
                                <div class="wizard-step-panel">
                                    <h3 class="form-section-title">Step 1: Project Information</h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="title">Project Title *</label>
                                            <input type="text" id="title" name="title" value="{{ old('title') }}" required>
                                            @error('title') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group full-width">
                                            <label for="description">Project Description *</label>
                                            <textarea id="description" name="description" rows="4" required>{{ old('description') }}</textarea>
                                            @error('description') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="category">Category *</label>
                                            <select id="category" name="category" required>
                                                <option value="">Select Category</option>
                                                <option value="goods" {{ old('category') == 'goods' ? 'selected' : '' }}>Goods</option>
                                                <option value="services" {{ old('category') == 'services' ? 'selected' : '' }}>Services</option>
                                                <option value="infrastructure" {{ old('category') == 'infrastructure' ? 'selected' : '' }}>Infrastructure</option>
                                                <option value="consultancy" {{ old('category') == 'consultancy' ? 'selected' : '' }}>Consultancy</option>
                                            </select>
                                            @error('category') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="location">Project Location *</label>
                                            <input type="text" id="location" name="location" value="{{ old('location') }}" placeholder="e.g., City Hall, Manila" required>
                                            @error('location') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="procurement_mode">Procurement Mode *</label>
                                            <select id="procurement_mode" name="procurement_mode" required>
                                                <option value="">Select Mode</option>
                                                <option value="public_bidding" {{ old('procurement_mode') == 'public_bidding' ? 'selected' : '' }}>Public Bidding</option>
                                                <option value="negotiated_procurement" {{ old('procurement_mode') == 'negotiated_procurement' ? 'selected' : '' }}>Negotiated Procurement</option>
                                                <option value="shopping" {{ old('procurement_mode') == 'shopping' ? 'selected' : '' }}>Shopping</option>
                                                <option value="small_value_procurement" {{ old('procurement_mode') == 'small_value_procurement' ? 'selected' : '' }}>Small Value Procurement</option>
                                                <option value="direct_contracting" {{ old('procurement_mode') == 'direct_contracting' ? 'selected' : '' }}>Direct Contracting</option>
                                                <option value="electronic_procurement" {{ old('procurement_mode') == 'electronic_procurement' ? 'selected' : '' }}>Electronic Procurement</option>
                                            </select>
                                            @error('procurement_mode') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="source_of_fund">Source of Fund *</label>
                                            <input type="text" id="source_of_fund" name="source_of_fund" value="{{ old('source_of_fund') }}" placeholder="e.g., General Fund, GAA" required>
                                            @error('source_of_fund') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="contract_duration">Contract Duration *</label>
                                            <input type="text" id="contract_duration" name="contract_duration" value="{{ old('contract_duration') }}" placeholder="e.g., 6 months, 1 year" required>
                                            @error('contract_duration') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="budget">Approved Budget for the Contract (ABC) *</label>
                                            <input type="number" id="budget" name="budget" value="{{ old('budget') }}" min="0" step="0.01" required>
                                            @error('budget') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Bidding Requirements -->
                            <div class="wizard-step-content" data-step-content="2">
                                <div class="wizard-step-panel">
                                    <h3 class="form-section-title">Step 2: Bidding Requirements</h3>
                                    <div class="form-grid">
                                        <div class="form-group full-width">
                                            <label>Required Documents Checklist</label>
                                            <div class="requirements-checklist">
                                                @php
                                                    $docOptions = [
                                                        'Business Permit',
                                                        'PhilGEPS Registration',
                                                        'DTI/SEC Registration',
                                                        'Tax Clearance',
                                                        'Omnibus Sworn Statement',
                                                        'Technical Proposal',
                                                        'Financial Proposal',
                                                        'Company Profile',
                                                        'PCAB License',
                                                        'Other BAC Required Documents'
                                                    ];
                                                    $oldDocs = old('required_documents', []);
                                                @endphp
                                                @foreach($docOptions as $doc)
                                                    <label class="checkbox-item">
                                                        <input type="checkbox" name="required_documents[]" value="{{ $doc }}" {{ in_array($doc, $oldDocs) ? 'checked' : '' }}>
                                                        <span>{{ $doc }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <small>Select all documents required from bidders.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Upload Bid Documents -->
                            <div class="wizard-step-content" data-step-content="3">
                                <div class="wizard-step-panel">
                                    <h3 class="form-section-title">Step 3: Upload Bid Documents</h3>
                                    <p class="wizard-help-text">
                                        Upload the necessary bidding documents for this project. Allowed file types: PDF, DOCX, XLSX. Max file size: 20MB per file.
                                    </p>
                                    <div class="form-group full-width">
                                        <label>Project Documents</label>
                                        <div id="documentUploadList" class="document-upload-list">
                                            <div class="document-upload-item" data-index="0">
                                                <select name="document_type[]" class="document-type-select" required>
                                                    <option value="">Select Document Type</option>
                                                    <option value="invitation_to_bid">Invitation to Bid</option>
                                                    <option value="bidding_documents">Bidding Documents</option>
                                                    <option value="terms_of_reference">Terms of Reference</option>
                                                    <option value="technical_specifications">Technical Specifications</option>
                                                    <option value="bill_of_quantities">Bill of Quantities</option>
                                                    <option value="project_plans">Project Plans / Drawings</option>
                                                    <option value="supplemental_bulletin">Supplemental Bid Bulletin</option>
                                                    <option value="other">Other</option>
                                                </select>
                                                <input type="file" name="project_documents[]" accept=".pdf,.doc,.docx,.xlsx,.xls,.jpg,.jpeg,.png" required>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-add-document" onclick="addDocumentRow()">
                                            <i class="fas fa-plus"></i> Add Another Document
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Important Dates -->
                            <div class="wizard-step-content" data-step-content="4">
                                <div class="wizard-step-panel">
                                    <h3 class="form-section-title">Step 4: Important Dates</h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="date_posted">Date Posted *</label>
                                            <input type="date" id="date_posted" name="date_posted" value="{{ old('date_posted', date('Y-m-d')) }}" required>
                                            @error('date_posted') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="pre_bid_conference_date">Pre-Bid Conference Date</label>
                                            <input type="datetime-local" id="pre_bid_conference_date" name="pre_bid_conference_date" value="{{ old('pre_bid_conference_date') }}">
                                            <small>Optional</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="clarification_deadline">Deadline for Questions / Clarifications *</label>
                                            <input type="datetime-local" id="clarification_deadline" name="clarification_deadline" value="{{ old('clarification_deadline') }}" required>
                                            @error('clarification_deadline') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="bid_submission_deadline">Bid Submission Deadline *</label>
                                            <input type="datetime-local" id="bid_submission_deadline" name="bid_submission_deadline" value="{{ old('bid_submission_deadline') }}" required>
                                            @error('bid_submission_deadline') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="bid_opening_date">Bid Opening Date *</label>
                                            <input type="datetime-local" id="bid_opening_date" name="bid_opening_date" value="{{ old('bid_opening_date') }}" required>
                                            @error('bid_opening_date') <small class="error-message">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="evaluation_start_date">Evaluation Start Date</label>
                                            <input type="date" id="evaluation_start_date" name="evaluation_start_date" value="{{ old('evaluation_start_date') }}">
                                        </div>
                                        <div class="form-group full-width">
                                            <label for="expected_award_date">Expected Award Date</label>
                                            <input type="date" id="expected_award_date" name="expected_award_date" value="{{ old('expected_award_date') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 5: Review & Publish -->
                            <div class="wizard-step-content" data-step-content="5">
                                <div class="wizard-step-panel">
                                    <h3 class="form-section-title">Step 5: Review & Publish</h3>

                                    <!-- Project Info Review -->
                                    <div class="review-card">
                                        <h4><i class="fas fa-info-circle"></i> Project Information</h4>
                                        <ul class="review-list" id="reviewProjectInfo"></ul>
                                    </div>

                                    <!-- Requirements Review -->
                                    <div class="review-card">
                                        <h4><i class="fas fa-list-check"></i> Bidding Requirements</h4>
                                        <ul class="review-list" id="reviewRequirements"></ul>
                                    </div>

                                    <!-- Documents Review -->
                                    <div class="review-card">
                                        <h4><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                                        <ul class="review-documents-list" id="reviewDocuments"></ul>
                                    </div>

                                    <!-- Dates Review -->
                                    <div class="review-card">
                                        <h4><i class="fas fa-calendar-alt"></i> Important Dates</h4>
                                        <ul class="review-list" id="reviewDates"></ul>
                                    </div>

                                    <div class="form-group confirm-publish-group">
                                        <label class="confirm-publish-label">
                                            <input type="checkbox" name="confirm_correct" required>
                                            <span>I confirm that all provided information is accurate and ready for publishing.</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Wizard Content Actions -->
                            <div class="wizard-actions-container">
                                <button type="button" class="btn btn-secondary btn-prev" id="prevBtn" onclick="changeStep(-1)" style="display:none;">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="btn btn-secondary btn-draft" id="draftBtn" onclick="saveAsDraft()" style="display:none;">
                                    <i class="fas fa-save"></i> Save as Draft
                                </button>
                                <button type="button" class="btn btn-primary btn-next" id="nextBtn" onclick="changeStep(1)">
                                    Next Step <i class="fas fa-arrow-right"></i>
                                </button>
                                <button type="submit" class="btn btn-primary btn-submit" id="submitBtn" onclick="document.getElementById('projectStatus').value='open'" style="display:none;">
                                    <i class="fas fa-check-circle"></i> Publish Project
                                </button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </main>
    </div>
</div>

<style>
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: translateY(8px) scale(0.998);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    html:has(.admin-projects-wizard-page),
    body:has(.admin-projects-wizard-page) {
        overflow: hidden;
    }

    /* Button System */
    .btn {
        border-radius: 8px;
        padding: 11px 26px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .btn-primary {
        background: #f59e0b;
        color: #ffffff;
        border-color: #f59e0b;
    }
    .btn-primary:hover {
        background: #d97706;
        border-color: #d97706;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
    }
    .btn-secondary {
        background: #1f1f1f;
        color: #d1d5db;
        border-color: #4b5563;
    }
     .btn-secondary:hover {
         background: #262626;
         color: #ffffff;
         border-color: #6b7280;
         transform: translateY(-0.5px);
     }
     .btn-danger {
         background: #7f1d1d;
         color: #fecaca;
         border-color: #dc2626;
     }
     .btn-danger:hover {
         background: #991b1b;
         color: #ffffff;
         border-color: #b91c1c;
     }

     /* Actions Footer */
    .wizard-actions-container {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 28px;
        border-top: 1px solid #4b5563;
        background: #0a0a0a;
    }
    @media (max-width: 640px) {
        .wizard-actions-container {
            flex-direction: column-reverse;
            align-items: stretch;
            gap: 8px;
            padding: 14px 20px;
        }
        .wizard-actions-container .btn {
            width: 100%;
            justify-content: center;
            padding: 11px 16px;
        }
    }

    /* Stepper */
    .wizard-steps {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        position: relative;
        width: 100%;
    }
    .wizard-step {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 1;
    }
                .wizard-step::after {
                    content: '';
                    position: absolute;
                    top: 16px;
                    left: 50%;
                    width: 100%;
                    height: 2px;
                    background: #4b5563;
                    z-index: -1;
                    transition: background 0.3s ease;
                }
                .wizard-step:last-child::after {
                    display: none;
                }
                .wizard-step.completed::after {
                    background: #f59e0b;
                }
                .step-number {
                    width: 34px;
                    height: 34px;
                    border-radius: 50%;
                    background: #000000;
                    border: 2px solid #404040;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 14px;
                    font-weight: 700;
                    color: #9ca3af;
                    margin-bottom: 8px;
                    transition: all 0.3s ease;
                    box-sizing: border-box;
                    position: relative;
                    z-index: 1;
                }
                .wizard-step.active .step-number {
                    border-color: #f59e0b;
                    background: #f59e0b;
                    color: #000000;
                    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.25);
                }
                .wizard-step.completed .step-number {
                    border-color: #f59e0b;
                    background: #f59e0b;
                    color: #000000;
                }
                .step-label {
                    font-size: 12px;
                    font-weight: 600;
                    color: #9ca3af;
                    text-align: center;
                    white-space: nowrap;
                    transition: color 0.3s ease;
                }
                .wizard-step.active .step-label {
                    color: #f59e0b;
                }
                .wizard-step.completed .step-label {
                    color: #d1d5db;
                }
    .wizard-step:last-child::after {
        display: none;
    }
    .wizard-step.completed::after,
    .wizard-step.active::after {
        background: #f59e0b;
    }
    .step-number {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #0a0a0a;
        border: 2px solid #262626;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        color: #6b7280;
        margin-bottom: 8px;
        transition: all 0.3s ease;
        box-sizing: border-box;
        position: relative;
        z-index: 1;
    }
    .wizard-step.active .step-number {
        border-color: #f59e0b;
        background: #f59e0b;
        color: #000000;
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
    }
    .wizard-step.completed .step-number {
        border-color: #f59e0b;
        background: #f59e0b;
        color: #000000;
    }
    .step-label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-align: center;
        white-space: nowrap;
        transition: color 0.3s ease;
    }
    .wizard-step.active .step-label {
        color: #f59e0b;
    }
    .wizard-step.completed .step-label {
        color: #d1d5db;
    }
    @media (max-width: 640px) {
        .wizard-steps {
            padding: 16px 12px;
        }
        .step-label {
            font-size: 10px;
            white-space: normal;
            text-align: center;
            line-height: 1.2;
        }
        .step-number {
            width: 28px;
            height: 28px;
            font-size: 12px;
            margin-bottom: 6px;
        }
    }

    /* Step Content */
    .wizard-step-content {
        display: none;
    }
    .wizard-step-content.active {
        display: block;
    }

    /* Form Section */
    .form-section-title {
        color: #ffffff;
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #404040;
        letter-spacing: 0;
    }

    /* Form Grid & Layout */
                .form-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 0 20px;
                }
                @media (max-width: 768px) {
                    .form-grid {
                        grid-template-columns: 1fr;
                        gap: 0;
                    }
                }
    .form-group {
        margin-bottom: 20px;
        padding-right: 10px;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
                .form-group label {
                    display: block;
                    font-size: 12px;
                    font-weight: 600;
                    color: #ffffff;
                    margin-bottom: 6px;
                    letter-spacing: 0.03em;
                    text-transform: uppercase;
                }
                .form-group input,
                .form-group select,
                .form-group textarea {
                    width: 100%;
                    padding: 11px 14px;
                    border: 1px solid #4b5563;
                    background: #111827;
                    color: #ffffff;
                    border-radius: 6px;
                    font-size: 14px;
                    font-family: inherit;
                    transition: all 0.2s ease;
                    box-sizing: border-box;
                    line-height: 1.5;
                }
                .form-group input:focus,
                .form-group select:focus,
                .form-group textarea:focus {
                    outline: none;
                    border-color: #f59e0b;
                    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
                    background: #1a1a1a;
                }
                .form-group input:hover,
                .form-group select:hover,
                .form-group textarea:hover {
                    border-color: #6b7280;
                }
                .form-group small {
                    display: block;
                    font-size: 12px;
                    color: #9ca3af;
                    margin-top: 6px;
                    line-height: 1.4;
                }
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
        line-height: 1.5;
    }
                .form-group select {
                    appearance: none;
                    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
                    background-position: right 12px center;
                    background-repeat: no-repeat;
                    background-size: 16px;
                    padding-right: 36px;
                }

    .error-message {
        color: #f87171;
        font-size: 12px;
        margin-top: 6px;
        display: block;
        font-weight: 500;
    }

    /* Document Upload */
    .document-upload-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .document-upload-item {
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    .document-upload-item select {
        flex: 0 0 240px;
        min-width: 200px;
    }
    @media (max-width: 640px) {
        .document-upload-item {
            flex-direction: column;
        }
        .document-upload-item select,
        .document-upload-item input[type="file"] {
            width: 100%;
            flex: none;
        }
    }

    /* Review Cards */
    .review-card {
        background: #111827;
        border: 1px solid #4b5563;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 16px;
    }
    .review-card h4 {
        color: #ffffff;
        margin: 0 0 16px;
        font-size: 14px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.02em;
    }
    .review-card h4 i {
        color: #f59e0b;
        font-size: 15px;
    }
    .review-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px 20px;
    }
    @media (max-width: 640px) {
        .review-list {
            grid-template-columns: 1fr;
            gap: 12px;
        }
    }
    .review-list li {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
                .review-list li span:first-child {
                    color: #d1d5db;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }
    .review-list li span:last-child {
        color: #e5e7eb;
        font-size: 13px;
        font-weight: 500;
        word-break: break-word;
    }
    .review-documents-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .review-documents-list li {
        color: #d1d5db;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 0;
    }
    .review-documents-list li i {
        color: #f59e0b;
        font-size: 13px;
    }

    /* Date inputs dark theme */
    input[type="date"],
    input[type="datetime-local"] {
        color-scheme: dark;
    }
    input[type="date"]::-webkit-calendar-picker-indicator,
    input[type="datetime-local"]::-webkit-calendar-picker-indicator {
        filter: invert(0.7);
    }

    /* Placeholder text */
    ::placeholder {
        color: #d1d5db;
        opacity: 1;
    }
    :-ms-input-placeholder {
        color: #d1d5db;
    }
    ::-ms-input-placeholder {
        color: #d1d5db;
    }
    :-ms-input-placeholder {
        color: #9ca3af;
    }
    ::-ms-input-placeholder {
        color: #9ca3af;
    }

    /* Full-width buttons */
    @media (max-width: 640px) {
        .wizard-step .step-label {
            font-size: 10px;
            line-height: 1.3;
            padding: 0 4px;
        }
        .document-upload-item select {
            flex: 0 0 100%;
        }
    }
     .step-number {
         background: #000000;
         border-color: #4b5563;
         color: #d1d5db;
         margin-bottom: 10px;
     }
     .step-label {
         color: #d1d5db;
     }

    /* Scoped wizard polish: overrides the older light-theme wizard rules in dashboard.css. */
    .admin-projects-wizard-page {
        --wizard-bg: #101418;
        --wizard-panel: #151a1f;
        --wizard-panel-soft: #1a2027;
        --wizard-field: #111a22;
        --wizard-field-hover: #16212b;
        --wizard-border: #2f3a45;
        --wizard-border-strong: #5d6b7a;
        --wizard-text: #f8fafc;
        --wizard-muted: #aeb8c5;
        --wizard-subtle: #7f8b9b;
        --wizard-accent: #f59e0b;
        --wizard-accent-hover: #d97706;
        --wizard-accent-soft: rgba(245, 158, 11, 0.16);
        --wizard-accent-line: rgba(245, 158, 11, 0.42);
        --wizard-danger: #ef4444;
    }

    .admin-projects-wizard-page .wizard-modal-host {
        min-height: calc(100vh - 60px);
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .admin-projects-wizard-page .wizard-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: clamp(16px, 2vw, 28px);
        box-sizing: border-box;
        background: rgba(0, 0, 0, 0.68);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
    }

    .admin-projects-wizard-page .wizard-container.modal-wizard {
        width: 100%;
        max-width: min(1120px, calc(100vw - 40px));
        max-height: min(860px, calc(100vh - 40px));
        margin: 0;
        display: block;
        position: relative;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-color: var(--wizard-border-strong) transparent;
        scrollbar-width: thin;
        color: var(--wizard-text);
        background: var(--wizard-bg);
        border: 1px solid rgba(148, 163, 184, 0.28);
        border-radius: 14px;
        box-shadow: 0 26px 60px rgba(0, 0, 0, 0.5);
        animation: modalFadeIn 0.18s ease-out;
    }

    .admin-projects-wizard-page .wizard-close-button {
        position: absolute;
        top: 18px;
        right: 18px;
        z-index: 10;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--wizard-muted);
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(148, 163, 184, 0.16);
        text-decoration: none;
        transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
    }

    .admin-projects-wizard-page .wizard-close-button:hover,
    .admin-projects-wizard-page .wizard-close-button:focus-visible {
        color: var(--wizard-text);
        background: rgba(255, 255, 255, 0.09);
        border-color: rgba(148, 163, 184, 0.34);
        transform: translateY(-1px);
        outline: none;
    }

    .admin-projects-wizard-page .wizard-modal-header {
        padding: 24px 72px 22px 32px;
        background: linear-gradient(180deg, #171b20 0%, #101315 100%);
        border-bottom: 1px solid var(--wizard-border);
    }

    .admin-projects-wizard-page .wizard-title-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 6px;
    }

    .admin-projects-wizard-page .wizard-title-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: var(--wizard-accent);
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.12), 0 0 18px rgba(245, 158, 11, 0.45);
        flex: 0 0 auto;
    }

    .admin-projects-wizard-page .wizard-modal-header h2 {
        margin: 0;
        color: var(--wizard-text);
        font-size: clamp(20px, 2vw, 24px);
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: 0;
    }

    .admin-projects-wizard-page .wizard-modal-header p {
        margin: 0 0 0 22px;
        color: var(--wizard-muted);
        font-size: 14px;
        line-height: 1.45;
    }

    .admin-projects-wizard-page .wizard-form {
        display: block;
        min-height: auto;
    }

    .admin-projects-wizard-page .wizard-steps {
        margin: 0;
        padding: 24px 36px 22px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px;
        background: linear-gradient(180deg, rgba(21, 26, 31, 0.72) 0%, rgba(16, 20, 24, 0.96) 100%);
        border-bottom: 1px solid rgba(148, 163, 184, 0.16);
        overflow-x: auto;
        scrollbar-width: none;
    }

    .admin-projects-wizard-page .wizard-steps::-webkit-scrollbar {
        display: none;
    }

    .admin-projects-wizard-page .wizard-step {
        flex: 1 0 120px;
        min-width: 92px;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 1;
    }

    .admin-projects-wizard-page .wizard-step::before {
        content: none;
        display: none;
    }

    .admin-projects-wizard-page .wizard-step::after {
        content: '';
        position: absolute;
        top: 21px;
        left: 50%;
        width: 100%;
        height: 2px;
        background: #33414f;
        z-index: -1;
        transition: background 0.22s ease;
    }

    .admin-projects-wizard-page .wizard-step:last-child::before,
    .admin-projects-wizard-page .wizard-step:last-child::after,
    .admin-projects-wizard-page .wizard-step[data-step="5"]::before,
    .admin-projects-wizard-page .wizard-step[data-step="5"]::after {
        content: none;
        display: none !important;
        width: 0 !important;
    }

    .admin-projects-wizard-page .wizard-step.completed::after {
        background: var(--wizard-accent);
    }

    .admin-projects-wizard-page .wizard-step.active::after {
        background: linear-gradient(90deg, var(--wizard-accent), #33414f);
    }

    .admin-projects-wizard-page .step-number {
        width: 44px;
        height: 44px;
        margin: 0 0 12px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        position: relative;
        z-index: 1;
        color: #b8c3d1;
        background: var(--wizard-field);
        border: 3px solid #11161b;
        box-shadow: 0 0 0 1px #4e5d6d;
        font-size: 15px;
        font-weight: 800;
        line-height: 1;
        transition: background 0.22s ease, color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease;
    }

    .admin-projects-wizard-page .wizard-step.active .step-number {
        color: #ffffff;
        background: var(--wizard-accent);
        box-shadow: 0 0 0 5px var(--wizard-accent-soft);
        transform: translateY(-1px);
    }

    .admin-projects-wizard-page .wizard-step.completed .step-number {
        color: #ffffff;
        background: #b76f02;
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.12);
    }

    .admin-projects-wizard-page .step-label {
        max-width: 130px;
        color: var(--wizard-muted);
        font-size: 13px;
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: 0;
        text-align: center;
        white-space: normal;
    }

    .admin-projects-wizard-page .wizard-step.active .step-label {
        color: #fbbf24;
    }

    .admin-projects-wizard-page .wizard-step.completed .step-label {
        color: var(--wizard-text);
    }

    .admin-projects-wizard-page .wizard-body {
        display: block;
        min-height: auto;
        overflow: visible;
        padding: 0;
        background: var(--wizard-bg);
    }

    .admin-projects-wizard-page .wizard-step-panel {
        padding: clamp(24px, 3vw, 34px) clamp(24px, 4vw, 44px) clamp(36px, 4vw, 48px);
    }

    .admin-projects-wizard-page .form-section-title {
        margin: 0 0 22px;
        padding-bottom: 14px;
        color: var(--wizard-text);
        border-bottom: 1px solid var(--wizard-border);
        font-size: 18px;
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: 0;
    }

    .admin-projects-wizard-page .wizard-help-text {
        max-width: 760px;
        margin: -8px 0 22px;
        color: var(--wizard-muted);
        font-size: 13px;
        line-height: 1.6;
    }

    .admin-projects-wizard-page .form-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 20px 28px;
        margin: 0;
    }

    .admin-projects-wizard-page .form-group {
        margin: 0;
        padding: 0;
        min-width: 0;
    }

    .admin-projects-wizard-page .form-group.full-width {
        grid-column: 1 / -1;
    }

    .admin-projects-wizard-page .form-group label {
        display: block;
        margin: 0 0 7px;
        color: #cbd5e1;
        font-size: 12px;
        font-weight: 800;
        line-height: 1.3;
        letter-spacing: 0;
        text-transform: uppercase;
    }

    .admin-projects-wizard-page .form-group input,
    .admin-projects-wizard-page .form-group select,
    .admin-projects-wizard-page .form-group textarea {
        width: 100%;
        min-height: 46px;
        box-sizing: border-box;
        border: 1px solid var(--wizard-border-strong);
        border-radius: 8px;
        background-color: var(--wizard-field);
        color: var(--wizard-text);
        font: inherit;
        font-size: 14px;
        line-height: 1.5;
        padding: 11px 14px;
        transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .admin-projects-wizard-page .form-group textarea {
        min-height: 132px;
        resize: vertical;
    }

    .admin-projects-wizard-page .form-group select {
        appearance: none;
        padding-right: 42px;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23aeb8c5' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.7' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 14px center;
        background-repeat: no-repeat;
        background-size: 16px;
    }

    .admin-projects-wizard-page .form-group select option {
        background: var(--wizard-field);
        color: var(--wizard-text);
    }

    .admin-projects-wizard-page .form-group input:hover,
    .admin-projects-wizard-page .form-group select:hover,
    .admin-projects-wizard-page .form-group textarea:hover {
        border-color: #7b8796;
        background-color: var(--wizard-field-hover);
    }

    .admin-projects-wizard-page .form-group input:focus,
    .admin-projects-wizard-page .form-group select:focus,
    .admin-projects-wizard-page .form-group textarea:focus {
        outline: none;
        border-color: var(--wizard-accent);
        background-color: var(--wizard-field-hover);
        box-shadow: 0 0 0 4px var(--wizard-accent-soft);
    }

    .admin-projects-wizard-page .form-group small,
    .admin-projects-wizard-page .wizard-help-text {
        color: var(--wizard-muted);
    }

    .admin-projects-wizard-page .form-group .error-message,
    .admin-projects-wizard-page .error-message {
        color: #fca5a5;
        font-size: 12px;
        font-weight: 700;
        margin-top: 7px;
    }

    .admin-projects-wizard-page ::placeholder {
        color: #9aa6b6;
        opacity: 1;
    }

    .admin-projects-wizard-page .requirements-checklist {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        padding: 0;
        background: transparent;
        border: 0;
        border-radius: 0;
    }

    .admin-projects-wizard-page .checkbox-item,
    .admin-projects-wizard-page .requirements-checklist .checkbox-item {
        min-height: 32px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 6px;
        border: 1px solid var(--wizard-border-strong);
        color: var(--wizard-text);
        background: var(--wizard-field);
        cursor: pointer;
        transition: all 0.15s ease;
    }
    
    .admin-projects-wizard-page .checkbox-item:hover,
    .admin-projects-wizard-page .requirements-checklist .checkbox-item:hover {
        background: var(--wizard-field-hover);
        border-color: var(--wizard-accent);
    }
    
    .admin-projects-wizard-page .checkbox-item:has(input[type="checkbox"]:checked),
    .admin-projects-wizard-page .requirements-checklist .checkbox-item:has(input[type="checkbox"]:checked) {
        background: var(--wizard-accent-soft);
        border-color: var(--wizard-accent);
    }
    
    .admin-projects-wizard-page .checkbox-item input[type="checkbox"],
    .admin-projects-wizard-page .requirements-checklist .checkbox-item input[type="checkbox"] {
        appearance: none;
        -webkit-appearance: none;
        width: 14px !important;
        height: 14px !important;
        min-width: 14px !important;
        min-height: 14px !important;
        margin: 0 !important;
        padding: 0 !important;
        flex-shrink: 0;
        display: inline-block !important;
        border: 1.5px solid #6b7280 !important;
        border-radius: 3px !important;
        background-color: var(--wizard-field) !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        box-shadow: none !important;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    
    .admin-projects-wizard-page .checkbox-item input[type="checkbox"]:hover,
    .admin-projects-wizard-page .requirements-checklist .checkbox-item input[type="checkbox"]:hover {
        border-color: var(--wizard-accent) !important;
        background-color: var(--wizard-field-hover) !important;
    }
    
    .admin-projects-wizard-page .checkbox-item input[type="checkbox"]:checked,
    .admin-projects-wizard-page .requirements-checklist .checkbox-item input[type="checkbox"]:checked {
        border-color: var(--wizard-accent) !important;
        background-color: var(--wizard-accent) !important;
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M3.5 8.2 6.6 11.2 12.7 4.8' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3e%3c/svg%3e") !important;
        background-size: 9px 9px !important;
    }
    
    .admin-projects-wizard-page .checkbox-item input[type="checkbox"]:focus-visible,
    .admin-projects-wizard-page .requirements-checklist .checkbox-item input[type="checkbox"]:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3) !important;
    }

    .admin-projects-wizard-page .checkbox-item span {
        color: var(--wizard-text);
        font-size: 13px;
        font-weight: 500;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .admin-projects-wizard-page .document-upload-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .admin-projects-wizard-page .document-upload-item {
        display: flex;
        align-items: stretch;
        gap: 12px;
        padding: 14px;
        background: var(--wizard-panel);
        border: 1px solid var(--wizard-border);
        border-radius: 8px;
    }

    .admin-projects-wizard-page .document-type-select {
        flex: 0 0 260px;
        min-width: 220px;
    }

    .admin-projects-wizard-page .document-upload-item input[type="file"] {
        flex: 1 1 auto;
        min-width: 0;
        padding: 9px;
    }

    .admin-projects-wizard-page .document-upload-item input[type="file"]::file-selector-button {
        margin-right: 12px;
        padding: 8px 12px;
        border: 1px solid var(--wizard-border-strong);
        border-radius: 8px;
        color: var(--wizard-text);
        background: var(--wizard-panel-soft);
        cursor: pointer;
    }

    .admin-projects-wizard-page .btn-add-document {
        margin-top: 12px;
    }

    .admin-projects-wizard-page .btn-remove-document {
        width: 46px;
        padding: 0;
        flex: 0 0 46px;
    }

    .admin-projects-wizard-page .review-card {
        margin: 0 0 16px;
        padding: 18px;
        background: var(--wizard-panel);
        border: 1px solid var(--wizard-border);
        border-radius: 8px;
    }

    .admin-projects-wizard-page .review-card h4 {
        margin: 0 0 14px;
        display: flex;
        align-items: center;
        gap: 9px;
        color: var(--wizard-text);
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 0;
    }

    .admin-projects-wizard-page .review-card h4 i,
    .admin-projects-wizard-page .review-documents-list li i {
        color: #fbbf24;
    }

    .admin-projects-wizard-page .review-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px 18px;
    }

    .admin-projects-wizard-page .review-list li {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .admin-projects-wizard-page .review-list li span:first-child {
        color: var(--wizard-subtle);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0;
        text-transform: uppercase;
    }

    .admin-projects-wizard-page .review-list li span:last-child {
        color: #e8eef5;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.45;
        text-align: left;
        overflow-wrap: anywhere;
    }

    .admin-projects-wizard-page .review-documents-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .admin-projects-wizard-page .review-documents-list li {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 10px;
        border-radius: 8px;
        color: #e2e8f0;
        background: rgba(255, 255, 255, 0.04);
        font-size: 13px;
        overflow-wrap: anywhere;
    }

    .admin-projects-wizard-page .confirm-publish-group {
        margin-top: 20px;
        padding-top: 8px;
        border-top: 1px solid var(--wizard-border);
    }
    
    .admin-projects-wizard-page .confirm-publish-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        line-height: 1.4;
    }
    
    .admin-projects-wizard-page .confirm-publish-label input {
        appearance: none;
        -webkit-appearance: none;
        width: 16px;
        height: 16px;
        min-height: 16px;
        margin: 0;
        flex-shrink: 0;
        display: grid;
        place-content: center;
        border: 1.5px solid #6b7280;
        border-radius: 4px;
        background-color: var(--wizard-field);
        background-position: center;
        background-repeat: no-repeat;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    
    .admin-projects-wizard-page .confirm-publish-label input:checked {
        border-color: var(--wizard-accent);
        background-color: var(--wizard-accent);
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M3.5 8.2 6.6 11.2 12.7 4.8' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3e%3c/svg%3e");
        background-size: 10px 10px;
    }
    
    .admin-projects-wizard-page .confirm-publish-label input:hover {
        border-color: var(--wizard-accent);
        background-color: var(--wizard-field-hover);
    }
    
    .admin-projects-wizard-page .confirm-publish-label input:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
    }
    
    .admin-projects-wizard-page .confirm-publish-label span {
        color: #dbe4ee;
        font-size: 13px;
        font-weight: 500;
        text-transform: none;
        line-height: 1.4;
    }

    .admin-projects-wizard-page .wizard-actions-container {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 0 clamp(24px, 4vw, 44px) clamp(28px, 4vw, 40px);
        min-height: 0;
        box-sizing: border-box;
        background: transparent;
        border-top: 0;
    }

    .admin-projects-wizard-page .btn {
        min-height: 44px;
        border-radius: 8px;
        padding: 10px 20px;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 800;
        line-height: 1;
        white-space: nowrap;
        cursor: pointer;
        transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .admin-projects-wizard-page .btn-primary,
    .admin-projects-wizard-page .btn-next,
    .admin-projects-wizard-page .btn-submit {
        color: #ffffff;
        background: var(--wizard-accent);
        border-color: var(--wizard-accent);
        box-shadow: none;
    }

    .admin-projects-wizard-page .btn-primary:hover,
    .admin-projects-wizard-page .btn-next:hover,
    .admin-projects-wizard-page .btn-submit:hover {
        background: var(--wizard-accent-hover);
        border-color: var(--wizard-accent-hover);
        transform: none;
        box-shadow: none;
    }

    .admin-projects-wizard-page .btn-secondary,
    .admin-projects-wizard-page .btn-prev,
    .admin-projects-wizard-page .btn-draft {
        color: #e2e8f0;
        background: var(--wizard-panel-soft);
        border-color: var(--wizard-border-strong);
    }

    .admin-projects-wizard-page .btn-secondary:hover,
    .admin-projects-wizard-page .btn-prev:hover,
    .admin-projects-wizard-page .btn-draft:hover {
        color: var(--wizard-text);
        background: #232c35;
        border-color: #748194;
        transform: none;
    }

    .admin-projects-wizard-page .btn-draft {
        margin-right: auto;
    }

    .admin-projects-wizard-page .btn-danger {
        color: #fee2e2;
        background: rgba(239, 68, 68, 0.12);
        border-color: rgba(239, 68, 68, 0.48);
    }

    .admin-projects-wizard-page .btn-danger:hover {
        color: #ffffff;
        background: var(--wizard-danger);
        border-color: var(--wizard-danger);
    }

    .admin-projects-wizard-page .wizard-container.modal-wizard::-webkit-scrollbar {
        width: 10px;
    }

    .admin-projects-wizard-page .wizard-container.modal-wizard::-webkit-scrollbar-track {
        background: transparent;
    }

    .admin-projects-wizard-page .wizard-container.modal-wizard::-webkit-scrollbar-thumb {
        border: 2px solid var(--wizard-bg);
        border-radius: 999px;
        background: var(--wizard-border-strong);
    }

    @media (max-width: 900px) {
        .admin-projects-wizard-page .wizard-container.modal-wizard {
            max-width: calc(100vw - 24px);
        }

        .admin-projects-wizard-page .form-grid,
        .admin-projects-wizard-page .requirements-checklist,
        .admin-projects-wizard-page .review-list {
            grid-template-columns: 1fr;
        }

        .admin-projects-wizard-page .document-upload-item {
            flex-direction: column;
        }

        .admin-projects-wizard-page .document-type-select,
        .admin-projects-wizard-page .document-upload-item input[type="file"],
        .admin-projects-wizard-page .btn-remove-document {
            width: 100%;
            flex: none;
        }

        .admin-projects-wizard-page .btn-remove-document {
            min-height: 42px;
        }
    }

    @media (max-width: 640px) {
        .admin-projects-wizard-page .wizard-modal-overlay {
            align-items: stretch;
            padding: 8px;
        }

        .admin-projects-wizard-page .wizard-container.modal-wizard {
            max-width: 100%;
            max-height: calc(100vh - 16px);
            border-radius: 12px;
        }

        .admin-projects-wizard-page .wizard-modal-header {
            padding: 20px 58px 18px 20px;
        }

        .admin-projects-wizard-page .wizard-modal-header p {
            margin-left: 0;
        }

        .admin-projects-wizard-page .wizard-steps {
            padding: 18px 18px 20px;
        }

        .admin-projects-wizard-page .wizard-step {
            flex-basis: 88px;
            min-width: 78px;
        }

        .admin-projects-wizard-page .step-number {
            width: 38px;
            height: 38px;
            font-size: 13px;
        }

        .admin-projects-wizard-page .step-label {
            font-size: 11px;
        }

        .admin-projects-wizard-page .wizard-step-panel {
            padding: 22px 18px;
        }

        .admin-projects-wizard-page .wizard-actions-container {
            flex-direction: column-reverse;
            align-items: stretch;
            padding: 14px 18px;
        }

        .admin-projects-wizard-page .wizard-actions-container .btn {
            width: 100%;
        }

        .admin-projects-wizard-page .btn-draft {
            margin-right: 0;
        }
    }
</style>

<script>
let currentStep = 1;
const totalSteps = 5;

document.getElementById('wizardModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) {
        window.location.href = "{{ route('admin.projects') }}";
    }
});

function updateStepUI() {
    document.querySelectorAll('.wizard-step').forEach((el, idx) => {
        const stepNum = idx + 1;
        el.classList.remove('active', 'completed');
        if (stepNum === currentStep) {
            el.classList.add('active');
        } else if (stepNum < currentStep) {
            el.classList.add('completed');
        }
    });

    document.querySelectorAll('.wizard-step-content').forEach(el => {
        el.classList.remove('active');
        if (parseInt(el.dataset.stepContent) === currentStep) {
            el.classList.add('active');
        }
    });

    document.getElementById('prevBtn').style.display = currentStep === 1 ? 'none' : 'inline-flex';
    document.getElementById('nextBtn').style.display = currentStep === totalSteps ? 'none' : 'inline-flex';
    document.getElementById('draftBtn').style.display = currentStep === totalSteps ? 'inline-flex' : 'none';
    document.getElementById('submitBtn').style.display = currentStep === totalSteps ? 'inline-flex' : 'none';

    const wizardScroller = document.querySelector('.modal-wizard');
    if (wizardScroller) wizardScroller.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep(step) {
    let isValid = true;
    const currentContent = document.querySelector(`[data-step-content="${step}"]`);
    const requiredFields = currentContent.querySelectorAll('[required]');
    const errorMessages = currentContent.querySelectorAll('.error-message');

    errorMessages.forEach(el => el.textContent = '');

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#ef4444';
            field.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.16)';
            const errorEl = field.parentElement.querySelector('.error-message');
            if (errorEl) errorEl.textContent = 'This field is required';
        } else if (field.type === 'date' && field.value) {
            const selected = new Date(field.value);
            const today = new Date();
            today.setHours(0,0,0,0);
            if (field.id === 'bid_submission_deadline' && selected <= today) {
                isValid = false;
                field.style.borderColor = '#ef4444';
                field.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.16)';
                const errorEl = field.parentElement.querySelector('.error-message');
                if (errorEl) errorEl.textContent = 'Bid submission deadline must be after today.';
            } else if (field.id === 'bid_opening_date') {
                const submission = new Date(document.getElementById('bid_submission_deadline').value);
                if (selected <= submission) {
                    isValid = false;
                    field.style.borderColor = '#ef4444';
                    field.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.16)';
                    const errorEl = field.parentElement.querySelector('.error-message');
                    if (errorEl) errorEl.textContent = 'Bid opening must be after submission deadline.';
                }
            } else {
                field.style.removeProperty('border-color');
                field.style.removeProperty('box-shadow');
            }
        } else {
            field.style.removeProperty('border-color');
            field.style.removeProperty('box-shadow');
        }
    });

    return isValid;
}

function changeStep(delta) {
    if (delta === 1 && !validateStep(currentStep)) return;

    const newStep = currentStep + delta;
    if (newStep >= 1 && newStep <= totalSteps) {
        currentStep = newStep;
        updateStepUI();
        if (currentStep === totalSteps) {
            populateReview();
        }
    }
}

function populateReview() {
    const formatDate = (val) => {
        if (!val) return 'Not set';
        const d = new Date(val);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    };

    const projectInfo = [
        { label: 'Title', value: document.getElementById('title').value || 'Not provided' },
        { label: 'Description', value: document.getElementById('description').value || 'Not provided' },
        { label: 'Category', value: document.getElementById('category').options[document.getElementById('category').selectedIndex].text || 'Not selected' },
        { label: 'Location', value: document.getElementById('location').value || 'Not provided' },
        { label: 'Procurement Mode', value: document.getElementById('procurement_mode').options[document.getElementById('procurement_mode').selectedIndex].text || 'Not selected' },
        { label: 'Source of Fund', value: document.getElementById('source_of_fund').value || 'Not provided' },
        { label: 'Contract Duration', value: document.getElementById('contract_duration').value || 'Not provided' },
        { label: 'Budget', value: 'PHP ' + (parseFloat(document.getElementById('budget').value) || 0).toFixed(2) }
    ];
    const infoList = document.getElementById('reviewProjectInfo');
    infoList.innerHTML = projectInfo.map(item => `<li><span>${item.label}</span><span>${item.value}</span></li>`).join('');

    const reqList = document.getElementById('reviewRequirements');
    const requiredDocs = Array.from(document.querySelectorAll('input[name="required_documents[]"]:checked')).map(cb => cb.parentElement.querySelector('span').textContent);

    reqList.innerHTML = `
        <li><span>Required Docs</span><span>${requiredDocs.length ? requiredDocs.join(', ') : 'None selected'}</span></li>
    `;

    const docRows = document.querySelectorAll('.document-upload-item');
    const docList = document.getElementById('reviewDocuments');
    docList.innerHTML = '';
    docRows.forEach(row => {
        const typeSelect = row.querySelector('select');
        const fileInput = row.querySelector('input[type="file"]');
        const typeLabel = typeSelect.options[typeSelect.selectedIndex].text;
        const fileName = fileInput.files[0]?.name || 'No file selected';
        if (typeLabel) {
            docList.innerHTML += `<li><i class="fas fa-file"></i> ${typeLabel}: ${fileName}</li>`;
        }
    });

    const dateList = document.getElementById('reviewDates');
    dateList.innerHTML = `
        <li><span>Date Posted</span><span>${formatDate(document.getElementById('date_posted').value)}</span></li>
        <li><span>Pre-Bid Conf.</span><span>${formatDate(document.getElementById('pre_bid_conference_date').value)}</span></li>
        <li><span>Clarification</span><span>${formatDate(document.getElementById('clarification_deadline').value)}</span></li>
        <li><span>Submission</span><span>${formatDate(document.getElementById('bid_submission_deadline').value)}</span></li>
        <li><span>Bid Opening</span><span>${formatDate(document.getElementById('bid_opening_date').value)}</span></li>
        <li><span>Evaluation</span><span>${formatDate(document.getElementById('evaluation_start_date').value)}</span></li>
        <li><span>Award Date</span><span>${formatDate(document.getElementById('expected_award_date').value)}</span></li>
    `;
}

function saveAsDraft() {
    document.getElementById('projectStatus').value = 'draft';
    document.getElementById('projectWizardForm').submit();
}

document.getElementById('projectWizardForm').addEventListener('submit', function(e) {
    const status = document.getElementById('projectStatus').value;
    if (status === 'draft') {
        return;
    }
    if (currentStep !== totalSteps) {
        e.preventDefault();
        currentStep = totalSteps;
        updateStepUI();
        populateReview();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    updateStepUI();
});

function addDocumentRow() {
    const list = document.getElementById('documentUploadList');
    const index = list.children.length;
    const newRow = document.createElement('div');
    newRow.className = 'document-upload-item';
    newRow.setAttribute('data-index', index);
    newRow.innerHTML = `
        <select name="document_type[]" class="document-type-select">
            <option value="">Select Document Type</option>
            <option value="invitation_to_bid">Invitation to Bid</option>
            <option value="bidding_documents">Bidding Documents</option>
            <option value="terms_of_reference">Terms of Reference</option>
            <option value="technical_specifications">Technical Specifications</option>
            <option value="bill_of_quantities">Bill of Quantities</option>
            <option value="project_plans">Project Plans / Drawings</option>
            <option value="supplemental_bulletin">Supplemental Bid Bulletin</option>
            <option value="other">Other</option>
        </select>
        <input type="file" name="document_files[]" accept=".pdf,.doc,.docx,.xlsx,.xls,.jpg,.jpeg,.png">
        <button type="button" class="btn btn-danger btn-remove-document" onclick="removeDocumentRow(this)">
            <i class="fas fa-trash"></i>
        </button>
    `;
    list.appendChild(newRow);
}

function removeDocumentRow(btn) {
    btn.closest('.document-upload-item').remove();
}
</script>

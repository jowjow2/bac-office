<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

    <!-- MAIN AREA -->
    <div class="main-area">

        <!-- NAVBAR -->
        <header class="navbar">
            <div class="nav-left">
                <h2>Create Project</h2>
                <p>Add new procurement project</p>
            </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="dashboard-content">
            <style>
                @media (max-width: 640px) {
                    .create-modal-actions {
                        flex-direction: column-reverse;
                        align-items: stretch;
                    }
                    .create-modal-actions button {
                        width: 100%;
                    }
                }
            </style>

            <!-- MODAL OVERLAY -->
            <div id="createModalOverlay" style="display: flex; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.92); z-index: 10000; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box; backdrop-filter: blur(4px);">
                <div class="form-container" style="background: #111827; border-radius: 16px; width: 100%; max-width: 800px; max-height: calc(100vh - 40px); display: flex; flex-direction: column; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; margin: 0; color: #ffffff;">
                    
                    <!-- Modal Header -->
                    <div style="padding: 20px 24px; border-bottom: 1px solid #374151; background: #111827; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #ffffff;">Create New Project</h2>
                            <p style="margin: 4px 0 0; font-size: 14px; color: #9ca3af;">Fill in the details below to create a new procurement project.</p>
                        </div>
                        <a href="{{ route('admin.projects') }}" style="width: 32px; height: 32px; border-radius: 8px; border: none; background: #374151; color: #d1d5db; font-size: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; text-decoration: none;" onmouseover="this.style.background='#4b5563'; this.style.color='#ffffff';" onmouseout="this.style.background='#374151'; this.style.color='#d1d5db';">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>

                    <!-- Modal Body -->
                    <div style="padding: 24px; overflow-y: auto;">

                @if($errors->any())
                <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form id="createProjectForm" action="{{ route('admin.projects.store') }}" method="POST">
                    @csrf

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #d1d5db; margin-bottom: 6px;">Project Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" required style="width: 100%; padding: 10px 12px; border: 1px solid #4b5563; background: #1f2937; color: #ffffff; border-radius: 8px; font-size: 14px;">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #d1d5db; margin-bottom: 6px;">Description</label>
                        <textarea name="description" rows="4" required style="width: 100%; padding: 10px 12px; border: 1px solid #4b5563; background: #1f2937; color: #ffffff; border-radius: 8px; font-size: 14px; resize: vertical;">{{ old('description') }}</textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #d1d5db; margin-bottom: 6px;">Category</label>
                            <input type="text" name="category" value="{{ old('category') }}" required style="width: 100%; padding: 10px 12px; border: 1px solid #4b5563; background: #1f2937; color: #ffffff; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #d1d5db; margin-bottom: 6px;">Budget (PHP)</label>
                            <input type="number" name="budget" value="{{ old('budget') }}" required min="0" max="9999999999999.99" step="0.01" style="width: 100%; padding: 10px 12px; border: 1px solid #4b5563; background: #1f2937; color: #ffffff; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #d1d5db; margin-bottom: 6px;">Deadline</label>
                            <input type="date" name="deadline" value="{{ old('deadline') }}" required style="width: 100%; padding: 10px 12px; border: 1px solid #4b5563; background: #1f2937; color: #ffffff; border-radius: 8px; font-size: 14px; color-scheme: dark;">
                        </div>
                    </div>

                    <input type="hidden" name="status" id="projectStatus" value="draft">

                </form>
                    </div>
                    
                    <!-- Modal Footer / Actions -->
                    <div style="padding: 16px 24px; border-top: 1px solid #374151; background: #111827; display: flex; justify-content: flex-end; gap: 12px;" class="create-modal-actions">
                        <a href="{{ route('admin.projects') }}" style="padding: 10px 24px; border: 1px solid #4b5563; border-radius: 8px; background: #374151; color: #d1d5db; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#374151'">Cancel</a>
                        <button type="button" onclick="document.getElementById('projectStatus').value='draft'; document.getElementById('createProjectForm').submit()" style="padding: 10px 24px; border: 1px solid #4b5563; border-radius: 8px; background: #374151; color: #d1d5db; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#374151'">
                            <i class="fas fa-file-alt" style="margin-right: 6px;"></i> Save as Draft
                        </button>
                        <button type="button" onclick="document.getElementById('projectStatus').value='open'; document.getElementById('createProjectForm').submit()" style="padding: 10px 24px; background: #3b82f6; color: #ffffff; border: 1px solid #3b82f6; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;" onmouseover="this.style.background='#2563eb'; this.style.borderColor='#2563eb'" onmouseout="this.style.background='#3b82f6'; this.style.borderColor='#3b82f6'">
                            <i class="fas fa-paper-plane"></i> Publish Project
                        </button>
                    </div>
                </div>
            </div>

            <script>
                document.getElementById('createModalOverlay').addEventListener('click', function(e) {
                    if (e.target === this) {
                        window.location.href = "{{ route('admin.projects') }}";
                    }
                });
            </script>
        </main>
    </div>
</div>

@php
    $isCurrentUser = auth('admin')->id() === $managedUser->id;
@endphp

<div class="card card-flush internal-card internal-form-card">
    <div class="card-header">
        <div class="card-title">
            <h3 class="fw-bold text-gray-900">{{ $title }}</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <form method="POST" action="{{ $action }}" class="form">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-6">
                    <label for="name" class="required form-label">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $managedUser->name) }}" required class="form-control @error('name') is-invalid @enderror" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="required form-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $managedUser->email) }}" required class="form-control @error('email') is-invalid @enderror" />
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Mobile number</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $managedUser->phone) }}" class="form-control @error('phone') is-invalid @enderror" />
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="role" class="required form-label">Role</label>
                    <select id="role" name="role" required class="form-select @error('role') is-invalid @enderror">
                        @foreach ($roleOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $selectedRole) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-6 mb-8 internal-form-grid">
                <div class="col-md-6">
                    <label for="password" class="form-label">{{ $isEditing ? 'New password' : 'Password' }}</label>
                    <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" {{ $isEditing ? '' : 'required' }} />
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        {{ $isEditing ? 'Leave blank to keep the current password.' : 'Use a strong password with at least 8 characters.' }}
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">{{ $isEditing ? 'Confirm new password' : 'Confirm password' }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" {{ $isEditing ? '' : 'required' }} />
                </div>
            </div>

            <div class="row g-6 mb-10 internal-form-grid">
                <div class="col-12">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $managedUser->is_active))>
                        <label class="form-check-label" for="is_active">
                            Account is active and allowed to sign in
                        </label>
                    </div>
                    @error('is_active')
                        <div class="text-danger fs-7 mt-2">{{ $message }}</div>
                    @enderror
                    @if ($isCurrentUser)
                        <div class="form-text">Your own administrator account cannot be deactivated from this screen.</div>
                    @endif
                </div>
            </div>

            <div class="d-flex justify-content-end internal-form-actions">
                <a href="{{ route('administrator.users.index') }}" class="btn btn-light">Back</a>
                <button type="submit" class="btn btn-primary">{{ $submit }}</button>
            </div>
        </form>
    </div>
</div>

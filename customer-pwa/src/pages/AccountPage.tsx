import { FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { updateCustomerPassword, updateCustomerProfile } from '../api/account';
import { ApiError, ApiValidationErrors } from '../api/client';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { PasswordField } from '../components/forms/PasswordField';
import { PageHeader } from '../components/common/PageHeader';
import { useAuthStore } from '../stores/authStore';
import { getFieldError } from '../utils/forms';

export function AccountPage() {
  const customer = useAuthStore((state) => state.customer);
  const syncCustomer = useAuthStore((state) => state.syncCustomer);
  const logout = useAuthStore((state) => state.logout);
  const navigate = useNavigate();
  const [profileForm, setProfileForm] = useState({
    name: '',
    email: '',
    phone: ''
  });
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: ''
  });
  const [profileErrors, setProfileErrors] = useState<ApiValidationErrors>({});
  const [passwordErrors, setPasswordErrors] = useState<ApiValidationErrors>({});
  const [profileMessage, setProfileMessage] = useState<string | null>(null);
  const [passwordMessage, setPasswordMessage] = useState<string | null>(null);
  const [profileMessageVariant, setProfileMessageVariant] = useState<'success' | 'error'>('success');
  const [passwordMessageVariant, setPasswordMessageVariant] = useState<'success' | 'error'>('success');
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [isSavingPassword, setIsSavingPassword] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  useEffect(() => {
    if (!customer) {
      return;
    }

    setProfileForm({
      name: customer.name,
      email: customer.email,
      phone: customer.phone ?? ''
    });
  }, [customer]);

  async function handleProfileSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSavingProfile(true);
    setProfileErrors({});
    setProfileMessage(null);
    setProfileMessageVariant('success');

    try {
      const response = await updateCustomerProfile({
        ...profileForm,
        phone: profileForm.phone.trim() || null
      });
      syncCustomer(response.data);
      setProfileMessage(response.message ?? 'Profile updated successfully.');
    } catch (error) {
      if (error instanceof ApiError) {
        setProfileErrors(error.errors);
        setProfileMessage(error.message);
        setProfileMessageVariant('error');
      } else {
        setProfileMessage('Unable to update your profile right now.');
        setProfileMessageVariant('error');
      }
    } finally {
      setIsSavingProfile(false);
    }
  }

  async function handlePasswordSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSavingPassword(true);
    setPasswordErrors({});
    setPasswordMessage(null);
    setPasswordMessageVariant('success');

    try {
      const response = await updateCustomerPassword(passwordForm);
      syncCustomer(response.data);
      setPasswordForm({
        current_password: '',
        password: '',
        password_confirmation: ''
      });
      setPasswordMessage(response.message ?? 'Password updated successfully.');
    } catch (error) {
      if (error instanceof ApiError) {
        setPasswordErrors(error.errors);
        setPasswordMessage(error.message);
        setPasswordMessageVariant('error');
      } else {
        setPasswordMessage('Unable to update your password right now.');
        setPasswordMessageVariant('error');
      }
    } finally {
      setIsSavingPassword(false);
    }
  }

  async function handleLogout(): Promise<void> {
    setIsLoggingOut(true);

    try {
      await logout();
      navigate('/', { replace: true });
    } finally {
      setIsLoggingOut(false);
    }
  }

  if (!customer) {
    return (
      <div className="page-container">
        <PageHeader title="Account" description="Customer session is still loading." />
      </div>
    );
  }

  return (
    <div className="page-container">
      <PageHeader
        title="Account"
        description="Manage your customer profile and session."
        rightSlot={
          <button type="button" className="btn btn-outline-dark rounded-pill btn-sm" onClick={() => void handleLogout()} disabled={isLoggingOut}>
            {isLoggingOut ? 'Signing out...' : 'Logout'}
          </button>
        }
      />

      <section className="account-hero">
        <span className="account-hero-badge">Customer session active</span>
        <h2>{customer.name}</h2>
        <p>{customer.email}</p>
        {customer.phone ? <p>{customer.phone}</p> : <p>Add a phone number to make pickup communication easier.</p>}
      </section>

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Shortcuts</span>
            <h2>Quick access</h2>
            <p>Jump back into ordering without crowding the bottom navigation.</p>
          </div>
        </div>
        <div className="account-link-list">
          <Link to="/favourites" className="account-link-row">
            <span>
              <i className="bi bi-heart"></i>
              Favourites
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/orders" className="account-link-row">
            <span>
              <i className="bi bi-receipt"></i>
              Orders
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/about" className="account-link-row">
            <span>
              <i className="bi bi-info-circle"></i>
              About
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/contact" className="account-link-row">
            <span>
              <i className="bi bi-chat-dots"></i>
              Contact
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/faq" className="account-link-row">
            <span>
              <i className="bi bi-question-circle"></i>
              FAQ
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/terms" className="account-link-row">
            <span>
              <i className="bi bi-file-text"></i>
              Terms
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/privacy" className="account-link-row">
            <span>
              <i className="bi bi-shield-check"></i>
              Privacy
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
        </div>
      </section>

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <h2>Edit profile</h2>
            <p>Keep your contact information current for order updates.</p>
          </div>
        </div>
        <FormFeedback message={profileMessage} variant={profileMessageVariant} />
        <form className="auth-form" onSubmit={(event) => void handleProfileSubmit(event)}>
          <FormField
            label="Full name"
            name="name"
            autoComplete="name"
            value={profileForm.name}
            onChange={(event) => setProfileForm((currentValue) => ({ ...currentValue, name: event.target.value }))}
            error={getFieldError(profileErrors, 'name')}
            required
          />
          <FormField
            label="Email address"
            name="email"
            type="email"
            autoComplete="email"
            inputMode="email"
            value={profileForm.email}
            onChange={(event) => setProfileForm((currentValue) => ({ ...currentValue, email: event.target.value }))}
            error={getFieldError(profileErrors, 'email')}
            required
          />
          <FormField
            label="Phone number"
            name="phone"
            type="tel"
            autoComplete="tel"
            inputMode="tel"
            value={profileForm.phone}
            onChange={(event) => setProfileForm((currentValue) => ({ ...currentValue, phone: event.target.value }))}
            error={getFieldError(profileErrors, 'phone')}
          />
          <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSavingProfile}>
            {isSavingProfile ? 'Saving profile...' : 'Save profile'}
          </button>
        </form>
      </section>

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <h2>Change password</h2>
            <p>The backend remains authoritative for current-password validation.</p>
          </div>
        </div>
        <FormFeedback message={passwordMessage} variant={passwordMessageVariant} />
        <form className="auth-form" onSubmit={(event) => void handlePasswordSubmit(event)}>
          <PasswordField
            label="Current password"
            name="current_password"
            autoComplete="current-password"
            value={passwordForm.current_password}
            onChange={(event) => setPasswordForm((currentValue) => ({ ...currentValue, current_password: event.target.value }))}
            error={getFieldError(passwordErrors, 'current_password')}
            required
          />
          <PasswordField
            label="New password"
            name="password"
            autoComplete="new-password"
            value={passwordForm.password}
            onChange={(event) => setPasswordForm((currentValue) => ({ ...currentValue, password: event.target.value }))}
            error={getFieldError(passwordErrors, 'password')}
            required
          />
          <PasswordField
            label="Confirm new password"
            name="password_confirmation"
            autoComplete="new-password"
            value={passwordForm.password_confirmation}
            onChange={(event) => setPasswordForm((currentValue) => ({ ...currentValue, password_confirmation: event.target.value }))}
            error={getFieldError(passwordErrors, 'password_confirmation')}
            required
          />
          <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSavingPassword}>
            {isSavingPassword ? 'Updating password...' : 'Update password'}
          </button>
        </form>
      </section>
    </div>
  );
}
